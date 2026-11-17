# Spec 001 - Create Account

**Status:** Approved

## Context and Objective

ProfeGo is an academic tutoring platform that connects students with tutors. The registration page is currently a static mock-up: the role is printed as a fixed "Estudiante" label, the form has no submission behavior, and the fields do not yet match the account data the platform needs.

This specification defines the first end-to-end capability of the platform: a visitor can create a student or tutor account from the registration page. The account is validated on the client when JavaScript is available and independently on the server, persisted securely, and followed by a redirect to the login page with a visible confirmation. The existing database schema is authoritative and must not be modified; the form is therefore aligned with it by adding a phone field and separating the combined email/ID field into two fields.

Server-side validation is authoritative. Client-side validation enhances the experience for student registration but is not required for the flow to work. The role control is required to switch the role in the browser; without JavaScript the role cannot be switched, so registration creates the role rendered in the hidden input: `estudiante` by default, or `docente` when the page was opened through the allow-listed homepage preselection (RF-2.2, RF-2.3).

The team has no direct database client. Persistence is therefore never accepted on the basis of interface text alone: a criterion that requires stored data is verified by reading the stored row back through the application's own PHP data path or by a post-submit database read correlated with a real browser submission. How the verification code reaches that data path — tools, scripts, commands, and evidence files — is decided in the plan; this specification requires only the outcome and states which layer of evidence proves each criterion.

## Users

- **Prospective student:** wants to create a student account with their personal and contact data.
- **Prospective tutor:** wants to create a tutor account so they can later offer tutoring sessions.
- **Visitor without JavaScript:** wants student registration to remain usable when client-side scripting is unavailable.
- **Returning visitor:** has just registered and needs clear confirmation before signing in.

## User Stories

- HU-1. As a prospective student, I want to create an account with my personal and contact data so that I can later book tutoring sessions.
- HU-2. As a prospective tutor, I want to register as a tutor so that I can later offer tutoring sessions.
- HU-3. As a visitor, I want one control that clearly switches between "Estudiante" and "Docente" so that I can choose the correct account type.
- HU-4. As a visitor, I want immediate, specific feedback in Spanish when a field is empty or invalid so that I can correct it without guessing.
- HU-5. As a visitor, I want to know which field is already registered so that I can use different data.
- HU-6. As a visitor, I want confirmation after registering and to be taken to the login page so that I know my account was created.
- HU-7. As a visitor without JavaScript, I want registration to still work so that client-side scripting is not a prerequisite for creating a student account.
- HU-8. As a prospective tutor, I want the homepage "Enseñar" action to open registration with Docente preselected so that I do not have to switch the role myself.

## Definitions

- **Account:** the platform record that identifies a student or tutor. New accounts start in the active state.
- **Role (rol):** the account type selected during registration. The supported registration roles are `estudiante` and `docente`. The `admin` role exists in the platform but is never selectable or reachable from registration.
- **Student (estudiante):** a user who requests tutoring sessions.
- **Tutor (docente):** a user who offers tutoring sessions and has an associated tutor profile pending verification.
- **Cédula/ID:** the national identification value stored for an account, distinct from the email address. It is stored exactly as typed after trimming surrounding whitespace; uniqueness is case-insensitive because the storage column uses the `utf8mb4_unicode_ci` collation.
- **Canonical phone:** the submitted phone number after removing an optional leading `+`, spaces, hyphens, and parentheses. The canonical form is ASCII digits only, 7 to 20 digits, and is the form that is validated, checked for uniqueness, and stored. `+50688887777` and `50688887777` are therefore the same number.
- **Visible (at a supported viewport width):** a control is rendered, not clipped, not overlapping other content, and reachable by vertical scrolling, with no horizontal scrolling.
- **Registration data fields:** the values that map to the account record: name, lastname, email, phone, Cédula/ID, and password. The password confirmation is not an additional account data field.
- **Registration text/password input controls:** the seven controls in the registration form, labeled exactly as "Nombre*", "Apellido*", "Correo electrónico*", "Teléfono*", "Cédula/ID*", "Crear contraseña*", and "Confirmar contraseña*". The `*` is the visible required marker.
- **Other registration form elements:** the role control (a two-state button whose visible text is exactly "Estudiante" or "Docente"), the terms checkbox, and the submit action labeled "Crear Cuenta". The terms checkbox label is "Acepto los términos y condiciones y la Política de Privacidad.*". The role control's accessible name expresses the current value and the action: "Tipo de cuenta: Estudiante, cambiar a Docente" when student is selected, and "Tipo de cuenta: Docente, cambiar a Estudiante" when tutor is selected. Its polite live-region announcement SHALL communicate the current role; the exact copy is implementation-defined, with the plan using "Tipo de cuenta seleccionado: Docente." and "Tipo de cuenta seleccionado: Estudiante."

