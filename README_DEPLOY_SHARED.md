# Deployment Guide (Z.com Shared Hosting)

## 1. Overview
This application is a plain PHP/MySQL site with PHPMailer. Shared hosting at Z.com provides cPanel (or similar) with: public_html root, MySQL via phpMyAdmin, SMTP options, and limited/no persistent Python runtime.

## 2. Prepare Local Package
1. Run `composer install` locally so `vendor/` has PHPMailer.
2. Remove any local-only secrets: keep `client_secret.example.json` but exclude real keys if not needed.
3. Confirm `connection.php` uses environment variables (it does). On shared hosting you likely cannot set true environment variables; you will override by editing the file or using a small config include.
4. Zip everything except large caches or unused Python services.

## 3. Upload Methods
### Option A: cPanel File Manager
1. Login to cPanel.
2. Go to `public_html/`.
3. Upload ZIP -> Extract.
4. Ensure main entry file (e.g. index.php or a wrapper) is in `public_html/`.

### Option B: FTP/SFTP
1. Create/locate FTP account (host, user, password).
2. Use FileZilla: Host = your domain or server IP, Port 21 (FTP) or 22 (SFTP if enabled).
3. Drag local folder contents into `public_html/`.

(SSH + git usually not available on basic shared hosting; if available you could clone and run composer.)

## 4. Database Setup
1. In cPanel > MySQL Database Wizard: create DB (e.g. `ihdentistdc`).
2. Create DB user with strong password; assign ALL privileges.
3. In phpMyAdmin (server side) import your local exported SQL.
4. Edit `connection.php`:
   - Replace environment variable logic with hard-coded values OR prepend a small `config.php`.
   Example:
   ```php
   $db_host = 'localhost';
   $db_user = 'cpanelprefix_dbuser';
   $db_pass = 'STRONG_PASSWORD';
   $db_name = 'cpanelprefix_ihdentistdc';
   $database = new mysqli($db_host, $db_user, $db_pass, $db_name);
   if ($database->connect_error) { die('DB Error'); }
   ```
   (Use `localhost` which maps internally.)

## 5. Email (PHPMailer)
1. Prefer SMTP: obtain SMTP host from Z.com or external provider (Mailgun/SendGrid).
2. Set credentials in a separate `smtp_config.php` and include it; keep file permissions 640/644.
3. Enforce TLS (Port 587) or SSL (Port 465) depending on provider.

## 6. Python Scripts Strategy
- Shared hosting rarely supports long-running Python web services. Options:
  1. Re-write Python logic in PHP (recommended).
  2. Host Python microservice elsewhere (VPS/Platform-as-a-Service) and call via HTTP.
  3. For simple tasks (send email, small API calls) use PHP equivalents and remove unused Python files to reduce clutter.
- If scripts are unused, delete them before upload.

## 7. .htaccess (Recommended)
Create or edit `.htaccess` in `public_html/`:
```
Options -Indexes
RewriteEngine On
# Force HTTPS
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# PHP error handling
php_flag display_errors Off
php_value error_log /home/CPANELUSER/logs/php-error.log
```
Replace `CPANELUSER` with your actual cPanel username and ensure `logs/` exists and is not web-accessible (place it above or protect with deny rules).

## 8. File/Directory Permissions
- Directories: 755
- Files: 644
- Upload directories (`uploads/`, `Media/`): keep 755; never 777.

## 9. Security Checklist
- Remove any unused admin scripts.
- Validate and sanitize all POST/GET inputs (prepared statements, escaping output).
- Limit upload file types (MIME + extension check).
- Keep PHPMailer updated; watch CVEs.
- Add CSRF tokens to forms (if missing).

## 10. Post-Deployment Verification
1. Load homepage: no PHP warnings.
2. Login/logout cycle works.
3. Create appointment: record appears in DB.
4. Calendar events display (AJAX endpoints reachable).
5. Email (password reset or test) sends and arrives.
6. Check browser console/network for 404 assets.
7. Confirm HTTPS padlock (no mixed content warnings).

## 11. Backups
- Weekly: Export DB (phpMyAdmin) + zip `public_html/`.
- Store off-server (cloud drive). Document restore steps.

## 12. Optional Improvements
- Introduce `config.php` + constants or use a simple array for credentials and settings.
- Add output caching for static content (HTML fragments).
- Use Cloudflare for CDN + DNS and free SSL.

## 13. Deployment Summary (Quick Reference)
1. `composer install` locally.
2. Export DB SQL.
3. Zip project; upload & extract to `public_html/`.
4. Create DB + user; import SQL.
5. Edit `connection.php` with production creds.
6. Configure PHPMailer SMTP.
7. Add `.htaccess` hardening.
8. Test core flows; set up backups.

---
Update this guide as your stack evolves or if you move to VPS (then you can use environment variables + systemd services).
