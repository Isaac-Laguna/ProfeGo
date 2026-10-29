# Plan 001 - Create Account

**Specification:** `specs/001-create-account/spec.md` (Approved)
**Status:** Approved — implementation in progress

## Objective and constraints

Implement the create-account capability and the approved homepage tutor-preselection revision without changing the database schema, adding dependencies, or reinterpreting requirements. The stack is fixed: USBWebServer 8.6.5, Apache, PHP 8.1.3 (`pdo_mysql`, `session`, `mbstring`, `curl`), MySQL 5.7.36 with `utf8mb4_unicode_ci` and `STRICT_TRANS_TABLES`, and Node v24.21.0 with the built-in `node --test` runner. User-facing copy is Spanish; identifiers, filenames, comments, and technical documentation are English.

The plan follows the existing page-centric PHP structure. New code is split only where responsibilities differ: request handling, validation, persistence/domain work, rendering, and browser enhancement. No framework, router, container, ORM, AJAX endpoint, or build step is introduced.

## Architecture overview

```text
Homepage index.php "Enseñar"
  └─ GET /pages/register.php?rol=docente → tutor role preselected, no auto-submit

Browser
  └─ POST pages/register.php (self-target, native form POST, PRG 303)
       └─ includes/register_controller.php  build_registration_response()
            ├─ GET preselection allow-list (docente | estudiante; otherwise student)
            ├─ includes/csrf.php            session-bound token
            ├─ includes/validation.php      field rules + Spanish messages
            ├─ includes/registration_service.php
            │    ├─ duplicate pre-checks
            │    ├─ includes/database.php → includes/config.php → PDO
            │    └─ transaction: usuarios + perfiles_docente
            └─ includes/register_view.php   full HTML, escaped output
  └─ GET pages/login.php?registro=ok
       └─ one-time session flag → role="status" notice → flag consumed
```

`pages/register.php` is the only HTTP entry point for registration. `index.php` only links to it; its "Enseñar" anchor changes destination to `/pages/register.php?rol=docente`, while the header "Crear Cuenta", "Aprender", and login links keep their current destinations. `pages/register.php` starts the session, calls the same controller function used by the CLI harnesses, emits the returned status/headers, and echoes the returned body. The controller never reads superglobals directly; it receives `$server`, `$post`, `$query`, and a session array by reference. This keeps the web path and the CLI verification path identical.

## Files and responsibilities

