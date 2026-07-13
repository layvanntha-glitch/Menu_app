@echo off
REM Starts the public tunnel and auto-updates the Mini App URL + Telegram button.
REM Leave this window open while you use the app on your phone. Ctrl+C to stop.
cd /d "%~dp0"
title Tasty Bites - Public Tunnel
"C:\xampp\php\php.exe" tunnel.php
echo.
echo Tunnel stopped. Press any key to close.
pause >nul
