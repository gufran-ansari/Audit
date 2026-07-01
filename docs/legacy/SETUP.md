# 🚀 Quick Setup Guide - Audit withGufran

## Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (Apache)

---

## Installation Steps

### 1. Database Setup

```bash
# Login to MySQL
mysql -u root -p

# Run the migration file (includes Phase 2 Part 1 & Part 2)
source database/migrations.sql

# Or directly:
mysql -u root -p < database/migrations.sql
```

**What Gets Created:**
- `users` table (authentication)
- `settings` table (app settings)
- `projects` table (project management) - NEW in Phase 2 Part 2
- `project_activity_logs` table (activity tracking) - NEW in Phase 2 Part 2
- Default super admin user
- Default settings

### 2. Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit .env file
nano .env
```

Update these values:
```env
DB_HOST=localhost
DB_DATABASE=audit_withgufran
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
APP_KEY=generate_random_32_char_string
```

### 3. Set Permissions

```bash
# Make logs directory writable
chmod 755 storage/logs
```

### 4. Access Application

```
Login Page: http://localhost/login.php

Default Credentials:
Email: admin@auditwithgufran.com
Password: admin123
```

⚠️ **IMPORTANT**: Change the default password immediately after first login!

---

## Testing Authentication

1. Navigate to `http://localhost/login.php`
2. Login with default credentials
3. You should be redirected to dashboard
4. Try clicking logout icon (bottom right of sidebar)
5. You should be redirected back to login

---

## Testing Project Management (Phase 2 Part 2)

1. After logging in, navigate to `http://localhost/projects.php`
2. Click "Create Project" button
3. Enter project name (e.g., "My First Project")
4. Enter domain (e.g., "example.com")
5. Click "Create Project"
6. Project should appear in the grid
7. Click "Open" to view project details
8. Test search and filter features

---

## Troubleshooting

### "Database connection failed"
- Check MySQL is running
- Verify .env database credentials
- Check database exists: `SHOW DATABASES;`

### "Session issues"
- Check session.save_path is writable
- Verify PHP session extension is enabled

### "CSRF token mismatch"
- Clear browser cookies
- Try in incognito mode
- Check PHP sessions are working

---

## Next Steps

After successful login and project creation:
- Change default admin password
- Create your projects
- Explore the project management interface
- Ready for Phase 2 Part 3 development (URL Management & Sitemap Discovery)

---

## Available Pages

- `/login.php` - Login page
- `/dashboard.php` - Main dashboard (protected)
- `/projects.php` - Projects listing (protected) - NEW in Phase 2 Part 2
- `/project.php?uuid=XXX` - Project details (protected) - NEW in Phase 2 Part 2

---

## File Structure

```
audit-withgufran/
├── app/                      # Backend application code
│   ├── Controllers/          # HTTP request handlers
│   ├── Models/              # Database models
│   ├── Services/            # Business logic
│   ├── Middleware/          # Request filters
│   ├── Helpers/             # Utility functions
│   ├── Modules/             # Feature modules
│   │   └── Projects/        # Project management module (Phase 2 Part 2)
│   │       ├── ProjectController.php
│   │       ├── ProjectService.php
│   │       ├── ProjectRepository.php
│   │       └── ProjectValidator.php
│   └── bootstrap.php        # App initialization
├── assets/                  # Frontend assets (from Phase 1)
│   ├── css/
│   ├── js/
│   ├── icons/
│   └── images/
├── config/                  # Configuration files
│   ├── app.php             # App config
│   └── database.php        # Database config
├── database/                # Database connection & migrations
│   ├── Database.php        # PDO wrapper class
│   └── migrations.sql      # Database schema (Phases 2.1 & 2.2)
├── docs/                    # Documentation
├── resources/               # Views and templates
│   └── views/
│       ├── auth/
│       └── errors/
├── routes/                  # Route handlers
│   ├── auth.php            # Authentication routes
│   └── projects.php        # Project API routes (Phase 2 Part 2)
├── storage/logs/            # Application logs
├── .env                     # Environment configuration (create from .env.example)
├── login.php                # Login entry point
├── dashboard.php            # Protected dashboard
├── projects.php             # Projects listing page (Phase 2 Part 2)
└── project.php              # Project details page (Phase 2 Part 2)
```

---

**Setup Complete!** 🎉
