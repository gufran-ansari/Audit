# Phase 4 Troubleshooting Guide

Quick solutions for common issues with the audit modules system.

---

## 🔍 Quick Diagnostics

### Check System Health

```bash
# Verify all module directories exist
ls -la app/Modules/*/

# Verify all route files exist  
ls -la routes/*audit.php

# Check for syntax errors
php -l routes/h1_audit.php
php -l routes/meta_audit.php
php -l routes/noindex_audit.php
php -l routes/faq_schema_audit.php
```

### Check Database

```sql
-- Verify table exists
SHOW TABLES LIKE 'page_audit_results';

-- Check table structure
DESCRIBE page_audit_results;

-- Count results by module
SELECT module_name, COUNT(*) 
FROM page_audit_results 
GROUP BY module_name;
```

---

## ❌ Common Issues & Solutions

### Issue 1: "Module not found" Error

**Symptoms:**
- Fatal error: Class not found
- Controller cannot be instantiated

**Causes:**
- Missing module files
- Incorrect namespace
- Autoloader not finding class

**Solutions:**

```bash
# Check if files exist
ls -la app/Modules/H1Audit/
ls -la app/Modules/MetaAudit/
ls -la app/Modules/NoindexAudit/
ls -la app/Modules/FaqSchemaAudit/

# Each should have 5 files:
# - Controller.php
# - Service.php
# - Processor.php
# - Repository.php
# - Validator.php
```

Check namespace in Controller.php:
```php
namespace App\Modules\NoindexAudit; // Must match directory name
```

Check autoloader in app/bootstrap.php:
```php
spl_autoload_register(function ($class) {
    // Should handle App\Modules\* namespace
});
```

---

### Issue 2: Checkboxes Not Showing

**Symptoms:**
- Only 2 checkboxes visible (H1 and Meta)
- Noindex and FAQ checkboxes missing

**Causes:**
- project.php not updated
- Browser cache

**Solutions:**

1. Check project.php has all 4 checkboxes:
```html
<input type="checkbox" id="h1AuditCheckbox">
<input type="checkbox" id="metaAuditCheckbox">
<input type="checkbox" id="noindexAuditCheckbox"> <!-- Must exist -->
<input type="checkbox" id="faqAuditCheckbox">     <!-- Must exist -->
```

2. Clear browser cache:
```
Ctrl+Shift+R (hard refresh)
or
Clear all cache in browser settings
```

---

### Issue 3: Table Columns Not Showing

**Symptoms:**
- Table only shows URL, H1, Meta columns
- Noindex and FAQ columns missing

**Causes:**
- project.php table headers not updated
- JavaScript not rendering columns

**Solutions:**

1. Check table headers in project.php:
```html
<th>URL</th>
<th>H1</th>
<th>Meta</th>
<th>Noindex</th>  <!-- Must exist -->
<th>FAQ</th>      <!-- Must exist -->
<th>Actions</th>
```

2. Check project-pages.js renderPages function:
```javascript
<td>${getAuditStatusBadge(page.h1_status)}</td>
<td>${getAuditStatusBadge(page.meta_status)}</td>
<td>${getAuditStatusBadge(page.noindex_status)}</td> // Must exist
<td>${getAuditStatusBadge(page.faq_status)}</td>     // Must exist
```

3. Verify colspan in empty states is 7 (not 5):
```javascript
<td colspan="7">...</td>
```

---

### Issue 4: "Action not found" Error

**Symptoms:**
- API returns "Invalid action" 
- 400 Bad Request

**Causes:**
- Wrong action parameter
- Controller switch case missing

**Solutions:**

Valid actions for all modules:
- `audit_page` (POST)
- `audit_pages` (POST)
- `audit_project` (POST)
- `results` (GET)
- `stats` (GET)
- `delete` (POST)

Check API call:
```javascript
formData.append('action', 'audit_project'); // Must be valid
```

Check controller has all cases:
```php
switch ($action) {
    case 'audit_page':
    case 'audit_pages':
    case 'audit_project':
    case 'results':
    case 'stats':
    case 'delete':
        break;
}
```

---

### Issue 5: Results Not Saving to Database

**Symptoms:**
- Audit runs but no results in database
- Table shows "Not Audited" after running

**Causes:**
- Database connection error
- Table doesn't exist
- Repository not saving

**Solutions:**

1. Check database connection:
```php
// In app/bootstrap.php
var_dump($pdo); // Should be PDO object, not null
```

2. Verify table exists:
```sql
SHOW TABLES LIKE 'page_audit_results';
```

3. Check Repository save method:
```php
public function save(int $pageId, string $moduleName, array $result): bool
{
    // Should have INSERT ... ON DUPLICATE KEY UPDATE
}
```

4. Check for SQL errors:
```php
try {
    $stmt->execute();
} catch (PDOException $e) {
    error_log($e->getMessage()); // Check error log
}
```

---

### Issue 6: "Fetch failed" Errors

**Symptoms:**
- All audits show ERROR status
- Tooltip says "Failed to fetch page"

**Causes:**
- Network issues
- Wrong URL format
- Firewall blocking
- SSL certificate issues

**Solutions:**

1. Test URL manually:
```bash
curl -I https://example.com/page.html
```

2. Check allow_url_fopen:
```php
<?php echo ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled'; ?>
```

3. Enable if disabled (php.ini):
```ini
allow_url_fopen = On
```

4. Or use cURL instead:
```php
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For dev only
$html = curl_exec($ch);
```

---

### Issue 7: Timeout on Large Projects