## Functional Requirements

**RF-1. Successful registration.** WHEN a visitor submits the registration form by POST to the registration endpoint with values that satisfy every validation rule, THE SYSTEM SHALL create exactly one active account with the submitted role and respond with a redirect to `login.php?registro=ok` using POST/Redirect/GET. The form SHALL submit natively by POST, and its primary action SHALL be a native submit control labeled "Crear Cuenta".

**RF-2. Role selection.** THE SYSTEM SHALL support role selection through the following atomic requirements:

- **RF-2.1 Role control.** THE SYSTEM SHALL render the role control as a `type="button"` control whose activation never submits the form. WHEN the visitor activates the control, THE SYSTEM SHALL switch the selected role to the other supported value and update every role signal: the visible text (exactly "Estudiante" or "Docente"), the hidden value, the accessible name defined in the Definitions section, and the polite announcement of the new role.

- **RF-2.2 Hidden role value and no-JavaScript semantics.** The form SHALL always include a hidden `rol` input whose value is the selected role (`estudiante` by default). A no-JavaScript POST SHALL submit the hidden input's rendered value: `estudiante` from a default or explicitly-`estudiante` GET, and `docente` when the page was opened through the allow-listed homepage preselection (RF-2.3). Role preservation across error re-renders is normatively defined in RF-11 (validation and duplicate rejections) and RF-15 (non-field failures).

- **RF-2.3 GET preselection allow-list.** A fresh GET without a preselection SHALL start with role `estudiante`. A fresh GET whose request carries a single allow-listed preselection (`docente`, or `estudiante` explicitly) SHALL render that preselected role in all role signals: the visible text, the hidden value, the accessible name defined in the Definitions section, and the polite announcement. ANY other GET value (tampered, repeated, unknown, or empty) SHALL render `estudiante`. The preselection only influences initial rendering: it never bypasses the allow-list, is never written to the database, and never auto-submits the form.

- **RF-2.4 POST role allow-list.** WHEN the form is submitted by POST, THE SYSTEM SHALL assign only the submitted allow-listed role (`estudiante` or `docente`) and SHALL reject a missing role on a direct request or any role value other than these two, using the non-field error presentation defined in RF-15. A POST submission's role SHALL always override the GET preselection. No injected value can create an account outside `estudiante`/`docente`.

**RF-3. Tutor profile.** WHEN an account is created with role `docente`, THE SYSTEM SHALL create its associated tutor profile in the same transaction, with verification state `pendiente`, average rating `0.00`, and total tutoring sessions `0`. IF any part of the creation fails, THEN THE SYSTEM SHALL persist neither the account nor the tutor profile.

**RF-4. Mandatory data.** THE SYSTEM SHALL require all seven registration text/password input controls and acceptance of the terms before creating an account. Each of these eight required controls SHALL display a visible `*` required marker and SHALL expose its required state with `required` and `aria-required`; the marker SHALL NOT be the only indication of required state. The required markers are an approved visual deviation from the current page.

**RF-5. Client-side validation.** WHEN the visitor attempts to submit the form with JavaScript available and one or more fields are empty or invalid, THE SYSTEM SHALL prevent submission, mark every invalid field with one specific Spanish message per failing field and rule, move focus to the first invalid field, and preserve the entered values in place.

**RF-6. Server-side validation.** WHEN a registration request reaches the server, THE SYSTEM SHALL independently validate every field using the rules below, regardless of whether client-side validation ran, and SHALL reject the request without writing data if any rule fails.

