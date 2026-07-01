# Phase 3 Part 3 - Visual Guide

## Workspace Integration + Multi-Sitemap Management

This visual guide shows the UI changes and user flow for Phase 3 Part 3.

---

## Before and After

### Before (Phase 3 Part 2)
```
┌─────────────────────────────────────────────────────────────┐
│ Project: Example Site                                        │
├─────────────────────────────────────────────────────────────┤
│ [Total URLs] [Passed] [Failed] [Status]                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Sitemaps                                                     │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ • Sitemap 1  [Import] [View] [Disable]               │  │
│ │ • Sitemap 2  [Import] [View] [Disable]               │  │
│ └──────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### After (Phase 3 Part 3)
```
┌─────────────────────────────────────────────────────────────┐
│ Project: Example Site                                        │
├─────────────────────────────────────────────────────────────┤
│ [Total URLs] [Passed] [Failed] [Status]                    │
├─────────────────────────────────────────────────────────────┤
│ [Sitemaps] [URLs] ← NEW TABS                               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Sitemaps Tab Content (unchanged)                            │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ • Sitemap 1  [Import] [View URLs] [Disable]         │  │
│ │ • Sitemap 2  [Import] [View URLs] [Disable]         │  │
│ └──────────────────────────────────────────────────────┘  │
│                                                              │
│ URLs Tab Content (NEW)                                      │
│ ┌──────────────────────────────────────────────────────┐  │
│ │ [Search] [Sitemap Filter ▼] [Status Filter ▼]       │  │
│ │                                                        │  │
│ │ Statistics: Total | Unique | Pending | Completed     │  │
│ │                                                        │  │
│ │ URL Table with Filtering & Pagination                │  │
│ └──────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## URLs Tab Layout

```
┌──────────────────────────────────────────────────────────────────┐
│ Pages & URLs                                                      │
│ All URLs imported from sitemaps                                  │
│                                                                   │
│ [🔍 Search URLs...]  [Sitemap ▼]  [Status ▼]                   │
├──────────────────────────────────────────────────────────────────┤
│ Statistics                                                        │
│ ┌─────────┬─────────┬─────────┬─────────┐                      │
│ │ Total   │ Unique  │ Pending │Complete │                      │
│ │  1,234  │  1,198  │  1,234  │    0    │                      │
│ └─────────┴─────────┴─────────┴─────────┘                      │
├──────────────────────────────────────────────────────────────────┤
│ URL Table                                                         │
│ ┌──┬────────────────────┬──────────────┬────────┬──────┬───────┐│
│ │☐│ URL                │ Sitemap Src  │ Status │ Seen │Actions││
│ ├──┼────────────────────┼──────────────┼────────┼──────┼───────┤│
│ │☐│ /page-1            │ sitemap.xml  │ Active │ 2h   │  👁   ││
│ │☐│ /page-2            │ sitemap.xml  │ Active │ 2h   │  👁   ││
│ │☐│ /blog/post-1       │ blog-map.xml │ Active │ 1d   │  👁   ││
│ │☐│ /products/item-1   │ sitemap.xml  │ Active │ 2h   │  👁   ││
│ └──┴────────────────────┴──────────────┴────────┴──────┴───────┘│
│                                                                   │
│ [◄] [1] [2] [3] ... [25] [►]  ← Pagination                      │
└──────────────────────────────────────────────────────────────────┘
```

---

## Filter Controls Detail

