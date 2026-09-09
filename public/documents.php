<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Documents';
$activeNav = 'documents';
$pdo = Database::connect();
$user = cg_current_user();

$sql = "SELECT d.*, c.case_number, c.title AS case_title, u.full_name AS uploaded_by_name
        FROM documents d JOIN cases c ON c.id = d.case_id LEFT JOIN users u ON u.id = d.uploaded_by";
$params = [];
if ($user['role'] === 'investigator') {
    $sql .= " WHERE d.uploaded_by = ? OR c.lead_investigator_id = ?";
    $params = [$user['id'], $user['id']];
}
$sql .= " ORDER BY d.uploaded_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

require __DIR__ . '/../includes/partials/header.php';
$statusColor = ['Uploaded' => 'status-new', 'Queued' => 'status-new', 'Processing' => 'status-under-investigation', 'Processed' => 'status-resolved', 'Failed' => 'status-critical'];
?>

<div class="cg-card p-0 overflow-hidden">
  <table class="cg-table mb-0">
    <thead><tr><th>Document</th><th>Case</th><th>Type</th><th>Status</th><th>Uploaded By</th><th>Uploaded</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $d): 
        $canDelete = in_array($user['role'], ['super_admin', 'administrator'], true) || ((int)($d['uploaded_by'] ?? 0) === (int)$user['id']);
    ?>
      <tr>
        <td class="fw-600 text-white"><i class="fa-solid fa-file-lines me-1"></i> <?= htmlspecialchars($d['name']) ?></td>
        <td class="text-muted small"><?= htmlspecialchars($d['case_number']) ?></td>
        <td><?= htmlspecialchars($d['doc_type']) ?></td>
        <td><span class="cg-badge-status <?= $statusColor[$d['status']] ?? 'status-new' ?>"><?= htmlspecialchars($d['status']) ?></span></td>
        <td><?= htmlspecialchars($d['uploaded_by_name'] ?? '—') ?></td>
        <td class="text-muted small"><?= date('d M Y', strtotime($d['uploaded_at'])) ?></td>
        <td class="text-end">
          <a href="case-details.php?id=<?= $d['case_id'] ?>#documents" class="cg-btn cg-btn-outline cg-btn-sm me-1" title="View in Case"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
          <?php if ($canDelete): ?>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteGlobalDocument(<?= $d['id'] ?>)" title="<?= in_array($user['role'], ['super_admin', 'administrator'], true) ? 'Delete Document (Super Admin Access)' : 'Delete My Uploaded Document' ?>"><i class="fa-solid fa-trash-can"></i></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$documents): ?>
    <div class="cg-empty-state"><i class="fa-solid fa-file-shield"></i>No documents found. Upload a document from within a case.</div>
  <?php endif; ?>
</div>

<script>
function deleteGlobalDocument(id) {
  if (!confirm('Are you sure you want to delete this document? Extracted intelligence will also be removed.')) return;
  fetch('<?= $base ?>/api/documents/delete.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ document_id: id })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      if (window.cgToast) cgToast(res.message, 'success');
      else alert(res.message);
      setTimeout(() => location.reload(), 500);
    } else {
      if (window.cgToast) cgToast(res.message, 'error');
      else alert(res.message);
    }
  })
  .catch(() => alert('Failed to delete document.'));
}
</script>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
