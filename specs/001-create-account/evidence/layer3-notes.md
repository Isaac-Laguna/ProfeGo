# Layer 3 Notes

## T5.1 - server-rendered view

Date: 2026-11-19.

This section records the CLI render verification of `includes/register_view.php`
(task T5.1). Browser/visual measurements from T5.3 will be appended below later.

### Environment and command notes

- Interpreter: `C:\Users\isaac\Documents\USBWebServer\php\php.exe` (PHP 8.1.3 CLI).
- `-n` is used so the unrelated local `php_gd2.dll` startup warning does not
  affect exit codes. Without `-n`, `php -l` still reports no syntax errors and
  exits 0, but the process prints that pre-existing startup warning.
- The assertion script is piped to `php -n` from stdin (here-string), not passed
  with `-r`: Windows native argument passing strips embedded double quotes and
  would corrupt the PHP string literals. `$OutputEncoding` is set to UTF-8 so
  accented Spanish product strings survive the pipe unchanged.

### Commands and results

Syntax check:

```powershell
& 'C:\Users\isaac\Documents\USBWebServer\php\php.exe' -n -l includes/register_view.php
# LINT_EXIT=0
# No syntax errors detected in includes/register_view.php
```

Render/assertion run (model and assertions in full below):

```powershell
$php = 'C:\Users\isaac\Documents\USBWebServer\php\php.exe'
$OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$code = @'
<?php
require 'includes/register_view.php';
/* ... assertion script shown below ... */
'@
$code | & $php -n 2>$null
Write-Output "VERIFY_EXIT=$LASTEXITCODE"
# VERIFY_EXIT=0
```

Default model used for the main render (safe defaults, student role):

```php
$token = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
$model = [
    'values' => [],
    'role' => 'estudiante',
    'errors' => [],
    'form_error' => null,
    'csrf_token' => $token,
    'terms' => false,
    'live_region' => 'Tipo de cuenta seleccionado: Estudiante.',
    'password_values_empty' => true,
];
$html = render_register_page($model);
```

### Rendered-markup assertions (all executed, all passed)

| # | Assertion | Result |
| --- | --- | --- |
| 1 | document starts with `<!DOCTYPE html>` and has `<html lang="es">` | PASS |
| 2 | POST self form `<form class="register-form" method="post" action="register.php" novalidate>` | PASS |
| 3 | native submit `<button type="submit" class="register-submit">Crear Cuenta</button>` | PASS |
| 4 | control `name="name"` | PASS |
| 5 | control `name="lastname"` | PASS |
| 6 | control `name="email"` | PASS |
| 7 | control `name="phone"` | PASS |
| 8 | control `name="id"` | PASS |
| 9 | control `name="password"` | PASS |
| 10 | control `name="password_confirm"` | PASS |
| 11 | control `name="terms"` | PASS |
| 12 | hidden `name="rol"` field | PASS |
| 13 | hidden `name="csrf_token"` field carries the model token | PASS |
| 14 | label `Nombre*` | PASS |
| 15 | label `Apellido*` | PASS |
| 16 | label `Correo electrónico*` | PASS |
| 17 | label `Teléfono*` | PASS |
| 18 | label `Cédula/ID*` | PASS |
| 19 | label `Crear contraseña*` | PASS |
| 20 | label `Confirmar contraseña*` | PASS |
| 21 | terms label, after tag stripping and whitespace normalization, is exactly `Acepto los términos y condiciones y la Política de Privacidad.*` | PASS |
| 22 | three existing placeholder `<a href="#">` links preserved | PASS |
| 23 | role button exact: `type="button"`, class/id, accessible name `Tipo de cuenta: Estudiante, cambiar a Docente`, visible text `Estudiante` | PASS |
| 24 | hidden role value exact: `<input type="hidden" name="rol" id="register-role-value" value="estudiante">` | PASS |
| 25 | no `aria-pressed` attribute anywhere | PASS |
| 26 | visually hidden live region `<span ... role="status" aria-live="polite">Tipo de cuenta seleccionado: Estudiante.</span>` | PASS |
| 27 | exact meta description `Regístrate en ProfeGo como estudiante o docente.` | PASS |
| 28 | exactly eight standalone `required` attributes | PASS |
| 29 | exactly eight `aria-required="true"` states | PASS |
| 30 | no `<script` tag added by this task | PASS |
| 31 | default render has no field error containers | PASS |
| 32 | default render has no `role="alert"` region | PASS |
| 33 | field error container `id="register-name-error"` | PASS |
| 34 | field error container `id="register-terms-error"` | PASS |
| 35 | one `<p>` per message (`<p>Ingresa tu nombre.</p>`) | PASS |
| 36 | `aria-invalid="true"` plus `aria-describedby="register-name-error"` on the invalid control | PASS |
| 37 | `autofocus` placed on the first invalid control (`register-name`) | PASS |
| 38 | non-password values preserved (`value="Ana"`, `value="ana@example.com"`) | PASS |
| 39 | password value never rendered (`... name="password" ... value=""`) | PASS |
| 40 | form-level alert only: `<div class="register-form-error" role="alert">No se pudo completar el registro. Inténtalo de nuevo.</div>` | PASS |
| 41 | no field error containers when `form_error` is set | PASS |
| 42 | script payload escaped (`&lt;script&gt;alert(1)&lt;/script&gt;` present) | PASS |
| 43 | no raw `<script` element anywhere in the XSS render | PASS |
| 44 | docente model renders Docente text, hidden `docente`, and accessible name `Tipo de cuenta: Docente, cambiar a Estudiante` | PASS |

Summary line from the run: `TOTAL_FAILURES 0`, `VERIFY_EXIT=0`.
(Assertions 1-44 above correspond to 44 assertion calls; the run also printed
two informational `XSS_INPUT` / `XSS_FRAGMENT` lines, not counted as assertions.)

### Escaping proof

Exact input placed in the model:

```php
$xssModel['values'] = ['name' => '<script>alert(1)</script>'];
```

Exact rendered output fragment (captured from the run):

```html
<input id="register-name" name="name" type="text" autocomplete="given-name" value="&lt;script&gt;alert(1)&lt;/script&gt;" required aria-required="true">
```

The literal `<script>` sequence does not occur anywhere in that document
(assertion 43), and no module or other script tag was emitted (assertion 30).

### Full assertion script executed

