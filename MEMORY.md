# Project Memory

## Main page

- `index.php` implements the Figma desktop and mobile homepage as static HTML; no app functionality yet.
- Styles live in `css/main.css`. Figma raster assets are in `pages/images/` and social SVG icons in `pages/icons/`.
- Responsive breakpoints: 1100px (hide steps image), 900px (mobile header and layout), 380px (compact CTA buttons).
- Typography uses Inter with a Segoe UI fallback because Inter is not bundled. Figma copy is kept verbatim, including existing typos.
- `api/`, `assets/`, `includes/`, `js/features/`, `js/shared/`, and `specs/` remain placeholders for upcoming work.
- The project is served by USBWebServer at `http://localhost/` (document root is the project folder).
