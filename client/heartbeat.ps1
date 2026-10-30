<#
.SYNOPSIS
    Periodic Heartbeat Ping Client for Home Internet Monitoring
.DESCRIPTION
    Sends an HTTP ping to your monitoring server every 60 seconds.
    When internet is alive, server records 'Connected' and detects your Public IP automatically.
#>

# Configuration: Update TARGET_URL to your deployed dashboard URL
$TargetUrl = "http://localhost:8000/api/ping"
$PingToken = "f7dd1cc238caf6f940050419744b95ec"
$IntervalSeconds = 60

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "   Home Internet Monitoring - Heartbeat Client   " -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "Target URL   : $TargetUrl" -ForegroundColor Gray
Write-Host "Interval     : $IntervalSeconds seconds" -ForegroundColor Gray
Write-Host "Press Ctrl+C to stop." -ForegroundColor Yellow
Write-Host "--------------------------------------------------" -ForegroundColor Gray

while ($true) {
    $now = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    try {
        $uri = "$TargetUrl`?token=$PingToken"
        $response = Invoke-RestMethod -Uri $uri -Method Get -TimeoutSec 10 -ErrorAction Stop

        if ($response.success) {
            Write-Host "[$now] [OK] Heartbeat success -> Status: $($response.status)" -ForegroundColor Green
        } else {
            Write-Host "[$now] [FAIL] Server rejected: $($response.error)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "[$now] [OFFLINE] Ping failed (No internet connection or server unreachable)" -ForegroundColor Red
    }

    Start-Sleep -Seconds $IntervalSeconds
}

