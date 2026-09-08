@echo off
title CYVANTA AI Desktop App Launcher
echo ========================================================
echo   CYVANTA — AI Criminal Network Analysis Desktop App
echo ========================================================
echo.

cd /d "%~dp0"

echo [1/3] Checking database initialization...
php database/seed.php >nul 2>&1

echo [2/3] Starting background PHP & WebSocket services...
start /b php -d upload_max_filesize=100M -d post_max_size=100M -d memory_limit=256M -S 127.0.0.1:8000 -t public >nul 2>&1
start /b php websocket/server.php >nul 2>&1

timeout /t 2 /nobreak >nul

echo [3/3] Opening CYVANTA Desktop Window...
where msedge >nul 2>&1
if %errorlevel% equ 0 (
    start msedge --app=http://127.0.0.1:8000 --name="CYVANTA"
    goto end
)

where chrome >nul 2>&1
if %errorlevel% equ 0 (
    start chrome --app=http://127.0.0.1:8000 --name="CYVANTA"
    goto end
)

start http://127.0.0.1:8000

:end
echo.
echo CYVANTA Desktop App is active.
