# Project Memory

## SDD process (spec 001)

- Spec 001 (create-account) is back to `Draft` for revision. The Status line lives only in `spec.md` (lifecycle: Draft → Approved → Implemented); `plan.md` and `tasks.md` carry no status.
- Revision agreement: the spec keeps observable outcomes and approved constraints (no direct SQL client, verification through the application data path and a real browser, 1440/402 widths, no testing framework). Mechanisms (rollback seam, per-run token format, commands, scripts, evidence paths, CDP procedures) live in `plan.md`/`tasks.md`. Migration rule: if a detail can change without changing product behavior, it belongs to the plan.
- RF-2 is atomized into RF-2.1 (role control), RF-2.2 (hidden value / no-JS), RF-2.3 (GET preselection allow-list), RF-2.4 (POST allow-list). The spec's edge cases are illustrative and non-normative; acceptance criteria reference RF IDs instead of restating behavior.
- CSRF policy (session-stable, non-expiring, non-rotating) is documented with its accepted trade-off under RF-9; test-write and residue risks are documented under Documented limitations.
- Implementation per `tasks.md` has not started (all tasks unchecked); `tests/`, `includes/`, and the evidence directory do not exist yet. First task when resumed: T1.1 baseline captures.
- `docs/constitution.md` is empty; drafting it is deferred until after spec 001 re-approval.

## Main page

- `index.php` implements the Figma desktop and mobile homepage as static HTML; styles live in `css/main.css`, raster assets in `assets/images/`, social SVGs in `assets/icons/`, and self-hosted Inter in `assets/fonts/`.
- Responsive breakpoints: 1100px (hide steps image), 900px (mobile header and layout), 380px (compact CTA buttons).
- The "Comentarios de estudiantes" section is omitted until real testimonials exist.
- The "Enseñar" CTA will change to `/pages/register.php?rol=docente` in task T5.4; the other homepage actions keep their current destinations.
- `api/`, `includes/`, `js/features/`, and `js/shared/` remain placeholders for upcoming work.

## Login page

- `pages/login.php` implements the Figma desktop and mobile login screen as static HTML; styles live in `css/login.css` and reuse tokens/brand from `main.css`.
- No form functionality yet; the password-recovery link and the submit button are placeholders. Mobile breakpoint is 900px.

## Register page

- `pages/register.php` implements the Figma registration screen and now includes a prototype backend from an early commit: a POST insert into `usuarios` with `password_hash`, a session success flag, and a 303 redirect to `login.php?registro=ok`. It predates the approved plan and lacks CSRF, server validation, role handling, tutor-profile creation, and output escaping; task T5.2 rewrites it as a thin controller adapter, so the prototype is left untouched until then.
- The current form has a combined email/cédula field and no phone field; the spec adds phone and separates email/ID. Error styles in `css/register.css` are present but inactive until backend validation exists.
- Mobile breakpoint is 900px and keeps the design's scaled proportions.

## Environment

- Served by USBWebServer at `http://localhost/` (document root is the project folder).
- DB credentials are currently hardcoded in `database/database.php` (`root`/`usbw`); migrating them to `includes/config.php` is part of task T2.1.
