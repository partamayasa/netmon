<?php
date_default_timezone_set('Asia/Makassar');

// Baca file .env
$env = [];
foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

$db_host = $env['DB_HOST'];
$db_port = $env['DB_PORT'];
$db_name = $env['DB_NAME'];
$db_user = $env['DB_USER'];
$db_pass = $env['DB_PASS'];

$token   = $env['TELEGRAM_BOT_TOKEN'];
$chat_id = $env['TELEGRAM_CHAT_ID'];

// Koneksi Database
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage() . PHP_EOL);
}

// Fungsi Ambil IP Publik
function getPublicIP() {
    $ch = curl_init('https://api.ipify.org');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $ip = curl_exec($ch);
    curl_close($ch);
    return $ip ? trim($ip) : null;
}

$public_ip = getPublicIP();

// Logika penetapan nilai
$status = $public_ip ? 'Connected' : null;
$time   = $public_ip ? time() : null;

// Simpan ke Database
$stmt = $pdo->prepare("INSERT INTO internet_logs (status, public_ip, created_at) VALUES (:status, :public_ip, :created_at)");
$stmt->execute([
    ':status'     => $status,
    ':public_ip'  => $public_ip,
    ':created_at' => $time
]);

// Kirim ke Telegram
$time_formatted = $time ? date('Y-M-d H:i', $time) : 'N/A';
$status_display = $status ?? 'Disconnected';
$ip_display     = $public_ip ?? 'N/A';

$message = "Internet status : " . $status_display . "\nIP Public : " . $ip_display . "\nLast update : " . $time_formatted;

$data = [
    'chat_id' => $chat_id,
    'text'    => $message
];

$ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$result = curl_exec($ch);
curl_close($ch);

echo "Log berhasil disimpan." . PHP_EOL;
?>