# Component Structure Guide

This document outlines the planned component architecture for the Audit withGufran application.

---

## Component Hierarchy

```
App
├── Sidebar
│   ├── Logo
│   ├── Navigation Menu
│   │   ├── MenuItem (Dashboard)
│   │   ├── MenuItem (New Audit)
│   │   ├── MenuItem (History)
│   │   ├── MenuItem (Reports)
│   │   └── MenuItem (Settings)
│   └── UserProfile
│
├── TopBar
│   ├── MobileMenuButton
│   ├── SearchBar
│   ├── NotificationBell
│   └── HelpButton
│
└── MainWorkspace
    ├── PageHeader
    ├── StatsGrid
    │   ├── StatCard (Total Audits)
    │   ├── StatCard (Pending Reviews)
    │   ├── StatCard (Completed)
    │   └── StatCard (Issues Found)
    │
    ├── QuickActions
    │   ├── ActionButton (New Audit)
    │   ├── ActionButton (Import Data)
    │   └── ActionButton (Generate Report)
    │
    └── AuditTable
        ├── TableHeader
        │   ├── Title
        │   ├── FilterButton
        │   └── ExportButton
        ├── TableView (Desktop)
        │   ├── TableHeader
        │   ├── TableBody
        │   │   └── TableRow[]
        │   │       ├── AuditID
        │   │       ├── ProjectName
        │   │       ├── Date
        │   │       ├── StatusBadge
        │   │       ├── PriorityBadge
        │   │       └── ActionButtons
        │   └── Pagination
        └── CardView (Mobile)
            ├── AuditCard[]
            │   ├── AuditID
            │   ├── ProjectName
            │   ├── Date
            │   ├── StatusBadge
            │   ├── PriorityBadge
            │   └── ActionButtons
            └── Pagination
```

---

## Component Details

### 1. Sidebar Component
**Location**: `#sidebar`  
**Responsive Behavior**:
- Mobile: Slide-in overlay
- Desktop: Fixed sidebar

**Sub-components**:
- `Logo`: Brand identity
- `Navigation Menu`: Main navigation links
- `UserProfile`: User info and logout

**Module Attachment**: Navigation module for routing

---

### 2. TopBar Component
**Location**: `<header>` element  
**Features**:
- Search functionality
- Notification system
- Help/support access

**Module Attachment**: Search and notification modules

---

### 3. StatsGrid Component
**Location**: Stats cards section  
**Purpose**: Display key metrics

**Individual StatCard**:
- Title
- Value (numeric)
- Trend indicator
- Icon

**Module Attachment**: Statistics module for real-time updates

---

### 4. QuickActions Component
**Location**: Action buttons grid  
**Purpose**: Primary user actions

**Module Attachment**: Workflow modules

---

### 5. AuditTable Component
**Location**: Main table container  
**Responsive Views**:
- Desktop: Traditional table
- Mobile: Card-based layout

**Features**:
- Sorting
- Filtering
- Pagination
- Action buttons (View, Edit, Delete)

**Module Attachment**: CRUD operations, data fetching

---

## Future Components (To Be Developed)

### Phase 2 Components

#### 1. Modal System
```
Modal
├── ModalOverlay
├── ModalContainer
│   ├── ModalHeader
│   ├── ModalBody
│   └── ModalFooter
```

**Use Cases**:
- Audit detail view
- Edit forms
- Confirmation dialogs
- Image previews

---

#### 2. Form Components
```
AuditForm
├── FormField[]
│   ├── Label
│   ├── Input/Select/Textarea
│   └── ErrorMessage
└── FormActions
    ├── SubmitButton
    └── CancelButton
```

**Form Types**:
- New Audit Form
- Edit Audit Form
- Filter Form
- Settings Form

---

#### 3. Filter Panel
```
FilterPanel
├── FilterGroup[]
│   ├── FilterLabel
│   ├── FilterInput
│   └── FilterOptions
└── FilterActions
    ├── ApplyButton
    └── ClearButton
```

**Filter Categories**:
- Status
- Priority
- Date range
- Project name

---

#### 4. Toast Notification System
```
ToastContainer
└── Toast[]
    ├── Icon
    ├── Message
    └── CloseButton
```

**Toast Types**:
- Success
- Error
- Warning
- Info

---

#### 5. Loading States
```
LoadingSpinner
SkeletonLoader
ProgressBar
```

---

### Phase 3 Components

#### 1. Audit Detail View
```
AuditDetail
├── DetailHeader
├── DetailTabs
│   ├── Overview
│   ├── Documents
│   ├── Comments
│   └── History
└── DetailActions
```

---

#### 2. Report Generator
```
ReportGenerator
├── ReportOptions
├── ReportPreview
└── ReportExport
```

---

#### 3. Data Visualization
```
Dashboard
├── ChartContainer[]
│   ├── LineChart
│   ├── BarChart
│   ├── PieChart
│   └── StatCards
```

---

## Component State Management

### Current (Phase 1)
- DOM manipulation with vanilla JavaScript
- Event-driven architecture
- Local state in closures

### Future (Phase 2+)
Consider implementing:
- State management library (Redux, Zustand, or custom)
- Component lifecycle management
- Centralized data store
- Reactive updates

---

## Component Naming Conventions

### HTML/CSS
- Component containers: `.component-name`
- Sub-elements: `.component-name__element`
- Modifiers: `.component-name--modifier`

### JavaScript
- Component classes: `ComponentName`
- Component instances: `componentName`
- Event handlers: `handleEventName`
- Utility functions: `doSomething`

---

## Module Integration Guidelines

### 1. Identify Component Location
Find the `MODULE ATTACHMENT POINT` comment in the code

### 2. Create Module File
```javascript
// assets/js/modules/componentName.js
export class ComponentName {
    constructor(options) {
        this.element = options.element;
        this.init();
    }
    
    init() {
        // Initialize component
    }
    
    render() {
        // Render logic
    }
}
```

### 3. Import and Initialize
```javascript
// assets/js/app.js
import { ComponentName } from './modules/componentName.js';

const component = new ComponentName({
    element: document.querySelector('#component')
});
```

### 4. Maintain Styling Consistency
- Use existing Tailwind classes
- Follow spacing patterns
- Maintain color scheme
- Respect responsive breakpoints

---

## Testing Checklist for New Components

- [ ] Desktop view (>1024px)
- [ ] Tablet view (768px-1024px)
- [ ] Mobile view (<768px)
- [ ] Touch interactions
- [ ] Keyboard navigation
- [ ] Screen reader compatibility
- [ ] Cross-browser testing
- [ ] Performance impact

---

## Design Tokens (To Be Formalized in Phase 2)

### Colors
- Primary: Indigo/Purple gradient
- Success: Green
- Warning: Orange
- Error: Red
- Info: Blue
- Neutral: Gray scale

### Spacing
- xs: 0.25rem (4px)
- sm: 0.5rem (8px)
- md: 1rem (16px)
- lg: 1.5rem (24px)
- xl: 2rem (32px)

### Typography
- Heading 1: 3xl
- Heading 2: 2xl
- Heading 3: xl
- Body: base
- Small: sm
- Tiny: xs

### Shadows
- sm: Small elevation
- md: Medium elevation
- lg: Large elevation
- xl: Maximum elevation

---

## Performance Considerations

### Current Optimizations
- Debounced search input
- CSS transitions (GPU accelerated)
- Minimal DOM manipulation
- Event delegation where applicable

### Future Optimizations
- Lazy loading for large tables
- Virtual scrolling
- Code splitting
- Image optimization
- Caching strategies

---

This structure is designed to be flexible and scalable. As you build each phase, update this document with actual implementation details.
