# Drillifyx — cPanel Deployment Guide (PHP 8.1)

## 1. Requirements on the host

- **PHP 8.1** (cPanel → Software → *Select PHP Version*: choose `8.1`).
- Required PHP extensions (cPanel → *Select PHP Version* → check them):
  `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `session`, `json`, `filter`,
  `gd` (logo re-encode check; the app still works without it via fallback),
  `curl` (optional — SMTP uses raw sockets, no curl needed).
- Apache 2.4 (standard on cPanel). No extra modules required
  (no mod_rewrite rules are used). `mod_headers` / `mod_expires` are
  optional — the `.htaccess` degrades gracefully without them.
- MySQL 5.7+ or MariaDB 10.3+.

## 2. What to upload

Upload the **contents** of this folder (not the folder itself) to
`public_html` (document root), via File Manager or FTP:

```
404.php  index.php  login.php  logout.php  register.php
verify.php  forgot-password.php  reset-password.php
maintenance.php  top-earners.php
.htaccess  admin/  ajax/  config/  images/  includes/  users/
uploads/proofs/.htaccess   (empty proofs dir + its .htaccess)
```

**Do NOT upload:** `.git/`, `config.zip`, `uploads.zip`, `*.html`,
`index.htm`, `webcopy-origin.txt`, `storage_reset_links.log`, the `s/`
and `ajax/libs/` offline-mirror copies (they are blocked by `.htaccess`
anyway, but deleting keeps the account clean), or any local `.env` with
secrets. Keep `uploads/proofs/` (it holds payment proofs) and
`uploads/branding/` if you have custom logos.

Recommended `public_html` layout after upload:

```
public_html/
  .htaccess  *.php  admin/  ajax/  config/  images/  includes/
  users/  uploads/proofs/  uploads/branding/  database/ (import files only)
```

`config/`, `includes/`, `database/` are blocked from browser access by
`.htaccess` + per-directory `.htaccess` files, so they are safe inside
the document root.

## 3. Database setup

1. cPanel → *MySQL Databases*: create database `cpuser_drillifx`
   (name gets your account prefix) and user `cpuser_drillifx` with a
   strong password; add the user to the database with ALL PRIVILEGES.
2. cPanel → *phpMyAdmin*: select the new database → *Import* →
   `database/drillifx.sql` → Go. Then import `database/phase2.sql`.
   (If re-importing phase2, ignore error 1060 "duplicate column" for
   `email_verified_at` — harmless on MySQL.)
3. Verify all 22 tables exist (`users`, `admins`, `settings`, …).

## 4. Configure credentials + site URL (pick ONE method)

**Method A — environment variables (recommended, no code edits):**
cPanel → *Software → MultiPHP INI Editor* (or add to `.htaccess`):

```
SetEnv DB_HOST localhost
SetEnv DB_PORT 3306
SetEnv DB_NAME cpuser_drillifx
SetEnv DB_USER cpuser_drillifx
SetEnv DB_PASS "your-strong-password"
SetEnv APP_URL https://yourdomain.com
# Optional: SetEnv APP_BASE_PATH /subdir   (only for subdirectory installs)
# Optional: SetEnv APP_DEBUG 1             (local debugging only, NEVER in prod)
```

**Method B — edit files:** set the same values as defaults in
`config/database.php` and `APP_URL` in `config/app.php`.

The app auto-detects HTTPS (including Cloudflare/proxy headers) and the
subdirectory base path, so `APP_URL` may be left empty — links and email
URLs are built from the current request host in that case.

## 5. PHP version, extensions, permissions

1. *Select PHP Version* → `8.1` → enable the extensions from §1 → Save.
2. Permissions: files `644`, directories `755`. Never `777`.
   `uploads/proofs/` and `uploads/branding/` must stay writable by PHP
   (`755` is enough; the app creates them if missing).

## 6. Email (SMTP)

Admin → *Email / SMTP*: host, port (587 + tls, or 465 + ssl), username,
password, from address/name → *Send test email*. Until SMTP is set,
transactional mail is logged as `skipped` in `email_logs` (nothing breaks).

## 7. Post-deploy verification checklist

- [ ] Home page loads, no PHP warnings in HTML.
- [ ] Register a test account → lands on dashboard.
- [ ] Login / logout / wrong-password error.
- [ ] Hash button credits once, second attempt hits daily limit message.
- [ ] Spin, withdrawal validation (`$100` minimum message), set-PIN.
- [ ] `/top-earners.php?page=2` paginates.
- [ ] `/admin/login.php` → dashboard; proof viewer; user search dropdown.
- [ ] `/ajax/hash.php` GET → `405`; logged-out POST → `401` JSON.
- [ ] Unknown URL (e.g. `/nope-xyz`) → your `404.php`.
- [ ] `/includes/functions.php`, `/config/database.php`, `*.sql`,
      `*.log`, `*.zip`, `*.html` → `403` (blocked).
- [ ] Viewport/mobile layout + browser console: no failed requests.

## 8. Troubleshooting

- **"Service temporarily unavailable"** → database credentials/host wrong.
  Check cPanel → *Errors* (error log) for the real message (never shown
  to visitors). Confirm the DB user is added to the database.
- **HTTP 500 on every page** → PHP version not 8.1, or a `.htaccess`
  directive your host disallows. Rename `.htaccess` temporarily to
  confirm, then re-enable sections one by one.
- **Redirect loop on login** → `APP_URL`/`APP_BASE_PATH` mismatch, or a
  proxy forcing http. The app auto-detects `X-Forwarded-Proto`; prefer
  leaving `APP_URL` empty behind proxies.
- **Subdirectory install (`/subdir`)** → set `APP_BASE_PATH=/subdir`
  (or rely on auto-detect) and add `ErrorDocument 404 /subdir/404.php`
  in the subdirectory `.htaccess`.
- **Mail not arriving** → check *Admin → Email logs*, verify SMTP port
  is open on the host (some shared hosts block 25/587 — use 465/ssl).
- **Uploads fail** → directory permissions; max 5 MB proofs
  (JPG/PNG/WEBP/PDF), 2 MB logos.

## 9. PHP 8.1 compatibility notes

No build step, no Composer, no 8.2+ syntax. All 61 PHP files pass
`php -l`; PDO with exceptions + prepared statements throughout;
sessions are cookie-only (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS)
with regeneration on login. Timezone defaults to UTC so daily
hash/spin limits and token expiry behave identically on any host.
