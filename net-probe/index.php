<?php
date_default_timezone_set('Asia/Makassar');

// Load configuration directly from main .env file
$env = [];
$mainEnvFile = dirname(__DIR__) . '/.env';
if (file_exists($mainEnvFile)) {
    foreach (file($mainEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

// Gateway Ping Configuration
$ping_host     = !empty($env['PROBE_PING_HOST']) ? trim($env['PROBE_PING_HOST']) : '100.64.0.1';
$ping_duration = !empty($env['PROBE_PING_DURATION']) ? (int)$env['PROBE_PING_DURATION'] : 20;
$ping_timeout  = !empty($env['PROBE_PING_TIMEOUT']) ? (int)$env['PROBE_PING_TIMEOUT'] : 5;
$ping_count    = !empty($env['PROBE_PING_COUNT']) ? (int)$env['PROBE_PING_COUNT'] : 10;

/**
 * Execute a single ping request to the target host.
 *
 * @param string $host Target host or IP address
 * @param int $timeoutSeconds Timeout in seconds
 * @return array ['success' => bool, 'output' => string]
 */
function pingOnce($host, $timeoutSeconds = 5) {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    if ($isWindows) {
        // Windows: -n 1 packet, -w timeout in milliseconds
        $cmd = "ping -n 1 -w " . ($timeoutSeconds * 1000) . " " . escapeshellarg($host);
    } else {
        // Linux / macOS: -c 1 packet, -W timeout in seconds
        $cmd = "ping -c 1 -W " . (int)$timeoutSeconds . " " . escapeshellarg($host) . " 2>&1";
    }

    $output = [];
    $returnCode = 0;
    @exec($cmd, $output, $returnCode);

    $outputStr = implode(" ", $output);

    // Evaluate command exit code
    if ($returnCode !== 0) {
        return ['success' => false, 'output' => $outputStr];
    }

    // Detect RTO (Request Timed Out) or transmission failure indicators
    if (stripos($outputStr, 'timed out') !== false ||
        stripos($outputStr, 'Destination host unreachable') !== false ||
        stripos($outputStr, '100% packet loss') !== false ||
        stripos($outputStr, '100% loss') !== false ||
        stripos($outputStr, 'General failure') !== false ||
        stripos($outputStr, 'Transmisi gagal') !== false) {
        return ['success' => false, 'output' => $outputStr];
    }

    return ['success' => true, 'output' => $outputStr];
}

/**
 * Execute continuous ping testing over a given duration (default up to 30 seconds or max 10 pings).
 * If any timeout / RTO > 5s occurs, connection is deemed Disconnected.
 *
 * @param string $host Target IP (default: 100.64.0.1)
 * @param int $durationSeconds Testing duration limit in seconds (default: 30)
 * @param int $timeoutSeconds RTO timeout threshold in seconds (default: 5)
 * @param int $maxPings Maximum number of pings to execute (default: 10)
 * @return array Test results summary
 */
function testConnectionOverDuration($host = '100.64.0.1', $durationSeconds = 30, $timeoutSeconds = 5, $maxPings = 10) {
    $startTime = time();
    $totalPings = 0;
    $failedPings = 0;

    echo "[" . date('H:i:s') . "] Starting ping test to {$host} (max {$maxPings} pings, up to {$durationSeconds}s, RTO threshold: {$timeoutSeconds}s)..." . PHP_EOL;

    while ((time() - $startTime) < $durationSeconds && $totalPings < $maxPings) {
        $totalPings++;
        $res = pingOnce($host, $timeoutSeconds);

        if (!$res['success']) {
            $failedPings++;
            $detail = !empty($res['output']) ? " [Detail: " . trim($res['output']) . "]" : "";
            echo "[" . date('H:i:s') . "] Ping #{$totalPings} -> RTO / Failed (> {$timeoutSeconds}s){$detail}" . PHP_EOL;
        } else {
            echo "[" . date('H:i:s') . "] Ping #{$totalPings} -> Reply OK" . PHP_EOL;
        }

        // If maximum ping count reached, finish immediately
        if ($totalPings >= $maxPings) {
            break;
        }

        // Pause for up to 2 seconds between pings if time remains
        $remaining = $durationSeconds - (time() - $startTime);
        if ($remaining > 0) {
            sleep(min(2, $remaining));
        }
    }

    // If any failure or RTO occurred during testing, mark as disconnected
    $isConnected = ($failedPings === 0 && $totalPings > 0);

    echo "[" . date('H:i:s') . "] Test completed: {$totalPings} total pings, {$failedPings} RTO/failed. Status: " . ($isConnected ? "Connected" : "Disconnected") . PHP_EOL;

    return [
        'is_connected' => $isConnected,
        'total_pings'  => $totalPings,
        'failed_pings' => $failedPings,
    ];
}

// 1. Execute ping test to gateway for specified duration (up to 30 seconds or max 10 pings)
$pingResult = testConnectionOverDuration($ping_host, $ping_duration, $ping_timeout, $ping_count);
$isConnected = $pingResult['is_connected'];

// 2. Determine connection status
$status = $isConnected ? 'Connected' : 'Disconnected';
$time = time();

echo "[" . date('H:i:s') . "] Final Result: status={$status}" . PHP_EOL;

// 3. Save gateway ping status to JSON file for Dashboard Connection Status indicator
$statusFile = dirname(__DIR__) . '/gateway_status.json';
$dataToSave = [
    'status'       => $status,
    'gateway'      => $ping_host,
    'total_pings'  => $pingResult['total_pings'],
    'failed_pings' => $pingResult['failed_pings'],
    'updated_at'   => $time,
    'updated_date' => date('Y-m-d H:i:s', $time),
];

@file_put_contents($statusFile, json_encode($dataToSave, JSON_PRETTY_PRINT));
echo "[" . date('H:i:s') . "] Status saved to JSON: {$statusFile}" . PHP_EOL;
