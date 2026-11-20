# Validation Report 001 - Create Account

**Spec ID:** 001-create-account
**Spec status:** Approved (becomes `Implemented` in T10.1; this report does not change it)
**Report date:** 2026-11-20
**Compiled by:** T9.5 (Phase 9, Final Verification)
**Scope:** Final verification of AC-01 through AC-15 across Layers 0-4, covering RF-1 through RF-16 and RNF-1 through RNF-8, at both supported viewport widths (1440x900 and 402x874). All cited evidence comes from the final runs of T9.1-T9.4.

**Evidence path convention:** every `evidence/...` path below is relative to `specs/001-create-account/`. A "T9.x section" is the appended final-run section inside the named file. Paths are to existing files; see Section 6 for integrity confirmation.

---

## 1. Acceptance criteria verdicts

| Criterion | Short name | Verdict | Concrete evidence (final runs) | Layer(s) |
| --- | --- | --- | --- | --- |
| AC-01 | Student account created once; password verifies | **Verified** | `evidence/layer2.txt` (T9.1: one account, `rol=estudiante`, stored hash differs from plaintext and verifies it, rejects wrong password); `evidence/layer3-notes.md` (T9.3 Flow 4, set A id=30) | 2 (primary), 3 |
| AC-02 | Tutor account plus pending tutor profile | **Verified** | `evidence/layer2.txt` (T9.1: one account + one `perfiles_docente` row, `pendiente` / `0.00` / `0`); `evidence/layer3-notes.md` (T9.3 Flow 7, set D id=33); `evidence/layer4-http.txt` (T9.4 Scenario G) | 2 (primary), 3, 4 |
| AC-03 | Role control toggles all signals without submitting | **Verified** | `evidence/layer3-notes.md` (T9.2 Item 3; T9.3 Flow 1, both widths); `evidence/layer3-a11y.json` (role surface, no pressed state) | 3 |
| AC-04 | Empty JS submit flags every field and focuses first | **Verified** | `evidence/layer3-notes.md` (T9.3 Flow 2, both widths; Flow 3 mixed-invalid, one message per failing rule) | 3 |
| AC-05 | Per-field duplicate errors, zero rows, no error mixing | **Verified** | `evidence/layer2.txt` (T9.1: email/phone/ID and combined duplicates, one message per field, counts unchanged); `evidence/layer3-notes.md` (T9.3 Flows 4 and 8); `evidence/layer4-pipeline.txt` (T9.4 validation-before-duplicates precedence) | 2, 3, 4 |
| AC-06 | Injected `admin` / unknown / missing `rol` rejected | **Verified** | `evidence/layer1-php.txt` (T9.1 role allow-list); `evidence/layer4-pipeline.txt` (T9.4 role admin/unknown/missing); `evidence/layer4-http.txt` (T9.4 Scenario B) | 1, 4 |
| AC-07 | Missing / foreign CSRF token rejected | **Verified** | `evidence/layer4-pipeline.txt` (T9.4 CSRF missing/malformed/foreign cases); `evidence/layer4-http.txt` (T9.4 Scenario C); `evidence/layer0.txt` (CHECK G CSRF primitive) | 4 (primary), 0 |
| AC-08a | Allowed-character SQLi payload stored and read back literally | **Verified** | `evidence/layer2.txt` (T9.1: payload stored literally, read back through application PDO path, no query altered, unrelated row unaffected); `evidence/layer4-http.txt` (T9.4 Scenario J) | 2 (primary), 4 |
| AC-08b | XSS payload rejected, escaped and inert on re-render | **Verified** | `evidence/layer3-notes.md` (T9.3 Flow 10: escaped `value`, no raw `<script>`, `scriptWithPayload: 0`, zero rows); `evidence/layer4-http.txt` (T9.4 Scenario K) | 3, 4 |
| AC-09 | PRG redirect and notice only with parameter + unconsumed flag | **Verified** | `evidence/layer3-notes.md` (T9.3 Flow 6, all four notice cases; refresh hides with no resubmit) | 3 |
| AC-10 | Student registration works with JavaScript disabled | **Verified** | `evidence/layer3-notes.md` (T9.3 Flow 5: script blocked, server re-render, valid native POST 303 + notice); `evidence/layer4-http.txt` (T9.4 Scenario G, no-JavaScript path) | 3 (primary), 4 |
| AC-11 | Layout at 1440 and 402 px, no overflow/clipping/overlap, no regression | **Verified** | `evidence/layer3-notes.md` (T9.2 Items 1-6b, both widths); `evidence/layer3-1440.png`; `evidence/layer3-402.png`; `evidence/baseline-1440.png`; `evidence/baseline-402.png`; `evidence/layer3-a11y.json` | 3 |
| AC-12 | PHP syntax valid; JS tests non-zero count and all passing | **Verified** | `evidence/layer0.txt` (T9.1: 17/17 `php -l` clean); `evidence/layer1-js.txt` (T9.1: tests 84, pass 84, fail 0); `evidence/layer1-php.txt` (T9.1: foundation 38/0) | 0 |
| AC-13 | Meta description exact | **Verified** | `evidence/layer3-notes.md` (T9.2 Items 7-8); `evidence/layer3-a11y.json` | 3 |
| AC-14 | Non-field failure: HTTP 200, form-level alert only, values preserved | **Verified** | `evidence/layer4-pipeline.txt` (T9.4: role/CSRF/forced DB failure, full presentation set, no technical detail); `evidence/layer4-http.txt` (T9.4 Scenarios B, C and response inspection) | 4 |
| AC-15 | Homepage "Enseñar" opens Docente with no submission | **Verified** | `evidence/layer3-notes.md` (T9.3 Flow 11, both widths; T5.4 section); `evidence/layer3-a11y.json` | 3 |

