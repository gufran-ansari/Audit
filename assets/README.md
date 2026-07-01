# Assets Directory

This folder contains all static assets for the Audit withGufran application.

---

## Directory Structure

```
assets/
├── css/           # Stylesheets
├── js/            # JavaScript files
├── icons/         # Custom icon files
└── images/        # Image assets
```

---

## 📁 Subdirectories

### css/
Contains all custom CSS stylesheets.

**Current Files:**
- `styles.css` - Custom styles, animations, and responsive adjustments

**Usage:**
```html
<link rel="stylesheet" href="assets/css/styles.css">
```

---

### js/
Contains all JavaScript files for application functionality.

**Current Files:**
- `app.js` - Core application logic, event handlers, and interactions

**Usage:**
```html
<script src="assets/js/app.js"></script>
```

**Future Files:**
- `modules/` - Modular JavaScript components
- `utils/` - Utility functions
- `api.js` - API integration (Phase 2)
- `auth.js` - Authentication logic (Phase 2)

---

### icons/
For custom icon files (SVG, PNG, etc.)

**Planned Usage:**
- Custom logo files
- Application-specific icons
- Favicon variations
- Social media icons

**Recommended Formats:**
- SVG (scalable, preferred)
- PNG (with @2x and @3x variants for Retina displays)
- ICO (for favicons)

**Example Usage:**
```html
<img src="assets/icons/logo.svg" alt="Audit withGufran Logo">
<link rel="icon" type="image/png" href="assets/icons/favicon.png">
```

---

### images/
For image assets like backgrounds, illustrations, photos.

**Planned Usage:**
- Background images
- Hero images
- Dashboard illustrations
- User avatars (placeholder)
- Chart/graph images (if needed)
- Marketing/promotional images

**Recommended Formats:**
- WebP (modern, compressed)
- PNG (for transparency)
- JPG (for photos)
- SVG (for illustrations)

**Example Usage:**
```html
<img src="assets/images/dashboard-bg.webp" alt="Dashboard Background">
```

---

## 🎨 Asset Guidelines

### File Naming
- Use lowercase with hyphens: `logo-dark.svg`, `hero-image.jpg`
- Be descriptive: `audit-icon-success.svg` not `icon1.svg`
- Include size in filename if multiple versions: `logo-256.png`, `logo-512.png`

### Organization
- Group related assets in subdirectories when needed:
  ```
  images/
  ├── backgrounds/
  ├── illustrations/
  └── avatars/
  ```

### Optimization
- **Images**: Compress before committing
  - Use tools like TinyPNG, ImageOptim, or Squoosh
  - Provide multiple resolutions for responsive images
  
- **SVGs**: Minify and optimize
  - Use SVGO or similar tools
  - Remove unnecessary metadata
  
- **CSS**: Keep organized with clear sections
  - Comment liberally
  - Group related styles
  
- **JS**: Keep modular and documented
  - Use clear function names
  - Comment complex logic

### Responsive Images
Use the `<picture>` element or `srcset` for responsive images:

```html
<picture>
  <source media="(min-width: 1024px)" srcset="assets/images/hero-desktop.webp">
  <source media="(min-width: 768px)" srcset="assets/images/hero-tablet.webp">
  <img src="assets/images/hero-mobile.webp" alt="Hero Image">
</picture>
```

Or with srcset:
```html
<img src="assets/images/logo.png"
     srcset="assets/images/logo.png 1x,
             assets/images/logo@2x.png 2x,
             assets/images/logo@3x.png 3x"
     alt="Logo">
```

---

## 📦 CDN Resources

Currently using CDN for:
- **Tailwind CSS**: https://cdn.tailwindcss.com
- **Font Awesome 6.4**: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css

**Future Consideration**: Self-host these resources for offline capability and better performance control (Phase 3/4).

---

## 🚀 Performance Best Practices

### Loading Strategy
1. **Critical CSS**: Inline critical styles in `<head>`
2. **Deferred JS**: Use `defer` or `async` for non-critical scripts
3. **Lazy Loading**: Lazy load images below the fold
4. **Preloading**: Preload critical assets

```html
<!-- Preload critical assets -->
<link rel="preload" href="assets/css/styles.css" as="style">
<link rel="preload" href="assets/js/app.js" as="script">

<!-- Lazy load images -->
<img src="assets/images/chart.png" loading="lazy" alt="Chart">
```

### Caching
Configure proper cache headers for assets:
- CSS/JS: Long cache with versioned filenames
- Images: Long cache for static images
- Use cache-busting for updated assets: `styles.css?v=1.0.1`

---

## 📝 Adding New Assets

### CSS Files
1. Create file in `assets/css/`
2. Link in the relevant PHP page (e.g. `dashboard.php`):
   ```html
   <link rel="stylesheet" href="assets/css/your-file.css">
   ```

### JavaScript Files
1. Create file in `assets/js/`
2. Import in HTML or use ES6 modules:
   ```html
   <script src="assets/js/your-file.js"></script>
   <!-- OR -->
   <script type="module" src="assets/js/your-module.js"></script>
   ```

### Icons/Images
1. Place file in appropriate directory
2. Reference in HTML/CSS:
   ```html
   <img src="assets/images/your-image.png" alt="Description">
   ```
   ```css
   .bg-hero {
     background-image: url('../images/hero-bg.jpg');
   }
   ```

---

## 🔄 Phase 2+ Additions

Future assets to be added:
- User avatar placeholders
- Chart/graph images or libraries
- Loading spinners/animations
- Success/error illustration graphics
- Email templates
- PDF report templates
- Print stylesheets

---

## 📊 Current Asset Inventory

### CSS (1 file)
- ✅ `styles.css` - 233 lines

### JavaScript (1 file)
- ✅ `app.js` - 318 lines

### Icons
- 🔲 Logo variants (to be added)
- 🔲 Favicon (to be added)

### Images
- 🔲 Backgrounds (to be added)
- 🔲 Illustrations (to be added)

---

**Last Updated**: June 14, 2026  
**Version**: 1.0.0 (Phase 1)