```php
<?php
require 'includes/register_view.php';

$token = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
$model = [
    'values' => [],
    'role' => 'estudiante',
    'errors' => [],
    'form_error' => null,
    'csrf_token' => $token,
    'terms' => false,
    'live_region' => 'Tipo de cuenta seleccionado: Estudiante.',
    'password_values_empty' => true,
];
$html = render_register_page($model);
$failures = 0;
function ck($name, $ok) {
    global $failures;
    if ($ok) { echo 'PASS ' . $name . PHP_EOL; }
    else { echo 'FAIL ' . $name . PHP_EOL; $failures++; }
}

ck('doctype and lang=es', strpos($html, '<!DOCTYPE html>') === 0 && strpos($html, '<html lang="es">') !== false);
ck('POST self form', strpos($html, '<form class="register-form" method="post" action="register.php" novalidate>') !== false);
ck('native submit Crear Cuenta', strpos($html, '<button type="submit" class="register-submit">Crear Cuenta</button>') !== false);

$names = ['name', 'lastname', 'email', 'phone', 'id', 'password', 'password_confirm', 'terms'];
foreach ($names as $n) { ck('control name=' . $n, strpos($html, 'name="' . $n . '"') !== false); }
ck('hidden rol field', strpos($html, 'name="rol"') !== false);
ck('csrf hidden field with model token', strpos($html, 'name="csrf_token" value="' . $token . '"') !== false);

$labels = ['Nombre*', 'Apellido*', 'Correo electrónico*', 'Teléfono*', 'Cédula/ID*', 'Crear contraseña*', 'Confirmar contraseña*'];
foreach ($labels as $l) { ck('label ' . $l, strpos($html, '>' . $l . '</label>') !== false); }

$plain = preg_replace('/<[^>]*>/', '', $html);
$plain = preg_replace('/\s+/u', ' ', $plain);
ck('terms label exact', strpos($plain, 'Acepto los términos y condiciones y la Política de Privacidad.*') !== false);
ck('three placeholder links preserved', substr_count($html, '<a href="#">') >= 3);

ck('role button student exact', strpos($html, '<button type="button" class="register-role" id="register-role" aria-label="Tipo de cuenta: Estudiante, cambiar a Docente">Estudiante</button>') !== false);
ck('hidden estudiante value exact', strpos($html, '<input type="hidden" name="rol" id="register-role-value" value="estudiante">') !== false);
ck('no aria-pressed', strpos($html, 'aria-pressed') === false);
ck('live region span', strpos($html, '<span class="register-visually-hidden" role="status" aria-live="polite">Tipo de cuenta seleccionado: Estudiante.</span>') !== false);
ck('exact meta description', strpos($html, '<meta name="description" content="Regístrate en ProfeGo como estudiante o docente.">') !== false);

preg_match_all('/\srequired(?=[\s>])/', $html, $req);
preg_match_all('/aria-required="true"/', $html, $aria);
ck('eight required attributes', count($req[0]) === 8);
ck('eight aria-required states', count($aria[0]) === 8);
ck('no script tag added', strpos($html, '<script') === false);
ck('default has no field errors', strpos($html, '-error') === false);
ck('default has no form alert', strpos($html, 'role="alert"') === false);

$errorModel = $model;
$errorModel['values'] = ['name' => 'Ana', 'email' => 'ana@example.com'];
$errorModel['errors'] = ['name' => ['Ingresa tu nombre.'], 'terms' => ['Debes aceptar los términos y condiciones.']];
$errorHtml = render_register_page($errorModel);
ck('name error container id', strpos($errorHtml, 'id="register-name-error"') !== false);
ck('terms error container id', strpos($errorHtml, 'id="register-terms-error"') !== false);
ck('one p per name message', strpos($errorHtml, '<p>Ingresa tu nombre.</p>') !== false);
ck('aria-invalid and describedby on name', strpos($errorHtml, 'aria-invalid="true" aria-describedby="register-name-error"') !== false);
preg_match('/<input id="register-name"[^>]*>/', $errorHtml, $nameInput);
ck('autofocus on first invalid control', isset($nameInput[0]) && strpos($nameInput[0], ' autofocus') !== false);
ck('non-password value preserved', strpos($errorHtml, 'value="Ana"') !== false && strpos($errorHtml, 'value="ana@example.com"') !== false);
ck('password value never rendered', strpos($errorHtml, 'name="password" type="password" autocomplete="new-password" value=""') !== false);

$formModel = $model;
$formModel['form_error'] = 'No se pudo completar el registro. Inténtalo de nuevo.';
$formHtml = render_register_page($formModel);
ck('form-level role=alert', strpos($formHtml, '<div class="register-form-error" role="alert">No se pudo completar el registro. Inténtalo de nuevo.</div>') !== false);
ck('no field errors with form-level error', strpos($formHtml, 'register-name-error') === false && strpos($formHtml, 'register-terms-error') === false);

$xssModel = $model;
$xssModel['values'] = ['name' => '<script>alert(1)</script>'];
$xssHtml = render_register_page($xssModel);
ck('script payload escaped', strpos($xssHtml, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false);
ck('no raw script element anywhere', strpos($xssHtml, '<script') === false);
preg_match('/<input id="register-name"[^>]*>/', $xssHtml, $xssInput);
echo 'XSS_INPUT <script>alert(1)</script>' . PHP_EOL;
echo 'XSS_FRAGMENT ' . $xssInput[0] . PHP_EOL;

$docModel = $model;
$docModel['role'] = 'docente';
$docModel['live_region'] = 'Tipo de cuenta seleccionado: Docente.';
$docHtml = render_register_page($docModel);
ck('docente role signals', strpos($docHtml, 'aria-label="Tipo de cuenta: Docente, cambiar a Estudiante">Docente</button>') !== false && strpos($docHtml, 'value="docente"') !== false);

echo 'TOTAL_FAILURES ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
```

### Scope statement

- No `<script type="module">` tag was added (owned by T7.1).
- No `pages/*`, `css/*`, `js/*`, controller, style, or MEMORY.md file was
  touched by this task.

## T5.3 - error styles

Date: 2026-11-19.

This section records the real-browser verification of `css/register.css`
(task T5.3). The T5.1 section above is unchanged; this section is appended.

### Environment

- URL: `http://localhost/pages/register.php` (GET form, self-target POST).
- Browser: Chrome via Chrome DevTools MCP. Viewports: `1440x900x1` and
  `402x874x1,mobile,touch`.
- CSS was hard-reloaded (`ignoreCache: true`); the first measurement after a
  normal navigation still served a cached stylesheet and was discarded.
- Only `css/register.css` was edited. No PHP, JS, view, or baseline file was
  touched.

### Test-first baseline (measured before the CSS change)

CSSOM/computed values on the pre-change page at 1440 confirmed the approved
deviations were needed:

| Property | Before | Required |
| --- | --- | --- |
| `font-family` of `.register-role` (`<button>`) | `Arial` (UA default, not inherited) | inherited page font |
| `cursor` of `.register-role` | `default` | `pointer` |
| `.register-role:focus-visible` rule | absent | visible 3 px outline |
| `.register-field__error` color | `rgb(236, 34, 31)` | `#b3261e` / `rgb(179, 38, 30)` |
| `.register-field__error` opacity | `0.6` | `1` (no opacity) |
| `.register-field__error` mobile font-size | `10.72px` | ≥ `12px` |
| `.register-form-error` rule | absent | present, contrast-compliant |
| `.register-visually-hidden` rule | absent | present |
| live region geometry | `position: static`, `311.45 x 24 px` (visible text on page) | 1 px clipped, off-flow |

All of the above pass after the change (values below).

### Server-generated field error (real POST, no JavaScript)

Procedure at each width: fresh GET → fill name with `Carlos99` (invalid:
contains digits), lastname `Pérez`, email `carlos.t53@example.com`, phone
`88887777`, ID `T5-001-AB`, password and confirmation `Clave1234`, check the
terms checkbox → click the native `<button type="submit">Crear Cuenta</button>`.

- Network: `POST http://localhost/pages/register.php [200]` at both widths
  (re-render, no redirect).
- `document.querySelectorAll('script').length === 0` on the response: the page
  carries no JavaScript, so the error is produced entirely by the server.
- Rendered message: `El nombre solo puede contener letras, espacios, guiones y apóstrofes.`
  in `#register-name-error`, with `aria-invalid="true"` and
  `aria-describedby="register-name-error"` on the control; `autofocus` left
  focus on `#register-name`.
- Re-render preservation: name value `Carlos99`, lastname/email/phone/ID
  preserved, terms checked, password fields empty, form-level alert absent.
- Zero writes: `php tests/php/test_support.php inspect --email=carlos.t53@example.com`
  → `NOT FOUND: no account matches correo = carlos.t53@example.com` (exit 0).
  No account or tutor profile was created by the invalid POST.

### Exact measurements after the change

| Measured value | 1440x900 | 402x874 |
| --- | --- | --- |
| error container class / id | `register-field__error` / `register-name-error` | same |
| error color (container and `<p>`) | `rgb(179, 38, 30)` | `rgb(179, 38, 30)` |
| error opacity (container and `<p>`) | `1` | `1` |
| error font-size | `14px` | `12px` |
| error line-height | `19.6px` | `16.8px` |
| `overflow-wrap` | `break-word` | `break-word` |
| `document.scrollWidth` / `clientWidth` | `1425` / `1425` | `402` / `402` |
| card width | `320px` | `244.953125px` |
| error container width | `272px` | `208.234375px` |
| error inside card (left/right bounds) | yes | yes |
| `<p>` `scrollWidth` / `clientWidth` | `272` / `272` | `208` / `208` |
| role button | visible, `Inter...`, `cursor: pointer` | visible, `Inter...`, `cursor: pointer` |
| all seven `*` label markers plus terms `*` | visible | visible |
| live region | `absolute`, `1px x 1px`, `clip: rect(0,0,0,0)` | same |

