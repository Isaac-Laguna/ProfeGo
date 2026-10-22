# Project Memory

## Main page

- `index.php` implements the Figma desktop and mobile homepage as static HTML; no app functionality yet.
- Styles live in `css/main.css`. Raster assets are in `assets/images/`, social SVGs in `assets/icons/`, and self-hosted Inter (variable, latin and latin-ext) in `assets/fonts/`.
- Responsive breakpoints: 1100px (hide steps image), 900px (mobile header and layout), 380px (compact CTA buttons).
- The "Comentarios de estudiantes" section is omitted until real testimonials exist; its avatar images were removed.
- User-facing copy typos from Figma were corrected (accents and "concierte").
- `api/`, `includes/`, `js/features/`, `js/shared/`, and `specs/` remain placeholders for upcoming work.
- The project is served by USBWebServer at `http://localhost/` (document root is the project folder).

## Login page

- `pages/login.php` implements the Figma desktop and mobile login screen as static HTML; styles live in `css/login.css` and reuse tokens/brand from `main.css`.
- No form functionality yet. Inputs start empty (the Figma error state was only a demo); links and the submit button are placeholders. Error styles remain in `login.css` for future validation.
- Mobile breakpoint is 900px; the mobile layout keeps the design's scaled proportions.
