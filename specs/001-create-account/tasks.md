# Tasks 001 - Create Account

**Plan:** `specs/001-create-account/plan.md`
**Specification:** `specs/001-create-account/spec.md` (Draft for revision; re-approval pending)
**Status:** Approved — implementation in progress

## Phase 1: Baseline Evidence (Before Any Source Change)

- [ ] **T1.1 Capture the pre-change baseline at both widths**
  - **RF:** RF-12; **RNF:** RNF-5; **AC:** AC-11
  - Capture `http://localhost/pages/register.php` at 1440x900 and 402x874 through Chrome DevTools/CDP before editing any source file.
  - Done when: `specs/001-create-account/evidence/baseline-1440.png` and `baseline-402.png` exist, `evidence/baseline-notes.md` records the date, viewport, URL, and that no source file had been modified, and both images show the current static page.

## Phase 2: Persistence Foundation (Server Configuration, PDO, Test Helpers, CSRF)

- [ ] **T2.1 Centralize database configuration and build the PDO factory**
  - **RF:** RF-1 (persistence support); **RNF:** RNF-3
  - Write `tests/php/assert_foundation.php` first and confirm it fails because the foundation files do not exist. Add `includes/config.php`, `includes/database.php` with `profego_pdo()`, and convert `database/database.php` into the compatibility adapter (`require_once` the factory; assign `$pdo = profego_pdo()`). Make `assert_foundation.php` CLI-only with a guard returning HTTP 404 when `PHP_SAPI !== 'cli'`.
  - Done when: `php tests/php/assert_foundation.php` exits 0 and proves `profego_pdo()` returns a PDO connection with `utf8mb4`, executes `SELECT 1`, and the existing `database/database.php` include still yields the same connection; `php -l` passes for every touched PHP file; the script contains the CLI/404 guard; the transcript is saved as `evidence/layer0-foundation.txt`.

- [ ] **T2.2 Build the CLI-only test-support helpers**
  - **RF:** RF-1, RF-8 (verification support); **RNF:** RNF-7
  - Add `tests/php/test_support.php` with the RNF-7 per-run token generator, assertion counter, captured-identifier cleanup (profiles before accounts), account lookup, and `inspect`/`cleanup` CLI modes used after browser runs. Guard the file so direct HTTP access returns 404.
  - Done when: `php -l tests/php/test_support.php` passes; a recorded CLI smoke check shows two `per_run_token()` values differ and encode valid email/phone/Cédula shapes; static inspection confirms cleanup deletes `perfiles_docente` before `usuarios`; the HTTP guard is present; no rows are written.

- [ ] **T2.3 Implement the session-bound CSRF primitive**
  - **RF:** RF-9; **RNF:** RNF-1
  - Extend `tests/php/assert_foundation.php` first with CSRF expectations and confirm it fails before `includes/csrf.php` exists. Add `ensure_csrf_token()` and `submitted_csrf_is_valid()` using 32 random bytes rendered as 64 hex characters, `hash_equals`, non-string rejection, and no rotation. Keep the CLI/404 guard on the assertion script.
  - Done when: `php tests/php/assert_foundation.php` exits 0 and proves the token is 64 hex characters, stable across calls, accepted when matching, rejected when missing/malformed/foreign/non-string, and unchanged after repeated issuance; the transcript updates `evidence/layer0-foundation.txt`.

## Phase 3: Shared Validation and Role Logic (PHP and JS)

- [ ] **T3.1 Create the shared validation corpus**
  - **RF:** RF-4, RF-5, RF-6; **RNF:** RNF-6
  - Add `tests/fixtures/validation-cases.json` covering empty and whitespace values; name accents, ñ, punctuation-only, 30/31 characters, and a name containing digits; email formats, non-ASCII, 150/151; phone normalization, `+` equivalence, a `+` in a non-leading position (rejected), 6/7/20/21 digits; Cédula 4/5/20/21, spaces, underscores, hyphen-only; password 7/8/72/73 UTF-8 bytes, letters-only, digits-only, and accented characters counted as UTF-8 bytes at a boundary; confirmation mismatch; terms.
  - Done when: the JSON parses with both PHP and Node, every rule in the spec table has at least one positive and one negative case, expected normalized values and Spanish messages are recorded, and the case inventory is saved in `evidence/layer1-corpus.md`.

