# Phase 4 Integration Test Guide

**Test Date:** June 23, 2026  
**Phase:** 4.2 Complete  
**Modules:** 4 Audit Modules

---

## 🎯 Test Objectives

Verify that all 4 audit modules work correctly:
1. H1 Audit (Phase 4.1)
2. Meta Audit (Phase 4.1)
3. Noindex Audit (Phase 4.2) ✨
4. FAQ Schema Audit (Phase 4.2) ✨

---

## ✅ Pre-Test Checklist

### Files Verification

Run these commands to verify all files exist:

```bash
# Module directories
ls -la app/Modules/H1Audit/
ls -la app/Modules/MetaAudit/
ls -la app/Modules/NoindexAudit/
ls -la app/Modules/FaqSchemaAudit/

# Route files
ls -la routes/h1_audit.php
ls -la routes/meta_audit.php
ls -la routes/noindex_audit.php
ls -la routes/faq_schema_audit.php

# Frontend files
ls -la assets/js/project-audits.js
ls -la assets/js/project-pages.js
ls -la project.php
```

### Database Verification

```sql
-- Check table exists
DESCRIBE page_audit_results;

-- Check existing results
SELECT module_name, COUNT(*) as count, result_status
FROM page_audit_results
GROUP BY module_name, result_status;
```

---

## 🧪 Test Scenarios

### Test 1: UI Elements Present

**Objective:** Verify all UI elements are visible and functional.

**Steps:**
1. Navigate to `project.php?uuid={project-uuid}`
2. Click **URLs** tab
3. Locate audit controls section

**Expected Results:**
- [x] ☑ H1 checkbox visible and functional
- [x] ☑ Meta checkbox visible and functional
- [x] ☑ Noindex checkbox visible and functional ✨
- [x] ☑ FAQ checkbox visible and functional ✨
- [x] "Run Selected Audits" button visible
- [x] Table headers show: URL | H1 | Meta | Noindex | FAQ | Actions
- [x] No console errors

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 2: Single Module Execution

**Objective:** Test each module individually.

#### 2a. H1 Audit Only

**Steps:**
1. Uncheck all modules except H1
2. Click "Run Selected Audits"
3. Confirm dialog
4. Wait for completion

**Expected Results:**
- [x] Only H1 audit runs
- [x] H1 column shows badges
- [x] Other columns unchanged
- [x] Success notification displayed
- [x] No errors in console

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

#### 2b. Meta Audit Only

**Steps:** Same as 2a but with Meta checkbox only

**Expected Results:**
- [x] Only Meta audit runs
- [x] Meta column shows badges
- [x] Other columns unchanged

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

#### 2c. Noindex Audit Only ✨

**Steps:** Same as 2a but with Noindex checkbox only

**Expected Results:**
- [x] Only Noindex audit runs
- [x] Noindex column shows badges
- [x] Other columns unchanged

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

#### 2d. FAQ Audit Only ✨

**Steps:** Same as 2a but with FAQ checkbox only

**Expected Results:**
- [x] Only FAQ audit runs
- [x] FAQ column shows badges
- [x] Other columns unchanged

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 3: Multiple Module Execution

**Objective:** Test running multiple modules simultaneously.

**Steps:**
1. Check all 4 module checkboxes
2. Click "Run Selected Audits"
3. Confirm dialog
4. Wait for completion

**Expected Results:**
- [x] All 4 audits run sequentially
- [x] Progress notifications appear for each
- [x] All columns show badges
- [x] Final success notification
- [x] Table refreshes with results
- [x] No errors in console

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 4: Status Badges

**Objective:** Verify status badges display correctly.

**Test Cases:**

| Module | Status | Badge Color | Icon |
|--------|--------|-------------|------|
| H1 | PASS | Green | check-circle |
| H1 | WARNING | Yellow | exclamation-triangle |
| H1 | FAIL | Red | x-circle |
| H1 | ERROR | Red | exclamation-octagon |
| Meta | PASS | Green | check-circle |
| Noindex | PASS | Green | check-circle ✨ |
| Noindex | FAIL | Red | x-circle ✨ |
| FAQ | PASS | Green | check-circle ✨ |
| FAQ | WARNING | Yellow | exclamation-triangle ✨ |

**Expected Results:**
- [x] All badges render correctly
- [x] Colors match status
- [x] Icons display properly

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 5: Tooltips

**Objective:** Verify tooltip data is formatted correctly.

**Steps:**
1. Run all audits
2. Hover over each badge
3. Check tooltip content

**Expected Results:**

**H1 Tooltip:**
- Shows H1 count
- Shows H1 text (if single)
- Shows "Multiple H1 tags" (if multiple)

**Meta Tooltip:**
- Shows title and length
- Shows description and length

**Noindex Tooltip:** ✨
- Shows robots content
- Shows index status
- Shows follow status