**Symptoms:**
- Script times out after 30 seconds
- Only partial results saved

**Causes:**
- PHP max_execution_time too low
- Too many URLs processing

**Solutions:**

1. Increase PHP timeout (php.ini):
```ini
max_execution_time = 300
```

2. Or set in code:
```php
set_time_limit(300); // 5 minutes
```

3. Process in smaller batches:
```javascript
// Instead of all at once
const batchSize = 50;
for (let i = 0; i < pages.length; i += batchSize) {
    const batch = pages.slice(i, i + batchSize);
    await auditBatch(batch);
}
```

---

### Issue 8: JavaScript Errors in Console

**Common Errors:**

**Error:** `Cannot read property 'checked' of null`

**Cause:** Checkbox element doesn't exist

**Solution:**
```javascript
// Add null check
const checked = document.getElementById('noindexAuditCheckbox')?.checked;
```

---

**Error:** `projectId is not defined`

**Cause:** Variable not set in project.php

**Solution:**
```php
// In project.php, add this script block:
<script>
const projectId = <?= $project['id'] ?>;
</script>
```

---

**Error:** `loadPages is not a function`

**Cause:** Function not defined in project-pages.js

**Solution:**
```javascript
// Check project-pages.js has:
function loadPages() {
    // ...
}
```

---

### Issue 9: Badges Not Rendering

**Symptoms:**
- Table shows text instead of colored badges
- No icons displaying

**Causes:**
- CSS not loading
- Bootstrap Icons not loading
- Function not returning HTML

**Solutions:**

1. Check Bootstrap Icons CDN:
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
```

2. Check getAuditStatusBadge function:
```javascript
function getAuditStatusBadge(status) {
    if (!status) return '<span class="status-badge badge-secondary">Not Audited</span>';
    // ... must return HTML string
}
```

3. Check CSS classes exist in styles.css:
```css
.status-badge { /* ... */ }
.badge-success { /* ... */ }
.badge-warning { /* ... */ }
.badge-danger { /* ... */ }
```

---

### Issue 10: Tooltips Not Working

**Symptoms:**
- Hover does nothing
- No tooltip appears

**Causes:**
- Tooltip library not initialized
- Title attribute missing
- formatAuditTooltip not called

**Solutions:**

1. Check tooltip initialization:
```javascript
// If using Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})
```

2. Check badge has title attribute:
```javascript
const tooltip = formatAuditTooltip(result, 'noindex');
return `<span title="${tooltip}">${badge}</span>`;
```

3. Ensure formatAuditTooltip has all cases:
```javascript
if (type === 'noindex') { /* ... */ }
if (type === 'faq') { /* ... */ }
```

---

## 🧪 Debug Mode

Enable detailed logging:

### PHP Debug

```php
// Add to top of Controller
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add logging
error_log("Audit started for page: " . $pageId);
error_log("Result: " . json_encode($result));
```

### JavaScript Debug

```javascript
// Add to project-audits.js
console.log('Running audit:', type);
console.log('API response:', data);
console.log('Page ID:', pageId);
```

### Database Query Debug

```php
// Before execute()
error_log("SQL: " . $stmt->queryString);
error_log("Params: " . json_encode($params));
```

---

## 📊 Health Check Script

Create `healthcheck.php`:

```php
<?php
require __DIR__ . '/app/bootstrap.php';

$checks = [
    'Database Connection' => isset($pdo),
    'Table Exists' => $pdo->query("SHOW TABLES LIKE 'page_audit_results'")->rowCount() > 0,
    'H1 Module' => class_exists('App\Modules\H1Audit\H1AuditController'),
    'Meta Module' => class_exists('App\Modules\MetaAudit\MetaAuditController'),
    'Noindex Module' => class_exists('App\Modules\NoindexAudit\NoindexAuditController'),
    'FAQ Module' => class_exists('App\Modules\FaqSchemaAudit\FaqSchemaAuditController'),
];

foreach ($checks as $name => $status) {
    echo $name . ': ' . ($status ? '✅ OK' : '❌ FAIL') . "\n";
}
```

Run: `php healthcheck.php`

---

## 🔧 Quick Fixes

### Reset All Audit Results

```sql
DELETE FROM page_audit_results WHERE module_name IN (
    'h1_audit', 'meta_audit', 'noindex_audit', 'faq_schema_audit'
);
```

### Reset Single Module

```sql
DELETE FROM page_audit_results WHERE module_name = 'noindex_audit';
```

### Clear Cache

```bash
# If using opcache
php -r "opcache_reset();"

# Clear browser cache
Ctrl+Shift+Delete
```

---

## 📞 Get Help

If issues persist:

1. Check error logs:
   - PHP error log
   - Browser console
   - Network tab in DevTools

2. Review documentation:
   - PHASE4_PART2_SUMMARY.txt
   - PHASE4_COMPLETE.txt

3. Verify installation:
   - Run health check script
   - Count files (should be 39 total)
   - Check database structure

4. Test with minimal case:
   - Create project with 1 URL
   - Run single module
   - Check if it works

---

## ✅ Verification Checklist

Before reporting issues, verify:

- [ ] All 4 module directories exist
- [ ] All 4 route files exist
- [ ] Database table exists
- [ ] All checkboxes visible in UI
- [ ] All table columns visible
- [ ] No JavaScript errors in console
- [ ] No PHP errors in log
- [ ] Health check passes
- [ ] Test URL is accessible

---

**Last Updated:** June 23, 2026  
**Phase:** 4.2 Complete
