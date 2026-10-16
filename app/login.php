<?php
require_once dirname(__DIR__) . '/config/config.php';

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header('Location: ./');
    exit;
}

$error_msg = null;
$success_msg = null;
$pdo = null;
$usersTable = env('DB_TABLE_USERS', 'users');

try {
    $pdo = get_db_connection();

    // Ensure users table exists with bcrypt password column
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS {$usersTable} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Auto-seed initial admin user with bcrypt if table is empty
    $countCheck = $pdo->query("SELECT COUNT(*) FROM {$usersTable}")->fetchColumn();
    if ($countCheck == 0) {
        $defaultUser = env('ADMIN_DEFAULT_USER', 'admin');
        $defaultEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@example.com');
        $defaultPassword = env('ADMIN_DEFAULT_PASSWORD', 'admin123');
        $defaultName = env('ADMIN_DEFAULT_NAME', 'Administrator');

        $hashedBcrypt = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $seedStmt = $pdo->prepare("INSERT INTO {$usersTable} (username, email, password, name) VALUES (?, ?, ?, ?)");
        $seedStmt->execute([$defaultUser, $defaultEmail, $hashedBcrypt, $defaultName]);
    }
} catch (Exception $e) {
    $error_msg = 'Database connection error: ' . $e->getMessage();
}

// ALTCHA Configuration
$altchaApiKey = env('ALTCHA_API_KEY', 'altcha_key_cfcf19784ea0bd2e5cfdd7544daf2947');
$altchaChallengeUrl = env('ALTCHA_CHALLENGE_URL', 'https://altcha.silverlightning.my.id/challenge?apiKey=' . $altchaApiKey);
$altchaVerifyUrl = env('ALTCHA_VERIFY_URL', 'https://altcha.silverlightning.my.id/verify');