**FAQ Tooltip:** ✨
- Shows schema found/not found
- Shows content detected/not detected
- Shows schema count if available

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 6: Error Handling

**Objective:** Test error scenarios.

#### 6a. No Modules Selected

**Steps:**
1. Uncheck all modules
2. Click "Run Selected Audits"

**Expected Results:**
- [x] Alert: "Please select at least one audit module"
- [x] No API calls made

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

#### 6b. Unreachable URL

**Steps:**
1. Add URL that returns 404
2. Run all audits
3. Check results

**Expected Results:**
- [x] ERROR badge displayed
- [x] Tooltip shows error message
- [x] Other URLs still process

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

#### 6c. Timeout

**Steps:**
1. Add slow/timeout URL
2. Run audits

**Expected Results:**
- [x] ERROR badge after timeout
- [x] Graceful error handling
- [x] Subsequent URLs still process

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 7: Database Persistence

**Objective:** Verify results are stored correctly.

**Steps:**
1. Run all 4 audits
2. Query database

```sql
SELECT 
    pp.url,
    par.module_name,
    par.result_status,
    par.checked_at
FROM page_audit_results par
JOIN project_pages pp ON pp.id = par.project_page_id
WHERE pp.project_id = ?
ORDER BY pp.id, par.module_name;
```

**Expected Results:**
- [x] Each URL has 4 result rows (one per module)
- [x] module_name values: h1_audit, meta_audit, noindex_audit, faq_schema_audit
- [x] result_status values: PASS, WARNING, FAIL, or ERROR
- [x] checked_at timestamp is recent
- [x] raw_data_json contains valid JSON

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 8: Result Update (Re-run)

**Objective:** Verify re-running audits updates existing results.

**Steps:**
1. Run audits for a project
2. Note result IDs from database
3. Re-run same audits
4. Check database again

**Expected Results:**
- [x] Result rows are UPDATED (not duplicated)
- [x] checked_at timestamp is updated
- [x] Same result IDs (no new inserts)

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 9: Large Project (Performance)

**Objective:** Test with large URL collection.

**Test Data:**
- Project with 100+ URLs

**Steps:**
1. Import sitemap with 100+ URLs
2. Select all 4 modules
3. Run audits
4. Monitor performance

**Expected Results:**
- [x] All URLs process successfully
- [x] No timeout errors
- [x] No memory errors
- [x] Reasonable execution time (< 5 min for 100 URLs)
- [x] Table displays all results

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

### Test 10: Browser Compatibility

**Objective:** Test across different browsers.

**Browsers to Test:**
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

**Expected Results:**
- [x] All features work in all browsers
- [x] Checkboxes functional
- [x] Badges render correctly
- [x] Tooltips display
- [x] No console errors

**Status:** ⬜ Not Tested | ✅ Pass | ❌ Fail

---

## 🐛 Bug Report Template

If issues are found, document them here:

### Bug #1

**Module:** [H1 | Meta | Noindex | FAQ]  
**Severity:** [Critical | High | Medium | Low]  
**Description:**  
**Steps to Reproduce:**  
1.  
2.  
3.  

**Expected Behavior:**  
**Actual Behavior:**  
**Screenshots:**  
**Browser/Environment:**  

---

## 📊 Test Results Summary

**Date Tested:** _______________  
**Tester:** _______________  
**Environment:** _______________

| Test | Status | Notes |
|------|--------|-------|
| Test 1: UI Elements | ⬜ | |
| Test 2a: H1 Only | ⬜ | |
| Test 2b: Meta Only | ⬜ | |
| Test 2c: Noindex Only ✨ | ⬜ | |
| Test 2d: FAQ Only ✨ | ⬜ | |
| Test 3: Multiple Modules | ⬜ | |
| Test 4: Status Badges | ⬜ | |
| Test 5: Tooltips | ⬜ | |
| Test 6a: No Selection | ⬜ | |
| Test 6b: 404 Error | ⬜ | |
| Test 6c: Timeout | ⬜ | |
| Test 7: Database | ⬜ | |
| Test 8: Re-run | ⬜ | |
| Test 9: Performance | ⬜ | |
| Test 10: Browsers | ⬜ | |

**Overall Status:** ⬜ Not Tested | ✅ All Pass | ⚠️ Some Issues | ❌ Failed

---

## 🎉 Sign-off

**Developer:**  
Name: _______________  
Date: _______________  
Signature: _______________

**QA Tester:**  
Name: _______________  
Date: _______________  
Signature: _______________

**Product Owner:**  
Name: _______________  
Date: _______________  
Signature: _______________

---

## 📝 Notes

Use this section for additional observations, recommendations, or feedback:

---

**Test Guide Complete**

This document should be used for integration testing before deploying Phase 4 to production.