| Path | Responsibility |
| --- | --- |
| `includes/config.php` | Return the database configuration array (DSN, user, password, PDO options). Values may be overridden by `PROFEGO_DB_*` environment variables; defaults mirror the existing USBWebServer local configuration. Server-side only. |
| `includes/database.php` | `profego_pdo(): PDO`; single static PDO factory using `includes/config.php`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`, `charset=utf8mb4`. |
| `database/database.php` | Preserve the existing include contract by delegating: `require_once __DIR__ . '/../includes/database.php'; $pdo = profego_pdo();`. No credentials duplicated. |
| `includes/csrf.php` | `ensure_csrf_token(array &$session)`, `submitted_csrf_is_valid(array $session, $submitted)`. 32 random bytes rendered as 64 hex characters; `hash_equals`; non-string submissions are rejected; token is stable, non-expiring, and never rotated. |
| `includes/validation.php` | Pure functions: `validate_registration(array $input): array` returning `['values' => normalized, 'errors' => [field => [Spanish messages]]]`, plus `normalize_phone()` and the rule helpers. One message per failing rule, grouped by field. No database access. |
| `includes/registration_service.php` | `find_duplicate_fields(PDO, array $values)`, `register_account(PDO, array $values, array $options = [])`. Uniqueness, password hashing, transaction, tutor profile, collision-to-duplicate mapping, and the CLI-only rollback seam. |
| `includes/register_controller.php` | `build_registration_response(array $server, array $post, array &$session, array $query = [], array $options = []): array` returning `['status' => int, 'headers' => array, 'body' => string]`. Enforces precedence, CSRF, role allow-list, GET preselection (including repeated/unknown values), POST override, PRG, session notice flag, and form-level presentation. Injects the allow-listed role into `values.role` before persistence. |
| `includes/register_view.php` | `render_register_page(array $model): string`. Full document, exact labels, required markers, role button, hidden `rol`, server-rendered live-region `<span>`, CSRF token, inline field errors, form-level `role="alert"`, meta description. Central escaping. The enhancement script only updates the live-region text. |
| `index.php` | Change only the homepage "Enseñar" anchor destination to `/pages/register.php?rol=docente`; preserve its classes, text, and visual styling. No other homepage behavior changes. |
| `pages/register.php` | Thin HTTP adapter: `session_start()`, require controller, call it with superglobals, emit status/headers, `exit` on redirect, echo body on 200. |
| `pages/login.php` | Start session; compute notice only when `registro=ok` and the unconsumed flag exist; consume only when shown; render `role="status"` notice. |
| `css/register.css` | Role button reset/focus, `*` marker support, field/error styles (no opacity, contrast-compliant red, 12 px mobile minimum), form-level alert, visually hidden live region. Preserve all existing layout rules. |
| `css/login.css` | Add the success-notice style only. No other login changes. |
| `js/package.json` | `{"type":"module"}` so Node treats the browser ES modules under `js/` as ESM during `node --test`. No dependency, no build step. |
| `js/features/register-validation.js` | DOM-free rules, normalization, and `validateRegistration(values)` returning `{ values, errors }` with the same messages as PHP. |
| `js/features/register-role.js` | DOM-free role constants, `nextRole()`, `roleLabel()`, and `roleAccessibleName()` implementing the exact accessible-name strings. |
| `js/features/register-form.js` | Progressive enhancement: role button clicks (text, hidden value, accessible name, polite live announcement), submit-time client validation, error rendering, `aria-invalid`, `aria-describedby`, and focus on the first invalid control. No AJAX. |
| `tests/js/register-validation.test.mjs` | `node --test` tests for field rules and normalization, driven by `tests/fixtures/validation-cases.json`. |
| `tests/js/register-role.test.mjs` | `node --test` tests for the role state machine, labels, and accessible names. |
| `tests/fixtures/validation-cases.json` | Single rule-case corpus consumed by both the JS tests and the PHP assertion script to prove client/server parity. |
| `tests/php/test_support.php` | CLI-only helpers: per-run token, assertion counter, captured-identifier cleanup, account lookup. Also a small CLI mode (`render`, `inspect`, `cleanup`) used for recorded renders and after browser runs. Returns 404 over HTTP. |
| `tests/php/assert_foundation.php` | CLI-only smoke and invariant checks for PDO connectivity and CSRF behavior. |
| `tests/php/assert_validation.php` | CLI-only parity checks against the same JSON corpus, plus the role allow-list. |
| `tests/php/assert_registration.php` | Layer 2 integration through the application data path: create, read back, duplicate, rollback, literal SQL storage, cleanup, zero residue. |
| `tests/php/assert_pipeline.php` | CLI-only Layer 4 controller-level checks: role and CSRF rejection, precedence, forced database failure, GET no-write, token stability. |
| `tests/php/assert_http_security.php` | CLI-only Layer 4 real-HTTP checks with `ext-curl`: direct role/CSRF attempts, rollback-seam attempt, two-tab token reuse, concurrent duplicate submission, cleanup. |
| `specs/001-create-account/evidence/` | Baseline screenshots, layer outputs, browser screenshots, accessibility captures, and `validation-report.md`. |

Dependency direction is one-way: `pages` → `register_controller` → `register_view` / `validation` / `registration_service` / `csrf` → `database` → `config`. Tests depend on application code, never the reverse. `js/features/register-form.js` imports the two DOM-free modules; the DOM-free modules import nothing.

## Requirements mapping

| Requirement | Implementation | Verification |
| --- | --- | --- |
| RF-1 successful registration | `register_controller` (PRG 303), `registration_service` (insert), `register_view` (`method="post"`, submit button, `name` fields) | Layers 2 and 3 |
| RF-2 role selection | `register_view` (`type="button"`, hidden `rol`, default `estudiante`, preselected signals), `register-role.js`, `register-form.js`, controller GET preselection and POST allow-list | Layer 3 for DOM/name/announcement and the GET preselection allow-list; Layers 1 and 4 for the submitted-role allow-list and missing-role rejection |
| RF-3 tutor profile and atomicity | `registration_service` transaction, explicit `pendiente`/`0.00`/`0`; CLI-only seam | Layer 2; gating in Layers 0 and 4 |
| RF-4 mandatory data and required state | `register_view` (`*` markers, `required`, `aria-required`, `novalidate`), `validation.php`, `register-validation.js` | Layers 1, 3, 4 |
| RF-5 client-side validation and focus | `register-validation.js` + `register-form.js` | Layers 1 (logic) and 3 (DOM) |
| RF-6 server validation rules | `validation.php`; uniqueness in `registration_service` | Layers 1, 2, 4 |
| RF-7 duplicate data | `find_duplicate_fields` (three parameterized probes), collision re-check | Layers 2, 3, 4 |
| RF-8 password confidentiality | `password_hash`/`password_verify`; no echoing/logging; empty password fields on render | Layers 2 (hash-only storage and round trip), 3 (repopulation), and 4 (responses and log) |
| RF-9 request authenticity | `includes/csrf.php`, controller check, hidden input, no rotation | Layer 4; static token inspection |
| RF-10 success hand-off | controller sets one-time session flag; `pages/login.php` requires parameter **and** flag; consumes only when shown | Layer 3 |
| RF-11 re-render after rejection | `register_view` model with raw values, preserved role/terms, empty passwords, associated messages | Layers 3 and 4 |
| RF-12 responsive presentation | Existing CSS preserved; only role/error/marker adjustments | Layer 3 at 1440 and 402 px |
| RF-13 safe repeated access | GET path performs no account/profile write; unique index + duplicate handling | Layer 4 |
| RF-14 meta description | `register_view` exact string | Layer 3 |
| RF-15 non-field presentation | controller returns only form-level `role="alert"`, preserves values/role/terms, issues token, no field errors | Layer 4 |
| RF-16 homepage tutor preselection | `index.php` "Enseñar" link, controller GET allow-list, `register_view` role signals | Layer 3 |
| RF-16 no-JavaScript submission via the homepage preselection | controller native POST path using the rendered hidden `rol` | Layer 4 |
| RNF-1 server-side security | Controller allows only two roles; server validation; CSRF; secrets only in server config; token only in form response | Layers 0 and 4 |
| RNF-2 credential protection | Hash-only storage and verification | Layers 2 and 4 |
| RNF-3 safe output and data handling | `htmlspecialchars` in view; prepared statements everywhere; no input concatenated into SQL | Layers 0, 2, 3, 4 |
| RNF-4 accessibility | Role button keyboard/name/no pressed state; live region; `required`/`aria-required`; error association; focused first invalid; focus outlines | Layer 3 |
| RNF-5 visual and responsive integrity | Existing tokens/spacing; error text `#b3261e`, no opacity, 14 px desktop / 12 px mobile; form-level alert | Layer 3 |
| RNF-6 maintainability and conventions | No dependency/build/schema change; PHP/JS rule parity; Spanish copy; English code | Layers 0 and 1 |
| RNF-7 data integrity and test isolation | Per-run tokens, captured identifiers, `finally` cleanup, profiles before accounts, no truncation, no parallel writes | Layers 2, 3, and 4 |
| RNF-8 compatibility and degradation | Classic POST; module script optional; no-JS path | Layer 3 |

