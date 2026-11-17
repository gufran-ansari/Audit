# Quick Reference Guide - Audit withGufran

## 📋 Project Status

**Current Phase**: Phase 2 Part 2 - Complete ✅  
**Last Updated**: June 16, 2026  
**Version**: 2.1.0

---

## 🎯 What's Working Now

### Authentication System ✅
- Login/Logout
- Session management
- Role-based access (super_admin, admin)
- CSRF protection
- Route protection

### Project Management ✅
- Create projects
- List projects (with pagination)
- View project details
- Archive/Restore projects
- Search projects
- Filter by status
- Activity logging
- Access control

### Frontend Pages ✅
- `/login.php` - Login page
- `/dashboard.php` - Main dashboard
- `/projects.php` - Projects listing
- `/project.php?uuid=XXX` - Project details
- `/report.html` - Public report (static)

---

## 🔑 Default Credentials

```
Email: admin@auditwithgufran.com
Password: admin123
```

⚠️ **Change these immediately after first login!**

---

## 📚 Database Tables

### Users Table
- Authentication and user management
- Roles: super_admin, admin
- Status: active, inactive

### Settings Table
- Application settings storage

### Projects Table
- Project information
- Domain tracking
- Status management (active, archived, paused)
- UUID for public access

### Project Activity Logs Table
- Activity tracking
- User attribution
- Timestamp tracking

---

## 🛠️ API Endpoints

### Authentication
```
GET  /login.php
POST /login.php
GET  /login.php?action=logout
```

### Projects
```
GET  /routes/projects.php?action=list
POST /routes/projects.php?action=create
GET  /routes/projects.php?action=view
POST /routes/projects.php?action=update
POST /routes/projects.php?action=archive
POST /routes/projects.php?action=restore
GET  /routes/projects.php?action=activity
```

---

## 📁 Key Files

### Backend Classes
```
app/
├── Controllers/
│   └── AuthController.php
├── Models/
│   └── User.php
├── Services/
│   └── AuthService.php
├── Middleware/
│   ├── AuthMiddleware.php
│   ├── RoleMiddleware.php
│   └── CsrfMiddleware.php
├── Modules/
│   └── Projects/
│       ├── ProjectController.php
│       ├── ProjectService.php
│       ├── ProjectRepository.php
│       └── ProjectValidator.php
└── Helpers/
    └── helpers.php
```

### Frontend Files
```
assets/
├── css/
│   └── styles.css
└── js/
    ├── app.js
    └── projects.js
```

### Configuration
```
config/
├── app.php
└── database.php
```

### Database
```
database/
├── Database.php
└── migrations.sql
```

---

## 🚀 Quick Commands

### Run Migrations
```bash
mysql -u root -p < database/migrations.sql
```

### Check Logs
```bash
tail -f storage/logs/app_YYYY-MM-DD.log
```

### Reset Database (Development Only)
```bash
mysql -u root -p
DROP DATABASE audit_withgufran;
source database/migrations.sql;
```

---

## 🧪 Testing Checklist

### Authentication
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Logout functionality
- [ ] Session persistence
- [ ] Protected route access

### Project Management
- [ ] Create new project
- [ ] View project list
- [ ] Search projects
- [ ] Filter projects by status
- [ ] Open project details
- [ ] Archive project
- [ ] Restore project
- [ ] View activity log

### Access Control
- [ ] Super admin sees all projects
- [ ] Admin sees only own projects
- [ ] Cannot access other user's projects

---

## 🔧 Configuration

### Environment Variables (.env)
```env
# Database
DB_HOST=localhost
DB_DATABASE=audit_withgufran
DB_USERNAME=root
DB_PASSWORD=your_password

# Application
APP_NAME=Audit withGufran
APP_ENV=production
APP_DEBUG=false
APP_KEY=your-secret-key-here

# Session
SESSION_NAME=audit_session
SESSION_LIFETIME=7200
SESSION_SECURE=false
```

### Database Config (config/database.php)
```php
'driver' => 'mysql',
'host' => env('DB_HOST', 'localhost'),
'database' => env('DB_DATABASE', 'audit_withgufran'),
'username' => env('DB_USERNAME', 'root'),
'password' => env('DB_PASSWORD', ''),
'charset' => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',
```

---

## 📊 Module Architecture

### Service Layer Pattern
```
Controller → Service → Repository → Database
     ↓          ↓
  Validator   Business Logic
```

### Benefits
- Separation of concerns
- Reusable business logic
- Easy testing
- Clear responsibilities

---

## 🔒 Security Features

- ✅ BCrypt password hashing
- ✅ PDO prepared statements
- ✅ CSRF token validation
- ✅ Session security (HttpOnly, SameSite)
- ✅ Input sanitization
- ✅ XSS prevention
- ✅ SQL injection prevention
- ✅ Access control middleware
- ✅ Role-based permissions

---

## 🐛 Common Issues

### "Database connection failed"
**Solution**: Check .env database credentials

### "CSRF token mismatch"
**Solution**: Clear cookies and try again

### "Access denied" on projects
**Solution**: Verify user role and project ownership

### Projects not loading
**Solution**: Check browser console and verify API endpoint

### "Session expired"
**Solution**: Login again or increase SESSION_LIFETIME

---

## 📝 Code Examples

### Creating a Project (JavaScript)
```javascript
const formData = new FormData();
formData.append('action', 'create');
formData.append('project_name', 'My Project');
formData.append('primary_domain', 'example.com');

const response = await fetch('/routes/projects.php', {
    method: 'POST',
    body: formData
});

const data = await response.json();
```

### Accessing Project Service (PHP)
```php
use App\Modules\Projects\ProjectService;

$service = new ProjectService();
$result = $service->createProject([
    'project_name' => 'My Project',
    'primary_domain' => 'example.com'
]);
```

### Repository Query (PHP)
```php
$repository = new ProjectRepository();
$projects = $repository->getAll([
    'status' => 'active',
    'search' => 'example'
], 1, 10);
```

---

## 🎯 Next Phase Preview

### Phase 2 Part 3 - URL Management & Sitemap Discovery
- Sitemap XML parsing
- URL import from sitemaps
- URL database table
- URL CRUD operations
- URL listing interface
- Batch URL operations

---

## 📞 Support

### Documentation Files
- `SETUP.md` - Installation guide
- `PHASE2_PART1_COMPLETE.md` - Authentication system docs
- `PHASE2_PART2_COMPLETE.md` - Project management docs
- `QUICK_REFERENCE.md` - This file

### Logs Location
- `storage/logs/app_YYYY-MM-DD.log` - Application logs
- `storage/logs/errors.log` - Error logs (production)

---

**Last Updated**: June 16, 2026  
**Version**: 2.1.0  
**Status**: Phase 2 Part 2 Complete ✅
