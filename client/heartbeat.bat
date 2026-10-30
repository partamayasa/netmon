@echo off
setlocal

:: Configuration
set "TARGET_URL=http://localhost:8000/api/ping"
set "PING_TOKEN=f7dd1cc238caf6f940050419744b95ec"
set "INTERVAL=60"

echo ==================================================
echo    Home Internet Monitoring - Heartbeat Client
echo ==================================================
echo Target URL : %TARGET_URL%
echo Interval   : %INTERVAL%s
echo Press Ctrl+C to stop.
echo --------------------------------------------------

:loop
echo [%date% %time%] Sending heartbeat...
curl.exe -s -m 10 "%TARGET_URL%?token=%PING_TOKEN%"
echo.
timeout /t %INTERVAL% /nobreak >nul
goto loop

