# Home Internet Monitoring System

A lightweight, modern, and responsive web dashboard designed for real-time home internet status monitoring, public IP tracking, and logging with interactive data tables and secure user authentication.

Built with **AdminLTE v4**, **Bootstrap 5**, and native **PHP (PDO)** without heavy framework dependencies.

---

## Key Features

- **Real-Time Dashboard Metrics**:
  - **Total Logs**: Live counter of recorded ping/connection logs.
  - **Last Updated**: Formatted timestamp of the most recent log.
  - **Connection Status**: Dynamic threshold check:
    - `Connected` (Green): If the latest log was recorded within the last 15 minutes ($\le$ 900 seconds).
    - `Disconnected` (Red): If the latest log exceeds 15 minutes or no data is found.
  - **Latest Public IP**: Current external IP address recorded from the client.
- **Interactive Data Table**:
  - Powered by **Tabulator JS** with Bootstrap 5 theme.
  - Client-side and server-side ready, client pagination (10, 25, 50, 100 rows), multi-column search filter, and column sorting.
  - One-click data export to **CSV** and **JSON**.
- **Secure Authentication**:
  - Secure login with session management.
  - Passwords hashed with standard **Bcrypt** (`PASSWORD_BCRYPT`).
  - Auto-seeds default administrator credentials on first run if the `users` table is empty.
- **Change Password Modal**:
  - Seamless in-page modal dialog via AJAX (no page reloads required).
  - Password show/hide toggle visibility icons.
  - Instant server-side verification and feedback.
- **Zero Heavy Build Step**:
  - All vendor assets (Bootstrap, Bootstrap Icons, Source Sans 3, OverlayScrollbars, Tabulator) are bundled locally for offline and intranet accessibility.

---

## Project Structure

```text
web-monitoring/
├── .env                      # Environment configuration (ignored in git)
├── .env.example              # Sample environment configuration template
├── .gitignore                # Git ignore rules
├── .htaccess                 # Apache rewrite & security protection rules
├── index.php                 # Main monitoring dashboard
├── README.md                 # Project documentation
├── app/
│   ├── login.php             # User authentication / sign-in page
│   ├── logout.php            # Session termination handler
│   ├── change_password.php   # AJAX endpoint for password updates
│   └── router.php            # Local PHP CLI built-in web server router
├── config/
│   └── config.php            # Environment loader & PDO database connection
├── assets/
│   ├── img/
│   │   ├── favicon.svg       # SVG favicon
│   │   └── default-user.svg  # Default avatar
│   └── vendor/               # Offline libraries (Bootstrap, Tabulator, Icons, ALTCHA, etc.)
├── css/
│   └── adminlte.css          # Production AdminLTE styling
└── js/
    └── adminlte.js           # Production AdminLTE scripts
```

---

## Requirements

- **PHP 8.0** or higher (with `pdo_mysql` extension enabled).
- **MySQL / MariaDB** database server.
- Modern web browser (Chrome, Edge, Firefox, Safari).

---

## Installation & Setup

### 1. Clone or Download Repository

Place the files into your local directory or web server root.

### 2. Configure Environment Variables

Copy the sample environment file to create your active `.env`:

```bash
cp .env.example .env
```

Open `.env` and fill in your database credentials:

```env
# Application Settings
APP_NAME="Home Internet Monitoring System"
APP_SHORT_NAME="InternetMonitoring"
APP_TIMEZONE="Asia/Makassar"

# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
DB_TABLE_LOGS=internet_logs
DB_TABLE_USERS=users

# Initial Administrator Credentials (Auto-seed if users table is empty)
ADMIN_DEFAULT_USER=admin
ADMIN_DEFAULT_EMAIL=admin@example.com
ADMIN_DEFAULT_PASSWORD=admin123
ADMIN_DEFAULT_NAME=Administrator
```

### 3. Database Tables

The application automatically creates the `users` table and seeds the default admin user upon first access to `app/login.php`.

For the internet logs table (`internet_logs`), ensure your logging client inserts records with the following schema:

```sql
CREATE TABLE IF NOT EXISTS internet_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(50) NOT NULL,
    public_ip VARCHAR(45) NULL,
    created_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

*(Note: `created_at` supports both Unix timestamps and standard `YYYY-MM-DD HH:MM:SS` strings).*

---

## Running the Application & Clean URLs

The application uses clean, masked URLs without exposing `.php` extensions or internal directories:

- `/` $\rightarrow$ Main Monitoring Dashboard
- `/login` $\rightarrow$ Sign In Page (masked from `app/login.php`)
- `/logout` $\rightarrow$ Sign Out Handler (masked from `app/logout.php`)
- `/change-password` $\rightarrow$ Password Update Endpoint (masked from `app/change_password.php`)

### Option A: Using PHP Built-in Server (Local Development)

Run the built-in server with the bundled router script:

```bash
php -S 0.0.0.0:8000 app/router.php
```

Access the application at `http://localhost:8000/login`.

### Option B: Apache / Docker (cPanel, VPS, Docker container)

The included [.htaccess](.htaccess) automatically handles clean URL rewriting and blocks sensitive directories (`config/`, `.env`, `app/router.php`). Ensure `mod_rewrite` is enabled:

```bash
# In Debian/Ubuntu / Docker container:
a2enmod rewrite
```

### Option C: Nginx

Point the `root` directive to the project folder and configure clean URL rewrites in your server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location = /login {
    fastcgi_pass php-fpm;
    fastcgi_param SCRIPT_FILENAME $document_root/app/login.php;
    include fastcgi_params;
}

location = /logout {
    fastcgi_pass php-fpm;
    fastcgi_param SCRIPT_FILENAME $document_root/app/logout.php;
    include fastcgi_params;
}

location = /change-password {
    fastcgi_pass php-fpm;
    fastcgi_param SCRIPT_FILENAME $document_root/app/change_password.php;
    include fastcgi_params;
}

location ~ /\.(env|git) {
    deny all;
}
location ^~ /config/ {
    deny all;
}
```

---

## Default Login Credentials

- **Username / Email**: `admin` or `admin@example.com`
- **Password**: `admin123`

> [!IMPORTANT]
> Please change the default password immediately after logging in using the **Change Password** option located in the top-right user menu.

---

## License & Credits

Copyright &copy; 2026 [Home Monitoring System](https://monitoring.partamayasa.my.id). All rights reserved.