- [ ] **T3.2 Implement the PHP validator with its assertion script**
  - **RF:** RF-2, RF-4, RF-6, RF-8; **RNF:** RNF-2, RNF-6
  - Write `tests/php/assert_validation.php` first against `tests/fixtures/validation-cases.json` and confirm it fails before `includes/validation.php` exists. Limit the failing-first expectation to the validator rules and the role allow-list. Implement `validate_registration()`, `normalize_phone()`, the rule helpers, non-scalar handling, and the role allow-list; return `field => array of messages` in the fixed rule order. Make the script CLI-only with a guard returning HTTP 404 when `PHP_SAPI !== 'cli'`.
  - Done when: `php tests/php/assert_validation.php` exits 0 and proves every corpus case matches the expected normalized values and messages, the role allow-list accepts only `estudiante`/`docente` and rejects `admin`, unknown, and missing, and the script contains the CLI/404 guard; the transcript is saved as `evidence/layer1-php.txt`.

- [ ] **T3.3 Implement the JS validation module with `node --test`**
  - **RF:** RF-5, RF-6; **RNF:** RNF-6
  - Write `tests/js/register-validation.test.mjs` first and confirm it fails before the module exists. Add `js/package.json` (`type: module`) and `js/features/register-validation.js`, consuming the same JSON corpus and producing the same normalized values and messages as PHP.
  - Done when: `node --test tests/js/register-validation.test.mjs` reports a non-zero test count, all tests pass, exit code is 0, and the transcript is saved as `evidence/layer1-js.txt`.

- [ ] **T3.4 Implement the DOM-free role state machine with `node --test`**
  - **RF:** RF-2; **RNF:** RNF-4
  - Write `tests/js/register-role.test.mjs` first and confirm it fails before the module exists. Add `js/features/register-role.js` with the role constants, `nextRole()`, `roleLabel()`, and `roleAccessibleName()` implementing the exact strings from the Definitions section.
  - Done when: `node --test tests/js/register-role.test.mjs` reports a non-zero test count, all tests pass, exit code is 0, and the transcript is appended to `evidence/layer1-js.txt`.

## Phase 4: Registration Domain and Integration

- [ ] **T4.1 Implement the registration service and integration harness**
  - **RF:** RF-1, RF-3, RF-6 (uniqueness), RF-7, RF-8; **RNF:** RNF-2, RNF-3, RNF-7; **AC:** AC-01, AC-02, AC-05 (zero-row part), AC-08a
  - Write `tests/php/assert_registration.php` first and confirm it fails before `includes/registration_service.php` exists. Add `find_duplicate_fields()`, `register_account()`, the transaction, `PASSWORD_DEFAULT` hashing, the explicit `pendiente`/`0.00`/`0` tutor profile, SQLSTATE `23000` handling, and the CLI-only rollback seam. Add the password-hash round trip here: the stored value differs from the plaintext, verifies the correct password, and rejects an incorrect one. Make the script CLI-only with a guard returning HTTP 404 when `PHP_SAPI !== 'cli'`.
  - Done when: `php tests/php/assert_registration.php` exits 0 and proves: every writing scenario uses the RNF-7 per-run token; student and tutor persistence have every column, `activo`, non-null `fecha_registro`, lowercase email, digits-only phone, Cédula as typed, a verifying non-plaintext hash that rejects an incorrect password, and the `utf8mb4_unicode_ci` collation of `documento`; each duplicate and combined duplicates return one error per field with unchanged counts; case-insensitive email/Cédula and a collision path behave identically; the rollback seam leaves no account; the allowed-character SQLi payload is stored literally, read back literally through the application data path, and leaves unrelated rows unaffected; `finally` cleanup deletes profiles before accounts and asserts zero residue; writing tests do not run in parallel; the script contains the CLI/404 guard. Transcript: `evidence/layer2.txt`.

