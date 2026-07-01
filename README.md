# Audit withGufran

A production-ready SEO audit platform. Crawl sitemaps, audit pages across 7 modules, generate client-facing reports, and manage results — all from a single dashboard.

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite (shared hosting compatible)
- cURL extension enabled

## Quick Start

```bash
# 1. Clone and configure
cp .env.example .env
# Edit .env with your database credentials and APP_URL

# 2. Create database
mysql -u root -p < database/migrations.sql

# 3. Run additional migrations (in order)
mysql -u root -p audit_withgufran < database/migrations_phase4_part3.sql
mysql -u root -p audit_withgufran < database/migrations_phase5_part2.sql
mysql -u root -p audit_withgufran < database/migrations_phase6_part1.sql
mysql -u root -p audit_withgufran < database/migrations_phase6_part2.sql
mysql -u root -p audit_withgufran < database/migrations_phase7_part1.sql
mysql -u root -p audit_withgufran < database/migrations_phase8_part1.sql
mysql -u root -p audit_withgufran < database/migrations_phase8_part2.sql
mysql -u root -p audit_withgufran < database/migrations_phase8_part3.sql
mysql -u root -p audit_withgufran < database/migrations_phase9_part1.sql

# 4. Set permissions
chmod 755 storage/cache storage/logs storage/rate_limits

# 5. Set up cron (if available)
# Add to crontab:
# * * * * * php /path/to/Audit/cron/process_jobs.php >> /path/to/Audit/storage/logs/cron.log 2>&1
# 0 3 * * * php /path/to/Audit/cron/maintenance.php >> /path/to/Audit/storage/logs/maintenance.log 2>&1

# 6. Login
# Default: admin@auditwithgufran.com / admin123
# CHANGE THIS IMMEDIATELY after first login.
```

## Architecture

```
Audit/
├── app/                    # Application code
│   ├── Controllers/        # Auth controller
│   ├── Helpers/            # Global helper functions
│   ├── Jobs/               # Background job processing
│   ├── Middleware/         # Auth, CSRF, Role, Performance
│   ├── Models/             # User model
│   ├── Modules/            # Feature modules (controller/service/repository pattern)
│   │   ├── AuditEngine/    # Audit orchestration
│   │   ├── Dashboard/      # Dashboard overview + search + notifications
│   │   ├── FaqSchemaAudit/ # FAQ schema audit module
│   │   ├── H1Audit/        # H1 tag audit module
│   │   ├── LoremAudit/     # Lorem ipsum detection
│   │   ├── MetaAudit/      # Meta tag audit module
│   │   ├── NoindexAudit/   # Noindex detection
│   │   ├── Overrides/      # Manual override system
│   │   ├── Pages/          # URL/page management
│   │   ├── Projects/       # Project management
│   │   ├── Reports/        # Report generation + data loader
│   │   ├── Settings/       # System settings + admin management
│   │   ├── Sitemaps/       # Sitemap discovery + import
│   │   ├── StagingAudit/   # Staging URL detection
│   │   └── WordOccurrenceAudit/  # Keyword occurrence
│   ├── Services/           # Shared services
│   └── bootstrap.php       # Application initialization
├── assets/                 # Frontend assets (CSS, JS)
├── config/                 # Configuration files
├── cron/                   # Scheduled tasks
├── database/               # Migrations + Database class
├── routes/                 # API route handlers
├── storage/                # Logs, cache, rate limits
└── templates/              # Shared templates
```

## Modules

| Module | Description |
|--------|-------------|
| H1 Audit | Validates H1 tags (missing, duplicate, empty) |
| Meta Audit | Checks meta title and description |
| Noindex Audit | Detects noindex/nofollow directives |
| FAQ Schema | Validates FAQ structured data |
| Lorem Ipsum | Detects placeholder text |
| Staging URLs | Finds staging/dev URLs in content |
| Word Occurrence | Counts keyword occurrences |

## Key Features

- **Project Management** — Create projects, import sitemaps, manage URLs
- **Automated Auditing** — Background job processing with batch execution
- **7 Audit Modules** — Comprehensive SEO checks
- **Public Reports** — Shareable client-facing report URLs (`/report/slug`)
- **Interactive Reports** — Filter, search, sort, expand audit details
- **PDF/CSV Export** — Download audit results
- **Manual Overrides** — Admin workflow without modifying audit data
- **Global Search** — Ctrl+K to search projects, URLs, reports
- **Notifications** — In-app notification system
- **Settings** — Centralized configuration (Super Admin)
- **Admin Management** — Create/deactivate admin users
- **Dark Mode** — Full theme support
- **Rate Limiting** — Login protection (5 attempts/15 min)
- **Security Headers** — CSP, X-Frame-Options, HSTS-ready

## Configuration

All configuration is via `.env` file and the Settings UI (Super Admin only).

Key environment variables:
- `APP_DEBUG` — Set to `false` in production
- `APP_URL` — Your full domain URL
- `SESSION_SECURE` — Set to `true` if using HTTPS
- `DB_*` — Database connection details

## Cron Jobs

| Schedule | Script | Purpose |
|----------|--------|---------|
| Every minute | `cron/process_jobs.php` | Process audit job queue |
| Daily at 3am | `cron/maintenance.php` | Cache cleanup, log rotation |

On shared hosting without cron, jobs can be triggered via `routes/jobs.php?action=process`.

## Security

- CSRF protection on all mutations
- Rate-limited login (file-based, no Redis needed)
- Session fingerprinting + rotation
- Parameterized SQL queries (PDO)
- XSS prevention (output escaping + CSP)
- Security headers on all responses
- No sensitive files accessible via web (.env, config, app, database blocked)

## API Endpoints

All admin endpoints require authentication. Public report endpoints require valid slug + public visibility.

| Route | Auth | Purpose |
|-------|------|---------|
| `routes/projects.php` | Yes | Project CRUD |
| `routes/pages.php` | Yes | URL management |
| `routes/sitemaps.php` | Yes | Sitemap operations |
| `routes/jobs.php` | Yes | Job management |
| `routes/reports.php` | Yes | Report admin |
| `routes/overrides.php` | Yes | Manual overrides |
| `routes/settings.php` | Super Admin | System settings |
| `routes/dashboard.php` | Yes | Dashboard data |
| `report.php` | No | Public report page |
| `routes/report-api.php` | No | Paginated report data |
| `routes/report-export.php` | No | CSV export |

## License

Proprietary — Audit withGufran by Codezyners

## Version

**1.9.0** — Production Ready
