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
