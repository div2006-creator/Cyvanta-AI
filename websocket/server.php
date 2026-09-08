<?php
/**
 * CYVANTA - Minimal WebSocket Server
 *
 * A small, dependency-free (no Composer/Ratchet) WebSocket server used to
 * push real-time events (DOCUMENT_PROCESSING_*, AI_ANALYSIS_*, CASE_*,
 * NOTIFICATION_CREATED, SYSTEM_ACTIVITY, SECURITY_ALERT) to connected
 * browser clients, per the specification's WebSocket architecture section.
 *
 * It implements the RFC 6455 handshake and text-frame encoding/decoding by
 * hand using PHP's native socket functions, so it runs anywhere PHP CLI is
 * available with no extra packages. It polls the `system_activity` and
 * `notifications` tables for new rows and broadcasts them as JSON frames
 * to every connected client.
 *
 * Run:  php websocket/server.php
 *
 * If this process is not running (or is unreachable, e.g. a restrictive
 * network), the frontend (assets/js/app.js) automatically falls back to
 * AJAX polling — the UI keeps working either way.
 */

require_once __DIR__ . '/../config/database.php';

set_time_limit(0);
ob_implicit_flush(true);

$host = WEBSOCKET_HOST;
$port = WEBSOCKET_PORT;

$server = stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Could not start WebSocket server on $host:$port — $errstr ($errno)\n");
    exit(1);
}
stream_set_blocking($server, false);
echo "[CYVANTA] WebSocket server listening on ws://$host:$port\n";

/** @var resource[] $clients raw sockets that completed the WS handshake */
$clients = [];
/** @var resource[] $pending sockets awaiting handshake */
$pending = [];

$lastActivityId = 0;
$lastNotificationId = 0;
$lastIntelligenceId = 0;
try {
    $pdo = Database::connect();
    $lastActivityId = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM system_activity')->fetchColumn();
    $lastNotificationId = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM notifications')->fetchColumn();
    $lastIntelligenceId = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM intelligence_events')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "Warning: could not read initial cursors — {$e->getMessage()}\n");
}

function ws_handshake($request): ?string
{
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $request, $m)) {
        return null;
    }
    $key = trim($m[1]);
    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    return "HTTP/1.1 101 Switching Protocols\r\n" .
           "Upgrade: websocket\r\n" .
           "Connection: Upgrade\r\n" .
           "Sec-WebSocket-Accept: $accept\r\n\r\n";
}

function ws_encode_frame(string $payload): string
{
    $length = strlen($payload);
    if ($length <= 125) {
        return chr(0x81) . chr($length) . $payload;
    }
    if ($length <= 65535) {
        return chr(0x81) . chr(126) . pack('n', $length) . $payload;
    }
    return chr(0x81) . chr(127) . pack('J', $length) . $payload;
}

function ws_decode_frame(string $data): ?string
{
    if (strlen($data) < 2) return null;
    $byte2 = ord($data[1]);
    $masked = ($byte2 & 0x80) !== 0;
    $len = $byte2 & 0x7F;
    $offset = 2;
    if ($len === 126) { $len = unpack('n', substr($data, 2, 2))[1]; $offset += 2; }
    elseif ($len === 127) { $len = unpack('J', substr($data, 2, 8))[1]; $offset += 8; }
    if (!$masked) return substr($data, $offset, $len);
    $maskKey = substr($data, $offset, 4);
    $offset += 4;
    $payload = substr($data, $offset, $len);
    $out = '';
    for ($i = 0; $i < strlen($payload); $i++) {
        $out .= $payload[$i] ^ $maskKey[$i % 4];
    }
    return $out;
}

function broadcast(array &$clients, string $event, array $payload): void
{
    $frame = ws_encode_frame(json_encode(['event' => $event, 'payload' => $payload]));
    foreach ($clients as $id => $sock) {
        if (!is_resource($sock) || feof($sock)) { unset($clients[$id]); continue; }
        @fwrite($sock, $frame);
    }
}

$lastPoll = 0;

while (true) {
    $read = array_merge([$server], $pending, $clients);
    $write = $except = [];
    $numChanged = @stream_select($read, $write, $except, 0, 200000);

    if ($numChanged > 0) {
        // New connection
        if (in_array($server, $read, true)) {
            $conn = @stream_socket_accept($server, 0);
            if ($conn) {
                stream_set_blocking($conn, false);
                $pending[(int) $conn] = $conn;
            }
            $key = array_search($server, $read, true);
            unset($read[$key]);
        }

        foreach ($read as $sock) {
            $id = (int) $sock;
            $data = @fread($sock, 8192);
            if ($data === '' || $data === false) {
                unset($pending[$id], $clients[$id]);
                @fclose($sock);
                continue;
            }
            if (isset($pending[$id])) {
                $response = ws_handshake($data);
                if ($response) {
                    fwrite($sock, $response);
                    $clients[$id] = $sock;
                }
                unset($pending[$id]);
            } elseif (isset($clients[$id])) {
                // Client -> server frames are ignored (this app only pushes server -> client),
                // but we still decode to keep connections alive / detect close frames.
                ws_decode_frame($data);
            }
        }
    }

    // Poll DB for new activity roughly 2x/second and broadcast to all clients
    $now = microtime(true);
    if ($now - $lastPoll > 0.5 && !empty($clients)) {
        $lastPoll = $now;
        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare('SELECT * FROM system_activity WHERE id > ? ORDER BY id ASC');
            $stmt->execute([$lastActivityId]);
            foreach ($stmt->fetchAll() as $row) {
                $lastActivityId = (int) $row['id'];
                broadcast($clients, 'SYSTEM_ACTIVITY', ['description' => $row['description']]);
            }

            $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id > ? ORDER BY id ASC');
            $stmt->execute([$lastNotificationId]);
            foreach ($stmt->fetchAll() as $row) {
                $lastNotificationId = (int) $row['id'];
                broadcast($clients, 'NOTIFICATION_CREATED', ['title' => $row['title'], 'message' => $row['message']]);
            }

            $stmt = $pdo->prepare('SELECT * FROM intelligence_events WHERE id > ? ORDER BY id ASC');
            $stmt->execute([$lastIntelligenceId]);
            foreach ($stmt->fetchAll() as $row) {
                $lastIntelligenceId = (int) $row['id'];
                broadcast($clients, 'INTELLIGENCE_EVENT_CREATED', [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'event_type' => $row['event_type'],
                    'source_type' => $row['source_type'],
                    'source_name' => $row['source_name'],
                    'source_url' => $row['source_url'],
                    'source_id' => $row['source_id'] ?? null,
                    'source_fetched_at' => $row['source_fetched_at'] ?? null,
                    'is_verified' => (bool) ($row['is_verified'] ?? 0),
                    'severity' => $row['severity'],
                    'confidence' => (int) $row['confidence'],
                    'location' => $row['location'],
                    'entities' => json_decode($row['entities'] ?? '[]', true),
                    'relationships' => json_decode($row['relationships'] ?? '[]', true),
                    'event_timestamp' => $row['event_timestamp']
                ]);
            }
        } catch (Throwable $e) {
            fwrite(STDERR, 'Polling error: ' . $e->getMessage() . "\n");
        }
    }
}