| Field | Validation rule |
| --- | --- |
| Name, lastname | 1 to 30 characters after trimming surrounding whitespace; letters (including accented Spanish letters and ñ), spaces, hyphens, and apostrophes only; at least one letter. |
| Email | A valid email address according to PHP's `FILTER_VALIDATE_EMAIL`; ASCII characters only; at most 150 characters; stored in lowercase; unique regardless of letter case. |
| Phone | Input may contain an optional single leading `+`, spaces, hyphens, and parentheses; after removing those, exactly 7 to 20 ASCII digits; stored as digits only; unique. |
| Cédula/ID | 5 to 20 characters after trimming; ASCII letters, digits, and hyphens only; at least one alphanumeric character; stored as typed; unique regardless of letter case. |
| Password | 8 to 72 UTF-8 bytes; at least one ASCII letter and one ASCII digit. |
| Password confirmation | Exactly equal to the password. |
| Terms | Checked. |

Surrounding whitespace is removed from name, lastname, email, phone, and Cédula/ID before validation and storage. Passwords and password confirmation are never trimmed. The password bounds are measured in UTF-8 bytes on both client and server, and the client SHALL NOT rely on a `maxlength` attribute as the only bound. Format and composition rules are shared between client and server; uniqueness is checked only on the server, and a failed server rule is surfaced as a field-specific Spanish message after re-render. Because only ASCII characters are allowed in email and Cédula/ID, the accent-insensitive collation does not create an additional equivalence class beyond letter case.

**RF-7. Duplicate data.** Duplicate pre-checks SHALL run only after every field validation rule passes. WHEN all field rules pass and the email, phone, or Cédula/ID is already registered, THE SYSTEM SHALL reject the request, create no account, and show a Spanish error associated with each duplicated field. The server SHALL pre-check all three fields and report every duplicated field in the same response. A duplicate detected by a database unique collision SHALL be handled identically. Validation errors and duplicate errors SHALL NOT be combined in one response. No database or technical detail SHALL be revealed.

**RF-8. Password confidentiality.** THE SYSTEM SHALL store only an irreversible password hash. The plaintext password SHALL never be stored, logged, included in any response, or repopulated in a re-rendered form.

**RF-9. Request authenticity.** THE SYSTEM SHALL require a valid CSRF token on every registration POST and SHALL reject a request whose token is missing, malformed, or not bound to the visitor's session, without writing data. The token SHALL be stable for the session, SHALL NOT expire, SHALL NOT rotate after a successful registration, and SHALL remain valid across multiple tabs and concurrent form instances of the same session.

> **Policy rationale (accepted trade-off).** A session-stable, non-expiring, non-rotating token trades single-use token semantics for multi-tab and concurrent-form usability. The exposure window is bounded by the session lifetime and by the fact that the token is delivered only in the registration form response to its own session. This trade-off is accepted for this increment and SHALL be revisited if sessions live beyond local development use.

**RF-10. Success hand-off.** Following the redirect specified in RF-1, the login page SHALL display a visible Spanish notice confirming that the account was created only when BOTH the `registro=ok` query parameter is present AND an unconsumed one-time session flag set by the successful registration is present. A request to the login page without the `registro=ok` parameter SHALL show no notice and SHALL NOT consume the flag. The flag SHALL be consumed only when the notice is displayed. Typing the login URL directly without the parameter SHALL show no notice; refreshing the login page after the redirect SHALL hide the notice. The notice SHALL use `role="status"` and SHALL be visible at both supported viewport widths. Refreshing the login page SHALL NOT resubmit the registration.

**RF-11. Re-render after server rejection.** IF server-side validation fails OR a duplicate is detected, THEN THE SYSTEM SHALL respond with HTTP status 200 and re-render the registration page with the raw, as-typed values of name, lastname, email, phone, and Cédula/ID preserved, the role selected through the control preserved in every role signal (visible text, hidden value, accessible name, and announcement), the terms checkbox checked state preserved, the fields marked with field-associated Spanish messages, and no technical or database details. Password and confirmation fields SHALL be rendered empty.

