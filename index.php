<?php
require_once __DIR__ . '/config/config.php';

// Handle direct logout request fallback
if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    $_SESSION = [];
    session_unset();
    if (ini_get("session.use_cookies")) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
    session_write_close();
    header('Location: /login');
    exit;
}

// Ensure user is authenticated before accessing monitoring data
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$logsTable = env('DB_TABLE_LOGS', 'internet_logs');



// Server-side query for initial load
$logs = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT id, status, public_ip, created_at FROM {$logsTable} ORDER BY id DESC");
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    if (file_exists(__DIR__ . '/internet_logs.json')) {
        $json = file_get_contents(__DIR__ . '/internet_logs.json');
        $decoded = json_decode($json, true);
        if ($decoded) {
            $logs = isset($decoded['id']) ? [$decoded] : $decoded;
        }
    }
}

// Compute dashboard metrics
$totalLogs = count($logs);
$latestLog = !empty($logs) ? $logs[0] : null;

// Format last update & parse timestamp (yyyy-mm-dd hh:mm)
$rawCreatedAt = $latestLog['created_at'] ?? null;
$timestamp = null;
if (!empty($rawCreatedAt)) {
    if (is_numeric($rawCreatedAt)) {
        $timestamp = (int)$rawCreatedAt;
        if ($timestamp > 10000000000) {
            $timestamp = (int)($timestamp / 1000);
        }
        $formattedLastUpdate = date('Y-m-d H:i', $timestamp);
    } else {
        $parsedTime = strtotime($rawCreatedAt);
        $timestamp = $parsedTime ?: null;
        $formattedLastUpdate = $parsedTime ? date('Y-m-d H:i', $parsedTime) : $rawCreatedAt;
    }
} else {
    $formattedLastUpdate = '-';
}

// Connection Status:
// Exclusively determined by gateway_status.json (produced by server-side net-probe ping to 100.64.0.1)
$gatewayStatusFile = __DIR__ . '/gateway_status.json';
$gatewayData = null;
if (file_exists($gatewayStatusFile)) {
    $rawGatewayJson = @file_get_contents($gatewayStatusFile);
    $gatewayData = @json_decode($rawGatewayJson, true);
}

$statusTimestamp = !empty($gatewayData['updated_at']) ? (int)$gatewayData['updated_at'] : null;
$statusDiff = $statusTimestamp !== null ? (time() - $statusTimestamp) : null;
$statusTimeoutLimit = (int)env('PROBE_STATUS_TIMEOUT', 90); // 90s (1.5 min) tolerance window for 1-minute cron
$isGatewayConnected = !empty($gatewayData['status']) && (strcasecmp($gatewayData['status'], 'Connected') === 0);

$statusTimeStr = '';
if ($statusTimestamp !== null) {
    $statusTimeStr = ' (' . date('H:i', $statusTimestamp) . ')';
}

if ($statusDiff !== null && $statusDiff <= $statusTimeoutLimit && $isGatewayConnected) {
    $latestStatus = 'Connected' . $statusTimeStr;
    $statusBgClass = 'text-bg-success';
    $statusIconClass = 'bi-wifi';
} else {
    $latestStatus = 'Disconnected' . $statusTimeStr;
    $statusBgClass = 'text-bg-danger';
    $statusIconClass = 'bi-wifi-off';
}