Acceptance criterion mapping: every criterion keeps the layer assigned in the specification. AC-15 (homepage "Enseñar" opens registration with Docente preselected, no submission) is proved in Layer 3 by the click-through step above and by task T5.4.

## Persistence and data path

- `includes/config.php` returns:

```php
[
    'dsn' => 'mysql:host=localhost;dbname=profeGo;charset=utf8mb4',
    'user' => <env or local default>,
    'password' => <env or local default>,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
]
```

`ProfeGo.sql` declares the database as `ProfeGo`, while the committed/previous version of `database/database.php` used the `profeGo` spelling in its DSN. The plan keeps that existing `profeGo` spelling in the DSN; MySQL on Windows compares database names case-insensitively. A case-sensitive deployment would require aligning the DSN to the actual database name. The SQL file is not modified. No credential is echoed, logged, documented, or placed in client code. The existing `database/database.php` becomes a compatibility adapter so any old include still receives `$pdo`.

- Account insert uses exactly the schema columns: `nombre`, `apellido`, `correo`, `telefono`, `documento`, `password_hash`, `rol`. `estado` and `fecha_registro` use their database defaults. No schema change.
- Tutor insert uses `id_docente`, `estado_verificacion='pendiente'`, `calificacion_prom=0.00`, `total_tutoria=0`.
- The integration script and browser post-checks call `profego_pdo()` and the same `register_account()` function used by the controller. No external SQL client and no ad-hoc PDO connection are used to claim persistence.

## Validation and messages

`validate_registration()` returns one message per failing rule, grouped by field in a fixed rule order (required/empty, length/bytes, character composition/content, format, confirmation). A field with several failures therefore shows several specific messages, all associated with that field. All error maps use `field => array of messages`; duplicate checks contribute one message per duplicated field. The client and server use the same order and messages.

| Field | Rules | Messages |
| --- | --- | --- |
| `name`, `lastname` | 1-30 characters after trim; Unicode letters, spaces, hyphens, apostrophes; at least one letter | `Ingresa tu nombre.` / `Ingresa tu apellido.`; `El nombre debe tener entre 1 y 30 caracteres.` / `El apellido debe tener entre 1 y 30 caracteres.`; `El nombre solo puede contener letras, espacios, guiones y apóstrofes.` / `El apellido solo puede contener letras, espacios, guiones y apóstrofes.`; `El nombre debe incluir al menos una letra.` / `El apellido debe incluir al menos una letra.` |
| `email` | `FILTER_VALIDATE_EMAIL`; ASCII only; at most 150; lowercase; unique | `Ingresa tu correo electrónico.`; `El correo electrónico no es válido.`; `El correo electrónico debe tener como máximo 150 caracteres.`; duplicate: `Este correo electrónico ya está registrado.` |
| `phone` | Optional single leading `+`; spaces, hyphens, parentheses allowed; 7-20 digits after normalization; unique | `Ingresa tu teléfono.`; `El teléfono debe tener entre 7 y 20 dígitos.`; duplicate: `Este teléfono ya está registrado.` |
| `id` | 5-20 characters after trim; ASCII letters, digits, hyphens; at least one alphanumeric; stored as typed; unique case-insensitively | `Ingresa tu cédula o ID.`; `La cédula o ID debe tener entre 5 y 20 caracteres.`; `La cédula o ID solo puede contener letras, números y guiones.`; duplicate: `Esta cédula o ID ya está registrada.` |
| `password` | 8-72 UTF-8 bytes; at least one ASCII letter and one ASCII digit; never trimmed | `Ingresa una contraseña.`; `La contraseña debe tener entre 8 y 72 caracteres.`; `La contraseña debe incluir al menos una letra y un número.` |
| `password_confirm` | Exactly equal to `password` | `Confirma tu contraseña.`; `Las contraseñas no coinciden.` |
| `terms` | Checked | `Debes aceptar los términos y condiciones.` |

Bytes are measured with `TextEncoder`/`strlen` (not `maxlength`). Name length uses code points (`[...value].length` / `mb_strlen`). Non-scalar submitted values are treated as empty rather than causing a type error. The client applies the shared ASCII structural pattern; the server applies that pattern plus `FILTER_VALIDATE_EMAIL`, so it may reject a value the client accepted. That is intentional: the server is authoritative, and the rejection is surfaced as a field error after re-render. The shared parity corpus contains cases where both rules agree; cases where `FILTER_VALIDATE_EMAIL` is stricter are asserted on the server only. Uniqueness is never checked on the client.

The user-facing password message says "caracteres" for readability, while both validators enforce the bound in UTF-8 bytes. The tests assert the byte boundaries (7/8/72/73) and the accented-character case so the message wording does not weaken the rule.

## Request lifecycle and controller algorithm

### GET

1. `session_start()` in `pages/register.php`.
2. `ensure_csrf_token($_SESSION)`.
3. Read a single scalar `rol` from the query string. If the raw query repeats `rol`, or the value is not exactly `estudiante` or `docente`, treat the preselection as invalid and use `estudiante`.
4. Build the model: role = the allow-listed preselection or `estudiante`; empty raw values, no errors, terms unchecked, valid token, and the matching live-region text.
5. Return `200` with the rendered document.

No account or tutor-profile write occurs. Preselection never writes to the database and never auto-submits. Session/token initialization is permitted by RF-13.

### POST