// Ensure HTTPS is always enforced to prevent browser Mixed-Content blocking
$altchaChallengeUrl = preg_replace('/^http:\/\//i', 'https://', $altchaChallengeUrl);
$altchaVerifyUrl = preg_replace('/^http:\/\//i', 'https://', $altchaVerifyUrl);

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $loginInput = trim($_POST['username'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');
    $altchaPayload = trim($_POST['altcha'] ?? ($_POST['payload'] ?? ''));

    if (empty($loginInput) || empty($passwordInput)) {
        $error_msg = 'Please enter both username/email and password.';
    } elseif (empty($altchaPayload)) {
        $error_msg = 'Please complete the security verification (ALTCHA).';
    } else {
        // Verify ALTCHA Payload with verification server
        $altchaVerified = false;
        $altchaError = null;
        $postData = json_encode(['payload' => $altchaPayload]);

        if (function_exists('curl_init')) {
            $ch = curl_init($altchaVerifyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $altchaApiKey,
                'Origin: https://inetmonitor.silverlightning.my.id',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($response !== false) {
                $decoded = json_decode($response, true);
                if ($httpCode === 200 && is_array($decoded) && !empty($decoded['success'])) {
                    $altchaVerified = true;
                } else {
                    $altchaError = $decoded['error'] ?? 'Security verification was rejected.';
                }
            } else {
                $altchaError = 'Connection to verification service failed: ' . $curlErr;
            }
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                 "Authorization: Bearer " . $altchaApiKey . "\r\n" .
                                 "Origin: https://inetmonitor.silverlightning.my.id\r\n",
                    'content' => $postData,
                    'timeout' => 10,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($altchaVerifyUrl, false, $context);
            if ($response !== false) {
                $decoded = json_decode($response, true);
                if (is_array($decoded) && !empty($decoded['success'])) {
                    $altchaVerified = true;
                } else {
                    $altchaError = $decoded['error'] ?? 'Security verification was rejected.';
                }
            } else {
                $altchaError = 'Connection to verification service failed.';
            }
        }

        if (!$altchaVerified) {
            $error_msg = $altchaError ?: 'Security verification failed. Please try again.';
        } else {
            // ALTCHA passed, authenticate user credentials
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, password, name FROM {$usersTable} WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$loginInput, $loginInput]);
                $user = $stmt->fetch();

                if ($user && password_verify($passwordInput, $user['password'])) {
                    // Login successful - Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_name'] = !empty($user['name']) ? $user['name'] : $user['username'];

                    header('Location: ./');
                    exit;
                } else {
                    $error_msg = 'Invalid username/email or password.';
                }
            } catch (Exception $e) {
                $error_msg = 'Authentication error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= htmlspecialchars(env('APP_NAME', 'Monitoring')) ?> | Login</title>

    <!--begin::Theme Init (Light Mode Only)-->
    <script>
      (() => {
        'use strict';
        try {
          localStorage.removeItem('lte-theme');
        } catch {}
        document.documentElement.setAttribute('data-bs-theme', 'light');
        document.documentElement.setAttribute('data-lte-color-mode', 'off');
        document.documentElement.style.colorScheme = 'light';
      })();
    </script>
    <!--end::Theme Init-->

    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light" />
    <meta name="theme-color" content="#007bff" />
    <!--end::Accessibility Meta Tags-->

    <!--begin::Primary Meta Tags-->
    <meta name="title" content="<?= htmlspecialchars(env('APP_NAME', 'Monitoring')) ?> | Login" />
    <meta name="author" content="ColorlibHQ" />
    <meta
      name="description"
      content="AdminLTE is a free Bootstrap 5 admin dashboard template with almost 50 example pages, built with vanilla JS and designed with accessibility in mind."
    />
    <!--end::Primary Meta Tags-->

    <meta name="supported-color-schemes" content="light" />
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg" />
    <link rel="alternate icon" href="favicon.svg" />
    <link rel="preload" href="css/adminlte.css" as="style" />

    <!--begin::Local Fonts & Third Party Plugins-->
    <link rel="stylesheet" href="assets/vendor/source-sans-3/index.css" />
    <link rel="stylesheet" href="assets/vendor/css/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="css/adminlte.css" />
    <!--end::Local Fonts & Third Party Plugins-->

    <script src="assets/vendor/altcha/altcha.min.js" defer></script>

    <style>
      altcha-widget {
        --altcha-max-width: 100%;
        width: 100%;
        display: block;
        min-height: 70px;
      }
      .altcha {
        display: block !important;
      }
    </style>
  </head>
  <!--end::Head-->

  <!--begin::Body-->
  <body class="login-page bg-body-secondary">
    <main class="login-box">
      <div class="login-logo mb-3">
        <a href="./" class="text-decoration-none"><b><?= htmlspecialchars(env('APP_SHORT_NAME', env('APP_NAME', 'Monitoring'))) ?></b></a>
      </div>
      <!-- /.login-logo -->

      <div class="card shadow-sm border-0">
        <div class="card-body login-card-body p-4">
          <p class="login-box-msg text-secondary mb-3">Sign in to start your session</p>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger py-2 px-3 fs-7 mb-3" role="alert">
              <i class="bi bi-exclamation-circle-fill me-1"></i>
              <?= htmlspecialchars($error_msg) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success py-2 px-3 fs-7 mb-3" role="alert">
              <i class="bi bi-check-circle-fill me-1"></i>
              <?= htmlspecialchars($success_msg) ?>
            </div>
          <?php endif; ?>

          <form action="login" method="post">
            <label class="visually-hidden" for="loginUsername">Username or Email</label>
            <div class="input-group mb-3">
              <input
                id="loginUsername"
                name="username"
                type="text"
                class="form-control"
                placeholder="Email or Username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                required
                autofocus
              />
              <div class="input-group-text">
                <span class="bi bi-person"></span>
              </div>
            </div>

            <label class="visually-hidden" for="loginPassword">Password</label>
            <div class="input-group mb-3">
              <input
                id="loginPassword"
                name="password"
                type="password"
                class="form-control"
                placeholder="Password"
                required
              />
              <div class="input-group-text">
                <span class="bi bi-lock-fill"></span>
              </div>
            </div>

            <!--begin::ALTCHA Widget-->
            <div class="mb-3">
              <altcha-widget challengeurl="<?= htmlspecialchars($altchaChallengeUrl) ?>" display="standard"></altcha-widget>
            </div>
            <!--end::ALTCHA Widget-->

            <!--begin::Row-->
            <div class="row align-items-center mb-3">
              <div class="col-8">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="remember" value="1" id="flexCheckDefault" />
                  <label class="form-check-label fs-7" for="flexCheckDefault"> Remember Me </label>
                </div>
              </div>
              <!-- /.col -->
              <div class="col-4">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Sign In</button>
                </div>
              </div>
              <!-- /.col -->
            </div>
            <!--end::Row-->
          </form>
        </div>
        <!-- /.login-card-body -->
      </div>
    </main>
    <!-- /.login-box -->

    <!--begin::Local Required Plugins-->
    <script src="assets/vendor/js/overlayscrollbars.browser.es6.min.js"></script>
    <script src="assets/vendor/js/popper.min.js"></script>
    <script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script src="js/adminlte.js"></script>
    <!--end::Local Required Plugins-->
  </body>
  <!--end::Body-->
</html>