**RF-12. Responsive presentation.** THE SYSTEM SHALL preserve the existing responsive design at viewport widths of 1440 px and 402 px with the seven registration text/password input controls and their labels, the role control, the terms checkbox, and the submit action visible as defined in this specification, without horizontal overflow, clipping, or overlapping content.

**RF-13. Safe repeated access.** WHEN the registration page is requested with GET, THE SYSTEM SHALL perform no account or tutor-profile write; session and CSRF-token initialization are permitted. WHEN the same valid registration is submitted more than once, THE SYSTEM SHALL create at most one account; the later request SHALL be rejected as a duplicate with field errors and SHALL NOT follow the success redirect.

**RF-14. Page description.** THE SYSTEM SHALL provide the registration page with the meta description exactly "Regístrate en ProfeGo como estudiante o docente."

**RF-15. Non-field error presentation.** IF a non-field failure occurs (a missing, tampered, or unknown role on a direct request, an invalid CSRF token, or a database failure), THEN the non-field failure SHALL be evaluated before field validation and duplicate pre-checks, and THE SYSTEM SHALL respond with HTTP status 200 and re-render the registration form with only a form-level generic Spanish error message in a `role="alert"` region and no field errors, all non-password field values preserved, the terms checkbox checked state preserved, the role selected through the control preserved when it is one of the two supported roles and the default student role rendered otherwise, and a valid per-session CSRF token (issued if the visitor does not yet have one), and SHALL reveal no technical detail. The form-level message SHALL be generic and user-actionable: it SHALL state that registration could not be completed and invite the visitor to try again, without naming the internal cause.

**RF-16. Homepage tutor preselection.** WHEN a visitor activates the registration action labeled "Enseñar" on the homepage, THE SYSTEM SHALL open the registration page with the tutor role preselected — visible text "Docente", the hidden role value `docente`, and the accessible name defined in the Definitions section ("Tipo de cuenta: Docente, cambiar a Estudiante") — and SHALL NOT auto-submit the registration form. Nothing else about the homepage changes: visual styling stays as-is.

## Non-Functional Requirements

**RNF-1. Server-side security.** THE SYSTEM SHALL enforce the role allow-list, CSRF protection, and all validation rules on the server; client-side checks and hidden form values SHALL NOT be the only control. Credentials, secrets, and tokens SHALL NOT appear in client-side code, responses, or logs, except that the visitor's own per-session CSRF token is delivered only in the registration form response. The CSRF token SHALL NOT appear in logs or client-side code. Verifiable by direct requests that bypass the interface and by secret inspection.

**RNF-2. Credential protection.** THE SYSTEM SHALL satisfy the credential-protection behavior defined in RF-8: only a value that verifies the correct password and rejects an incorrect one may be stored. Verifiable by response and log inspection plus password verification.

**RNF-3. Safe output and data handling.** THE SYSTEM SHALL treat all user input as data: values rendered back into HTML SHALL be escaped, database access SHALL use parameterized statements, and user input SHALL never be concatenated into SQL. Verifiable by static inspection, by literal storage of an allowed-character SQL-injection payload, and by escaped re-rendering of a rejected XSS payload.

**RNF-4. Accessibility.** THE SYSTEM SHALL make the role control operable by keyboard and expose it as a button whose accessible name expresses the current value and the action as defined in the Definitions section, updated when the role changes; the selected state SHALL NOT be exposed through a pressed-state attribute. Visible text SHALL remain exactly "Estudiante" or "Docente". Each role change SHALL be announced through an `aria-live="polite"` status that communicates the current role; the exact copy is implementation-defined as stated in the Definitions section. THE SYSTEM SHALL programmatically associate every error message with its field, mark invalid fields, move focus to the first invalid field, and preserve visible focus indicators. Verifiable through the browser accessibility tree, keyboard interaction, and error-state inspection.

**RNF-5. Visual and responsive integrity.** THE SYSTEM SHALL preserve the existing typography, colors, spacing, and component styles while satisfying the responsive behavior in RF-12. Registration error text SHALL be rendered without reduced opacity, in a red with a contrast ratio of at least 4.5:1 against white, and at a minimum font size of 12 px on mobile. The error-text styling is an approved deviation from the current stylesheet. Verifiable by viewport inspection, screenshots at 1440 px and 402 px, and contrast measurement.