No AC is failed or unverified.

---

## 2. Command and exit-code summary (final runs)

All Layer 0-2 commands were run in sequence in one process; no writing test ran in parallel. The known `php_gd2.dll` startup warning is environment noise on stderr and does not affect exit codes.

| Layer | Command / check (final run) | Result | Exit code | Evidence |
| --- | --- | --- | --- | --- |
| 0 | `php -l` over 17 PHP files | 17/17 clean | 0 for each file | `evidence/layer0.txt` section 1 |
| 0 | Static inspections A-G (dependency, schema, secrets, language, SQL, seam, CSRF) | all PASS | inspection (n/a) | `evidence/layer0.txt` section 2 |
| 0/1 | `node --test tests/js/register-validation.test.mjs tests/js/register-role.test.mjs` | tests 84, pass 84, fail 0 | 0 | `evidence/layer1-js.txt` T9.1 section |
| 1 | `php tests/php/assert_foundation.php` | Assertions 38, Failures 0 | 0 | `evidence/layer1-php.txt` T9.1 foundation section |
| 1 | `php tests/php/assert_validation.php` | Assertions 224, Failures 0 | 0 | `evidence/layer1-php.txt` T9.1 validator section |
| 1 | Shared corpus: parse in PHP and Node + 48 byte/length checks in each runtime | cases=74; checks=48, failures=0 in each | 0 for each runtime | `evidence/layer1-corpus.md` section 3.2 |
| 2 | `php tests/php/assert_registration.php` | Assertions 84, Failures 0; zero residue | 0 | `evidence/layer2.txt` T9.1 section |
| 2 | Independent read-only residue confirmation via `profego_pdo()` | 0 accounts, 0 profiles (prototype row excluded) | 0 | `evidence/layer2.txt` T9.1 section |
| 3 | CDP browser commands at 1440x900 and 402x874 (`emulate`, `navigate_page`, `take_snapshot`, `take_screenshot`, `evaluate_script`, `fill_form`, `click`, `press_key`, `list_network_requests`, `list_console_messages`) | all checks PASS; JS-enabled consoles clean | tool-based (n/a) | `evidence/layer3-notes.md` T9.2 and T9.3 sections |
| 3 | `php tests/php/test_support.php inspect --email=...` / `cleanup --...` | NOT FOUND / zero residue; profiles before accounts | 0 each | `evidence/layer3-notes.md` T9.2 and T9.3 cleanup sections |
| 4 | `php tests/php/assert_pipeline.php` | Assertions 234, Failures 0 | 0 | `evidence/layer4-pipeline.txt` T9.4 section |
| 4 | `php tests/php/assert_http_security.php` | Assertions 1181, Failures 0 | 0 | `evidence/layer4-http.txt` T9.4 section |
| 4 | `curl.exe -s -o NUL -w "%{http_code}" http://localhost/tests/php/<script>.php` for all six scripts | 6/6 HTTP 404 | 0 | `evidence/layer4-http.txt` T9.4 section, item 2 |
| 4 | Per-response inspection + PHP/Apache log scan (`php -i`, `Get-Content`) | all categories PASS; "never logged" VERIFIED | inspection (n/a) | `evidence/layer4-http.txt` T9.4 section, items 3-4 |