```
┌────────────────────────────────────────────────────────────┐
│ Filter Bar                                                  │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  🔍 [Search URLs...                    ]                   │
│     ↑ Real-time search with 500ms debounce                │
│     Searches: original_url, normalized_url                 │
│                                                             │
│  📋 [All Sitemaps              ▼]                          │
│     ↑ Dropdown populated from project sitemaps            │
│     Options: All, Sitemap 1, Sitemap 2, ...               │
│                                                             │
│  🏷️ [All Statuses              ▼]                          │
│     ↑ Filter by URL lifecycle status                       │
│     Options: All, Active, Disabled, Removed, Pending       │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

---

## Statistics Cards

```
┌──────────────────────────────────────────────────────────────┐
│ URL Statistics                                                │
├──────────────┬──────────────┬──────────────┬────────────────┤
│              │              │              │                │
│   Total      │   Unique     │   Pending    │   Completed    │
│   URLs       │   URLs       │   Audit      │   Audits       │
│              │              │              │                │
│   1,234      │   1,198      │   1,234      │      0         │
│              │              │              │                │
│ Gray bg      │ Green bg     │ Blue bg      │ Purple bg      │
└──────────────┴──────────────┴──────────────┴────────────────┘
```

---

## URL Table Columns

```
┌──┬──────────────────────────────────────────────────────────┐
│☐ │ Checkbox for bulk selection (future feature)             │
├──┼──────────────────────────────────────────────────────────┤
│  │ URL Column:                                              │
│  │ • Shows original URL                                      │
│  │ • If normalized differs, shows "Normalized: ..."         │
│  │ • Truncates long URLs with ellipsis                      │
│  │ • Full URL visible on hover                              │
├──┼──────────────────────────────────────────────────────────┤
│  │ Sitemap Source Column:                                   │
│  │ • Shows full sitemap URL (truncated)                     │
│  │ • "Manual" if added manually                             │
│  │ • Hover shows full sitemap URL                           │
├──┼──────────────────────────────────────────────────────────┤
│  │ URL Status Column:                                        │
│  │ • Badge with icon and text                               │
│  │ • Active: Green badge with ✓                            │
│  │ • Disabled: Gray badge with –                            │
│  │ • Removed: Red badge with ✗                             │
│  │ • Pending Audit: Blue badge with ⏱                       │
├──┼──────────────────────────────────────────────────────────┤
│  │ Last Seen Column:                                         │
│  │ • Relative time (2h ago, 1d ago)                         │
│  │ • Updates on reimport                                     │
│  │ • Shows when URL was last in sitemap                     │
├──┼──────────────────────────────────────────────────────────┤
│  │ Audit Status Column (Placeholder):                       │
│  │ • "Pending Audit" badge for now                          │
│  │ • Will show audit results in Phase 4                     │
│  │ • Future: Pass/Fail badges with counts                   │
├──┼──────────────────────────────────────────────────────────┤
│  │ Actions Column:                                           │
│  │ • 👁 View details button                                 │
│  │ • Will open URL details panel in future                  │
└──┴──────────────────────────────────────────────────────────┘
```

---

## Empty States

### No URLs Yet
```
┌────────────────────────────────────────────────────────┐
│                                                         │
│                    🔗                                   │
│                (Large icon)                            │
│                                                         │
│             No URLs Found                              │
│                                                         │
│     Import URLs from a sitemap to get started         │
│                                                         │
│         [← Back to Sitemaps Tab]                       │
│                                                         │
└────────────────────────────────────────────────────────┘
```

### Loading State
```
┌────────────────────────────────────────────────────────┐
│                                                         │
│                    ⟳                                   │
│              (Spinning loader)                         │
│                                                         │
│              Loading URLs...                           │
│                                                         │
└────────────────────────────────────────────────────────┘
```

---

## User Flow Diagrams

### Flow 1: Discover → Import → View
```
┌──────────┐      ┌──────────┐      ┌──────────┐      ┌──────────┐
│ Discover │  →   │ Validate │  →   │  Import  │  →   │   View   │
│ Sitemaps │      │ Sitemaps │      │   URLs   │      │   URLs   │
└──────────┘      └──────────┘      └──────────┘      └──────────┘
     │                  │                  │                  │
     │ Auto-Discover    │ Check XML        │ Extract URLs     │ URLs Tab
     │ or Manual Add    │ Detect Type      │ Normalize        │ with Filters
     │                  │ Count URLs       │ Batch Insert     │
```

### Flow 2: Filter URLs
```
┌──────────┐      ┌──────────┐      ┌──────────┐
│  Search  │  →   │  Apply   │  →   │  Display │
│  Input   │      │  Filter  │      │ Results  │
└──────────┘      └──────────┘      └──────────┘
     │                  │                  │
     │ Type search      │ Backend query    │ Table update
     │ 500ms debounce   │ with filters     │ Pagination
     │                  │                  │
```

### Flow 3: Tab Switching
```
┌──────────┐      ┌──────────┐      ┌──────────┐
│ Sitemaps │  ⇄   │   URLs   │  ⇄   │ Sitemaps │
│   Tab    │      │   Tab    │      │   Tab    │
└──────────┘      └──────────┘      └──────────┘
     │                  │                  │
     │ Click URLs tab   │ Click Sitemaps   │ No reload
     │ Load on first    │ Return to last   │ Smooth
     │ access           │ state            │ transition
```

### Flow 4: Import → Auto-Switch
```
┌──────────────┐
│  Sitemaps    │
│     Tab      │
└──────┬───────┘
       │
       │ Click Import
       ▼
┌──────────────┐
│  Importing   │
│   (Modal)    │
└──────┬───────┘
       │
       │ Complete
       ▼
┌──────────────┐
│   Success    │
│ Notification │
└──────┬───────┘
       │
       │ Auto after 1.5s
       ▼
┌──────────────┐
│    URLs      │
│     Tab      │  ← Shows imported URLs
└──────────────┘
```

### Flow 5: View URLs from Sitemap
```
┌──────────────┐
│  Sitemaps    │
│     Tab      │
└──────┬───────┘
       │
       │ Click "View URLs"
       │ on sitemap card
       ▼
┌──────────────┐
│    URLs      │
│     Tab      │
└──────┬───────┘
       │
       │ Filter auto-applied
       ▼
