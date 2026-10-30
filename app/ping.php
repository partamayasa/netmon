<?php
/**
 * Heartbeat Ping API Endpoint
 * Accepts periodic HTTP ping requests from client devices (curl, PowerShell, etc.)
 * Validates authentication token and records the ping timestamp into ping_logs.json.
 * NOTE: IP Address is NOT saved. Logs are stored locally in ping_logs.json.
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/config.php';

// Allow GET and POST methods
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($requestMethod, ['GET', 'POST', 'HEAD'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Retrieve provided token from multiple possible sources
$providedToken = null;
if (!empty($_GET['token'])) {
    $providedToken = trim($_GET['token']);
} elseif (!empty($_POST['token'])) {
    $providedToken = trim($_POST['token']);
} elseif (!empty($_SERVER['HTTP_X_PING_TOKEN'])) {
    $providedToken = trim($_SERVER['HTTP_X_PING_TOKEN']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($authHeader, 'Bearer ') === 0) {
        $providedToken = substr($authHeader, 7);
    }
}

$configuredToken = env('PING_TOKEN');

// Validate authentication
if (empty($configuredToken) || empty($providedToken) || !hash_equals($configuredToken, $providedToken)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Invalid or missing ping token'
    ]);
    exit;
}

$time = time();
$status = 'Connected';

/**
 * Determine a guaranteed writable path for ping_logs.json.
 * Tries root directory -> app/ directory -> system temp directory.
 */
function get_writable_ping_log_path() {
    $rootDir = dirname(__DIR__);
    $candidates = [
        $rootDir . '/ping_logs.json',
        __DIR__ . '/ping_logs.json',
        sys_get_temp_dir() . '/netmon_ping_logs.json',
    ];

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            if (!is_writable($path)) {
                @chmod($path, 0666);
            }
            if (is_writable($path)) {
                return $path;
            }
        }
        $dir = dirname($path);
        if (is_dir($dir) && is_writable($dir)) {
            return $path;
        }
    }

    return $candidates[0];
}

$logFile = get_writable_ping_log_path();
$existingLogs = [];

if (file_exists($logFile)) {
    $content = @file_get_contents($logFile);
    if (!empty($content)) {
        $decoded = @json_decode($content, true);
        if (is_array($decoded)) {
            $existingLogs = $decoded;
        }
    }
}

// Compute next incremental ID
$nextId = 1;
if (!empty($existingLogs) && isset($existingLogs[0]['id'])) {
    $nextId = (int)$existingLogs[0]['id'] + 1;
}

$newEntry = [
    'id'             => $nextId,
    'status'         => $status,
    'created_at'     => $time,
    'formatted_date' => date('Y-m-d H:i:s', $time),
];

// Prepend the new entry so index 0 is always the most recent log
array_unshift($existingLogs, $newEntry);

// Maintain the latest 15 logs to prevent file bloat
if (count($existingLogs) > 15) {
    $existingLogs = array_slice($existingLogs, 0, 15);
}

$jsonOutput = json_encode($existingLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$writeSuccess = @file_put_contents($logFile, $jsonOutput);

http_response_code(200);
echo json_encode([
    'success'         => true,
    'message'         => 'Ping recorded successfully',
    'status'          => $status,
    'timestamp'       => $time,
    'formatted_time'  => date('Y-m-d H:i:s', $time),
    'file_saved'      => ($writeSuccess !== false),
    'log_file_path'   => basename($logFile),
    'full_path'       => $logFile,
    'total_ping_logs' => count($existingLogs),
]);
