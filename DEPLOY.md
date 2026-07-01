# Deployment Guide

## Shared Hosting Deployment

### 1. Upload Files

Upload the entire project to your hosting (e.g., `public_html/audit/` or root).

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_HOST=localhost
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_pass
SESSION_SECURE=true
```

### 3. Database Setup

Import migrations via phpMyAdmin or CLI:

```sql
-- Run in order:
source migrations.sql;
source migrations_phase4_part3.sql;
source migrations_phase5_part2.sql;
source migrations_phase6_part1.sql;
source migrations_phase6_part2.sql;
source migrations_phase7_part1.sql;
source migrations_phase8_part1.sql;
source migrations_phase8_part2.sql;
source migrations_phase8_part3.sql;
source migrations_phase9_part1.sql;
```

### 4. Directory Permissions

```bash
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/logs/
chmod 755 storage/rate_limits/
```

### 5. Cron Setup (if available)

```
* * * * * php /path/to/audit/cron/process_jobs.php >> /path/to/audit/storage/logs/cron.log 2>&1
0 3 * * * php /path/to/audit/cron/maintenance.php >> /path/to/audit/storage/logs/maintenance.log 2>&1
```

If cron is not available, the job processor can be triggered via the dashboard.

### 6. Security Checklist

- [ ] Change default admin password immediately
- [ ] Set `APP_DEBUG=false`
- [ ] Set `SESSION_SECURE=true` (if HTTPS)
- [ ] Verify `.htaccess` blocks access to sensitive directories
- [ ] Test that `/config/`, `/app/`, `/database/`, `/.env` return 403
- [ ] Generate a unique `APP_KEY` (any random 32+ char string)

### 7. Verify Installation

1. Visit your domain — should show the login page
2. Login with default credentials
3. Change password in Settings
4. Create a test project
5. Verify cron is processing jobs (check storage/logs/cron.log)

## Production Checklist

| Item | Status |
|------|--------|
| APP_DEBUG = false | Required |
| APP_URL = full HTTPS URL | Required |
| SESSION_SECURE = true | Required (HTTPS) |
| Default password changed | Required |
| .htaccess security rules active | Required |
| storage/ directories writable | Required |
| Cron configured | Recommended |
| Error logging to file | Auto (production) |
| PHP 8.0+ | Required |
| MySQL 5.7+ | Required |

## Troubleshooting

**500 Error on first load**: Check `.env` database credentials and that the database exists.

**CSRF Token Mismatch**: Ensure session cookies are working. Check `SESSION_SECURE` matches your HTTPS status.

**Jobs not processing**: Verify cron is set up, or manually trigger via `routes/jobs.php?action=process`.

**Permission denied on storage/**: Run `chmod -R 755 storage/`.