**RNF-6. Maintainability and conventions.** THE SYSTEM SHALL introduce no new framework, external dependency, build step, or database schema change. User-facing copy SHALL be in Spanish; code identifiers, filenames, comments, and technical documentation SHALL be in English. Format and composition rules SHALL be consistent between client and server; uniqueness SHALL remain a server-side concern. Verifiable by dependency inspection, static review, and the validation test suite.

**RNF-7. Data integrity and test isolation.** THE SYSTEM SHALL prevent partial accounts and test residue. Every test that writes — integration and browser end-to-end registrations included — SHALL use unique per-run identifiers, SHALL delete only the rows it created in a cleanup step that runs even on failure, deleting tutor profiles before their accounts, and SHALL assert zero remaining residue by the captured identifiers rather than by a universal literal prefix. Tests SHALL NOT truncate tables and SHALL NOT run in parallel. The identifier-generation mechanism is defined in the plan.

**RNF-8. Compatibility and graceful degradation.** THE SYSTEM SHALL work in the project's target environment (USBWebServer 8.6.5, PHP 8.1.3, MySQL 5.7.36) and SHALL complete student registration when JavaScript is disabled or fails to load. Verifiable by a JavaScript-disabled browser run.

## Verification Strategy

No direct database client is available to the team. Persistence is therefore never accepted on the basis of interface text alone: a criterion that requires stored data is verified by reading the stored row back through the application's own PHP data path or by a post-submit database read correlated with a real browser submission. Verification SHALL exercise the same data path used by a normal web request and SHALL NOT use an external SQL client or a separate ad-hoc connection to claim persistence. How the verification code reaches that data path — scripts, commands, tools, and evidence files — is decided in the plan and the task list; this specification requires only the outcome.

Before implementation changes the page, capture screenshots of the current registration page at 1440 px and 402 px. Those screenshots are the visual baseline for comparison; the Figma project remains the visual reference. Evidence for every layer is recorded in the validation report for this specification.

### Layer 0 — Syntax and static checks

Proves that the change set is valid and convention-compliant without executing product behavior: every touched PHP file passes syntax validation; the JavaScript tests run with a non-zero test count and all tests passing; the change set introduces no new dependency, build step, or database schema change and exposes no client-side secret, including the CSRF token; user-facing copy is Spanish and code identifiers are English; every SQL statement is parameterized, with user input never concatenated into SQL; and any security-relevant test hook introduced to verify RF-3 is unreachable from HTTP. This layer proves AC-12 and supports RNF-1, RNF-3, and RNF-6.

### Layer 1 — Unit tests without a database

Proves the validation and role logic without a database or DOM: every RF-6 rule and documented boundary, phone normalization and `+` equivalence, client/server rule and message parity demonstrated with the same case corpus, and the submitted-role allow-list (`estudiante`, `docente`, `admin`, unknown, missing) behind RF-2.4. No testing framework or new dependency is introduced. This layer proves the logic behind RF-2.4 (allow-list), RF-4, RF-5, and RF-6 (format and composition), and supports RNF-6. The password-hash round trip is proved in Layer 2.

### Layer 2 — Integration through the application's own data path

Proves persistence and domain behavior against the real `profeGo` database, using the application's own connection and registration path. A student registration creates exactly one row whose columns match the documented normalizations and whose stored password verifies the plaintext and is not the plaintext. A tutor registration additionally creates its pending tutor profile. Every duplicate scenario returns a domain error with no partial row and unchanged counts, including case-insensitive duplication and a database unique collision handled like an application-level duplicate. Atomicity (RF-3) is demonstrated by forcing the tutor-profile write to fail after the account insert through the test-only mechanism defined in the plan; the account must not be left behind. A SQL-injection payload composed only of characters the approved rules allow is stored literally and read back. Cleanup deletes only created rows — tutor profiles before accounts — and asserts zero residue. This layer is the authoritative proof of RF-1 (persistence), RF-3, RF-6 (server enforcement), RF-7 (domain-level duplicate handling), RF-8 (hash-only storage, round trip, and stored value), RNF-3 (literal storage), and RNF-7, and of AC-01, AC-02, AC-05 (zero-new-row part), and AC-08a.