┌──────────────┐
│ URLs from    │
│ that sitemap │
│    only      │
└──────────────┘
```

---

## Status Badge Colors

```
Active:         🟢 Green background   (#d1fae5) | Dark green text (#065f46)
Disabled:       ⚫ Gray background    (#f3f4f6) | Dark gray text  (#4b5563)
Removed:        🔴 Red background     (#fee2e2) | Dark red text   (#991b1b)
Pending Audit:  🔵 Blue background    (#dbeafe) | Dark blue text  (#1e40af)

Icon + Text format:
✓ Active
– Disabled
✗ Removed
⏱ Pending Audit
```

---

## Pagination Examples

### Small Dataset (3 pages)
```
[◄] [1] [2] [3] [►]
```

### Medium Dataset (10 pages, on page 5)
```
[◄] [1] [2] [3] [4] [5] [6] [7] ... [10] [►]
    └─────────────┘
    Current ±2 pages
```

### Large Dataset (50 pages, on page 25)
```
[◄] [1] ... [23] [24] [25] [26] [27] ... [50] [►]
            └──────────────────┘
            Current ±2 pages
```

---

## Responsive Behavior

### Desktop (>1024px)
```
┌──────────────────────────────────────────────────────────┐
│ All filters in one row                                    │
│ [Search............] [Sitemap ▼] [Status ▼]             │
│                                                           │
│ Stats: 4 columns side by side                           │
│ [Total] [Unique] [Pending] [Completed]                  │
│                                                           │
│ Table: All columns visible                               │
│ ☐ URL | Sitemap | Status | Seen | Audit | Actions       │
└──────────────────────────────────────────────────────────┘
```

### Tablet (768-1024px)
```
┌────────────────────────────────────────────┐
│ Filters stack vertically                   │
│ [Search.......................]            │
│ [Sitemap ▼] [Status ▼]                    │
│                                             │
│ Stats: 2x2 grid                            │
│ [Total  ] [Unique  ]                       │
│ [Pending] [Complete]                       │
│                                             │
│ Table: Horizontal scroll if needed         │
└────────────────────────────────────────────┘
```

### Mobile (<768px)
```
┌──────────────────────────┐
│ Filters stack vertically │
│ [Search...........]      │
│ [Sitemap ▼]             │
│ [Status ▼]              │
│                          │
│ Stats: 2x2 grid         │
│ [Total][Unique]         │
│ [Pend.][Compl.]         │
│                          │
│ Table: Card layout      │
│ ┌──────────────────────┐│
│ │ URL: /page-1         ││
│ │ Sitemap: sitemap.xml ││
│ │ Status: Active       ││
│ │ Seen: 2h ago         ││
│ └──────────────────────┘│
└──────────────────────────┘
```

---

## Interaction States

### Button States
```
Normal:     [Import URLs]  ← Default state
Hover:      [Import URLs]  ← Slightly darker, opacity 0.9
Active:     [Import URLs]  ← Pressed state
Disabled:   [Import URLs]  ← Grayed out, cursor: not-allowed
Loading:    [⟳ Importing]  ← Spinner icon, disabled
```

### Filter States
```
Empty:      [All Sitemaps ▼]  ← Default text
Selected:   [sitemap.xml ▼]   ← Selected option
Focus:      [All Sitemaps ▼]  ← Blue ring outline
Disabled:   [All Sitemaps ▼]  ← Grayed out (if no sitemaps)
```

### Tab States
```
Inactive:   [Sitemaps]  ← Gray text, transparent border
Active:     [Sitemaps]  ← Indigo text, indigo bottom border
Hover:      [Sitemaps]  ← Indigo text (on inactive tabs)
```

---

## Data Flow

```
Backend (PHP)                   Frontend (JS)
─────────────                   ──────────────

PageRepository                  project-pages.js
      │                                │
      │ SQL queries                    │ AJAX requests
      │ with filters                   │
      ▼                                ▼
PageService                       DOM Updates
      │                                │
      │ Business logic                 │ Render tables
      │ Stats calculation              │ Render stats
      ▼                                ▼
PageController                    User Interface
      │                                │
      │ JSON response                  │ User actions
      │                                │
      └────────────────────────────────┘
           HTTP Request/Response
```

---

## Summary

Phase 3 Part 3 adds:

✅ **Visual UI Changes:**
- Tab navigation bar
- URLs tab with complete interface
- Filter controls bar
- Statistics dashboard
- Paginated URL table

✅ **Interaction Improvements:**
- Smooth tab switching
- Real-time search with debounce
- Dynamic filter dropdowns
- Pagination navigation
- Status badges with icons

✅ **User Experience Enhancements:**
- Auto-switch after import
- Pre-filtered view from sitemaps
- Empty/loading states
- Notifications
- Responsive design

The workspace is now visually complete and provides a seamless experience
for managing sitemaps and viewing imported URLs.