```php
build_registration_response(server, post, session, query, options):
  if server.method != POST:
      ensure token
      # GET preselection allow-list; no unconditional default render
      if single scalar query.rol exists and query.rol == 'docente':
          role = 'docente'
      else if single scalar query.rol exists and query.rol == 'estudiante':
          role = 'estudiante'
      else:
          # repeated, unknown, empty, or absent rol
          role = 'estudiante'
      return 200 render(raw=empty values, role=role, terms unchecked,
                        fieldErrors={}, formError=null,
                        csrf=ensure_csrf_token(session), emptyPasswords=false,
                        liveRegion=role)

  raw = pick(post, [name, lastname, email, phone, id, password, password_confirm])
  submittedTerms = exists(post.terms)
  submittedRole = post.rol ?? null

  # 1. Non-field failures: evaluated before field validation and duplicates
  if not submitted_csrf_is_valid(session, post.csrf_token):
      return form_level_error(raw, submittedRole, submittedTerms, session)
  if submittedRole is not one of estudiante/docente:
      return form_level_error(raw, submittedRole, submittedTerms, session)
  # Other malformed request data is not trusted; role is the only shape check here.

  # 2. Field validation
  validated = validate_registration(post)
  if validated.errors not empty:
      return 200 render(raw, role=submittedRole, terms=submittedTerms,
                        fieldErrors=validated.errors, formError=null,
                        csrf=ensure_csrf_token(session), emptyPasswords=true)

  # The controller injects the allow-listed POST role; the service never reads
  # the request and uses only values.role for account creation.
  validated.values.role = submittedRole

  # 3. Duplicate pre-checks, only after every field rule passes; any database
  #    failure at this stage or during persistence becomes a non-field failure.
  try:
      duplicates = find_duplicate_fields(profego_pdo(), validated.values)
      if duplicates not empty:
          return 200 render(raw, role, terms, fieldErrors=duplicates, formError=null,
                            csrf=..., emptyPasswords=true)

      # 4. Persist atomically
      result = register_account(profego_pdo(), validated.values, options)
      if result.ok:
          session.registration_notice = true
          return 303 { Location: login.php?registro=ok }
      # a unique collision surfaced by the service
      if result.duplicates not empty:
          return 200 render(raw, role, terms, fieldErrors=result.duplicates, ...)
      return form_level_error(raw, submittedRole, submittedTerms, session)
  catch Throwable:
      # never expose the exception message, SQLSTATE, stack trace, or paths
      return form_level_error(raw, submittedRole, submittedTerms, session)

form_level_error(raw, submittedRole, submittedTerms, session):
  role = submittedRole in {estudiante, docente} ? submittedRole : 'estudiante'
  return 200 render(raw, role, submittedTerms,
                    fieldErrors={}, formError=<generic Spanish message>,
                    csrf=ensure_csrf_token(session), emptyPasswords=true)
```

The generic form-level message is: `No se pudo completar el registro. Inténtalo de nuevo.` It does not name CSRF, roles, the database, or any internal cause. Only this message uses `role="alert"`; field and duplicate errors are field-associated and never accompanied by the form-level alert.

### Service algorithm

```php
find_duplicate_fields(pdo, values):
  duplicates = {}
  for (field, column, value) in [
      (email, correo, values.email),
      (phone, telefono, values.phone),
      (id, documento, values.id),
  ]:
      stmt = pdo.prepare("SELECT 1 FROM usuarios WHERE {$column} = :value LIMIT 1")
      # column is a fixed internal literal, never user input
      stmt.execute({value})
      if stmt.fetch(): duplicates[field] = duplicate message
  return duplicates

register_account(pdo, values, options):
  # values.role is set by the controller to the allow-listed POST role; the
  # service never reads request data or the GET preselection.
  hash = password_hash(values.password, PASSWORD_DEFAULT)
  pdo.beginTransaction()
  try:
      insert usuarios (nombre, apellido, correo, telefono, documento, password_hash, rol)
          values (..., hash, values.role)
      id = lastInsertId()
      if values.role == 'docente':
          if options.fail_tutor_profile and PHP_SAPI == 'cli':
              throw RuntimeException('forced tutor profile failure')
          insert perfiles_docente (id_docente, estado_verificacion, calificacion_prom, total_tutoria)
              values (id, 'pendiente', 0.00, 0)
      pdo.commit()
      return {ok: true, id: id}
  catch PDOException e:
      pdo.rollback()
      if e.code == '23000':
          duplicates = find_duplicate_fields(pdo, values)
          if duplicates not empty: return {ok: false, duplicates: duplicates}
      throw
  catch Throwable:
      pdo.rollback()
      throw
```

The rollback seam is an `$options` argument, never a request field. The service additionally checks `PHP_SAPI === 'cli'`, so even accidental invocation through the web returns normal behavior. Static inspection confirms it is referenced only by tests and the service; the direct HTTP attempt proves request data cannot enable it. The controller is the only place that turns a request role into `values.role`; the service writes that allow-listed value and nothing from the GET preselection.

## Role control and accessibility