## Phase 5: Server-Rendered Registration Page

- [ ] **T5.1 Implement the server-rendered registration view**
  - **RF:** RF-4, RF-11, RF-14; **RNF:** RNF-3, RNF-4 (server markup); **AC:** AC-13
  - Add `includes/register_view.php` rendering the full document from a model: `method="post"` with a self action, the native submit control labeled "Crear Cuenta", exact labels, the `name`, `lastname`, `email`, `phone`, `id`, `password`, `password_confirm`, and `terms` controls, `*` markers, `required` and `aria-required`, role button with hidden `rol` defaulting to `estudiante`, CSRF hidden input, error containers, form-level `role="alert"`, exact meta description, a server-rendered visually hidden live-region span, and centralized escaping. The module script tag is added by T7.1; do not add it here.
  - Done when: `php -l` passes and a recorded CLI render of the default model contains the POST form and native submit control, all eight control names, all seven labels, the terms label, the role button and hidden `estudiante` value, the token field, the exact meta description, and the eight required states; a rendered test value containing `<script>` appears escaped; the render transcript is saved.

- [ ] **T5.2 Implement the request controller, thin entry point, and pipeline harness**
  - **RF:** RF-1, RF-2 (server allow-list), RF-4, RF-7, RF-11, RF-13, RF-15; **RNF:** RNF-1, RNF-3, RNF-7; **AC:** AC-06, AC-07, AC-14
  - Write `tests/php/assert_pipeline.php` first and confirm it fails before the controller exists. Add `includes/register_controller.php` and rewrite `pages/register.php` as a thin adapter. Implement non-field-first precedence (CSRF, then role), field validation, duplicate pre-check, transaction/PRG 303, one-time notice flag, field re-render with raw values/role/terms and empty passwords, and form-level-only presentation for non-field or database failures. Add the CLI render mode to `tests/php/test_support.php`: `php tests/php/test_support.php render --role=estudiante` (and `--role=docente`) prints the controller-rendered document for recorded evidence. Make `assert_pipeline.php` CLI-only with a guard returning HTTP 404 when `PHP_SAPI !== 'cli'`.
  - Done when: `php tests/php/assert_pipeline.php` exits 0 and proves: invalid/missing role and invalid CSRF each return 200 with the full AC-14 set and zero writes; a seeded account makes a real duplicate value, and a request with an invalid field plus that duplicate returns only field errors and no duplicate error; the forced database failure through `['fail_tutor_profile' => true]` in CLI returns 200 with the full AC-14 set, no partial account, and is not recorded as unverified; GET with query parameters performs no account/profile write; the token is unchanged before and after a successful registration; the success path returns 303 with `login.php?registro=ok`; the script contains the CLI/404 guard; `php tests/php/test_support.php render --role=estudiante` and `--role=docente` each print a complete document whose role signals match the requested role, and those outputs are saved as evidence. Use the per-run token, capture identifiers, clean up in `finally`, and assert zero residue. Transcript: `evidence/layer4-pipeline.txt`.

- [ ] **T5.3 Apply the registration styles and approved visual deviations**
  - **RF:** RF-4, RF-12; **RNF:** RNF-4 (focus), RNF-5; **AC:** AC-11 support
  - Update `css/register.css` only: role button reset/focus, required-marker support, error container styles with no opacity, `#b3261e` text (contrast ≥4.5:1 on white), 14 px desktop and 12 px mobile error text, form-level alert, and a visually hidden live region. Preserve existing spacing, typography, colors, and responsive rules.
  - Done when: browser checks at 1440 and 402 px show the role button, markers, and a server-generated field error without horizontal overflow, clipping, or overlap; long error messages wrap within the card at both widths without horizontal overflow; the computed error contrast against white is at least 4.5:1; mobile error text is at least 12 px; focus indicators remain visible; screenshots and measurements are saved in `evidence/layer3-notes.md`.