### Layer 3 — Browser end-to-end

Drives a real browser against the running application at 1440 px and 402 px, using per-run identifiers with the RNF-7 cleanup. It proves the rendered experience: visibility and accessibility of every control and its `*` marker; the role control's signals, keyboard behavior, and live announcement (RF-2.1, RF-2.2); the GET preselection allow-list matrix (RF-2.3), the homepage "Enseñar" flow (RF-16), and the POST override of a preselection in a re-render (RF-2.4); client-side validation and focus management (RF-5); server re-renders with values, role, and terms preserved and passwords empty (RF-11); the success redirect and the notice lifecycle requiring both parameter and unconsumed flag (RF-10); duplicate field errors (RF-7); post-submit reads through the application data path; no-JavaScript registration (RNF-8); XSS rejection and escaped repopulation (AC-08b); the exact meta description (RF-14); and a clean console in JavaScript-enabled runs. This layer proves RF-1 (navigation), RF-2.1–RF-2.4 (including the POST override), RF-4, RF-5, RF-10, RF-11, RF-12, RF-14, RF-16, RNF-4, RNF-5, RNF-7, and RNF-8, and AC-03, AC-04, AC-05 (message and re-render part), AC-09, AC-10, AC-11, AC-13, AC-15, and AC-08b (repopulation part).

### Layer 4 — Negative and security checks

Exercises the server through direct requests that bypass the interface, asserting rejection and zero writes: role violations and CSRF violations, each producing the full RF-15/AC-14 presentation set; validation-before-duplicates precedence; the same token reused across tabs accepted, and a concurrent duplicate submission producing exactly one account; the test-only failure-injection hook not triggerable over HTTP, while the forced database failure in a CLI context produces the full AC-14 set; oversized boundary values rejected without truncation or database detail; a SQL-injection payload stored literally and read back; an XSS payload rejected and escaped; GET requests performing no write; a no-JavaScript native submission through the homepage preselection creating a `docente` account plus its pending profile (RF-2.2); and mandatory response inspection — plus log inspection when the sink is accessible — confirming that no password, hash, SQL error, stack trace, path, or misplaced CSRF token is exposed. This layer proves RF-2.4 (server role rejection), RF-2.2 (no-JavaScript preselected submission), RF-7 (security-level handling and precedence), RF-8 (responses mandatory; log when accessible), RF-9, RF-13, RF-15, RNF-1, RNF-2 (response part; log when accessible), and RNF-3 (escaping and SQL-injection part), and AC-06, AC-07, AC-08b, and AC-14.

### Requirement-to-layer mapping

| Requirement / clause | Proving layer(s) |
| --- | --- |
| RF-1 account creation | Layer 2 |
| RF-1 POST submission and native submit action | Layer 3 (no-JavaScript run) |
| RF-2.1 role control, toggle, naming, live announcement | Layer 3 |
| RF-2.2 hidden value and no-JavaScript semantics | Layers 3 and 4 |
| RF-2.3 GET preselection allow-list | Layer 3 |
| RF-2.4 submitted-role allow-list, POST override, and missing-role rejection | Layers 1, 3 (override), and 4 |
| RF-3 tutor profile and atomicity | Layer 2 (forced write failure per plan) |
| RF-4 required values enforced by the server | Layers 1 and 4 |
| RF-4 visible required markers and programmatic required state | Layer 3 |
| RF-5 client-side validation and preservation | Layers 1 (logic) and 3 (DOM) |
| RF-6 format and composition rules | Layers 1, 2, and 4 |
| RF-6 uniqueness | Layers 2 and 4 |
| RF-7 duplicate pre-check, precedence, per-field errors, collision handling | Layers 2, 3, and 4 |
| RF-8 hash-only storage and round trip | Layer 2 |
| RF-8 never logged, echoed, or repopulated | Layers 3 (repopulation) and 4 (responses mandatory; log when accessible) |
| RF-9 CSRF lifetime, stability, tabs, and rejection | Layer 4 |
| RF-10 login notice requiring both conditions and its lifecycle | Layer 3 |
| RF-11 re-render after validation or duplicate rejection | Layers 3 and 4 |
| RF-12 responsive presentation | Layer 3 |
| RF-13 no account or tutor-profile write on GET and at most one account | Layer 4 |
| RF-14 meta description | Layer 3 |
| RF-15 non-field error presentation | Layer 4 |
| RF-16 homepage tutor preselection | Layer 3 |
| RF-16 no-JavaScript submission via the homepage preselection | Layer 4 |
| RNF-1 server-side security | Layers 0 and 4 |
| RNF-2 credential protection | Layers 2 and 4 |
| RNF-3 safe output and data handling | Layers 0, 2, 3, and 4 |
| RNF-4 accessibility | Layer 3 |
| RNF-5 visual and responsive integrity | Layer 3 |
| RNF-6 maintainability and conventions | Layers 0 and 1 |
| RNF-7 data integrity and test isolation | Layers 2, 3, and 4 |
| RNF-8 compatibility and graceful degradation | Layer 3 |