---

## 3. Layer-by-layer results

### Layer 0 - Syntax and static checks (T9.1)

Proved AC-12 and supported RNF-1, RNF-3, RNF-6. `php -l` passed on all 17 PHP files (17/0, exit 0). The combined `node --test` run executed 84 tests with 84 passing and 0 failing (exit 0). Static inspections A-G all passed: no framework, external dependency, build step, lockfile or bundle config; no database schema change; no client-side token value; Spanish user-facing copy with English identifiers/comments/docs; all SQL parameterized (the only interpolated identifiers are the three fixed literals in `find_duplicate_fields()`); the `fail_tutor_profile` seam confined to the service and tests and gated by `PHP_SAPI === 'cli'`; and the CSRF primitive using 32 random bytes as 64 hex characters, `hash_equals`, no rotation, and no token in client-side JavaScript.

Evidence: `evidence/layer0.txt`, `evidence/layer1-js.txt` (T9.1), `evidence/layer1-php.txt` (T9.1 foundation section).

### Layer 1 - Unit tests without a database (T9.1)

Proved the logic behind RF-2.4, RF-4, RF-5 and RF-6 (format/composition) and supported RNF-6. `assert_foundation.php`: 38 assertions, 0 failures, exit 0. `assert_validation.php`: 224 assertions, 0 failures, exit 0. JS: 84 tests, 84 pass, 0 fail, exit 0. The 74-case shared corpus parsed in both PHP and Node; 48 byte/length checks in each runtime with 0 failures (email boundary handling, name in code points, password in UTF-8 bytes, `+` phone equivalence). The role allow-list accepts only `estudiante`/`docente` and rejects `admin`, unknown, missing and non-scalar values. No framework or dependency was added; client and server share the same corpus and messages.

Evidence: `evidence/layer1-php.txt` (T9.1), `evidence/layer1-js.txt` (T9.1), `evidence/layer1-corpus.md`.

### Layer 2 - Integration through the application's own data path (T9.1)

The authoritative proof of RF-1 (persistence), RF-3, RF-6 (server enforcement), RF-7 (domain duplicates), RF-8 (hash-only storage and round trip), RNF-3 (literal storage), RNF-7, and AC-01, AC-02 and AC-08a. `assert_registration.php`: 84 assertions, 0 failures, exit 0. Student registration created exactly one row with normalized values, `activo`, non-null `fecha_registro`, lowercase email, digits-only phone, Cédula as typed, and a non-plaintext hash verifying the correct password and rejecting an incorrect one. Tutor registration created one account plus one pending profile (`pendiente` / `0.00` / `0`). Every duplicate scenario (single and combined, case-insensitive email/Cédula, SQLSTATE `23000` collision) produced one Spanish message per field with unchanged counts and no partial row. The CLI-only forced-failure seam aborted the transaction with no account left behind. The allowed-character SQLi payload was stored and read back literally. Cleanup deleted profiles before accounts by captured identifiers and asserted zero residue; an independent read-only check confirmed 0 non-prototype accounts and 0 profiles (exit 0). Collation check: `usuarios.documento` is `utf8mb4_general_ci`, database default is `utf8mb4_unicode_ci`; case-insensitive uniqueness was proved behaviorally, and the spec wording was corrected in commit `3835329`. No schema change was made.