// Latest Public IP
$latestIp = !empty($latestLog['public_ip']) ? trim($latestLog['public_ip']) : '-';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= htmlspecialchars(env('APP_NAME', 'Monitoring')) ?></title>

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
    <meta name="title" content="<?= htmlspecialchars(env('APP_NAME', 'Monitoring')) ?>" />
    <meta name="author" content="ColorlibHQ" />
    <meta
      name="description"
      content="AdminLTE is a free Bootstrap 5 admin dashboard template with almost 50 example pages, built with vanilla JS and designed with accessibility in mind."
    />
    <!--end::Primary Meta Tags-->

    <meta name="supported-color-schemes" content="light" />
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg" />
    <link rel="preload" href="./css/adminlte.css" as="style" />

    <!--begin::Local Fonts & Third Party Plugins-->
    <link rel="stylesheet" href="./assets/vendor/source-sans-3/index.css" />
    <link rel="stylesheet" href="./assets/vendor/css/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="./assets/vendor/bootstrap-icons/bootstrap-icons.min.css" />
    <!--end::Local Fonts & Third Party Plugins-->

    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="./css/adminlte.css?v=<?= filemtime(__DIR__ . '/css/adminlte.css') ?>" />
    <!--end::Required Plugin(AdminLTE)-->

    <link rel="stylesheet" href="./assets/vendor/tabulator/tabulator_bootstrap5.min.css" />

  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
      <!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a
                class="nav-link"
                data-lte-toggle="sidebar"
                href="#"
                role="button"
                aria-label="Toggle sidebar"
              >
                <i class="bi bi-list"></i>
              </a>
            </li>

            <li class="nav-item d-none d-md-block">
              <a href="./index.php" class="nav-link active">
                Monitoring
              </a>
            </li>
          </ul>
          <!--end::Start Navbar Links-->

          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto">
            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="./assets/img/default-user.svg"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                />
                <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 300px;">
                <li class="user-header text-bg-primary p-3 text-center">
                  <img
                    src="./assets/img/default-user.svg"
                    class="rounded-circle shadow"
                    alt="User Image"
                    style="width: 80px; height: 80px;"
                  />
                  <p class="mt-2 mb-0">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                    <small class="d-block text-white-50"><?= htmlspecialchars($_SESSION['email'] ?? 'home_monitoring') ?></small>
                  </p>
                </li>
                <li class="user-footer d-flex justify-content-between align-items-center p-2 bg-body-tertiary">
                  <button type="button" class="btn btn-default btn-flat text-nowrap" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="bi bi-key me-1"></i> Change Password
                  </button>
                  <a href="/logout" class="btn btn-default btn-flat text-nowrap">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign out
                  </a>
                </li>
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
      </nav>
      <!--end::Header-->

      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand text-center">
          <a href="./index.php" class="brand-link w-100 justify-content-center text-center">
            <span class="brand-text fw-semibold m-0"><?= htmlspecialchars(env('APP_SHORT_NAME', 'Monitoring')) ?></span>
          </a>
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2" aria-label="Main navigation">
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              data-accordion="false"
              id="navigation"
            >
              <!-- Menu Monitoring -->
              <li class="nav-item">
                <a href="./index.php" class="nav-link active">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Monitoring</p>
                </a>
              </li>
            </ul>
          </nav>
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->

      <main class="app-main">
        <div class="app-content-header">
          <!--begin::Container-->
          <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
              <div class="col-sm-6">
                <h1 class="mb-0 fs-3">Dashboard</h1>
              </div>
              <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="./index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                  </ol>
                </nav>
              </div>
            </div>
            <!--end::Row-->
          </div>
          <!--end::Container-->
        </div>
        <div class="app-content">
          <!--begin::Container-->
          <div class="container-fluid">
            <!-- Info boxes -->
            <div class="row">
              <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                  <span class="info-box-icon text-bg-primary shadow-sm">
                    <i class="bi bi-hdd-stack-fill"></i>
                  </span>

                  <div class="info-box-content">
                    <span class="info-box-text">Total Logs</span>
                    <span class="info-box-number" id="card-total-logs"><?= number_format($totalLogs) ?></span>
                  </div>
                  <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
              </div>
              <!-- /.col -->
              <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                  <span class="info-box-icon text-bg-info shadow-sm">
                    <i class="bi bi-clock-history"></i>
                  </span>

                  <div class="info-box-content">
                    <span class="info-box-text">Last Updated</span>
                    <span class="info-box-number" id="card-last-updated"><?= htmlspecialchars($formattedLastUpdate) ?></span>
                  </div>
                  <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
              </div>
              <!-- /.col -->

              <!-- fix for small devices only -->
              <!-- <div class="clearfix hidden-md-up"></div> -->

              <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                  <span class="info-box-icon <?= $statusBgClass ?> shadow-sm" id="card-status-icon-box">
                    <i class="bi <?= $statusIconClass ?>" id="card-status-icon"></i>
                  </span>

                  <div class="info-box-content">
                    <span class="info-box-text">Connection Status</span>
                    <span class="info-box-number" id="card-status-text"><?= htmlspecialchars($latestStatus) ?></span>
                  </div>
                  <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
              </div>
              <!-- /.col -->
              <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                  <span class="info-box-icon text-bg-warning shadow-sm">
                    <i class="bi bi-globe2"></i>
                  </span>

                  <div class="info-box-content">
                    <span class="info-box-text">Latest Public IP</span>
                    <span class="info-box-number" id="card-latest-ip"><?= htmlspecialchars($latestIp) ?></span>
                  </div>
                  <!-- /.info-box-content -->
                </div>
                <!-- /.info-box -->
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Monitoring Logs</h3>
                <div class="card-tools">
                  <div class="input-group input-group-sm" style="width: 16rem">
                    <span class="input-group-text">
                      <i class="bi bi-search" aria-hidden="true"></i>
                    </span>
                    <input
                      id="table-filter"
                      type="search"
                      class="form-control"
                      placeholder="Filter rows&hellip;"
                      aria-label="Filter rows"
                    />
                  </div>
                </div>
              </div>
              <div class="card-body">
                <div class="d-flex gap-2 mb-3">
                  <button id="export-csv" type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>
                    Export CSV
                  </button>
                  <button id="export-json" type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-filetype-json me-1" aria-hidden="true"></i>
                    Export JSON
                  </button>
                </div>
                <div id="users-table" aria-label="Internet Logs"></div>
                <div
                  id="table-pagination"
                  class="d-flex flex-wrap gap-2 justify-content-end mt-2"
                ></div>
              </div>
              <div class="card-footer bg-body text-body-secondary small">
                Powered by
                <a href="https://tabulator.info/" target="_blank" rel="noopener">Tabulator</a>
                &mdash; vanilla JS, no jQuery required.
              </div>
            </div>
          </div>
        </div>
      </main>
      <!--begin::Footer-->
      <footer class="app-footer">
        <!--begin::Copyright-->
        <strong>
          Copyright &copy; <?= date('Y') ?>&nbsp;
          <a href="<?= htmlspecialchars(env('APP_URL') ?: '#') ?>" class="text-decoration-none" target="_blank" rel="noopener"><?= htmlspecialchars(env('APP_NAME', 'Home Monitoring System')) ?></a>.
        </strong>
        All rights reserved.
        <!--end::Copyright-->
      </footer>
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->

    <!--begin::Change Password Modal-->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title d-flex align-items-center" id="changePasswordModalLabel">
              <i class="bi bi-shield-lock text-primary me-2 fs-5"></i> Change Password
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="form-change-password">
            <div class="modal-body">
              <div id="change-pwd-alert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>

              <div class="mb-3">
                <label for="current_password" class="form-label fw-semibold">Current Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-key"></i></span>
                  <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Enter current password">
                  <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="current_password" title="Show/Hide Password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <div class="mb-3">
                <label for="new_password" class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required placeholder="Minimum 6 characters">
                  <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="new_password" title="Show/Hide Password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <div class="form-text">Use a combination of at least 6 characters.</div>
              </div>

              <div class="mb-3">
                <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required placeholder="Repeat new password">
                  <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="confirm_password" title="Show/Hide Password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" id="btn-save-pwd" class="btn btn-primary">
                <span class="spinner-border spinner-border-sm d-none me-1" id="spinner-save-pwd" role="status" aria-hidden="true"></span>
                <i class="bi bi-check-circle me-1" id="icon-save-pwd"></i>Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!--end::Change Password Modal-->

    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script src="./assets/vendor/js/overlayscrollbars.browser.es6.min.js"></script>
    <!--end::Third Party Plugin(OverlayScrollbars)-->

    <!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script src="./assets/vendor/js/popper.min.js"></script>
    <!--end::Required Plugin(popperjs for Bootstrap 5)-->

    <!--begin::Required Plugin(Bootstrap 5)-->
    <script src="./assets/vendor/js/bootstrap.min.js"></script>
    <!--end::Required Plugin(Bootstrap 5)-->

    <!--begin::Required Plugin(AdminLTE)-->
    <script src="./js/adminlte.js"></script>
    <!--end::Required Plugin(AdminLTE)-->

    <!--begin::OverlayScrollbars Configure-->
    <script>
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const Default = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
      };
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);

        // Disable OverlayScrollbars on mobile devices to prevent touch interference
        const isMobile = window.innerWidth <= 992;

        if (
          sidebarWrapper &&
          globalThis.OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined &&
          !isMobile
        ) {
          OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: Default.scrollbarTheme,
              autoHide: Default.scrollbarAutoHide,
              clickScroll: Default.scrollbarClickScroll,
            },
          });
        }
      });
    </script>
    <!--end::OverlayScrollbars Configure-->

    <script src="./assets/vendor/tabulator/tabulator.min.js"></script>
    <script>
      const statusBadge = (cell) => {
        const value = cell.getValue();
        const str = String(value || '').trim();
        const map = {
          Active: 'success',
          Connected: 'success',
          Disconnected: 'danger',
          Suspended: 'secondary',
          Invited: 'info',
        };
        const color = map[str] || (str.toLowerCase() === 'connected' ? 'success' : 'danger');
        return `<span class="badge text-bg-${color}">${value}</span>`;
      };

      document.addEventListener('DOMContentLoaded', () => {
        const data = <?= json_encode($logs) ?>;

        const table = new Tabulator('#users-table', {
          data: data,
          layout: 'fitColumns',
          pagination: true,
          paginationElement: document.getElementById('table-pagination'),
          paginationSize: 10,
          paginationSizeSelector: [10, 25, 50, 100],
          movableColumns: true,
          columns: [
            {
              title: '#',
              formatter: 'rownum',
              formatterParams: { relativeToPage: true },
              hozAlign: 'center',
              width: 60,
              headerSort: false,
              accessorDownload: (value, data, type, params, column, row) => row.getPosition(true),
            },
            {
              title: 'Status',
              field: 'status',
              formatter: statusBadge,
              width: 130,
              hozAlign: 'center',
            },
            {
              title: 'Public IP',
              field: 'public_ip',
            },
            {
              title: 'Created At',
              field: 'created_at',
              sorter: 'string',
              formatter: (cell) => {
                const raw = cell.getValue();
                if (!raw) return '-';
                let d;
                const num = Number(raw);
                if (!isNaN(num) && num > 0) {
                  d = new Date(num > 10000000000 ? num : num * 1000);
                } else {
                  d = new Date(String(raw).replace(/-/g, '/'));
                }
                if (isNaN(d.getTime())) return raw;
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                const hh = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
              },
              width: 170,
            },
          ],
        });

        table.on('tableBuilt', () => {
          // Keep the scroll region keyboard reachable inside a valid grid.
          // Pagination lives outside the grid so its buttons are not grid rows.
          const grid = document.getElementById('users-table');
          grid.removeAttribute('aria-owns');
          grid.querySelector('.tabulator-tableholder')?.setAttribute('role', 'rowgroup');
          grid.querySelector('.tabulator-table')?.setAttribute('role', 'presentation');
        });

        table.on('renderComplete', () => {
          document.querySelectorAll('#table-pagination button').forEach((button) => {
            button.classList.add('btn', 'btn-sm', 'btn-outline-secondary');
          });
        });

        document.getElementById('table-filter').addEventListener('input', (e) => {
          const value = e.target.value;
          if (value) {
            table.setFilter([
              [
                { field: 'public_ip', type: 'like', value: value },
                { field: 'status', type: 'like', value: value },
              ],
            ]);
          } else {
            table.clearFilter();
          }
        });

        document
          .getElementById('export-csv')
          .addEventListener('click', () => table.download('csv', 'internet_logs.csv'));
        document
          .getElementById('export-json')
          .addEventListener('click', () => table.download('json', 'internet_logs.json'));

        // Auto-check gateway_status.json every 1 minute (60s) for real-time status
        const updateGatewayStatus = async () => {
          const iconBox = document.getElementById('card-status-icon-box');
          const icon = document.getElementById('card-status-icon');
          const text = document.getElementById('card-status-text');
          if (!iconBox || !icon || !text) return;

          try {
            const res = await fetch('./gateway_status.json?v=' + Date.now(), { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.status) return;

            const nowSec = Math.floor(Date.now() / 1000);
            const ts = Number(data.updated_at) || 0;
            const diff = ts > 0 ? (nowSec - ts) : null;
            const isConnected = String(data.status).trim().toLowerCase() === 'connected';
            const timeoutLimit = <?= (int)env('PROBE_STATUS_TIMEOUT', 90) ?>;

            let timeStr = '';
            if (data.updated_date && typeof data.updated_date === 'string') {
              const parts = data.updated_date.trim().split(' ');
              if (parts.length >= 2) {
                const timeParts = parts[1].split(':');
                if (timeParts.length >= 2) {
                  timeStr = ` (${timeParts[0]}:${timeParts[1]})`;
                }
              }
            } else if (ts > 0) {
              const d = new Date(ts * 1000);
              const hh = String(d.getHours()).padStart(2, '0');
              const mm = String(d.getMinutes()).padStart(2, '0');
              timeStr = ` (${hh}:${mm})`;
            }

            if (diff !== null && diff <= timeoutLimit && isConnected) {
              iconBox.className = 'info-box-icon text-bg-success shadow-sm';
              icon.className = 'bi bi-wifi';
              text.textContent = 'Connected' + timeStr;
            } else {
              iconBox.className = 'info-box-icon text-bg-danger shadow-sm';
              icon.className = 'bi bi-wifi-off';
              text.textContent = 'Disconnected' + timeStr;
            }
          } catch (e) {
            // Ignore fetch errors
          }
        };

        // Run check every 60 seconds (1 minute)
        setInterval(updateGatewayStatus, 60000);

        // ==========================================
        // Change Password Feature (Modal Popup)
        // ==========================================
        // Toggle Show/Hide Password
        document.querySelectorAll('.toggle-pwd-btn').forEach((btn) => {
          btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (!input || !icon) return;

            if (input.type === 'password') {
              input.type = 'text';
              icon.className = 'bi bi-eye-slash';
            } else {
              input.type = 'password';
              icon.className = 'bi bi-eye';
            }
          });
        });

        // Form Submit Handler via AJAX
        const formChangePwd = document.getElementById('form-change-password');
        const alertBox = document.getElementById('change-pwd-alert');
        const btnSavePwd = document.getElementById('btn-save-pwd');
        const spinnerSavePwd = document.getElementById('spinner-save-pwd');
        const iconSavePwd = document.getElementById('icon-save-pwd');
        const modalEl = document.getElementById('changePasswordModal');

        if (modalEl) {
          modalEl.addEventListener('hidden.bs.modal', function () {
            if (formChangePwd) formChangePwd.reset();
            alertBox.className = 'alert d-none py-2 px-3 mb-3';
            alertBox.textContent = '';
            ['current_password', 'new_password', 'confirm_password'].forEach((id) => {
              const el = document.getElementById(id);
              if (el) el.type = 'password';
            });
            document.querySelectorAll('.toggle-pwd-btn i').forEach((icon) => {
              icon.className = 'bi bi-eye';
            });
          });
        }

        if (formChangePwd) {
          formChangePwd.addEventListener('submit', async function (e) {
            e.preventDefault();

            const newPwd = document.getElementById('new_password').value;
            const confirmPwd = document.getElementById('confirm_password').value;

            if (newPwd.length < 6) {
              alertBox.className = 'alert alert-danger py-2 px-3 mb-3';
              alertBox.textContent = 'New password must be at least 6 characters.';
              return;
            }

            if (newPwd !== confirmPwd) {
              alertBox.className = 'alert alert-danger py-2 px-3 mb-3';
              alertBox.textContent = 'New password confirmation does not match.';
              return;
            }

            // Show loading state
            btnSavePwd.disabled = true;
            spinnerSavePwd.classList.remove('d-none');
            iconSavePwd.classList.add('d-none');
            alertBox.className = 'alert d-none py-2 px-3 mb-3';

            try {
              const formData = new FormData(formChangePwd);
              const response = await fetch('change-password', {
                method: 'POST',
                body: formData,
              });

              const result = await response.json();

              if (result.success) {
                alertBox.className = 'alert alert-success py-2 px-3 mb-3';
                alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + result.message;
                formChangePwd.reset();
                setTimeout(() => {
                  const modalInstance = bootstrap.Modal.getInstance(modalEl);
                  if (modalInstance) modalInstance.hide();
                }, 1500);
              } else {
                alertBox.className = 'alert alert-danger py-2 px-3 mb-3';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + (result.message || 'Failed to update password.');
              }
            } catch (err) {
              alertBox.className = 'alert alert-danger py-2 px-3 mb-3';
              alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>A server connection error occurred.';
            } finally {
              btnSavePwd.disabled = false;
              spinnerSavePwd.classList.add('d-none');
              iconSavePwd.classList.remove('d-none');
            }
          });
        }
      });
    </script>
  </body>
</html>