### Criterion-to-layer mapping

| Criterion | Proving layer(s) |
| --- | --- |
| AC-01 | Layer 2 |
| AC-02 | Layer 2 |
| AC-03 | Layer 3 (including the Docente run) |
| AC-04 | Layer 3 |
| AC-05 | Layers 2, 3, and 4 |
| AC-06 | Layers 1 and 4 |
| AC-07 | Layer 4 |
| AC-08a | Layer 2 |
| AC-08b | Layers 3 and 4 |
| AC-09 | Layer 3 (including both-condition and refresh checks) |
| AC-10 | Layer 3 |
| AC-11 | Layer 3 |
| AC-12 | Layer 0 |
| AC-13 | Layer 3 |
| AC-14 | Layer 4 |
| AC-15 | Layer 3 |

## Acceptance Criteria

Each criterion names the requirements it exercises and states once the observable that defines pass/fail. Layer assignments live in the criterion-to-layer mapping above.

- **AC-01** A valid student registration creates exactly one `usuarios` row with role `estudiante` whose stored password verifies the submitted plaintext (RF-1, RF-6, RF-8).
- **AC-02** A valid tutor registration creates one `usuarios` row plus one `perfiles_docente` row in `pendiente` state (RF-3).
- **AC-03** Activating the role control toggles Estudiante/Docente and updates every role signal without submitting the form; the submitted role matches the selected role (RF-2.1, RF-2.2).
- **AC-04** An empty submission with JavaScript available does not navigate, flags every field, and focuses the first invalid field (RF-5).
- **AC-05** Each duplicated field returns its own Spanish field error, zero new rows are created, and validation and duplicate errors never combine (RF-7).
- **AC-06** An injected `admin` role, an unknown role, or a missing `rol` on a direct POST is rejected (RF-2.4, RF-15).
- **AC-07** A POST without a CSRF token or with a foreign token is rejected (RF-9, RF-15).
- **AC-08a** A SQL-injection payload composed only of characters the approved rules allow (for example `O'Brien' OR 'a' LIKE 'a'--` in the name field) is stored literally, the application keeps working, no query is altered, and reading the row back returns the literal value (RNF-3).
- **AC-08b** An XSS payload submitted in a field whose rules reject it produces a validation error, writes zero rows, and is repopulated escaped as inert text (RF-6, RNF-3).
- **AC-09** Successful registration redirects to `login.php?registro=ok`, and the notice appears only when both the parameter and the unconsumed one-time flag are present; refreshing hides the notice without resubmitting (RF-1, RF-10).
- **AC-10** Student registration still works with JavaScript disabled (RNF-8).
- **AC-11** The layout at 1440 px and 402 px shows every registration control and label without horizontal overflow, clipping, or overlap, and without visual regression against the baseline screenshots (RF-12).
- **AC-12** Every touched PHP file passes syntax validation, and the JavaScript test files run with a non-zero test count and all tests passing (Layer 0).
- **AC-13** The registration page's meta description is exactly "Regístrate en ProfeGo como estudiante o docente." (RF-14).
- **AC-14** A non-field failure re-renders the form with HTTP 200, only a form-level Spanish error in a `role="alert"` region and no field errors, non-password values and the terms state preserved, the selected role preserved when valid (otherwise `estudiante`), a valid CSRF token, and no technical detail (RF-15).
- **AC-15** Activating the homepage "Enseñar" control opens registration with visible text "Docente", hidden value `docente`, the corresponding accessible name, and no form submission (RF-2.3, RF-16).