- Markup: `<button type="button" class="register-role" id="register-role" aria-label="Tipo de cuenta: Estudiante, cambiar a Docente">Estudiante</button>` plus `<input type="hidden" name="rol" id="register-role-value" value="estudiante">`.
- No `aria-pressed`; the state is carried by visible text, hidden value, and accessible name.
- `register-role.js` owns `ROLE_ORDER`, `nextRole()`, `roleLabel()`, and `roleAccessibleName()`. The exact names are `Tipo de cuenta: Estudiante, cambiar a Docente` and `Tipo de cuenta: Docente, cambiar a Estudiante`.
- The server-rendered view outputs the visually hidden `<span role="status" aria-live="polite">` with the current role text. `register-form.js` only updates that span's text (to `Tipo de cuenta seleccionado: Docente.` or `Tipo de cuenta seleccionado: Estudiante.`) and never creates the live region. Native button activation handles Enter, Space, and click.
- Server-rendered role text, hidden value, `aria-label`, and live-region text always derive from the model role. A GET allow-listed preselection initializes that role; a POST role overrides it. A field or duplicate re-render preserves the submitted valid role. A non-field re-render preserves a valid role and otherwise renders `estudiante`.
- Required state: every text/password input and the terms checkbox have `required` and `aria-required="true"`. The visible label text includes `*`. No `maxlength` is used as the only bound.
- Error association: each field with errors has one error container `<div id="register-<field>-error">` holding one `<p>` per failing rule; the control gets `aria-invalid="true"` and `aria-describedby` pointing to the container. Stable ids include `register-email-error`, `register-password-confirm-error`, and `register-terms-error`. The server adds `autofocus` to the first invalid control in a field/duplicate re-render; the client focuses the first invalid control before preventing submission. Focus outlines remain the existing 3 px primary outline.

## Login notice

`pages/login.php` starts the session and computes:

```php
show = (query.registro === 'ok') && !empty(session.registration_notice)
if show: unset(session.registration_notice)
```

- With both conditions: render `<p class="login-notice" role="status">Tu cuenta se creó correctamente. Ya puedes iniciar sesión.</p>` and consume the flag.
- With the parameter but no flag: no notice, no consumption (nothing to consume).
- Without the parameter: no notice and the flag, if present, is left intact.
- Refresh after the redirect: the flag is already consumed, so the notice disappears; the browser performs a GET, so no registration is resubmitted.
- `css/login.css` adds only `.login-notice` with a success color meeting normal-text contrast; layout is otherwise unchanged.

## Interface contract

| Element | Value |
| --- | --- |
| Form | `method="post"`, `action="register.php"`, `novalidate` (custom Spanish messages; server and script enforce) |
| POST target | `http://localhost/pages/register.php` (self) |
| Homepage "Enseñar" | `/pages/register.php?rol=docente`; classes, text, and styling unchanged |
| Preselection parameter | Single `rol=estudiante` or `rol=docente`; repeated, unknown, or empty values render `estudiante` |
| Fields | `name`, `lastname`, `email`, `phone`, `id`, `password`, `password_confirm`, `terms`, `rol`, `csrf_token` |
| Labels | `Nombre*`, `Apellido*`, `Correo electrónico*`, `Teléfono*`, `Cédula/ID*`, `Crear contraseña*`, `Confirmar contraseña*` |
| Terms label | `Acepto los términos y condiciones y la Política de Privacidad.*` (existing links preserved) |
| Role values | `estudiante` (default) and `docente`; hidden input always present; GET preselection is allow-listed and POST overrides it |
| Submit | `<button type="submit" class="register-submit">Crear Cuenta</button>` |
| Success redirect | `303 Location: login.php?registro=ok` |
| Meta description | `Regístrate en ProfeGo como estudiante o docente.` |
| Error IDs | `register-name-error`, `register-lastname-error`, `register-email-error`, `register-phone-error`, `register-id-error`, `register-password-error`, `register-password-confirm-error`, `register-terms-error` |
| Scripts | `<script type="module" src="../js/features/register-form.js"></script>` |

## Technical decisions

1. **Single PDO factory and shared service for web, integration, and post-submit reads.**
   `profego_pdo()` is the only connection constructor; `register_account()` is the only account-creation path. Integration and browser data checks call this code, so a successful read proves the application's own path.
   *Rejected:* constructing a separate PDO in tests (would not prove the web path), using an external SQL client (unavailable and disallowed), or asserting persistence from the success page (interface-only evidence).

2. **Controller function returns status, headers, and rendered body.**
   This makes the same pipeline callable from CLI without HTTP. `pages/register.php` is a thin emitter; `assert_pipeline.php` calls `build_registration_response()` with simulated request/session arrays and inspects the produced status and HTML regions. The database-failure presentation is therefore forced and captured, not inferred.
   *Rejected:* duplicating validation in a test harness (would not test the real controller), testing only `register_account()` (would not prove RF-15/AC-14), or a JSON/AJAX endpoint (unapproved architecture and would break the no-JS path).

3. **Rollback seam as an `$options` argument gated by `PHP_SAPI === 'cli'`.**
   Tests force the tutor-profile insert to fail after the account insert; the controller never forwards request data into options. Static inspection and a direct HTTP POST containing a look-alike field prove it cannot be triggered remotely.
   *Rejected:* a hidden query parameter or form field (remotely reachable), an environment toggle (harder to prove), or relying on an actual missing table (would modify schema).

4. **Native PRG with a 303 redirect and a one-time session flag.**
   303 makes the POST-to-GET transition explicit. The flag lives in the registration/login session already authorized by the spec. The login page requires both the query parameter and the flag, so a typed URL or a refresh cannot replay the notice.
   *Rejected:* rendering the notice on the registration response (no PRG), a 302 redirect (less precise for POST), or trusting the query string alone (replayable).

5. **DOM-free ES modules with Node tests, plus a scoped `js/package.json`.**
   `register-validation.js` and `register-role.js` contain no DOM access, so they run under `node --test` and in the browser. `js/package.json` (`type: module`) makes `.js` ESM in Node without a bundler. The DOM controller imports the same modules the browser loads.
   *Rejected:* a bundler/build step (prohibited), `.mjs` files served by Apache (the USBWebServer MIME configuration maps only `.js` to JavaScript), or a global-script pattern (global variables and weaker testability).

