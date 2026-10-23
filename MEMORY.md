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
- No form functionality yet. Inputs start empty (the Figma error state was only a demo); the password-recovery link and the submit button are placeholders. The "Regístrate aquí" link points to `register.php`.
- Mobile breakpoint is 900px; the mobile layout keeps the design's scaled proportions.

## Register page

- `pages/register.php` implements the Figma desktop and mobile create account screen as static HTML; styles live in `css/register.css` and reuse tokens/brand from `main.css`.
- No functionality yet: the fields are native inputs, the role is a static "Estudiante" indicator, and the submit button is inert. No JavaScript is needed for this page.
- The terms row, login link, and submit button sit outside the 320px form card, matching Figma. The checkbox starts unchecked (Figma only shows its checked demo state).
- Mobile breakpoint is 900px and keeps the design's scaled proportions.
- Error styles (`.register-field--error`, `.register-field__error`) are present but inactive until backend validation exists.
