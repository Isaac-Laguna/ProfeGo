# Project Memory

## SDD process (spec 001)

- Spec 001 (create-account) is `Approved`. The Status line lives only in `spec.md` (lifecycle: Draft → Approved → Implemented); `plan.md` and `tasks.md` carry no status.
- Revision agreement: the spec keeps observable outcomes and approved constraints; mechanisms live in `plan.md`/`tasks.md`. Rule: if a detail can change without changing product behavior, it belongs in the plan.
- RF-2 is atomized into RF-2.1–RF-2.4; edge cases are illustrative; acceptance criteria reference RF IDs.
- CSRF policy (session-stable, non-expiring, non-rotating) is justified under RF-9; test-write/residue risks are documented under Documented limitations.
- Implementation: T1.1–T7.2 complete with recorded evidence (Layers 0–3). Remaining: T8.1 (HTTP security harness), T9.1–T9.5 (validation), T10.1 (handoff). Next task: T8.1.
- `docs/constitution.md` defines principles C1–C6, approved Nov 2026.

## Branch hygiene (feature/create-account)

- User decision: tolerate off-topic historical commits; apply best practices from now on. Moving them requires rewriting already-pushed history (force-push), so do NOT rewrite this branch.
- When the feature PR is opened, its description MUST disclose the commits that do not honor the branch and explain why they remain:
  - `22ea314 Readme` (global docs; `origin/chore/readme` already exists as separate work).
  - `3166faa Git Include` (repository hygiene markers).
  - `a649e51 docs: add commit conventions to AGENTS.md` (repo-wide governance).
  - `b07056b docs: adopt project constitution` (project-wide, not feature-specific).
  - `7666b4b Database connection` (pre-plan prototype, later replaced by T2.1/T5.2).
  - Non-conventional early messages: `098c445`, `0b9292e`, `b73c6a8`, `7edf0ad`, `208a114`, `8dca53e`.
- Cleanup branch `chore/remove-unused-placeholders` (`0d9a7f9`) removes empty placeholders; PR to `main` pending.

## Main page

- `index.php` implements the Figma desktop/mobile homepage; styles in `css/main.css`, assets in `assets/`, Inter self-hosted. Breakpoints: 1100/900/380px.
- "Enseñar" CTA points to `/pages/register.php?rol=docente` (T5.4 done); other homepage actions unchanged.

## Login page

- `pages/login.php` implements the Figma login screen; the form stays inert by design (authentication is out of scope). The one-time `role="status"` notice was added in T6.1; styles in `css/login.css`.

## Register page

- `pages/register.php` is a thin adapter; the prototype INSERT was removed in T5.2. Pipeline: `includes/register_controller.php`, view: `includes/register_view.php`, domain service: `includes/registration_service.php`.
- Validation (`includes/validation.php`) and CSRF (`includes/csrf.php`) are server-authoritative; browser enhancement: `js/features/register-form.js` with DOM-free modules `register-validation.js` and `register-role.js`.
- Error styles in `css/register.css` are active and contrast-compliant.

## Environment

- Served by USBWebServer at `http://localhost/` (document root is the project folder). PHP CLI: `C:\Users\isaac\Documents\USBWebServer\php\php.exe` (not on PATH).
- DB config centralized in `includes/config.php` with `PROFEGO_DB_*` overrides; `database/database.php` is a compatibility adapter.
- Local `profeGo` DB has one pre-existing all-empty prototype row in `usuarios`; test cleanup never touches it.
