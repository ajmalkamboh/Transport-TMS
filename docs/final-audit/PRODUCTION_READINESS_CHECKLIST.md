# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — PRODUCTION READINESS CHECKLIST

**Document Reference:** TMS-PRD-001  
**Audit Date:** November 11, 2026  
**Auditor:** DevOps Lead & Site Reliability Engineer (SRE)  
**Status:** **READY FOR DEPLOYMENT (WITH OPERATIONAL PRE-FLIGHT CHECKLIST)**

---

## 1. PRE-DEPLOYMENT PRODUCTION CHECKLIST

| Category | Verification Item | Production Requirement | Audit State | Action / Configuration |
| :--- | :--- | :--- | :---: | :--- |
| **Environment** | `APP_ENV` & `APP_DEBUG` | `APP_ENV=production`, `APP_DEBUG=false` | **CONFIGURED** | Provided in `.env.production.example` |
| **Environment** | Application Key | Generated 32-byte base64 encryption key | **READY** | Run `php artisan key:generate` |
| **Security** | HTTPS / TLS Certificate | Enforce TLS 1.3 with automated Let's Encrypt | **READY** | Configured in `deploy/nginx/tms.conf` |
| **Security** | Security Headers | Strict CSP, HSTS, X-Frame-Options: SAMEORIGIN | **VERIFIED** | Enforced via `SecurityHeaders` middleware |
| **Security** | Session Cookie Flags | `SESSION_SECURE_COOKIE=true`, `HTTP_ONLY=true`| **CONFIGURED** | Configured in production `.env` |
| **Database** | MySQL 8.0+ Engine | InnoDB engine with UTF8MB4 charset | **VERIFIED** | 40 migrations ready (`migrate --force`) |
| **Database** | Database User Privileges | Restricted user granting only DML permissions | **PLANNED** | Database security best practice |
| **Storage** | Public Storage Link | Symbolic link from `public/storage` to `app/public`| **REQUIRED** | Execute `php artisan storage:link` |
| **Storage** | Private Document Dir | Restricted private storage for PODs & documents | **VERIFIED** | Stored in `storage/app/private/` |
| **Assets** | Frontend Asset Compilation | Production minified Vite assets | **READY** | Execute `npm run build` |
| **Optimization** | Configuration Cache | Compiled configuration array | **READY** | Execute `php artisan config:cache` |
| **Optimization** | Route Cache | Pre-compiled route registration tree | **READY** | Execute `php artisan route:cache` |
| **Optimization** | View Cache | Pre-compiled Blade view templates | **READY** | Execute `php artisan view:cache` |
| **Background** | Queue Worker Daemon | Supervisord worker managing queue processing | **CONFIGURED** | Template: `deploy/supervisor/tms-worker.conf` |
| **Background** | Scheduled Cron Jobs | Linux crontab executing `schedule:run` every min | **CONFIGURED** | `* * * * * cd /var/www/tms && php artisan schedule:run` |
| **Monitoring** | Health Probe | Automated endpoint checking DB, cache & storage | **VERIFIED** | Verified: `GET /health` returns HTTP 200 JSON |
| **Disaster Recovery**| Database Backups | Automated daily backups with SHA-256 validation | **VERIFIED** | Command: `php artisan tms:backup-db` |
| **Disaster Recovery**| Database Restoration | Restores database with pre-flight dry-run check | **VERIFIED** | Command: `php artisan tms:restore-db --dry-run` |

---

## 2. PRODUCTION DEPLOYMENT RUNBOOK

### Step 1: Clone & Configure Environment
```bash
git clone https://github.com/ajmalkamboh/Transport-TMS.git /var/www/tms
cd /var/www/tms
cp .env.production.example .env
nano .env # Set database credentials, app URL, and mail settings
```

### Step 2: Install Dependencies & Build Frontend
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### Step 3: Initialize Database & Links
```bash
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force # Seeds default Chart of Accounts and Super Admin
```

### Step 4: Cache Configurations
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Configure Background Services
```bash
sudo cp deploy/supervisor/tms-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tms-worker:*

sudo cp deploy/nginx/tms.conf /etc/nginx/sites-available/tms
sudo ln -s /etc/nginx/sites-available/tms /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. ROLLBACK & DISASTER RECOVERY PROCEDURES

In the event of an operational anomaly:
1. **Pre-Flight Dry-Run:**
   ```bash
   php artisan tms:restore-db /path/to/backup.sql --dry-run
   ```
2. **Execute Full Database Restore:**
   ```bash
   php artisan tms:restore-db /path/to/backup.sql --force
   ```
3. **Purge Caches:**
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   ```

**Production Readiness Status: APPROVED (GREEN)**