- [ ] **T5.4 Implement homepage tutor preselection**
  - **RF:** RF-2, RF-16; **RNF:** RNF-1; **AC:** AC-15
  - Update `includes/register_controller.php` for GET preselection handling with the amended RF-2 allow-list rules (single `rol=docente` or `rol=estudiante`; repeated, unknown, empty, or tampered values render `estudiante`) while leaving POST precedence unchanged; update `includes/register_view.php` to render the preselected role in the visible text, hidden value, accessible name, and live-region text; update `index.php` only to change the "Enseñar" anchor destination to `/pages/register.php?rol=docente`, preserving its classes, text, and styling. Do not change the other homepage actions.
  - Done when: browser click-through from the homepage "Enseñar" anchor shows `/pages/register.php?rol=docente` with visible text "Docente", hidden `docente`, the correct accessible name, live-region text for Docente, and no auto-submission or account creation; a fresh GET without preselection renders `estudiante`; explicit `?rol=estudiante` renders the same signals as the default; `?rol=admin`, an unknown value, a repeated `rol`, and an empty `rol` each render `estudiante` and write nothing; a NON-writing POST submission (invalid data or a duplicate) overrides the preselection in the re-render; after arriving via the homepage link with Docente unchanged, a field-error re-render keeps Docente (field errors force the re-render, no successful submission); the homepage styling is unchanged; a static diff confirms the header "Crear Cuenta", "Aprender", and login hrefs on `index.php` are unchanged; evidence is recorded in the evidence directory.

## Phase 6: Login Success Notice

- [ ] **T6.1 Implement the one-time login success notice**
  - **RF:** RF-10; **RNF:** RNF-7; **AC:** AC-09
  - Update `pages/login.php` to start the session, require both `registro=ok` and the unconsumed session flag, consume the flag only when the notice is rendered, and output the notice in a `role="status"` region. Add only `.login-notice` to `css/login.css`.
  - Done when: a browser sequence proves parameter plus flag shows the notice and consumes it; parameter without flag shows nothing; no parameter shows nothing and does not consume a present flag; refresh shows nothing and resubmits nothing; the notice is visible at 1440 and 402 px. Use per-run registration data, capture identifiers, clean up profiles before accounts in a failure-safe step, and assert zero residue.

## Phase 7: Progressive Enhancement

- [ ] **T7.1 Implement the client-side role control**
  - **RF:** RF-2; **RNF:** RNF-4; **AC:** AC-03
  - Add `js/features/register-form.js` importing `register-role.js`, and add the module script tag to `includes/register_view.php`. Implement the `type="button"` toggle: visible text, hidden `rol`, exact accessible name, and polite live announcement; never submit on activation and never expose `aria-pressed`.
  - Done when: browser runs at both widths show the initial `Estudiante` state; keyboard activation (Enter and Space) does not submit and updates text, hidden value, accessible name, and live-region text to `Docente`; a second activation returns all signals to `Estudiante`; the accessibility tree shows the button role and no pressed state; the console has no errors or warnings.

- [ ] **T7.2 Implement client-side validation and focus management**
  - **RF:** RF-5; **RNF:** RNF-4, RNF-8 (support); **AC:** AC-04
  - Extend `js/features/register-form.js` to validate on submit using `register-validation.js`, render one message per failing rule grouped by field, set `aria-invalid` and `aria-describedby`, focus the first invalid control, preserve entered values, flag an unchecked terms checkbox, and allow the native POST when the form is valid. Do not rely on `maxlength`.
  - Done when: browser runs at both widths show empty submit does not navigate, every field is flagged, and the first invalid field receives focus; a mixed invalid form shows one message per failing rule and preserves other values; unchecked terms is flagged and focused; a valid submission proceeds natively; a no-JavaScript submission still posts. Use per-run data, capture and clean up any created rows in `finally`, and assert zero residue.

## Phase 8: Real-HTTP Security Harness

