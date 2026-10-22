# Project Memory

## Main page

- `index.php` implements the Figma desktop and mobile homepage as static HTML; no app functionality yet.
- Styles live in `css/main.css`. Raster assets are in `assets/images/`, social SVGs in `assets/icons/`, and self-hosted Inter (variable, latin and latin-ext) in `assets/fonts/`.
- Responsive breakpoints: 1100px (hide steps image), 900px (mobile header and layout), 380px (compact CTA buttons).
- The "Comentarios de estudiantes" section is omitted until real testimonials exist; its avatar images were removed.
- User-facing copy typos from Figma were corrected (accents and "concierte").
- `api/`, `includes/`, `js/features/`, `js/shared/`, and `specs/` remain placeholders for upcoming work.
- The project is served by USBWebServer at `http://localhost/` (document root is the project folder).