## Illustrative Examples (non-normative)

The normative sources of behavior are the functional requirements, the non-functional requirements, and the RF-6 rule tables. These examples clarify intent and add no rules:

- `+50688887777` and `50688887777` are the same phone number; registering the second after the first is a duplicate (Definitions, RF-6).
- A second registration differing only in email or Cédula/ID letter case is a duplicate (RF-6).
- Without JavaScript, a visitor arriving through the homepage "Enseñar" link and submitting natively creates a `docente` account (RF-2.2, RF-2.3).
- Submitting the same valid registration twice concurrently creates exactly one account; the losing request is rejected as a duplicate (RF-13).
- A rejected value containing markup is repopulated as inert escaped text; no script executes and no markup is injected (AC-08b).

## Out of Scope

- Login form behavior, authentication, and authenticated session establishment after login. The registration page's PHP session used for the CSRF token and the one-time notice flag is in scope. Displaying the registration success notice on the login page is in scope; the login form itself remains out of scope.
- Password recovery, email verification, rate limiting, CAPTCHA, and account lockout.
- User dashboard or panel, profile editing, and administration of accounts.
- Creation or selection of `admin` accounts.
- Remember-me, social login, and multi-language interface support.
- Persisting terms acceptance, and real terms or privacy pages (their links remain placeholders).
- Changing the database schema or `database/ProfeGo.sql`.
- Switching the role in the browser without JavaScript. Without JavaScript the role can only be the value rendered in the hidden input, which is `docente` only when the page was opened through the homepage preselection.
- Redesigning the established registration page beyond the approved field, required-marker, error-text, and role-control changes.
- Running tests that write to the database in parallel.

## Documented limitations

- Terms acceptance is required but not persisted because the account schema has no column for it.
- Without JavaScript the role cannot be switched in the browser; registration creates the role rendered in the hidden input. A no-JavaScript submission from the homepage "Enseñar" preselection therefore creates a `docente` account. This is documented behavior, not an error.
- No email verification is performed; any syntactically valid ASCII email is accepted.
- No rate limiting or abuse throttling is applied.
- A successful registration does not sign the visitor in.
- Duplicate-field errors allow account enumeration for email, phone, and Cédula/ID.
- Without JavaScript, validation errors appear only after a full POST and page reload.
- Tests that write, including integration and browser end-to-end runs, briefly write to the local `profeGo` development database before cleaning up.
- An aborted integration or browser test run can leave residue; cleanup is by captured identifiers only and tables are never truncated. This residue risk is accepted for local development and tests never run in parallel.
- If the database is unavailable, registration fails with the generic form-level error defined in RF-15; the outage is not otherwise handled, retried, or reported in detail.
- Log verification depends on access to the PHP error log; when the sink cannot be read, the "never logged" clause of RF-8 is recorded as unverified and listed here rather than claimed as satisfied.

## Completion Criteria

- A visitor can create a student or tutor account from the registration page and is redirected to the login page with a visible Spanish confirmation.
- All acceptance criteria AC-01 through AC-15 are verified with recorded evidence; no AC remains failed or unverified.
- Any unverified clause outside AC-01 through AC-15 is explicitly listed under Documented limitations and is not claimed as satisfied.
- The specification moves to `Implemented` only after the outcomes above are demonstrated.

## Open Questions

- None. Every previously open item has an approved decision recorded in this document: phone identity (digits only), Cédula/ID stored as typed, simultaneous duplicate reporting, validation/duplicate precedence, non-field error presentation, login-notice conditions, CSRF lifetime and its accepted trade-off, role-control semantics, password and email rules, re-render preservation, log verification when the sink is accessible, and test cleanup for every writing layer.

### Accepted deviations and limitations

- Visible `*` required markers on the seven text/password input controls and the terms checkbox (RF-4).
- Adjusted error-text color and minimum size for contrast and legibility (RNF-5).
- The remaining known limitations are listed under Documented limitations and do not require further decisions for this increment.
