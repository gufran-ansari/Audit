# Design System - Audit withGufran

Complete design specifications for the Audit withGufran application.

---

## Color Palette

### Primary Colors
```
Indigo-900: #312e81 (Sidebar dark)
Indigo-800: #3730a3 (Sidebar hover)
Indigo-700: #4338ca (Borders)
Indigo-600: #4f46e5 (Primary actions)
Indigo-500: #6366f1
Indigo-400: #818cf8
Indigo-300: #a5b4fc (Secondary text)
Indigo-200: #c7d2fe (Borders)
Indigo-100: #e0e7ff (Backgrounds)
Indigo-50:  #eef2ff (Light backgrounds)

Purple-900: #581c87 (Gradient end)
Purple-600: #9333ea (Accent)
```

### Status Colors
```
Success (Green):
- green-800: #166534 (Text)
- green-600: #16a34a (Icons)
- green-500: #22c55e (Indicators)
- green-100: #dcfce7 (Background)

Warning (Orange):
- orange-800: #9a3412 (Text)
- orange-600: #ea580c (Icons)
- orange-500: #f97316 (Indicators)
- orange-100: #ffedd5 (Background)

Error (Red):
- red-800: #991b1b (Text)
- red-600: #dc2626 (Icons)
- red-500: #ef4444 (Indicators)
- red-100: #fee2e2 (Background)

Info (Blue):
- blue-800: #1e40af (Text)
- blue-600: #2563eb (Icons)
- blue-500: #3b82f6 (Indicators)
- blue-100: #dbeafe (Background)

Pending (Yellow):
- yellow-800: #854d0e (Text)
- yellow-600: #ca8a04 (Icons)
- yellow-500: #eab308 (Indicators)
- yellow-100: #fef9c3 (Background)
```

### Neutral Colors
```
Gray Scale:
- gray-900: #111827 (Headings)
- gray-800: #1f2937 (Primary text)
- gray-700: #374151
- gray-600: #4b5563 (Secondary text)
- gray-500: #6b7280
- gray-400: #9ca3af (Disabled)
- gray-300: #d1d5db (Borders)
- gray-200: #e5e7eb (Dividers)
- gray-100: #f3f4f6 (Hover backgrounds)
- gray-50:  #f9fafb (Page background)

White: #ffffff
Black: #000000
```

---

## Typography

### Font Family
```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 
             Roboto, 'Helvetica Neue', Arial, sans-serif;
```

### Font Sizes
```
text-xs:   0.75rem  (12px) - Badges, captions
text-sm:   0.875rem (14px) - Secondary text, table cells
text-base: 1rem     (16px) - Body text
text-lg:   1.125rem (18px) - Subheadings
text-xl:   1.25rem  (20px) - Section titles
text-2xl:  1.5rem   (24px) - Card titles
text-3xl:  1.875rem (30px) - Page headings
```

### Font Weights
```
font-normal:   400 - Body text
font-medium:   500 - Navigation, labels
font-semibold: 600 - Card titles, badges
font-bold:     700 - Page headings, emphasis
```

### Line Heights
```
leading-tight:  1.25  - Headings
leading-normal: 1.5   - Body text
leading-relaxed: 1.625 - Long-form content
```

---

## Spacing System

### Base Unit: 0.25rem (4px)

```
Spacing Scale:
0:   0
1:   0.25rem  (4px)
2:   0.5rem   (8px)
3:   0.75rem  (12px)
4:   1rem     (16px)
5:   1.25rem  (20px)
6:   1.5rem   (24px)
8:   2rem     (32px)
10:  2.5rem   (40px)
12:  3rem     (48px)
16:  4rem     (64px)
20:  5rem     (80px)
24:  6rem     (96px)
```

### Common Patterns
```
Component padding:     p-6 (24px)
Card padding:          p-6 (24px)
Section margin:        mb-6 (24px)
Element spacing:       space-x-3, space-y-2
Container padding:     px-6 py-4
```

---

## Border Radius

```
rounded-none: 0
rounded-sm:   0.125rem (2px)
rounded:      0.25rem  (4px)
rounded-md:   0.375rem (6px)
rounded-lg:   0.5rem   (8px)
rounded-xl:   0.75rem  (12px)
rounded-2xl:  1rem     (16px)
rounded-full: 9999px   (Circle)
```

### Usage
- Cards: `rounded-xl` (12px)
- Buttons: `rounded-lg` (8px)
- Badges: `rounded-full` (circular)
- Inputs: `rounded-lg` (8px)
- Avatar: `rounded-full` (circle)

---

## Shadows

```css
/* Small elevation - Hover states */
shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05)

/* Medium elevation - Cards */
shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1),
        0 1px 2px -1px rgba(0, 0, 0, 0.1)

/* Large elevation - Modals, overlays */
shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
           0 4px 6px -4px rgba(0, 0, 0, 0.1)

/* Extra large - Emphasis */
shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
           0 8px 10px -6px rgba(0, 0, 0, 0.1)

/* Custom hover shadow */
box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1)
```

---

## Components Specifications

### 1. Buttons

#### Primary Button
```css
Classes: bg-gradient-to-r from-indigo-600 to-purple-600 
         text-white rounded-xl p-6 shadow-lg
Hover:   shadow-xl, scale(1.02)
Active:  scale(0.98)
```

#### Secondary Button
```css
Classes: bg-white border-2 border-indigo-200 text-indigo-600 
         rounded-xl p-6
Hover:   shadow-md, scale(1.02)
```