Evidence: `evidence/layer2.txt` (T9.1 section and independent residue check).

### Layer 3 - Browser end-to-end (T9.2 and T9.3), BOTH viewport widths

All browser checks were executed at **1440x900** (`1440x900x1`, desktop) and **402x874** (`402x874x1,mobile,touch`) through real CDP.

T9.2 (visual and accessibility) proved AC-11 and AC-13 and RF-2.1/RF-2.2 surface, RF-4, RF-12, RF-14, RNF-4, RNF-5: seven exact labels and submit visible at both widths, no horizontal overflow (`scrollWidth == clientWidth`: 1425/1425 and 402/402), no clipping or overlap, vertical-scroll reachability; 8 visible `*` markers, 8 `required` and 8 `aria-required` attributes; role button with exact accessible name, `aria-pressed` absent, polite live region, keyboard Enter/Space toggle; `aria-invalid`/`aria-describedby` association and visible focus outlines on the server error; error colour `rgb(179, 38, 30)`, opacity 1, font size 14 px desktop and 12 px mobile, contrast **6.5365:1** (>= 4.5:1); 624-character simulated long error wrapping inside the card with no horizontal overflow; exact meta description `Regístrate en ProfeGo como estudiante o docente.`; clean console on fresh GETs; zero DB writes. Final screenshots: `evidence/layer3-1440.png`, `evidence/layer3-402.png`; structured capture: `evidence/layer3-a11y.json`. Compared against `evidence/baseline-1440.png` and `evidence/baseline-402.png`, the only differences are the approved spec changes.

T9.3 (functional flows) proved AC-03, AC-04, AC-05 (browser part), AC-08b, AC-09, AC-10, AC-15 and AC-02 support. Eleven flow groups passed: role toggling (all four signals, no POST); empty and mixed invalid submits (messages per failing rule, first-invalid focus, values preserved); duplicate email with JavaScript enabled and duplicate combinations (per-field errors, zero rows, role/terms preserved, passwords empty); four notice cases (parameter + unconsumed flag, consumption, refresh with no resubmit, parameter-without-flag and flag-without-parameter); valid Docente submission with a data-path read showing `rol=docente` and the pending profile; post-submit normalization (lowercase email, digits-only phone, Cédula as typed); no-JavaScript student registration; XSS payload rejected and repopulated escaped and inert; and the homepage "Enseñar" click-through with visible Docente, hidden `docente`, exact accessible name, no auto-submit, plus fresh/unknown-role defaults and a POST override in the re-render. JS-enabled consoles were clean; the two expected CSP notices in script-blocking runs were recorded separately. Every browser registration used an RNF-7 per-run token; cleanup was failure-safe, deleted profiles before accounts, and left zero residue (`FINAL_COUNTS accounts=1 profiles=0`) with the pre-existing prototype row untouched. No new screenshot was required; the T9.2 screenshots and a11y JSON remain the visual/a11y record.

Evidence: `evidence/layer3-notes.md` (T9.2 and T9.3 sections), `evidence/layer3-a11y.json`, `evidence/layer3-1440.png`, `evidence/layer3-402.png`, `evidence/baseline-1440.png`, `evidence/baseline-402.png`.

### Layer 4 - Negative and security checks (T9.4)

