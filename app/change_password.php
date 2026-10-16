<?php
/**
 * Change Password Handler (AJAX Endpoint)
 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/config.php';

// Verify authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Request method not allowed.']);
    exit;
}

$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword     = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// Validation
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New password confirmation does not match.']);
    exit;
}

if ($currentPassword === $newPassword) {
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as the current password.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $usersTable = env('DB_TABLE_USERS', 'users');

    // Fetch user current hashed password
    $stmt = $pdo->prepare("SELECT id, password FROM {$usersTable} WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User account not found.']);
        exit;
    }

    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    // Hash new password using bcrypt
    $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    $updateStmt = $pdo->prepare("UPDATE {$usersTable} SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashedNewPassword, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error occurred: ' . $e->getMessage()]);
}