#### Icon Button
```css
Classes: text-gray-600 hover:text-indigo-600
Size:    text-xl (icons)
Padding: p-2 (optional background)
```

---

### 2. Cards

#### Stat Card
```css
Classes: bg-white rounded-xl shadow-sm p-6 card-hover
Hover:   translateY(-2px), shadow-lg
```

#### Table Card
```css
Classes: bg-white rounded-xl shadow-sm
Border:  border-b border-gray-200 (sections)
```

---

### 3. Badges

#### Status Badge
```css
Classes: px-3 py-1 text-xs font-semibold rounded-full
Colors:  bg-{color}-100 text-{color}-800

Variants:
- Completed: green
- In Progress: yellow
- Pending: red
- Archived: gray
```

#### Priority Badge
```css
Classes: px-3 py-1 text-xs font-semibold rounded-full
Colors:  bg-{color}-100 text-{color}-800

Variants:
- High: orange
- Medium: blue
- Low: gray
```

---

### 4. Input Fields

```css
Classes: w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300
Focus:   ring-2 ring-indigo-500 border-transparent
Icon:    absolute left-3 top-3 text-gray-400
```

---

### 5. Navigation Items

```css
Default:  flex items-center space-x-3 px-4 py-3 rounded-lg
Hover:    bg-indigo-800, padding-left: 1.25rem
Active:   bg-white/10, border-left: 4px solid white
```

---

### 6. Table

#### Desktop Table
```css
Header:  bg-gray-50 text-xs font-semibold text-gray-600 uppercase
Cell:    px-6 py-4 text-sm
Row:     hover:bg-gray-50 cursor-pointer
```

#### Mobile Card
```css
Container: p-4 hover:bg-gray-50
Spacing:   mb-2 for elements
```

---

## Animations & Transitions

### Duration
```
Fast:    0.15s - Hover states
Default: 0.2s  - Most interactions
Slow:    0.3s  - Sidebar, modals
```

### Easing
```css
ease:        cubic-bezier(0.25, 0.1, 0.25, 1.0)
ease-in:     cubic-bezier(0.42, 0, 1.0, 1.0)
ease-out:    cubic-bezier(0.0, 0, 0.58, 1.0)
ease-in-out: cubic-bezier(0.42, 0, 0.58, 1.0)
```

### Common Animations

#### Card Hover
```css
transform: translateY(-2px);
box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
transition: all 0.2s ease;
```

#### Button Press
```css
/* Hover */
transform: scale(1.02);
/* Active */
transform: scale(0.98);
```

#### Fade In
```css
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
```

#### Slide In
```css
@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}
```

---

## Breakpoints

```
Mobile:  < 768px   (sm)
Tablet:  768px     (md)
Desktop: 1024px    (lg)
Large:   1280px    (xl)
XLarge:  1536px    (2xl)
```

### Responsive Patterns

#### Grid Systems
```
Mobile:  grid-cols-1
Tablet:  md:grid-cols-2
Desktop: lg:grid-cols-4
```

#### Sidebar
```
Mobile:  Hidden by default, overlay on open
Desktop: Always visible, fixed position
```

#### Table
```
Mobile:  Card-based layout (hidden md:block for table)
Desktop: Traditional table layout
```

---

## Icon Usage

### Icon Library: Font Awesome 6.4.0

### Common Icons
```
Dashboard:      fa-home
New Audit:      fa-plus-circle
History:        fa-history
Reports:        fa-file-alt
Settings:       fa-cog
User:           fa-user
Search:         fa-search
Notification:   fa-bell
Help:           fa-question-circle
View:           fa-eye
Edit:           fa-edit
Delete:         fa-trash
Filter:         fa-filter
Export:         fa-download
Upload:         fa-upload
Check:          fa-check-circle
Warning:        fa-exclamation-triangle
Close:          fa-times
Menu:           fa-bars
Arrow Right:    fa-arrow-right
Arrow Up:       fa-arrow-up
```

### Icon Sizes
```
Small:   text-sm
Default: text-lg
Large:   text-xl
XLarge:  text-2xl
```

---

## Accessibility Guidelines

### Color Contrast
- Text on background: Minimum 4.5:1 ratio
- Large text: Minimum 3:1 ratio
- Interactive elements: Clear focus states

### Focus States
```css
focus:outline-none 
focus:ring-2 
focus:ring-indigo-500
```

### ARIA Labels (To be implemented in Phase 2)
- All interactive elements
- Icon-only buttons
- Form inputs
- Status indicators

---

## Grid System

### Container
```css
max-width: Full width with padding
padding: px-6 (24px)
```

### Layout Grid
```
Stats Grid:    grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6
Actions Grid:  grid-cols-1 lg:grid-cols-3 gap-6
```

---

## Best Practices

### 1. Consistency
- Use design tokens consistently
- Follow component patterns
- Maintain spacing rhythm

### 2. Responsiveness
- Mobile-first approach
- Test all breakpoints
- Touch-friendly targets (min 44x44px)

### 3. Performance
- Use CSS transforms for animations
- Minimize repaints/reflows
- Optimize asset loading

### 4. Accessibility
- Semantic HTML
- Keyboard navigation
- Screen reader support

---

## Future Enhancements

### Dark Mode (Phase 3)
- Define dark color palette
- Toggle mechanism
- Persistent preference

### Custom Theme Support (Phase 4)
- CSS variables for colors
- Dynamic theme switching
- Brand customization

---

**Last Updated**: June 14, 2026  
**Version**: 1.0.0