Proved AC-06, AC-07, AC-08b, AC-14, and RF-2.4, RF-2.2 (no-JavaScript preselected submission), RF-7, RF-8, RF-9, RF-13, RF-15, RNF-1, RNF-2, RNF-3. `assert_pipeline.php`: 234 assertions, 0 failures, exit 0; role and CSRF violations, the forced database failure through the CLI seam, GET no-write, token stability and validation-before-duplicates precedence all passed with the full AC-14 presentation set. `assert_http_security.php`: 1181 assertions, 0 failures, exit 0; direct empty/invalid POSTs, role violations, missing/malformed/foreign CSRF, the look-alike `fail_tutor_profile=1` field (not triggerable over HTTP), token reuse across two tabs, the single controlled concurrent duplicate pair (exactly one 303 and one duplicate 200 with one row), the no-JavaScript preselected Docente submission with pending profile, boundary values, POST without terms, literal SQLi storage and read back, and XSS rejection/escaping. An independent `curl.exe` request to all six `tests/php/*.php` scripts returned HTTP 404 for 6/6. Per-response inspection across all responses passed every category (no plaintext password, hash, SQLSTATE/PDO/Exception text, stack trace, path or misplaced token; the CSRF token appeared only inside the form's hidden input). Cleanup of 11 captured-identifier sets left zero residue with the prototype row untouched.

Evidence: `evidence/layer4-pipeline.txt` (T9.4 section), `evidence/layer4-http.txt` (T9.4 section, items 1-4).

---

## 4. Requirements coverage

### Functional requirements

| ID | Proving layer(s) | Evidence |
| --- | --- | --- |
| RF-1 account creation + native POST | 2, 3 (no-JS), 4 | `evidence/layer2.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flow 5; `evidence/layer4-http.txt` T9.4 Scenario G |
| RF-2.1 role control, toggle, naming, live announcement | 3 | `evidence/layer3-notes.md` T9.2 Item 3, T9.3 Flow 1; `evidence/layer3-a11y.json` |
| RF-2.2 hidden value and no-JS semantics | 3, 4 | `evidence/layer3-notes.md` T9.3 Flow 5; `evidence/layer4-http.txt` T9.4 Scenario G |
| RF-2.3 GET preselection allow-list | 3 | `evidence/layer3-notes.md` T9.3 Flow 11, T5.4 section |
| RF-2.4 POST allow-list, override, missing-role rejection | 1, 3 (override), 4 | `evidence/layer1-php.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flow 11; `evidence/layer4-pipeline.txt` T9.4 |
| RF-3 tutor profile and atomicity | 2 (seam gating in 0 and 4) | `evidence/layer2.txt` T9.1; `evidence/layer0.txt` CHECK F; `evidence/layer4-pipeline.txt` T9.4 |
| RF-4 mandatory data, markers, required state | 1, 3, 4 | `evidence/layer1-php.txt` T9.1; `evidence/layer3-notes.md` T9.2 Item 2; `evidence/layer4-http.txt` T9.4 Scenario I |
| RF-5 client-side validation and preservation | 1 (logic), 3 (DOM) | `evidence/layer1-js.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flows 2-3 |
| RF-6 validation rules, normalization, uniqueness | 1, 2, 4 | `evidence/layer1-php.txt` / `evidence/layer1-js.txt` T9.1; `evidence/layer2.txt` T9.1; `evidence/layer4-http.txt` T9.4 Scenario H |
| RF-7 duplicate pre-check, precedence, collision | 2, 3, 4 | `evidence/layer2.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flows 4, 8; `evidence/layer4-pipeline.txt` T9.4 |
| RF-8 hash-only storage, no echo/repopulate/log | 2, 3 (repopulation), 4 (responses + log) | `evidence/layer2.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flow 10; `evidence/layer4-http.txt` T9.4 items 3-4 |
| RF-9 CSRF lifetime, stability, rejection | 4 (static in 0) | `evidence/layer4-pipeline.txt` T9.4; `evidence/layer4-http.txt` T9.4 Scenario C; `evidence/layer0.txt` CHECK G |
| RF-10 login notice conditions and lifecycle | 3 | `evidence/layer3-notes.md` T9.3 Flow 6; T6.1 section |
| RF-11 re-render after validation/duplicate rejection | 3, 4 | `evidence/layer3-notes.md` T9.3 Flows 4, 8; `evidence/layer4-pipeline.txt` T9.4 |
| RF-12 responsive presentation | 3 (both widths) | `evidence/layer3-notes.md` T9.2; `evidence/layer3-1440.png`; `evidence/layer3-402.png` |
| RF-13 no write on GET; at most one account | 4 | `evidence/layer4-pipeline.txt` T9.4; `evidence/layer4-http.txt` T9.4 Scenario F |
| RF-14 exact meta description | 3 | `evidence/layer3-notes.md` T9.2 Items 7-8; `evidence/layer3-a11y.json` |
| RF-15 non-field error presentation | 4 | `evidence/layer4-pipeline.txt` T9.4; `evidence/layer4-http.txt` T9.4 Scenarios B, C |
| RF-16 homepage tutor preselection (incl. no-JS) | 3, 4 | `evidence/layer3-notes.md` T9.3 Flow 11; `evidence/layer4-http.txt` T9.4 Scenario G |

### Non-functional requirements

| ID | Proving layer(s) | Evidence |
| --- | --- | --- |
| RNF-1 server-side security | 0, 4 | `evidence/layer0.txt` CHECKS C, E, G; `evidence/layer4-http.txt` T9.4 |
| RNF-2 credential protection | 2, 4 | `evidence/layer2.txt` T9.1; `evidence/layer4-http.txt` T9.4 items 3-4 |
| RNF-3 safe output and data handling | 0, 2, 3, 4 | `evidence/layer0.txt` CHECK E; `evidence/layer2.txt` T9.1; `evidence/layer3-notes.md` T9.3 Flow 10; `evidence/layer4-http.txt` T9.4 Scenarios J, K |
| RNF-4 accessibility | 3 | `evidence/layer3-notes.md` T9.2 Items 3-5; `evidence/layer3-a11y.json` |
| RNF-5 visual and responsive integrity | 3 | `evidence/layer3-notes.md` T9.2 Items 1, 6, 6b; `evidence/layer3-1440.png`; `evidence/layer3-402.png` |
| RNF-6 maintainability and conventions | 0, 1 | `evidence/layer0.txt` CHECKS A, B, D; `evidence/layer1-js.txt`; `evidence/layer1-corpus.md` |
| RNF-7 data integrity and test isolation | 2, 3, 4 | `evidence/layer2.txt` T9.1; `evidence/layer3-notes.md` T9.3 cleanup; `evidence/layer4-http.txt` T9.4 cleanup |
| RNF-8 compatibility and graceful degradation | 3 (no-JS run) | `evidence/layer3-notes.md` T9.3 Flow 5; `evidence/layer4-http.txt` T9.4 Scenario G |

---

## 5. Non-AC clauses and limitations

**Verified non-AC clause.** The "never logged" clause of RF-8 / RNF-2 is **VERIFIED**, not assumed: `php -i` located the PHP configuration and Apache logs; both Apache logs were readable and scanned for the synthetic passwords, stored hash, SQLSTATE/PDO/Exception text, stack-trace frames, `csrf_token`, 64-hex tokens, `password_hash` and plaintext password fields. All occurrence counts were 0; only optional-extension startup warnings and Apache operational lines were present. Evidence: `evidence/layer4-http.txt` T9.4 section, item 4.

**No unverified AC and no unverified AC-supporting clause.** Every AC-01 through AC-15 is Verified in Section 1, and no clause needed by an AC is recorded as unverified.

Informational / documented limitations recorded for completeness (none is a failed AC):

1. **Figma file not directly consulted.** No Figma file key is available in the repository; AGENTS.md treats Figma as a visual reference rather than a strict technical specification, so the T1.1 baseline screenshots were the concrete approved visual reference. Recorded, not treated as a blocker. Evidence: `evidence/layer3-notes.md` T9.2 "Figma-reference caveat"; `evidence/layer3-a11y.json` `figma_reference`.
2. **XSS alert-detection mechanism limitation.** The `alert()` override installed from the no-JS initScript is document-scoped and is lost on the POST re-render, so post-render alert detection rests on the escaped-value/inert-DOM evidence and on the absence of any observed dialog during the run. Evidence: `evidence/layer3-notes.md` T9.3 Flow 10.
3. **No-JavaScript mechanism adaptation.** The earlier CSP-injection mechanism no longer worked in the current Chrome DevTools MCP build; an equivalent `navigate_page` initScript `MutationObserver` inserting the same meta CSP (`script-src 'none'`) as the first child of `<head>` was used. It produced the same observable behavior (module blocked, native POST and server re-render working, two expected CSP notices). This is an environment-driven adaptation, not a product change. Evidence: `evidence/layer3-notes.md` T9.3 Flow 5 and "No-JavaScript mechanism used (adaptation, recorded)".
4. **`csrf_token` literal in two JS comments.** The literal field-name string appears twice in `js/features/register-form.js`, both times in comments; it is never executable code and no token value appears in client-side JavaScript. No secret exposure; recorded as a non-blocking note because this task is evidence-only and did not modify source. Evidence: `evidence/layer0.txt` CHECK C.
5. **Pre-existing all-empty prototype row retained.** The single prototype `usuarios` row (`id=1`, all identifying fields empty) predates the feature, was used as the baseline, and was never read for assertions, modified, or deleted. Zero residue is asserted by captured identifiers against this baseline. Evidence: `evidence/layer2.txt` T9.1 residue check; `evidence/layer3-notes.md` T9.3 cleanup; `evidence/layer4-http.txt` T9.4 cleanup.
6. **Spec "Documented limitations" still apply.** These are recorded in `spec.md` and are outside AC scope: terms acceptance is required but not persisted; no browser role switch without JavaScript; no email verification; no rate limiting; no automatic sign-in; duplicate-field errors allow account enumeration; no-JS validation requires a full POST; writing tests briefly use the local development database; an aborted run can leave residue (cleanup is by captured identifiers only, tables never truncated, tests never parallel); database outages produce the generic RF-15 error. The log-verification dependency listed there is resolved as VERIFIED in this report.
7. **Environment noise (informational).** The `php_gd2.dll` startup warning is an optional-extension message that does not affect exit codes (`evidence/layer0.txt` header); a favicon 404 seen with `ignoreCache:true` is a pre-existing site-wide asset condition unrelated to registration JavaScript (`evidence/layer3-notes.md` T9.2 Items 7-8; `evidence/layer3-a11y.json` observations).

---

## 6. Integrity checks

- **Zero test residue, by captured identifiers.** Layer 2 final zero-residue assertion passed and an independent read-only check returned 0 accounts and 0 profiles (prototype excluded). Layer 3 cleanup returned `ALL_CLEANUP_OK=True`, final counts `accounts=1 profiles=0`, and all nine captured emails returned NOT FOUND. Layer 4 `assert_pipeline.php` and `assert_http_security.php` each asserted final zero residue after deleting profiles before accounts (11 captured sets in the HTTP harness). Tables were never truncated and writing tests were not run in parallel. Evidence: `evidence/layer2.txt`, `evidence/layer3-notes.md` (T9.3 cleanup), `evidence/layer4-pipeline.txt` (T9.4), `evidence/layer4-http.txt` (T9.4).
- **No new dependency, framework or build step.** `js/package.json` contains only `{"type": "module"}`; no lockfile, `node_modules`, `vendor`, `composer.json` or bundler config exists. No external script URL was introduced. Evidence: `evidence/layer0.txt` CHECK A; `evidence/layer1-js.txt` notes.
- **No schema change.** `database/ProfeGo.sql` is unmodified (`git diff` and `git status --porcelain` empty for that path). Evidence: `evidence/layer0.txt` CHECK B; `evidence/layer2.txt` T9.1 collation note.
- **Spec, plan and task statuses unchanged by this task.** T9.5 changed only this report and its own checkbox in `tasks.md`. The spec remains `Approved`; no source, CSS, JS, PHP, fixture, baseline, other evidence file, or MEMORY.md was modified by T9.5.

---

## 7. Conclusion

All acceptance criteria AC-01 through AC-15 are **Verified** with concrete evidence paths from the final runs, with no AC failed or unverified. Layers 0-4 are represented, and every Layer 3 result is recorded at both 1440x900 and 402x874. Zero test residue, no new dependency/build step/schema change, and the "never logged" clause verified are confirmed. The specification may move to `Implemented` in T10.1 after MEMORY.md, task statuses and evidence pointers are finalized; its status remains `Approved` at the time of this report.