No horizontal overflow, clipping, or overlap was observed at either width;
the extra live-region text that was previously visible on the page is now
visually hidden but remains in the accessibility tree (`role="status"`,
`aria-live="polite"`).

### Focus indicators

- Role button, focused with Tab: `matches(':focus-visible') === true`,
  `outline: 3px solid rgb(48, 68, 133)`, `outline-offset: 2px` (1440).
- First invalid control (`#register-name`), focused by server `autofocus`:
  `matches(':focus-visible') === true`, `outline: 3px solid rgb(48, 68, 133)`,
  `outline-offset: 1px` (1440 and 402).

### Contrast calculation

Computed error color `#b3261e` = `rgb(179, 38, 30)`; background white.

- Relative luminance of white: `1.0`.
- Relative luminance of `#b3261e`: `0.1106366969` (sRGB linearization with
  the WCAG 0.03928 / 0.055 / 2.4 formula: R≈0.4507, G≈0.01941, B≈0.01298;
  `0.2126*R + 0.7152*G + 0.0722*B`).
- Ratio: `(1 + 0.05) / (0.1106366969 + 0.05)` = `6.5365 : 1` → **≥ 4.5:1 PASS**
  (measured identically at both widths).

### Long-error wrapping — SIMULATED MEASUREMENT ONLY

For this measurement only, a long string was injected into the existing error
`<p>` with `evaluate_script`; it is not server content and not present in the
screenshots. The page was restored to its real server state afterwards (GET
navigation at 402, reload at 1440).

| Simulated value | 1440x900 | 402x874 |
| --- | --- | --- |
| injected characters | 350 | 490 |
| `<p>` `scrollWidth` / `clientWidth` | `272` / `272` | `208` / `208` |
| wrapped lines (height / line-height) | ~11 (215.53px / 19.6px) | ~16 (268.75px / 16.8px) |
| error within card bounds | yes | yes |
| document `scrollWidth` / `clientWidth` | `1425` / `1425` | `402` / `402` |

No horizontal overflow, clipping, or overlap in either simulated case.

### Screenshots (real server error state)

- `specs/001-create-account/evidence/layer3-error-1440.png`
- `specs/001-create-account/evidence/layer3-error-402.png`

These are distinct from the T1.1 baseline screenshots and from the T9.2 final
screenshots; the baseline files were not modified.

## T5.4 - homepage preselection

Date: 2026-11-19.

This section is appended; the T5.1 and T5.3 sections above are unchanged.

### Scope and files

- `includes/register_controller.php`: added the pure helper
  `preselected_role_from_request(array $server, array $query): string` and
  wired it into the non-POST (GET) branch. The POST branch was not modified.
- `includes/register_view.php`: NO change. All four role signals already derive
  from the model role, so no new view logic was needed: visible text from
  `$roleLabel`, hidden `input[name=rol]` from `e($role)`, accessible name from
  `$roleName`, and live-region text from the controller-supplied `live_region`
  (canonical copy via `registration_live_region()`). `git diff` for the view is
  empty.
- `index.php`: only the "Enseñar" anchor destination changed; classes, text,
  styling, and all other homepage actions were untouched.

### Helper allow-list behavior (exact)

`preselected_role_from_request()` inspects the raw `$server['QUERY_STRING']`
first, because PHP's parsed `$_GET` is last-value-wins and cannot detect
repetition. The raw query is split on `&`; each key is taken before the first
`=` and URL-decoded. If any decoded key starts with `rol[` (for example
`rol[]`), the result is `estudiante`; if the number of literal `rol` keys is
not exactly one, the result is `estudiante`. When no raw query string is
provided (CLI render path), the parsed `$query['rol']` is used directly. The
remaining value must satisfy `is_allowed_registration_role()` (a string equal
to exactly `estudiante` or `docente`); anything else returns `estudiante`.

### Test-first check (inline harness, no test file written)

Before the change, the assertion harness printed
`EXPECTED_PRE_FAIL: helper preselected_role_from_request() does not exist yet`
and exited 2. After the change, the identical 13-case matrix passed
(`TOTAL_FAILURES 0`, exit 0): fresh/no query, docente, explicit estudiante,
admin, unknown foo, repeated in both orders, empty, case-variant DOCENTE,
array injection (`rol[]=docente` and URL-encoded `rol%5B%5D=docente`), the CLI
render path without QUERY_STRING, and an unrelated query with no rol key.

### Browser click-through from the homepage "Enseñar" action

- Start URL: `http://localhost/`, viewport `1440x900x1`. The accessibility
  snapshot showed "Enseñar" pointing at `/pages/register.php?rol=docente`
  while "Crear Cuenta" still pointed at `/pages/register.php`, "Aprender" at
  `/pages/register.php`, and "Iniciar Sesión" at `/pages/login.php`.
- Activating the "Enseñar" link navigated to
  `http://localhost/pages/register.php?rol=docente`.
- Observed role signals: visible text `Docente`; hidden `docente`; accessible
  name `Tipo de cuenta: Docente, cambiar a Estudiante`; live-region text
  `Tipo de cuenta seleccionado: Docente.`; `document.querySelectorAll('script').length === 0`.
- Network after the click contained only GET requests (document, `main.css`,
  `register.css`, logo, font). No POST occurred, so there was no auto-submission.

### Browser GET allow-list matrix (each URL navigated directly)

| Requested URL | Visible text | Hidden value | Accessible name | Live-region text |
| --- | --- | --- | --- | --- |
| `http://localhost/pages/register.php` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=estudiante` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=admin` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=foo` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=docente&rol=estudiante` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol[]=docente` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |
| `http://localhost/pages/register.php?rol=DOCENTE` | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. |

Every case rendered the student signals; only the two allow-listed docente
paths (the homepage link and a single `rol=docente`) rendered Docente.

### Browser POST override (non-writing)

- Opened `http://localhost/pages/register.php?rol=docente`, then changed the
  hidden `rol` value to `estudiante` with `evaluate_script`.
- Filled name `Carlos99` (invalid: contains digits), lastname `Perez`, email
  `override.t54@example.com`, phone `88887777`, ID `T54-OVR-AB`, password and
  confirmation `Clave1234`, checked the terms box, and clicked the native
  "Crear Cuenta" button.
- Network: `POST http://localhost/pages/register.php [200]` (no 303 redirect).
- Re-render at `http://localhost/pages/register.php`: visible text
  `Estudiante`, hidden `estudiante`, accessible name
  `Tipo de cuenta: Estudiante, cambiar a Docente`, live-region text
  `Tipo de cuenta seleccionado: Estudiante.`; name error
  `El nombre solo puede contener letras, espacios, guiones y apóstrofes.`;
  name value `Carlos99` and terms checked were preserved.
- This proves the submitted POST role overrides the GET preselection.

### Browser field-error re-render from the homepage link (non-writing)

- Activated "Enseñar" from `http://localhost/` again; the hidden `rol` was
  confirmed as `docente` before submission.
- Filled name `Ana99` (invalid), lastname `Perez`, email
  `field-error.t54@example.com`, phone `88887777`, ID `T54-FLD-AB`, password
  and confirmation `Clave1234`, checked the terms box, left the hidden `rol`
  unchanged at `docente`, and clicked "Crear Cuenta".
- Network: `POST http://localhost/pages/register.php [200]` (no 303 redirect).
- Re-render at `http://localhost/pages/register.php`: visible text `Docente`,
  hidden `docente`, accessible name
  `Tipo de cuenta: Docente, cambiar a Estudiante`, live-region text
  `Tipo de cuenta seleccionado: Docente.`; name error
  `El nombre solo puede contener letras, espacios, guiones y apóstrofes.`;
  name value `Ana99` and terms checked were preserved.

### No-write proof

- Read-only application-path row count before the browser cases:
  `BASELINE accounts=1 profiles=0`.