- [ ] **T8.1 Build and run the direct HTTP security harness**
  - **RF:** RF-8 (response part), RF-9, RF-13, RF-15, RF-16 (no-JavaScript path); **RNF:** RNF-1, RNF-7; **AC:** AC-06, AC-07, AC-08a, AC-08b, AC-14
  - Write `tests/php/assert_http_security.php` using `ext-curl` with a cookie jar against `http://localhost/pages/register.php`. Cover a direct POST with empty and invalid values and zero writes; `rol=admin`, an unknown role, and a missing role with the full AC-14 set and zero writes; missing, malformed, and foreign CSRF tokens with the full AC-14 set and zero writes; the look-alike `fail_tutor_profile=1` field proven not to trigger the seam; the same token from two requests with different valid data accepted twice; concurrent identical submissions producing exactly one 303 and one duplicate 200 with one row; GET `/pages/register.php?rol=docente` followed by a native-form-equivalent POST of the rendered hidden `rol=docente` with otherwise valid data creating a `docente` account plus its pending tutor profile (no JavaScript involved; Option A); oversized values at the documented boundaries; a POST without `terms`; an allowed-character SQLi payload stored literally and read back through the application data path; an XSS payload rejected and escaped without unescaped angle brackets or an injected script element; and response inspection for password, hash, SQLSTATE/PDO text, paths, and the CSRF token outside the form response.
  - Done when: `php tests/php/assert_http_security.php` exits 0 with a printed assertion count; every writing scenario, including the no-JavaScript homepage-preselection run, uses the per-run token; profiles are deleted before accounts in `finally`; zero residue is asserted; separate test scripts are not run in parallel (the single controlled concurrency scenario is the only concurrent request pair); the script contains the CLI/404 guard and issues a real HTTP request proving every `tests/php/*.php` script returns 404; transcript: `evidence/layer4-http.txt`.

## Phase 9: Final Verification (Layers 0-4)

- [ ] **T9.1 Rerun Layers 0-2 and record final automated evidence**
  - **RF:** RF-1 through RF-8; **RNF:** RNF-2, RNF-3, RNF-6, RNF-7; **AC:** AC-01, AC-02, AC-05, AC-08a, AC-12
  - Run `php -l` over every touched PHP file, `node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs`, `php tests/php/assert_foundation.php`, `php tests/php/assert_validation.php`, and `php tests/php/assert_registration.php` in sequence, never in parallel. Inspect the change set for new dependencies, build steps, schema edits, client-side secrets including `csrf_token`, and Spanish/English conventions; inspect every SQL statement for parameterization, allowing only the fixed column identifiers in `find_duplicate_fields`; inspect that the rollback seam appears only in the service and tests and is CLI-gated; and inspect the CSRF implementation for 32 random bytes rendered as 64 hex characters, `hash_equals`, no rotation, and no token in client-side JavaScript.
  - Done when: every command exits 0, Node reports a non-zero test count, integration cleanup leaves zero residue, the static inspections pass and are recorded, and final transcripts are saved as `evidence/layer0.txt`, `evidence/layer1-js.txt`, `evidence/layer1-php.txt`, and `evidence/layer2.txt` with commands and exit codes.

- [ ] **T9.2 Run Layer 3 visual and accessibility verification at both widths**
  - **RF:** RF-2 (role surface), RF-4, RF-12, RF-14; **RNF:** RNF-4, RNF-5; **AC:** AC-11, AC-13
  - At 1440x900 and 402x874, compare against the baseline screenshots and the Figma reference; verify all seven text/password controls, labels, role control, terms, and submit action are visible per the spec definition (rendered, not clipped, not overlapping, reachable by vertical scrolling, without horizontal scrolling); verify eight `*` markers plus `required`/`aria-required`; capture the accessibility tree for the role control and a required/invalid control; confirm the role name, button role, no pressed state, error association, and visible focus indicators; measure error contrast and mobile font size; confirm long error messages wrap within the card without horizontal overflow; assert the meta description.
  - Done when: final screenshots, the accessibility capture (`evidence/layer3-a11y.json`), and measurements in `evidence/layer3-notes.md` show every check passed at both widths, including contrast ≥4.5:1, ≥12 px mobile error text, and long-error wrapping without horizontal overflow.