6. **One JSON corpus for rule parity.**
   Both `register-validation.test.mjs` and `assert_validation.php` read `tests/fixtures/validation-cases.json`. A divergence in rules or messages fails at least one layer.
   *Rejected:* duplicated inline case lists (drift risk) or server-only tests (no client parity evidence).

7. **Evidence under `specs/001-create-account/evidence/`.**
   Baseline and final screenshots, layer transcripts, accessibility captures, and the validation report stay with the specification they prove.
   *Rejected:* temporary directories (not durable), repository root (clutter), or relying on chat output (not reviewable).

8. **No `api/` endpoint.**
   The form posts natively and the server re-renders. This satisfies progressive enhancement and the spec's explicit rejection of JavaScript-only flows.
   *Rejected:* fetch/AJAX with JSON (would require a second contract, break no-JS, and add unapproved architecture).

9. **GET preselection through a single allow-listed query value.**
   The homepage "Enseñar" link uses `?rol=docente`; the controller accepts only a single scalar `docente` or `estudiante`, treats repeated/unknown/empty values as student, and renders the role signals server-side. The preselection is not persisted in the session, never auto-submits, and is overridden by the POST role before validation and persistence.
   *Rejected:* a session flag (would persist stale state and complicate concurrent tabs), a JavaScript-only default (breaks the no-JS path and gives no server-rendered preselection), or trusting the query value without an allow-list (violates RF-2/RNF-1).

10. **Password messages keep "caracteres" while enforcing UTF-8 bytes.**
    "Caracteres" is the readable Spanish term for users; both validators still enforce the 8–72 UTF-8 byte bound and test byte boundaries. The wording change does not weaken the rule.
    *Rejected:* exposing "bytes" in user-facing copy (less clear in Spanish) or switching to a character-count rule (would change the approved requirement).

11. **Keep `novalidate`.**
    Native browser validation messages are replaced by the custom Spanish, field-associated messages from the JS and server. `novalidate` prevents native bubbles from competing with the custom presentation; server-side and JS validation remain authoritative.
    *Rejected:* relying on native browser messages (inconsistent language and presentation, not server-authoritative) or removing the attribute without replacing native behavior (could bypass custom messaging).

## Date-dependent logic

None. `fecha_registro` is produced by the database default `CURRENT_TIMESTAMP`; no PHP date calculation exists. The per-run test token includes a timestamp for uniqueness only, not product behavior. No `today` parameter is needed.

## Testing strategy

All writing tests use the RNF-7 per-run token: `getmypid() . '-' . time() . '-' . bin2hex(random_bytes(4))`, encoded as a unique email local part, digits only for phone, and an allowed-character Cédula/ID. Names receive a short suffix that stays within 30 characters. Every script captures the generated identifiers, deletes `perfiles_docente` rows before `usuarios` rows in a `finally` block, and asserts zero residue by those identifiers. Tables are never truncated and separate test scripts never run in parallel. The two concurrent requests in the Layer 4 duplicate scenario are one controlled scenario inside a single script, not parallel test execution. Every script under `tests/php/` carries a CLI guard that returns HTTP 404 when `PHP_SAPI !== 'cli'`; a real HTTP check of that guard is part of Layer 4 verification.

Evidence is written to `specs/001-create-account/evidence/`:

- `baseline-1440.png`, `baseline-402.png` (captured before any source change)
- `baseline-notes.md` (capture date, viewport, and URL)
- `layer0.txt`, `layer0-foundation.txt`, `layer1-js.txt`, `layer1-php.txt`, `layer2.txt`, `layer4-pipeline.txt`, `layer4-http.txt`
- `layer3-1440.png`, `layer3-402.png`, `layer3-a11y.json`, `layer3-notes.md`
- `validation-report.md` (final mapping of every AC to verified/unverified evidence)

### Layer 0 - Syntax and static checks

```powershell
Get-ChildItem -Path includes,pages,database,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l index.php
node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs
```

- Expected: every `php -l` reports `No syntax errors detected` and exit code 0; `node --test` reports a non-zero test count and exit code 0. A zero-test run is not accepted.
- Inspect the change set for new dependencies, build steps, schema edits, and client-side secrets (including `csrf_token`).
- Confirm Spanish user-facing copy and English identifiers, filenames, comments, and technical documentation.
- Inspect every SQL statement: only `prepare()` with bound parameters is allowed; the only interpolated identifiers are the fixed column names inside `find_duplicate_fields`.
- Inspect the rollback seam: `fail_tutor_profile` appears only in `registration_service.php` and tests, and is guarded by `PHP_SAPI === 'cli'`.
- Record the transcript in `evidence/layer0.txt`.

### Layer 1 - Unit tests without a database

```powershell
node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs
php tests/php/assert_validation.php
```

- The JS suite covers empty and whitespace values, name accents/ñ/punctuation-only, 30/31 length, email formats/non-ASCII/150/151, phone normalization/`+` equivalence/6/7/20/21, Cédula 4/5/20/21, password 7/8/72/73 UTF-8 bytes and composition, confirmation mismatch, and terms.
- `assert_validation.php` reads the same JSON corpus, asserts identical normalized values and messages, and checks the role allow-list (`estudiante`, `docente`, `admin`, unknown, missing). It prints the assertion count and exits non-zero on failure. The password-hash round trip is proved in `assert_registration.php` (T4.1).
- Evidence: `layer1-js.txt`, `layer1-php.txt`.

### Layer 2 - Integration through the application data path

```powershell
php tests/php/assert_registration.php
```

The script uses `profego_pdo()` and `register_account()` and asserts:

- A student registration creates exactly one row; reading it back by `lastInsertId()` returns the normalized name/lastname, lowercase email, digits-only phone, Cédula as typed, role `estudiante`, state `activo`, non-null `fecha_registro`, and a hash that verifies the plaintext and is not the plaintext.
- A tutor registration creates one account plus one `perfiles_docente` row with `pendiente`, `0.00`, and `0`.
- Each duplicate field, and a combination of duplicates with all other rules passing, returns one domain error per duplicated field, no partial row, and unchanged counts. Case-insensitive email/Cédula duplication and a simulated unique collision behave like application-level duplicates.
- The rollback seam (`['fail_tutor_profile' => true]`) leaves no account.
- The allowed-character SQL-injection payload in the name is stored literally and read back; unrelated rows are unaffected.
- Cleanup runs in `finally`, deletes profiles before accounts, and prints zero-residue assertions by captured identifiers.
- Evidence: `layer2.txt`.

### Layer 3 - Browser end-to-end

Using the Chrome DevTools tools against `http://localhost/pages/register.php` (document root is the project folder; `/ProfeGo/...` is not the entry point):

1. `emulate` viewport `1440x900x1` and `402x874x1,mobile,touch`; `take_screenshot` into the evidence directory at each width. Compare against both the pre-change baseline screenshots and the Figma reference.
2. From `http://localhost/`, activate the "Enseñar" control; assert registration opens with visible text `Docente`, hidden `docente`, accessible name `Tipo de cuenta: Docente, cambiar a Estudiante`, live-region text for Docente, and no automatic submission or account creation.
   - GET allow-list checks: request `?rol=admin`, an unknown value, a repeated `rol`, an empty `rol`, and an explicit `?rol=estudiante`; assert each renders the student signals (visible text `Estudiante`, hidden `estudiante`, accessible name `Tipo de cuenta: Estudiante, cambiar a Docente`, student live-region text) and performs no account or tutor-profile write. Tasks T5.4 and T9.3 exercise these checks.
3. `take_snapshot` for the accessibility tree, supplemented by `evaluate_script` attribute checks. Confirm: role control name, button role, no pressed state; all eight required controls expose `required` and `aria-required`; `*` markers visible; error association after an invalid submit; visible focus outlines.
4. Role: on a fresh GET without preselection, initial text `Estudiante`, hidden `estudiante`, name `Tipo de cuenta: Estudiante, cambiar a Docente`; from the homepage link, initial text is `Docente` with the matching signals. Activate with `press_key` (Tab/Enter and Space): no submission; from student, text/name/value switch to Docente and the live region updates; from preselected Docente, activation returns them to student. Use `evaluate_script` to read the hidden value and live-region text.
5. Empty submit: no navigation; every field is flagged; `document.activeElement` is the first invalid control.
6. Mixed invalid form: one message per failing rule, grouped by field; focus on the first invalid field; preservation of the other values.
7. JS-enabled duplicate: first create one student account with the same form, then select Docente, check terms, and submit a second valid form with that existing email; assert status 200, raw values, role and terms preserved, empty passwords, associated Spanish errors, no technical detail.
8. JS-unavailable run: open a separate page with `emulate` `extraHttpHeaders: {"Content-Security-Policy":"script-src 'none'"}` (or an equivalent CDP script-disable override). Submit an invalid form and assert the server re-render; submit a valid student form and assert the account is created. Clear the header afterwards.
9. Success: complete a valid student registration, observe `login.php?registro=ok`, verify the `role="status"` notice at both widths, then verify the four notice cases (parameter+flag shows and consumes; parameter without flag shows nothing; no parameter shows nothing and leaves a flag; refresh hides).
10. Tutor: toggle Docente, submit, observe the redirect, then run `php tests/php/test_support.php inspect --email=<unique>` and assert `rol='docente'` plus a pending profile.
11. Duplicate email and combined duplicates in the browser: create the first accounts, then submit duplicates and assert the RF-11 re-render with the associated field errors and zero new accounts.
12. Post-submit values: `php tests/php/test_support.php inspect --email=<unique>` confirms lowercase email, digits-only phone, and Cédula as typed.
13. No-JS student registration (step 8) confirms AC-10.
14. XSS payload: submit with JavaScript unavailable so the server renders the rejection; inspect the DOM for inert escaped text and no script execution.
15. Meta description via `evaluate_script`: exactly `Regístrate en ProfeGo como estudiante o docente.`
16. Contrast: `evaluate_script` reads the computed error color and font size; compute the ratio against white (must be ≥4.5:1) and confirm ≥12 px at 402.
17. Long error messages: force a multi-line error state and assert it wraps within the card at both widths without horizontal overflow, clipping, or overlap, and remains within the card.
18. Console: in JavaScript-enabled runs, `list_console_messages` reports no errors or warnings. In the script-blocking CSP run, record the expected CSP violation notice separately and assert there are no other console issues.
19. Capture final screenshots and the accessibility JSON. Collect every created email/ID; clean up with `php tests/php/test_support.php cleanup --email=<unique>` (profiles before accounts) and assert zero rows remain.

No AJAX is used; every submission is the native form. The CSP header is an emulation of JavaScript unavailability, not a product change.

### Layer 4 - Negative and security checks

```powershell
php tests/php/assert_pipeline.php
php tests/php/assert_http_security.php
```

`assert_pipeline.php` calls `build_registration_response()` with simulated requests and asserts:

- Invalid role (`admin`, unknown, missing) → 200, only form-level `role="alert"`, no field errors, raw non-password values and terms preserved, role preserved when valid/student otherwise, valid token, zero writes, no technical detail.
- Missing, malformed, and foreign CSRF tokens → the same full AC-14 set, zero writes, valid token in the response.
- Field validation precedes duplicate checks: a request with an invalid field and an otherwise-duplicate value returns only field errors.
- Forced database failure via `['fail_tutor_profile' => true]` in CLI → 200, full AC-14 set, no partial account, no crash. This case is not recorded as unverified.
- GET with query parameters → no account or tutor-profile write.
- Token comparison before and after a successful call → unchanged; reuse accepted.
- Evidence: `layer4-pipeline.txt`.

`assert_http_security.php` uses `ext-curl` with a cookie jar and asserts:

- A direct HTTP POST with empty and invalid values is rejected with zero writes.
- A direct HTTP POST with a look-alike `fail_tutor_profile=1` field and otherwise valid Docente data is not treated as the seam: it follows normal registration (account plus pending profile), then cleans up. This proves the seam is unreachable over HTTP.
- The same token from two requests with different valid data → both accepted (303), two accounts under the same session.
- Two concurrent requests with the same valid data and token → exactly one 303 success, the other 200 duplicate re-render, and exactly one row.
- Role/CSRF direct HTTP attempts return 200 with the form-level alert and no writes.
- A no-JavaScript-equivalent native submission after GET `/pages/register.php?rol=docente` posts the rendered hidden `rol=docente` with otherwise valid data and creates a `docente` account plus its pending tutor profile (Option A). This is a writing scenario: it uses the RNF-7 per-run token, captures the identifiers, deletes profiles before accounts in `finally`, and asserts zero residue.
- Oversized values at the documented boundaries (150/151 email, 30/31 name, 20/21 phone and Cédula, 72/73 password bytes) are rejected without truncation or database error leakage.
- A direct POST without `terms` writes zero rows and returns a field-specific terms error.
- An allowed-character SQLi payload is stored literally and read back through the application data path, with queries unchanged.
- An XSS payload is rejected; the response and DOM inspection show escaped inert text, no unescaped angle brackets, no injected script element, and no script execution.
- Every `tests/php/*.php` script returns HTTP 404 when requested directly over HTTP, proving the CLI guard.
- Response inspection covers the test password, stored hash, SQLSTATE/PDO messages, stack traces, paths, and the CSRF token outside the registration form response.
- Cleanup by captured email/ID, profiles before accounts; zero residue.
- Evidence: `layer4-http.txt`. The five spec-mandated Layer 4 cases above (empty/invalid POST, oversized boundaries, missing terms, SQLi read-back, and XSS inspection) are also owned by task T8.1.

The PHP error log sink is located with `php -i` (look for `error_log`) and the USBWebServer Apache log directory. If readable, inspect it for the test password, stored hash, SQLSTATE/PDO messages, stack traces, file paths, and the CSRF token. If it is not readable, record the "never logged" clause as unverified in Documented limitations and do not claim it as satisfied. Response inspection is always mandatory.

## Risks and dependencies

- **MySQL availability and credentials.** The integration and HTTP layers need the local `profego` database. If MySQL is stopped or the configured credentials differ, the affected layers cannot run; report the limitation instead of substituting an external client.
- **Database name case.** `ProfeGo.sql` declares `ProfeGo`; the DSN keeps the `profeGo` spelling used by the committed/previous `database/database.php`. Windows MySQL compares database names case-insensitively. A case-sensitive server would require the DSN to be aligned to the actual database name; the SQL file must not be edited.
- **`STRICT_TRANS_TABLES`.** Overlength values fail at the database layer. Validation must reject boundaries before insert; boundary tests (150/151 email, 30/31 name, 20/21 phone/Cédula, 72/73 password bytes) are mandatory.
- **Unique-collision race.** The pre-check is not atomic. The unique indexes are authoritative; `register_account()` catches SQLSTATE `23000`, rolls back, re-checks all three fields, and returns duplicate errors. If no duplicate can be identified, it falls back to the generic form-level error.
- **Same-session concurrency.** PHP's default file session handler may serialize two requests that share a session. The Layer 4 scenario still issues both HTTP requests concurrently and asserts exactly one success, one duplicate re-render, and one row; the unique index remains the guarantee if the session lock is released early.
- **Homepage link scope.** `index.php` changes only the "Enseñar" anchor destination to `/pages/register.php?rol=docente`; its classes, text, and styling stay unchanged, and the header "Crear Cuenta", "Aprender", and login links keep their current destinations. The registration page remains the only HTTP entry point for registration.
- **Session state in CLI.** The controller takes a session array by reference rather than calling `session_start()`. CLI harnesses therefore exercise CSRF and the notice flag without real cookies; the HTTP script uses `ext-curl` cookies to exercise the real session.
- **Apache document root.** The project folder is the document root. Browser runs use `http://localhost/pages/register.php`; test scripts are CLI-only and return 404 over HTTP so they cannot be driven remotely.
- **Node ESM.** `js/package.json` scopes `type: module` to `js/`; no npm install or build is required. If the environment ignores the file, the `.mjs` tests still import the modules via Node's module detection, but the package file is the deterministic path.
- **Browser tooling.** Chrome DevTools/CDP must be available with the listed tools. A no-JS run is emulated by blocking scripts with a CSP header; if the tool cannot apply it, use a CDP script-disable override and record which mechanism was used.
- **Preselection repetition.** Repeated or malformed `rol` query values must be detected from the raw query string and defaulted to `estudiante`; the controller must not rely on PHP's last-value-wins parsing alone.
- **Log sink access.** The "never logged" clause may be unverifiable. It must be listed under Documented limitations rather than claimed.
- **Approval gate.** No source file may be created or modified until the user approves this plan and `tasks.md`. The baseline screenshots are the only pre-implementation evidence step and do not change source.