- Read-only row count after every browser case:
  `AFTER accounts=1 profiles=0` (unchanged).
- `php tests/php/test_support.php inspect --email=override.t54@example.com`
  -> `NOT FOUND: no account matches correo = override.t54@example.com` (exit 0).
- `php tests/php/test_support.php inspect --email=field-error.t54@example.com`
  -> `NOT FOUND: no account matches correo = field-error.t54@example.com` (exit 0).
- Both POSTs were 200 field-error re-renders, never success redirects. No
  account and no tutor profile was created in any T5.4 scenario.

### Static diff of index.php

`git diff -- index.php` reported exactly one changed line:

```diff
-                    <a class="button button--accent" href="/pages/register.php">Enseñar</a>
+                    <a class="button button--accent" href="/pages/register.php?rol=docente">Enseñar</a>
```

No other line changed. Direct href inspection confirmed the header
"Crear Cuenta" -> `/pages/register.php`, "Aprender" -> `/pages/register.php`,
and login -> `/pages/login.php` are unchanged, as are the brand, hero, and
social links. No CSS, JS, view, or baseline file was touched by this task.

### Regression checks

- `php -n -l includes/register_controller.php`: exit 0, no syntax errors.
- `php -n -l index.php`: exit 0, no syntax errors.
- `php tests/php/test_support.php render --role=docente`: exit 0; rendered
  Docente button text, hidden `docente`, and Docente live-region text.
- `node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs`:
  84 tests, 84 pass, 0 fail, exit 0.
- `php tests/php/assert_pipeline.php`: 234 assertions, 0 failures, zero
  residue, exit 0. The POST branch and its precedence are unchanged.

## T6.1 - login notice

Date: 2026-11-19. RF-10, RNF-7, AC-09. This section is appended; the T5.1,
T5.3 and T5.4 sections above are unchanged.

### Scope and files

- `pages/login.php`: added `session_start()`, the `$showNotice` condition,
  flag consumption inside `if ($showNotice)`, and the conditional
  `role="status"` notice paragraph after the panel title. No login/authentication
  behavior was added; the form, links, and button are untouched.
- `css/login.css`: added only the `.login-notice` rule. No other selector,
  breakpoint, or property was changed.
- No controller, view, service, JS, corpus, MEMORY.md, or baseline file was
  touched by this task.

Exact condition and consumption point in `pages/login.php`:

```php
$showNotice = isset($_GET['registro']) && $_GET['registro'] === 'ok' && !empty($_SESSION['registration_notice']);

// The one-time flag is consumed only when the notice is actually displayed.
if ($showNotice) {
    unset($_SESSION['registration_notice']);
}
```

Rendered only when `$showNotice`:

```html
<p class="login-notice" role="status">Tu cuenta se creó correctamente. Ya puedes iniciar sesión.</p>
```

CSS added (only this rule):

```css
/* One-time registration success notice (spec 001, RF-10). */
.login-notice {
    width: 100%;
    margin: 0;
    color: #1b5e20;
    font-size: 16px;
    line-height: 1.4;
    text-align: center;
}
```

### Test-first check (inline PHP harness, no test file written)

An inline assertion harness was piped to `php -n` from stdin (the pattern used
in earlier tasks). It includes `pages/login.php` under output buffering with
simulated `$_GET`/`$_SESSION` and checks the four logic states. No file was
created.

Before the change (red): 3 failures / 8 assertions, `HARNESS_EXIT=1`:

```text
FAIL A notice text present
FAIL A role=status notice region present
FAIL A flag consumed
PASS B no notice without flag
PASS C no notice without parameter
PASS C flag intact without parameter
PASS D no notice for wrong parameter value
PASS D flag intact for wrong parameter value
TOTAL_FAILURES 3
```

After the change (green): 0 failures / 8 assertions, `HARNESS_EXIT=0`, with
the same eight labels all PASS. `php -n -l pages/login.php` also reported
`No syntax errors detected`, exit 0.

### Browser environment and per-run data

- URL base: `http://localhost/` (document root is the project folder).
- Browser: Chrome via Chrome DevTools MCP. Viewports `1440x900x1` and
  `402x874x1,mobile,touch`. No extra HTTP headers, no script emulation.
- The registration page carried no JavaScript (`document.querySelectorAll('script').length === 0`),
  so every registration was a native form POST with server-rendered CSRF token.
- Two RNF-7 per-run registrations were created, each with unique email, phone,
  Cédula/ID and name (generated by `tests/php/test_support.php`). Identifiers
  captured for cleanup:

  | Set | Purpose | Email | Phone | Cédula/ID |
  | --- | --- | --- | --- | --- |
  | A | native form POST | `run.10356-1795111352-387febe0@example.invalid` | `5060308071514111400` | `id-111352-387febe0` |
  | B | fetch with `redirect:'manual'` | `run.10356-1795111352-700fd92f@example.invalid` | `5060700001513090215` | `id-111352-700fd92f` |

  Test password and CSRF token values are deliberately not recorded here.

### Case 1 - parameter + flag shows and consumes the notice (1440x900)

- Flow: fresh GET `http://localhost/pages/register.php` -> filled all fields
  with set A, checked terms -> clicked native `<button type="submit">Crear Cuenta</button>`.
- Network: `POST http://localhost/pages/register.php [303]` (reqid=172), then
  `GET http://localhost/pages/login.php?registro=ok [200]` (reqid=173).