- [ ] **T9.3 Run Layer 3 functional browser flows and cleanup**
  - **RF:** RF-1, RF-2, RF-5, RF-7, RF-10, RF-11, RF-16; **RNF:** RNF-7, RNF-8; **AC:** AC-03, AC-04, AC-05 (browser part), AC-08b, AC-09, AC-10, AC-15
  - Drive the browser through role toggling, empty and mixed validation, duplicate email with JavaScript enabled, invalid and valid submissions with JavaScript unavailable (using a script-blocking CSP or equivalent override), the four notice cases, a valid Docente submission followed by a data-path read, duplicate combinations, post-submit normalization, a no-JavaScript student registration, and the XSS payload.
  - Homepage flow: from `http://localhost/`, activate the "Enseñar" control and assert registration opens with visible text "Docente", hidden `docente`, the accessible name "Tipo de cuenta: Docente, cambiar a Estudiante", live-region text for Docente, and no submission; confirm a fresh GET without preselection still renders `estudiante`, an unknown `rol` value renders `estudiante`, and a POST submission overrides the preselection.
  - Done when: every flow matches the spec, every browser registration uses the RNF-7 per-run token, the post-submit reads through the application data path show the stored values and pending tutor profile, the console has no errors or warnings in JavaScript-enabled runs (the expected CSP violation notice in the script-blocking run is recorded separately), every created identifier is captured, cleanup deletes profiles before accounts in a failure-safe step, and zero residue is asserted. Record results in `evidence/layer3-notes.md`.

- [ ] **T9.4 Rerun Layer 4 and inspect responses and the PHP error log**
  - **RF:** RF-8, RF-9, RF-13, RF-15, RF-16 (no-JavaScript path); **RNF:** RNF-1, RNF-2, RNF-7; **AC:** AC-06, AC-07, AC-14
  - Rerun `php tests/php/assert_pipeline.php` and `php tests/php/assert_http_security.php`. Request every `tests/php/*.php` script over HTTP and assert each returns 404, proving the CLI guard. Inspect every response for plaintext passwords, hashes, SQLSTATE/PDO messages, stack traces, paths, and the CSRF token outside the form response. Locate the PHP error log with `php -i`; inspect it if readable.
  - Done when: both scripts exit 0, every `tests/php/*.php` script returns 404 over real HTTP, response inspection is recorded as passed, cleanup leaves zero residue, and the log outcome is recorded. If the sink is unreadable, the "never logged" clause is recorded as unverified under Documented limitations and is not claimed as satisfied.

- [ ] **T9.5 Compile the final validation report**
  - **RF:** RF-1 through RF-16; **RNF:** RNF-1 through RNF-8; **AC:** AC-01 through AC-15
  - Map every acceptance criterion to its recorded evidence across Layers 0-4 at both widths, list every command and exit code, state any unverified non-AC clause and its reason, and confirm zero test residue and no new dependency, build step, or schema change.
  - Done when: `specs/001-create-account/evidence/validation-report.md` exists, every AC-01 through AC-15 is marked verified with a concrete evidence path from the final runs, no AC is left failed or unverified, all four layers and both viewport widths are represented, and any unverified non-AC clause (for example the log clause) is explicitly listed rather than claimed.

## Phase 10: Documentation and Handoff

- [ ] **T10.1 Update project state and finalize evidence pointers**
  - **RF:** RF-1 through RF-16 (state reporting); **RNF:** RNF-6
  - Update `MEMORY.md` with the implemented capability, approved decisions, and any unresolved or unverified clauses; keep it at or below 50 lines and free of credentials, tokens, and passwords. Finalize task statuses and evidence pointers in the validation report. After T9.5's report is complete, set `spec.md` status to `Implemented`. Do not modify `README.md` unless separately approved.
  - Done when: `MEMORY.md` reflects the implemented registration capability, the validation report links every evidence artifact, `spec.md` status is `Implemented`, no credential/token/password appears in either file, and no file outside the approved scope was changed by this task.