- Final URL: `http://localhost/pages/login.php?registro=ok`.
- Observed DOM: one `[role=status]` element with `class="login-notice"`,
  `textContent === 'Tu cuenta se creó correctamente. Ya puedes iniciar sesión.'`
  (exact copy), `display: block`, `color: rgb(27, 94, 32)` (#1b5e20),
  `font-size: 16px`, `opacity: 1`. It sits between the panel title and the form.
- Flag consumed by this render; a later request with the same parameter shows no notice.
- Screenshot: `evidence/layer3-notice-1440.png` (82529 bytes).

### Case 2 - refresh shows nothing and resubmits nothing

- Changing the emulated viewport to `402x874x1,mobile,touch` caused CDP to
  reload the page: `GET http://localhost/pages/login.php?registro=ok [200]`
  (reqid=178). This is an ordinary GET refresh, not a form resubmission.
- Observed DOM: `noticePresent: false`, no `[role=status]` region.
- Network after the registration POST contained no further POST: requests
  173-182 are all GET (document, CSS, logo, font). No registration was resubmitted.
- Data path: `php tests/php/test_support.php inspect --email=<set A email>`
  returned exactly one account (id 23); a captured-identifier count confirmed
  `accounts_A=1`. No new row was created by the refresh.

### Case 3 - parameter WITHOUT flag shows nothing

- After case 1 consumed set A's flag, navigating again to
  `http://localhost/pages/login.php?registro=ok` gave `noticePresent: false`.
- After case 5 consumed set B's flag, the same URL again gave
  `noticePresent: false`. A fresh session with no flag likewise has nothing to show.

### Case 4 - no parameter WITH a present flag: no notice and flag intact

- A fresh GET `http://localhost/pages/register.php` supplied a valid CSRF
  token. An `evaluate_script` call submitted set B with
  `fetch('/pages/register.php', {method:'POST', body: FormData, redirect:'manual', credentials:'same-origin'})`.
  The 303 was received but not followed: `fetchStatus: 0`,
  `fetchType: 'opaqueredirect'`. The server set the one-time flag; no navigation occurred.
- Then `navigate_page` to `http://localhost/pages/login.php` (NO parameter):
  `noticePresent: false`, `statusRegions: []`. The parameterless request did not
  consume the flag.
- Then `navigate_page` to `http://localhost/pages/login.php?registro=ok`:
  the notice reappeared with the exact copy, `role="status"`, `display: block`,
  `color: rgb(27, 94, 32)`, `font-size: 16px`, `opacity: 1`. This proves the
  parameterless request left the flag intact.
- This final state was captured at 402px: `evidence/layer3-notice-402.png`
  (62564 bytes). Measured `document.scrollWidth === document.clientWidth === 402`
  (no horizontal overflow); notice rect `x=8.5, y=339.8, w=385, h=44.8`, fully
  inside the viewport and below/above the expected neighbors. The notice is
  visible, not clipped, and does not overlap. The existing 900px breakpoint was
  left unchanged; no mobile-only rule was needed for legibility.

### Variant - wrong parameter value

- `GET http://localhost/pages/login.php?registro=not-ok` -> `noticePresent: false`.
- The inline harness also proved that with a flag present and `registro=not-ok`,
  no notice is rendered and the flag stays intact (case D above).

### Cleanup proof (RNF-7, failure-safe step)

Both created accounts were removed by the CLI helper, which deletes
`perfiles_docente` before `usuarios` and matches only the captured
identifiers (no truncation, no prefix match). Commands and output:

```text
php tests/php/test_support.php cleanup --email=run.10356-1795111352-387febe0@example.invalid --id=id-111352-387febe0 --phone=5060308071514111400
Deleted tutor profiles: 0, accounts: 1. Remaining rows for the captured identifiers: 0   [exit 0]

php tests/php/test_support.php cleanup --email=run.10356-1795111352-700fd92f@example.invalid --id=id-111352-700fd92f --phone=5060700001513090215
Deleted tutor profiles: 0, accounts: 1. Remaining rows for the captured identifiers: 0   [exit 0]
```

Both cleanup exit codes were checked (`ALL_CLEANUP_OK=True`). A final read-only
application-path check printed `residue_A=0 residue_B=0` with `RESIDUE_EXIT=0`.
No pre-existing row was touched; tables were never truncated; no writing tests
ran in parallel.

### Scope statement

No login/authentication behavior was implemented; the login form, links, and
button are unchanged. No other page behavior changed. No controller, view,
service, JS, corpus, MEMORY.md, or baseline file was modified by this task.

## T7.1 - client role control

Date: 2026-11-19. RF-2.1, RNF-4, AC-03. This section is appended; the T5.1,
T5.3, T5.4 and T6.1 sections above are unchanged.

### Scope and files

- New `js/features/register-form.js`: DOM enhancement controller. It imports
  `ROLE_TEACHER`, `nextRole`, `roleLabel` and `roleAccessibleName` from
  `./register-role.js`; no string owned by that module is duplicated. A click
  listener on the native `type="button"` reads the current role from the
  server-rendered hidden `rol` input on each activation, computes `nextRole`,
  and updates all four signals: button text, `aria-label`, hidden value, and
  the existing polite live region. Live-region copy is the only local Spanish
  string (`Tipo de cuenta seleccionado: Docente.` / `...: Estudiante.`).
- `includes/register_view.php`: exactly one line added, a module script tag
  immediately before `</body>`. No PHP logic change.
- No validation code (T7.2), no PHP/controller/CSS/corpus/MEMORY.md change, no
  `fetch`/AJAX, no secret, and no `aria-pressed` was added.

### Test-first (red) baseline

Before the change, a real browser GET at 1440x900 showed
`document.querySelectorAll('script').length === 0` and a programmatic
`document.getElementById('register-role').click()` left all signals at
`Estudiante` / `estudiante` / `Tipo de cuenta: Estudiante, cambiar a Docente` /
`Tipo de cuenta seleccionado: Estudiante.` - i.e. no toggle. This is the
expected failing state that the enhancement fixes.

### Browser environment

- URL: `http://localhost/pages/register.php` (document root is the project
  folder). Fresh GET each run, no preset cookies or extra headers.
- Chrome via Chrome DevTools MCP. Viewports `1440x900x1` and
  `402x874x1,mobile,touch`.
- Four signals read with `evaluate_script` after every activation: button
  `textContent`, `aria-label`, hidden `#register-role-value` `value`, and the
  `[role="status"][aria-live="polite"]` span `textContent`. `aria-pressed` was
  also read and was `null` in every observation.

### Exact observations - 1440x900

Module loaded: `scripts === 1`,
`script[type=module].src === '../js/features/register-form.js'`; network also
shows `GET .../js/features/register-form.js [200]` and
`GET .../js/features/register-role.js [200]`.

| State | Visible text | Hidden value | Accessible name | Live-region text | aria-pressed |
| --- | --- | --- | --- | --- | --- |
| Initial (fresh GET) | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. | null |
| After Enter (button focused) | Docente | docente | Tipo de cuenta: Docente, cambiar a Estudiante | Tipo de cuenta seleccionado: Docente. | null |
| After Space | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. | null |
| After mouse click 1 | Docente | docente | Tipo de cuenta: Docente, cambiar a Estudiante | Tipo de cuenta seleccionado: Docente. | null |
| After mouse click 2 | Estudiante | estudiante | Tipo de cuenta: Estudiante, cambiar a Docente | Tipo de cuenta seleccionado: Estudiante. | null |

Preselected `?rol=docente`: initial state Docente / docente /
`Tipo de cuenta: Docente, cambiar a Estudiante` /
`Tipo de cuenta seleccionado: Docente.` (null `aria-pressed`); after one
activation returned to the four Estudiante signals. The script therefore reads
the server-rendered hidden value and does not assume `estudiante`.

### Exact observations - 402x874

Same procedure and identical results to 1440x900 for all five rows of the
table above, including the two mouse clicks and the preselected Docente case.
Additional mobile observation:
`document.documentElement.scrollWidth === document.documentElement.clientWidth === 402`
(no horizontal overflow) in the initial, post-Enter and post-Space states.

### Accessibility tree (take_snapshot)

Student state excerpt (both widths; uid prefixes differ per snapshot):

```text
uid=1_7 form
  uid=1_8 button "Tipo de cuenta: Estudiante, cambiar a Docente" focusable focused
  uid=1_9 status atomic live="polite" relevant="additions text"
    uid=1_10 "Tipo de cuenta seleccionado: Estudiante."
```

Docente state excerpt after a toggle (mobile width shown; desktop identical):

```text
uid=3_8 button "Tipo de cuenta: Docente, cambiar a Estudiante" focusable focused
uid=3_9 status atomic live="polite" relevant="additions text"
  uid=5_0 "Tipo de cuenta seleccionado: Docente."
```

The control is exposed as a `button` with the exact normative accessible name;
no `pressed` state appears in the tree, and `getAttribute('aria-pressed')`
returned `null` in every DOM read.

### Console and no-submission proof

- `list_console_messages`: `<no console messages found>` after every run at
  both widths - no errors and no warnings.
- `list_network_requests` after all activations at 1440 contained only
  `GET http://localhost/pages/register.php [200]`, `GET .../css/main.css`,
  `GET .../css/register.css`, `GET .../assets/images/logo.png`,
  `GET .../js/features/register-form.js`, `GET .../js/features/register-role.js`,
  `GET .../assets/fonts/inter-latin.woff2`. No POST and no navigation occurred.
- The page URL stayed `http://localhost/pages/register.php` (or
  `...?rol=docente` for the preselected case) across Enter, Space and click.
- Read-only application-path count before and after the browser runs:
  `accounts=1 profiles=0` (unchanged). No account or tutor profile was written.

### No-JavaScript server-rendered state (script optional)

A raw HTTP GET (no script execution) still carried all four server-rendered
signals: default GET showed the button `Estudiante` with
`aria-label="Tipo de cuenta: Estudiante, cambiar a Docente"`, hidden
`value="estudiante"`, and live region `Tipo de cuenta seleccionado: Estudiante.`;
`?rol=docente` showed the Docente equivalents. Exactly one `<script` tag was
present in each document - the optional module tag. The full no-JS flow belongs
to later tasks; this check only confirms the rendered state does not depend on
the script.

### Regression checks

- `php -n -l includes/register_view.php`: exit 0, no syntax errors.
- `node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs`:
  84 tests, 84 pass, 0 fail, exit 0.

### Scope statement

Validation logic was not added (owned by T7.2); the native POST submission path
is unchanged. No database row was written by this task. No controller, service,
CSS, corpus, MEMORY.md, or baseline file was modified.

## T7.2 - client validation

Date: 2026-11-19. RF-5, RNF-4, RNF-8 (support), AC-04. This section is appended;
the T5.1, T5.3, T5.4, T6.1 and T7.1 sections above are unchanged.

### Scope and files

- `js/features/register-form.js`: filled `initSubmitValidation()` and added
  small helpers. The module now imports `validateRegistration` from
  `./register-validation.js`. A `submit` listener on `form.register-form` reads
  the eight validated controls from the live form (`readFormValues`), calls the
  DOM-free validator, and renders the returned field-grouped messages. The
  T7.1 role-toggle code is byte-for-byte unchanged.
- No other file was modified. No view, CSS, controller, service, corpus, PHP,
  MEMORY.md, or baseline file was touched. No dependency or build step added.
- The hidden `rol` and `csrf_token` inputs are never read into the validator,
  never modified, and never removed. `novalidate` stays on the form. No `maxlength`
  is used or added; UTF-8 byte bounds come only from the validator. No AJAX/fetch.

### Submit-handler logic

- `FIELD_ORDER = ['name','lastname','email','phone','id','password',
  'password_confirm','terms']` is both the render order and the document/focus
  order.
- `CONTROL_IDS` maps each field to its stable server control id; the error
  container id is `<control id>-error` (for example `register-password-confirm-error`).
- `renderFieldErrors()` reuses an existing container by id or creates a
  `<div class="register-field__error" id="...">` after the control, clears it,
  and appends one `<p class="register-field__error">` per message using
  `textContent` (inert text). It adds `register-field--error` to the field
  wrapper and sets `aria-invalid="true"` plus `aria-describedby` on the control.
- `clearFieldError()` removes the container, the wrapper class, `aria-invalid`,
  and just the matching `aria-describedby` token for fields that are valid, so
  stale client- or server-rendered errors cannot linger.
- `handleSubmit()` focuses the first invalid control in document order and calls
  `event.preventDefault()` only when at least one field fails. With no errors it
  does nothing: the native POST carries `rol`/`csrf_token` and follows the PRG.

### Test-first (red) baseline

Before the change, a real browser GET at 1440x900 and an empty submit produced
`POST http://localhost/pages/register.php [200]` (reqid=56) and navigated to
`http://localhost/pages/register.php`; the errors were server-rendered and
`autofocus` put focus on `#register-name`. After the change the same action
produces no POST and no navigation (evidence below).

### Browser environment and per-run data

- URL: `http://localhost/pages/register.php` (project folder is the document root).
- Chrome via Chrome DevTools MCP. Viewports `1440x900x1` and `402x874x1,mobile,touch`.
- Module loaded in every JS-enabled run: `document.querySelectorAll('script').length === 1`,
  `script[type=module].src === '../js/features/register-form.js'`; network also
  showed `GET .../js/features/register-form.js [200/304]` and
  `GET .../js/features/register-role.js [200]`.
- Per-run identifiers (RNF-7, generated by `tests/php/test_support.php`):

  | Set | Use | Email | Phone | Cedula/ID |
  | --- | --- | --- | --- | --- |
  | A | 1440 valid JS submission | run.22432-1795131965-3eef0a6a@example.invalid | 5060314141500100610 | id-131965-3eef0a6a |
  | D | 402 valid JS submission | run.22432-1795131965-331f1410@example.invalid | 5060303011501040100 | id-131965-331f1410 |
  | B | no-JS valid submission | run.22432-1795131965-8c8b81c8@example.invalid | 5060812081108011208 | id-131965-8c8b81c8 |
  | C | case 3 (values preserved, no write) | run.22432-1795131965-8f073796@example.invalid | 5060815000703070906 | id-131965-8f073796 |
  | E | no-JS invalid submission (no write) | run.22432-1795131965-c244a7c2@example.invalid | 5061202040410071202 | id-131965-c244a7c2 |

  Test password and CSRF token values are deliberately not recorded here.

### Case 1 - empty submit (both widths)

Procedure: fresh GET, click native `<button type="submit">Crear Cuenta</button>`
with all eight controls untouched.

| Observation | 1440x900 | 402x874 |
| --- | --- | --- |
| URL after submit | register.php (unchanged) | register.php (unchanged) |
| POST in network | none (only `GET register.php [200]`) | none (only `GET register.php [200]`) |
| Fields flagged | 8 of 8 | 8 of 8 |
| Messages total | 8 (one per field) | 8 (one per field) |
| `document.activeElement.id` | `register-name` | `register-name` |
| container / `<p>` class | `register-field__error` on both | same |
| `register-field--error` on wrapper | true for all 8 | true for all 8 |
| `aria-invalid` / `aria-describedby` | set correctly on all 8 | same |
| `scrollWidth === clientWidth` (402) | n/a | true (no horizontal overflow) |

Exact messages, one per field: `Ingresa tu nombre.`, `Ingresa tu apellido.`,
`Ingresa tu correo electrónico.`, `Ingresa tu teléfono.`, `Ingresa tu cédula o ID.`,
`Ingresa una contraseña.`, `Confirma tu contraseña.`,
`Debes aceptar los términos y condiciones.`

### Case 2 - mixed invalid form (both widths)

Values entered: name `Carlos99`, lastname `Pérez`, email `correo-invalido`,
phone `123`, ID `@@`, password `abc1`, confirmation `xyz99999`, terms checked.

| Observation | 1440x900 | 402x874 |
| --- | --- | --- |
| Fields flagged / messages | 6 fields / 7 messages | 6 fields / 7 messages |
| Focus | `register-name` | `register-name` |
| POST in network | none | none |
| Values preserved after submit | all 8 read back unchanged | all 8 read back unchanged |

Per-field messages (identical at both widths):

- name (1): `El nombre solo puede contener letras, espacios, guiones y apóstrofes.`
- lastname (0): valid, no error, no invalid attributes.
- email (1): `El correo electrónico no es válido.`
- phone (1): `El teléfono debe tener entre 7 y 20 dígitos.`
- ID (2): `La cédula o ID debe tener entre 5 y 20 caracteres.` and
  `La cédula o ID solo puede contener letras, números y guiones.`
  (demonstrates one message per failing rule grouped by field).
- password (1): `La contraseña debe tener entre 8 y 72 caracteres.`
- confirmation (1): `Las contraseñas no coinciden.`
- terms (0): checked, no error.

### Case 3 - unchecked terms, otherwise valid (both widths)

Values: set C, terms unchecked. Result at both widths: exactly 1 flagged field
(terms) with 1 message `Debes aceptar los términos y condiciones.`,
`aria-invalid="true"` and `aria-describedby="register-terms-error"` on the
checkbox; all other fields clean; `document.activeElement.id === 'register-terms'`;
no POST (only the GET document request). No account was created (set C inspection
below).

### Extra check - stale server-rendered error removal (402x874)

A server-shaped error was injected into the DOM for `lastname` before submit
(`div.register-field__error#register-lastname-error` with a message `<p>`,
`register-field--error` on the wrapper, `aria-invalid` and `aria-describedby` on
the control). The form was then filled with all-valid values except name
(`Carlos99`) and submitted. After submit: `register-lastname-error` present
before = true; container gone = true; wrapper class removed = true;
`aria-invalid`/`aria-describedby` on lastname = null/null; new name error rendered;
focus = `register-name`; URL unchanged (no navigation). This proves previously
rendered errors and invalid attributes are removed when a field becomes valid.

### Case 4 - valid JS submission (both widths)

- 1440x900, set A: fresh GET, all fields valid, terms checked, role hidden
  `estudiante`, token length 64, no errors present before submit. Native click
  produced `POST .../register.php [303]` (reqid=97) then
  `GET .../login.php?registro=ok [200]` (reqid=98); final URL `login.php?registro=ok`;
  `role="status"` notice text exactly
  `Tu cuenta se creó correctamente. Ya puedes iniciar sesión.`
- 402x874, set D: same flow; `POST .../register.php [303]` (reqid=151) then
  `GET .../login.php?registro=ok [200]` (reqid=152); notice present.
- No `preventDefault()` on the valid path: the POST was issued natively with the
  hidden `rol` and `csrf_token` intact. No AJAX/fetch. Console had no messages.

### Case 5 - no-JavaScript submission (402x874)

Mechanism used: Chrome DevTools `navigate_page` with an `initScript` that calls
`document.write` to inject `<meta http-equiv="Content-Security-Policy"
content="script-src 'none'">` before the document's own scripts. The alternative
`extraHttpHeaders: {"Content-Security-Policy":"script-src 'none'"}` was tried first
and rejected because it is a request header and had no effect (the role toggle
still changed `estudiante` to `docente`). The meta-CSP mechanism worked: with it
applied, clicking the role button left the hidden value at `estudiante`, proving
the enhancement module did not execute.

- Expected script-blocking console notices (recorded separately, not counted as
  JS-run console issues): one `[error]`
  `Loading the script 'http://localhost/js/features/register-form.js' violates
  the following Content Security Policy directive: "script-src 'none'" ... The
  action has been blocked.` and one `[issue] Content Security Policy of your
  site blocks some resources (count: 1)`. No other console messages appeared.
- INVALID submit (set E, name `Carlos99`): `POST .../register.php [200]`
  (reqid=180); server-rendered `#register-name-error` text
  `El nombre solo puede contener letras, espacios, guiones y apóstrofes.`,
  `aria-invalid="true"`, `aria-describedby="register-name-error"`, focus on
  `register-name` (server `autofocus`), name/terms values preserved, both password
  values empty. The page was CSP-blocked at submit time, so these errors are
  server output (RNF-8). Set E inspection below confirmed no row was written.
- VALID submit (set B): CSP re-applied, toggle again confirmed blocked; native
  `POST .../register.php [303]` (reqid=196) then
  `GET .../login.php?registro=ok [200]` (reqid=197); notice shown. This proves
  student registration completes with JavaScript unavailable (AC-10).

Note: the meta-CSP is applied per navigation; each of the two POSTs above was
started from a page on which the module was confirmed blocked.

### Console results

- All JavaScript-enabled runs (both widths, cases 1-4 and the stale-error check):
  `list_console_messages` reported `<no console messages found>` - no errors and
  no warnings.
- The two no-JS runs: only the two expected CSP blocking notices listed above;
  no other messages.

### Cleanup proof (RNF-7, failure-safe step)

Read-only application-path inspection before cleanup showed the three writing
sets present (A id=2, D id=3, B id=4) and the two non-writing sets absent
(C and E: `NOT FOUND`). Cleanup by captured identifiers, using
`php tests/php/test_support.php cleanup --email=<...> --id=<...> --phone=<...>`
(helper deletes `perfiles_docente` before `usuarios`, never truncates, no prefix
matching):

```text
A: Deleted tutor profiles: 0, accounts: 1. Remaining rows for the captured identifiers: 0 [exit=0]
D: Deleted tutor profiles: 0, accounts: 1. Remaining rows for the captured identifiers: 0 [exit=0]
B: Deleted tutor profiles: 0, accounts: 1. Remaining rows for the captured identifiers: 0 [exit=0]
```

Final inspection: A, B, C, D and E all returned
`NOT FOUND: no account matches correo = ...` (all exit=0), i.e. zero residue.
A read-only count confirmed `accounts_total=1 prototype_id1=1 profiles_total=0`:
the pre-existing prototype row is intact and no test row remains. No tables were
truncated and no writing tests ran in parallel.

### Regression checks

- `node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs`:
  84 tests, 84 pass, 0 fail, exit 0 (run before and after the change).

### Scope statement

Only `js/features/register-form.js` was modified for behavior; this evidence file
and the T7.2 checkbox in `tasks.md` are the only other changed paths. No CSS,
view, controller, service, PHP, corpus, MEMORY.md, or baseline file was modified.
No secret was added to client-side code and no residue remains.

## T9.2 - final visual and accessibility verification

Date: 2026-11-20. RF-2.1, RF-2.2 (role surface), RF-4, RF-12, RF-14; RNF-4, RNF-5;
AC-11, AC-13. This section is appended; every earlier section above is unchanged.

### Scope, environment and method

- This task produced evidence only. No source, CSS, JS, PHP, corpus, baseline, or
  MEMORY.md file was changed. No database row was written by any step here.
- URL: `http://localhost/pages/register.php`, fresh GET per run. Chrome DevTools
  MCP (real CDP): `emulate` `1440x900x1` and `402x874x1,mobile,touch`;
  `navigate_page`, `take_snapshot` (verbose), `evaluate_script`, `fill_form`,
  `click`, `press_key`, `list_network_requests`, `list_console_messages`.
- The genuine server-generated field error was produced with JavaScript ENABLED:
  the email `usu..ario@example.com` is accepted by the permissive shared ASCII
  pattern in `js/features/register-validation.js` (the client reported zero
  errors) but rejected by PHP `FILTER_VALIDATE_EMAIL`. The native POST therefore
  reached the server and returned HTTP 200 with a server-rendered field error.
  No script-blocking emulation was needed for this task.
- Structured a11y capture and the machine-readable assertion list are in
  `evidence/layer3-a11y.json` (validated with PowerShell `ConvertFrom-Json`).

### Visual comparison against the T1.1 baseline

- Reference used: `evidence/baseline-1440.png` and `evidence/baseline-402.png`
  (T1.1 pre-change captures). Both baseline files were left untouched.
- Preserved at both widths: the centered white page, the ProfeGo logo in the
  header, the "Crear cuenta" heading, the filled blue account-type control, the
  bordered white field card on desktop / borderless card on mobile, the terms
  row, the "¿Ya tienes una cuenta?" line, the "Crear Cuenta" submit, the Inter
  typography, and the primary blue / dark-blue / grey palette.
- Observable differences are exactly the approved spec changes and no others:
  the combined "Correo electrónico o cédula/ID*" field was split into separate
  "Correo electrónico*" and "Cédula/ID*" fields, a "Teléfono*" field was added,
  all seven labels and the terms label now carry the visible `*` marker, and the
  account-type control is now an interactive button instead of a static label.
- At 402 px the terms label wraps onto two lines inside its container and the
  submit sits below the terms row; both are reachable by vertical scrolling,
  with no horizontal overflow, clipping, or overlap. This is the intended
  mobile adaptation, not a scaled-down desktop layout.
- Result: no visual regression beyond approved deviations; AC-11 PASS at both
  widths.

### Figma-reference caveat

No Figma file key is available in the repository. AGENTS.md treats Figma as a
visual reference rather than a strict technical specification, so the T1.1
baseline screenshots were used as the concrete approved visual reference. The
Figma file was not directly consulted for this task; this is a recorded
limitation and was not treated as a blocker.

### Item 1 - control visibility, geometry and scroll reachability

| Measurement | 1440x900 | 402x874 |
| --- | --- | --- |
| Viewport (w x h, dpr) | 1440 x 900, 1 | 402 x 874, 1 (mobile,touch) |
| `document.scrollWidth` / `clientWidth` | 1425 / 1425 | 402 / 402 |
| Horizontal overflow | no | no |
| `document.scrollHeight` / viewport height | 1203 / 900 | 1013 / 874 |
| Controls rendered / labels exact | 7 / 7 | 7 / 7 |
| Exact labels | Nombre*, Apellido*, Correo electrónico*, Teléfono*, Cédula/ID*, Crear contraseña*, Confirmar contraseña* | identical |
| Each control `display` / `visibility` / `opacity` | block / visible / 1 | block / visible / 1 |
| Each control `scrollWidth == clientWidth` | yes (238/238 input content; 272 rendered) | yes (182/182 input content; 208.23 rendered) |
| Detected pairwise control overlaps | none | none |
| Role button rendered | `BUTTON type=button`, visible | identical |
| Terms checkbox + label rendered | visible, exact label | visible, exact label |
| Submit rendered | `BUTTON type=submit`, "Crear Cuenta", visible | identical |
| Vertical-scroll reachability | max scroll 303/303; password-confirm, terms and submit fully inside viewport | max scroll 139/139; terms and submit fully inside viewport |

Acts 1-3 of the a11y tree (`banner`, `main`, `region "Crear cuenta"`, `form`)
were present at both widths, with the expected reading order.

### Item 2 - required markers and programmatic required state

| Measurement | 1440x900 | 402x874 |
| --- | --- | --- |
| Labels containing a visible `*` | 8 (seven inputs + terms) | 8 |
| `[required]` attributes in DOM | 8 | 8 |
| `[aria-required="true"]` attributes in DOM | 8 | 8 |
| Exact seven input label strings | PASS | PASS |
| Terms label string | `Acepto los términos y condiciones y la Política de Privacidad.*` | identical |

A fresh-GET DOM check confirmed the markers are not the only signal: e.g.
`#register-name` has `required`, `aria-required="true"`, and no `aria-invalid`,
and `#register-terms` has `required`, `aria-required="true"`, and no
`aria-invalid` attribute. Chrome's a11y tree shows the unchecked required terms
box as `invalid="true"`; that is the browser's inferred representation of an
unsatisfied required control (the DOM has no `aria-invalid`), and after the
server re-render with terms checked the tree shows `checked`. Recorded as an
observation, not a failure.

### Item 3 - role control surface and toggle

| Signal | 1440x900 | 402x874 |
| --- | --- | --- |
| Visible text / hidden `rol` | Estudiante / estudiante | Estudiante / estudiante |
| Accessible name | Tipo de cuenta: Estudiante, cambiar a Docente | identical |
| Role / `aria-pressed` | button / null (absent) | button / null (absent) |
| Live region | Tipo de cuenta seleccionado: Estudiante. | identical |
| After Enter | Docente / docente / Tipo de cuenta: Docente, cambiar a Estudiante / live Docente / aria-pressed null | identical |
| After Space | back to all Estudiante signals | identical |
| Navigation on activation | none (URL unchanged) | none (URL unchanged) |
| Horizontal overflow after toggle | none | none |

A11y tree excerpt, student state (1440; 402 identical with different uids):

```text
uid=1_12 button "Tipo de cuenta: Estudiante, cambiar a Docente"
  uid=1_13 "Estudiante"
uid=1_14 status atomic live="polite" relevant="additions text"
  uid=1_15 "Tipo de cuenta seleccionado: Estudiante."
```

Docente state (1440; 402 identical with different uids):

```text
uid=1_12 button "Tipo de cuenta: Docente, cambiar a Estudiante" focusable focused
  uid=2_0 "Docente"
uid=1_14 status atomic live="polite" relevant="additions text"
  uid=2_1 "Tipo de cuenta seleccionado: Docente."
```

No `pressed` state appears in either tree.

### Items 4-5 - server error, a11y association and focus indicators

Both widths: native POST returned `POST http://localhost/pages/register.php [200]`
with `scripts=1` (JavaScript enabled) and no redirect. The only field error was
`register-email-error` = `El correo electrónico no es válido.`; no form-level
`role="alert"` was present.

| Measurement | 1440x900 | 402x874 |
| --- | --- | --- |
| `aria-invalid` / `aria-describedby` on `#register-email` | true / register-email-error | true / register-email-error |
| Focused element after re-render | register-email | register-email |
| `:focus-visible` on invalid control | true | true |
| Invalid-control outline | 3px solid rgb(48, 68, 133), offset 1px | identical |
| Role-button outline (Tab-focused, fresh GET) | 3px solid rgb(48, 68, 133), offset 2px | identical |
| `scrollWidth == clientWidth` on error `<p>` | 272 / 272 | 208 / 208 |
| Error inside card bounds | yes | yes |

Invalid-control a11y tree (1440; 402 is structurally identical with uids 6_x):

```text
uid=4_30 LabelText
  uid=4_31 "Correo electrónico*"
uid=4_32 textbox "Correo electrónico*" description="El correo electrónico no es válido." focusable focused invalid="true" required value="usu..ario@example.com"
  uid=4_33 generic
    uid=4_34 "usu..ario@example.com"
uid=4_35 generic
  uid=4_36 paragraph
    uid=4_37 "El correo electrónico no es válido."
```

The `description` on the textbox is the error text itself, confirming the
`aria-describedby` association is exposed in the accessibility tree.

Required-control a11y tree (fresh GET, 1440; 402 identical with uids 5_x):

```text
uid=1_18 ignored
  uid=1_19 LabelText
    uid=1_20 "Nombre*"
  uid=1_21 textbox "Nombre*" required
    uid=1_22 generic
```

### Item 6 - error text color, opacity, font size and contrast

| Measurement | 1440x900 | 402x874 |
| --- | --- | --- |
| Computed color (container and `<p>`) | rgb(179, 38, 30) | rgb(179, 38, 30) |
| Computed opacity | 1 | 1 |
| Font size | 14px | 12px (>= 12px REQUIRED) |
| Line height | 19.6px | 16.8px |
| `overflow-wrap` | break-word | break-word |

Contrast calculation (identical at both widths):

```text
foreground #b3261e = rgb(179, 38, 30)
linear R = 0.4507857828, G = 0.0193823610, B = 0.0129830323
L_foreground = 0.2126*R + 0.7152*G + 0.0722*B = 0.1106366969
L_white = 1.0
ratio = (1.0 + 0.05) / (0.1106366969 + 0.05) = 6.5365 : 1
required >= 4.5 : 1  ->  PASS
```

### Item 6b - simulated long-error wrapping

For measurement only, a long string was injected into the existing error `<p>`
with `evaluate_script`; it is explicitly simulated, not server content, and is
not present in the screenshots. The exact real server message was restored
afterwards and the restoration was asserted.

| Simulated value | 1440x900 | 402x874 |
| --- | --- | --- |
| Injected characters | 624 | 624 |
| `<p>` `scrollWidth` / `clientWidth` | 272 / 272 | 208 / 208 |
| Wrapped lines (height / line-height) | ~17 (333.09px / 19.6px) | ~19 (319.14px / 16.8px) |
| Error inside card horizontally | yes | yes |
| Document `scrollWidth` / `clientWidth` | 1425 / 1425 | 402 / 402 |
| Horizontal overflow | none | none |
| Restored to real server message | yes | yes |

### Items 7-8 - meta description and console

- `meta[name=description].content` is exactly
  `Regístrate en ProfeGo como estudiante o docente.` at both widths (AC-13 PASS).
- `list_console_messages` on fresh GETs: `<no console messages found>` at both
  widths (no errors, no warnings). The final screenshots were captured on those
  same clean fresh-GET states.
- Environment note: an earlier navigation using `ignoreCache:true` caused the
  browser to request the missing `/favicon.ico` and log a 404 console error.
  The specified fresh-GET procedure produced no favicon request and a clean
  console. The missing favicon is a pre-existing site-wide asset condition,
  unrelated to the registration page JavaScript.

### No database writes and evidence artifacts

- After each width's invalid POST:
  `php tests/php/test_support.php inspect --email=usu..ario@example.com`
  -> `NOT FOUND: no account matches correo = usu..ario@example.com` (exit 0).
  No account and no tutor profile was created.
- Final screenshots (fresh GET default state, full-page PNG):
  `evidence/layer3-1440.png`, `evidence/layer3-402.png`.
  These are distinct from the baseline and `layer3-error-*.png` files; neither
  baseline nor error screenshot was modified.
- Structured a11y capture: `evidence/layer3-a11y.json`.

### Result

Every check passed at both widths: all controls/labels/markers/actions visible
with no horizontal overflow, clipping, or overlap; role signals and toggle
correct with button role and no pressed state; error association, focus
outlines, and a11y trees confirmed; error contrast 6.5365:1 >= 4.5:1; mobile
error font 12px >= 12px; simulated long errors wrap inside the card without
horizontal overflow; meta description exact; console clean; zero DB writes.
AC-11 and AC-13 are verified.
