# Spec 001 - Create Account

**Status:** Approved

## Context and Objective

ProfeGo is an academic tutoring platform that connects students with tutors. The registration page is currently a static mock-up: the role is printed as a fixed "Estudiante" label, the form has no submission behavior, and the fields do not yet match the account data the platform needs.

This specification defines the first end-to-end capability of the platform: a visitor can create a student or tutor account from the registration page. The account is validated on the client when JavaScript is available and independently on the server, persisted securely, and followed by a redirect to the login page with a visible confirmation. The existing database schema is authoritative and must not be modified; the form is therefore aligned with it by adding a phone field and separating the combined email/ID field into two fields.

Server-side validation is authoritative. Client-side validation enhances the experience for student registration but is not required for the flow to work. The role control is required to switch the role in the browser; without JavaScript the role cannot be switched, so registration creates the role rendered in the hidden input: `estudiante` by default, or `docente` when the page was opened through the allow-listed homepage preselection. The homepage action labeled "Enseñar" opens registration with the tutor role preselected; it does not submit the form.

The specification also defines how account creation will be verified. The team has no direct database client, so persistence must be demonstrated through the application's own data path and a real browser, not inferred from interface text. The Verification Strategy section states which layer proves each acceptance criterion.

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

**RF-2. Role selection.** THE SYSTEM SHALL render the role control as a `type="button"` control whose activation never submits the form. A fresh GET without a preselection SHALL start with role `estudiante`; a fresh GET whose request carries an allow-listed preselection (`docente`, or `estudiante` explicitly) SHALL render that preselected role in all role signals: the visible text, the hidden value, the accessible name defined in the Definitions section, and the polite announcement. ANY other GET value (tampered, repeated, unknown, or empty) SHALL render `estudiante`. A POST submission's role SHALL always override the GET preselection. The preselection only influences initial rendering; it never bypasses the allow-list and is never written to the database, so an injected value cannot create an account outside `estudiante`/`docente`. The form SHALL always include a hidden `rol` input whose value is the selected role (`estudiante` by default), so a no-JavaScript POST submits the hidden input's rendered value: `estudiante` from a default or explicitly-`estudiante` GET, and `docente` when the page was opened through the allow-listed homepage preselection. WHEN the visitor activates the control, THE SYSTEM SHALL switch the selected role to the other supported value and update the visible text, the hidden value, the accessible name defined in the Definitions section, and the polite announcement of the new role. WHEN the form is submitted, THE SYSTEM SHALL assign only the submitted allow-listed role (`estudiante` or `docente`) and SHALL reject a missing role on a direct request or any role value other than these two. An error re-render SHALL start from the role selected through the control, reflected in the visible text, hidden value, and accessible name.

**RF-3. Tutor profile.** WHEN an account is created with role `docente`, THE SYSTEM SHALL create its associated tutor profile in the same transaction, with verification state `pendiente`, average rating `0.00`, and total tutoring sessions `0`. IF any part of the creation fails, THEN THE SYSTEM SHALL persist neither the account nor the tutor profile. The test-only injection point used to verify this atomicity SHALL be available only in a CLI/test context and SHALL be disabled for web requests.

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

**RF-10. Success hand-off.** Following the redirect specified in RF-1, the login page SHALL display a visible Spanish notice confirming that the account was created only when BOTH the `registro=ok` query parameter is present AND an unconsumed one-time session flag set by the successful registration is present. A request to the login page without the `registro=ok` parameter SHALL show no notice and SHALL NOT consume the flag. The flag SHALL be consumed only when the notice is displayed. Typing the login URL directly without the parameter SHALL show no notice; refreshing the login page after the redirect SHALL hide the notice. The notice SHALL use `role="status"` and SHALL be visible at both supported viewport widths. Refreshing the login page SHALL NOT resubmit the registration.

**RF-11. Re-render after server rejection.** IF server-side validation fails OR a duplicate is detected, THEN THE SYSTEM SHALL respond with HTTP status 200 and re-render the registration page with the raw, as-typed values of name, lastname, email, phone, and Cédula/ID preserved, the role selected through the control preserved, the terms checkbox checked state preserved, the fields marked with field-associated Spanish messages, and no technical or database details. Password and confirmation fields SHALL be rendered empty.

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

**RNF-7. Data integrity and test isolation.** THE SYSTEM SHALL prevent partial accounts and test residue. Every test that writes, including integration and browser end-to-end registrations, SHALL use a unique per-run token composed of the process identifier, a timestamp, and a random suffix, encoded per field: a unique token in the email local part, digits only for phone, an allowed-character form for Cédula/ID, and a short suffix that keeps names within the 30-character limit. Each test SHALL delete only the rows it created in a `finally` block or an equivalent cleanup step that runs even on failure, deleting tutor profiles before their accounts, and SHALL assert zero remaining residue by the captured identifiers rather than by a universal literal prefix. Tests SHALL NOT truncate tables and SHALL NOT run in parallel. The 26-character SQL-injection payload used by the literal-storage test is exempt from tokenization because uniqueness is carried by the accompanying email, phone, and Cédula/ID test values; cleanup still uses captured identifiers. The intent is unchanged: unique values, own-row cleanup, and zero residue. Verifiable through integration and browser-run results and post-test row counts.

**RNF-8. Compatibility and graceful degradation.** THE SYSTEM SHALL work in the project's target environment (USBWebServer 8.6.5, PHP 8.1.3, MySQL 5.7.36) and SHALL complete student registration when JavaScript is disabled or fails to load. Verifiable by a JavaScript-disabled browser run.

## Verification Strategy

No direct database client is available to the team. Persistence is therefore never accepted on the basis of interface text alone: a criterion that requires stored data is verified by reading the stored row back through the application's own PHP data path or by a post-submit database read correlated with a real browser submission. Verification SHALL exercise the same data path used by a normal web request and SHALL NOT use an external SQL client or a separate ad-hoc connection to claim persistence. How the verification code reaches that data path is decided in the plan; this specification requires only the outcome.

Before implementation changes the page, capture screenshots of the current registration page at 1440 px and 402 px. Those screenshots are the visual baseline for comparison; the Figma project remains the visual reference. Store them with the validation evidence. Evidence for every layer is recorded in the task/validation report for this specification.

### Layer 0 - Syntax and static checks

- Run `php -l` on every PHP file touched. Expected result: "No syntax errors detected" and exit code 0.
- Run `node --test` against the explicitly named JavaScript test files defined in the plan. Expected result: a non-zero test count and exit code 0; a run that executes zero tests exits successfully but SHALL NOT be accepted as evidence.
- Inspect the change set for prohibited items: new dependencies, build steps, schema modifications, and client-side secrets, including the CSRF token. Separately confirm the required conventions: Spanish user-facing copy, and English identifiers, filenames, comments, and technical documentation.
- Inspect database access statically: confirm that queries use parameterized statements and that user input is never concatenated into SQL.
- Inspect that the test-only rollback injection point is unreachable from HTTP: available only in a CLI/test context and disabled for web requests.

This layer proves AC-12, supports RNF-1, RNF-3, and RNF-6, and verifies the RF-3 seam gating by static inspection.

### Layer 1 - Unit tests without a database

- Test the validation rules and the role-state transition without a DOM under `node --test`, using the mechanism decided in the plan. Cover empty values, whitespace, boundary lengths and bytes, accents, punctuation-only values, email formats and non-ASCII input, phone normalization and `+` equivalence, Cédula/ID characters, password composition, confirmation mismatch, and terms.
- Exercise the server-side validator through a committed PHP CLI assertion script with explicit checks and a non-zero exit code on failure. No testing framework or new dependency is introduced. Use the same cases as the JavaScript tests to demonstrate rule parity.
- Test the server-side role allow-list with `estudiante`, `docente`, `admin`, an unknown value, and a missing value.

This layer proves the logic behind RF-2 (submitted-role allow-list), RF-4, RF-5, and RF-6 (format and composition), and supports RNF-6 and AC-06 in part. The password-hash round trip is proved in Layer 2.

### Layer 2 - Integration through the application's own data path

- Run an integration script against the real `profego` database that uses the application's configuration and the same data path as the web request. The connection mechanism is decided in the plan.
- After a successful student registration, SELECT the row by its generated identifier and assert every column: identifier, name, lastname, lowercase email, digits-only phone, Cédula/ID stored as typed, role `estudiante`, state `activo`, a non-null registration date, and a stored value that verifies the plaintext password. Assert that the stored value is not the plaintext and that the Cédula/ID column uses the case-insensitive `utf8mb4_unicode_ci` collation.
- After a successful tutor registration, assert the same account data plus the associated tutor profile with verification state `pendiente`, rating `0.00`, and zero sessions.
- For each duplicate scenario (email, phone, Cédula/ID), assert a domain error, no partial row, and unchanged row counts. Submit a combination containing more than one duplicate, with all other field rules satisfied, and assert one error per duplicated field. Also assert case-insensitive duplication for email and Cédula/ID, and that a database unique collision is handled like an application-level duplicate.
- Verify rollback using a test-only injection point that forces the tutor-profile insert to fail after the account insert: assert that the account is not left behind. The seam SHALL be unreachable from HTTP: available only in a CLI/test context and disabled for web requests. Its gating is verified by static inspection in Layer 0 and by a direct HTTP attempt in Layer 4. The seam exists only for verification and does not change production behavior.
- Submit a SQL-injection payload composed only of approved characters (for example `O'Brien' OR 'a' LIKE 'a'--`) as a name through the registration data path, accompanied by unique email, phone, and Cédula/ID values; assert the account is created, the value is stored literally, the application keeps working, and a subsequent query returns only the expected row. Read the row back to confirm the literal value.
- Clean up in a `finally` block using only the captured identifiers created by the test, deleting tutor profiles before accounts, then assert zero rows remain for those identifiers. Never truncate tables. Apply the per-run token encoding defined in RNF-7.

This layer is the authoritative proof of RF-1 (persistence), RF-3, RF-6 (server enforcement), RF-7 (domain-level duplicate handling), RF-8 (hash-only storage, round trip, and stored value), RNF-3 (literal storage), and RNF-7, and of AC-01, AC-02, AC-05 (zero-new-row part), and AC-08a.

### Layer 3 - Browser end-to-end

- Drive Chrome DevTools/CDP against `http://localhost/pages/register.php` at viewport widths of 1440 px and 402 px. The document root is the project folder: this URL returns HTTP 200. The path `http://localhost/ProfeGo/...` is not the entry point and returns 404.
- From `http://localhost/`, activate the homepage action labeled "Enseñar"; assert it opens the registration page with visible text "Docente", hidden value `docente`, the accessible name "Tipo de cuenta: Docente, cambiar a Estudiante", the live-region text for Docente, and no automatic form submission or account creation.
- Request the registration page with `?rol=admin`, an unknown `rol` value, a repeated `rol` parameter, an empty `rol`, and an explicit `?rol=estudiante`; assert every one of these renders the student role signals (visible text "Estudiante", hidden value `estudiante`, accessible name "Tipo de cuenta: Estudiante, cambiar a Docente", and the student announcement) and writes nothing.
- At both widths, confirm the seven text/password input controls and their labels, the role control, the terms checkbox, and the submit action are visible per the definition, reachable, and free of horizontal overflow, clipping, or overlap. Confirm each of the eight required controls displays its `*` marker and exposes `required` and `aria-required` programmatically. Compare against the pre-change screenshots and the Figma reference.
- Capture the accessibility tree for the role control and for a required control and an invalid control: confirm the role control's name, button role, and absence of a pressed state; the required state and error association of the other controls; and visible focus indicators. Measure error-text contrast against white (at least 4.5:1) and confirm error text is at least 12 px at 402 px.
- Initial state: on a fresh GET without preselection, visible text "Estudiante", hidden value `estudiante`, accessible name "Tipo de cuenta: Estudiante, cambiar a Docente". When arriving through the homepage "Enseñar" link, the initial state is the preselected Docente state; the fresh-GET default remains Estudiante. From the student state, activate the control by keyboard: activation does not submit, the visible text becomes "Docente", the hidden value becomes `docente`, the accessible name becomes "Tipo de cuenta: Docente, cambiar a Estudiante", and the change is announced through the polite live region. From the preselected Docente state, activation returns all signals to the student state; a further activation toggles back. No activation submits.
- Submit with empty fields: no navigation occurs, every field is flagged, and the first invalid field receives focus.
- Submit a mixed valid/invalid form (bad email, bad phone, bad Cédula/ID, short password, mismatched confirmation) and assert one message per field and rule, focus on the first invalid field, and preservation of the other values.
- With JavaScript enabled, select Docente, check the terms, and submit a valid form whose email already exists (a server-only failure); assert HTTP status 200, the full RF-11 re-render with raw as-typed values, the selected role, and the terms checked state preserved, the password and confirmation fields empty, field-associated Spanish errors, and no technical or database details.
- With JavaScript disabled, submit an invalid-format form with the terms checked to exercise server-side validation re-render; assert HTTP status 200, raw as-typed values, the selected role, the terms checked state preserved, empty password fields, field-associated Spanish errors, and no technical or database details.
- With JavaScript enabled, attempt submission with terms unchecked: assert no navigation, the checkbox is flagged, and focus moves to it.
- Complete a valid student registration and observe the redirect to `login.php?registro=ok`; confirm the visible Spanish success notice in a `role="status"` region at both widths. Confirm the notice requires both conditions: a request to `login.php?registro=ok` with the unconsumed flag shows the notice and consumes it; a request to `login.php?registro=ok` without a flag shows no notice; a request to the login URL without the parameter shows no notice and, when made while a flag is still present, does not consume it; refreshing shows no notice and does not resubmit. Confirm the browser console has no errors or warnings.
- Complete a valid Docente registration: toggle the role, submit, observe the redirect, and perform a post-submit SELECT through the application's data path confirming `rol='docente'` plus the associated pending tutor profile.
- Register a duplicate email, then a combination of duplicates, and assert HTTP status 200, the RF-11 re-render with raw as-typed values, the selected role, the terms checked state, empty password fields, a field-associated Spanish message for each duplicated field, zero new accounts, and no technical or database details. Confirm that a response containing duplicate errors contains no field validation errors and vice versa.
- Perform a post-submit SELECT through the application's data path and confirm that the values entered in the browser are present after the documented normalizations (lowercase email and digits-only phone, Cédula/ID as typed).
- Repeat a student registration with JavaScript disabled and confirm a student account is still created.
- Submit an XSS payload (for example `"><script>alert(1)</script>`) in a field whose approved rules reject it, bypassing client-side validation or with JavaScript disabled; confirm a validation error, zero new rows, and the rejected value repopulated as inert text. Inspect the DOM and console to confirm no script executes and no markup is injected.
- Assert the page's meta description is exactly "Regístrate en ProfeGo como estudiante o docente."
- Use the per-run token encoding defined in RNF-7 for every browser registration; capture the identifiers of every account and tutor profile created and delete them in a cleanup step that runs even when a step fails, deleting tutor profiles before accounts; assert zero residue by those identifiers.

This layer proves RF-1 (navigation), RF-2, RF-4, RF-5, RF-10, RF-11, RF-12, RF-14, RF-16, RNF-4, RNF-5, RNF-7, and RNF-8, and AC-03, AC-04, AC-05 (browser message and re-render part), AC-09, AC-10, AC-11, AC-13, AC-15, and AC-08b (repopulation part).

### Layer 4 - Negative and security checks

- Send a direct POST that bypasses client-side validation with empty and invalid values; assert rejection and zero account or tutor-profile writes.
- Send a request containing both an invalid field and a value that would otherwise be a duplicate; assert only the field validation errors are returned and no duplicate error is combined with them.
- Send `rol=admin`, an unknown role, and a direct request with no `rol`; assert only a form-level Spanish error (no field errors) in a `role="alert"` region, HTTP status 200, preservation of non-password values and the terms checked state, the selected role preserved when valid and the default student role rendered otherwise, a valid CSRF token, zero account or tutor-profile writes, and no technical detail.
- Submit a POST with no CSRF token, a malformed token, and a token from another session; assert HTTP status 200, only a form-level Spanish error (no field errors) in a `role="alert"` region, preservation of non-password values and the terms checked state, the selected role preserved when valid and the default student role rendered otherwise, a valid CSRF token, zero account or tutor-profile writes, and no technical detail.
- Use the same CSRF token from two tabs with different valid registration data; assert both registrations are accepted, neither request is rejected for token reuse, and the accounts are created as submitted.
- Submit the same valid registration twice concurrently; assert one account is created and the later request is rejected as a duplicate per RF-13, with no success redirect.
- Inspect the token implementation statically and compare the token value before and after a successful registration; assert the value is unchanged and reuse is accepted.
- Attempt to trigger the test-only rollback injection point through a direct HTTP request; assert it cannot be triggered and that the request follows normal HTTP behavior.
- Force a write failure through the documented test-only injection point in a CLI/test context that drives the same request pipeline and captures the produced HTTP response; assert HTTP status 200, only a form-level Spanish error (no field errors) in a `role="alert"` region, preservation of non-password values and the terms checked state, the selected role preserved when valid and the default student role rendered otherwise, a valid CSRF token, no technical detail, zero account or tutor-profile writes, no partial account, and no crash. This case SHALL be forced and SHALL NOT be recorded as unverified.
- The role, CSRF, and database-failure cases SHALL exercise the same non-field handler and SHALL each assert the full AC-14 set.
- Send oversized values at the documented boundaries; assert rejection without truncation or database error leakage.
- Submit a SQL-injection payload composed only of approved characters by direct POST, accompanied by unique email, phone, and Cédula/ID values; assert the value is stored literally, queries behave unchanged, and a read-back returns the literal value.
- Submit an XSS payload (for example `"><script>alert(1)</script>`) by direct POST in a field that rejects it; assert a validation error, zero account or tutor-profile writes, and a re-rendered form in which the rejected value is escaped as inert text. Inspect the response and DOM for unescaped angle brackets, a script element, or executed script.
- Request the registration page with GET, including query parameters; assert no account or tutor-profile write (session and CSRF-token initialization are permitted).
- With JavaScript disabled or bypassed, request registration through the allow-listed homepage preselection `?rol=docente` and submit a valid form natively; assert a tutor account with role `docente` and its pending tutor profile are created, using the per-run token and the RNF-7 cleanup and zero-residue assertions. This confirms that a no-JavaScript visitor arriving via the homepage "Enseñar" link creates a tutor account.
- Submit a direct POST without `terms`; assert zero rows and a field-specific terms error.
- Inspect every response for the test password, the stored hash, SQLSTATE/PDO messages, stack traces, file paths, and secrets other than the visitor's own per-session CSRF token in the registration form response; assert none are exposed. Response inspection is mandatory.
- Inspect the PHP error log configured by the USBWebServer environment for the same values and the CSRF token; assert none are exposed. Log inspection is mandatory when the sink is accessible. If the sink cannot be read, record the "never logged" clause of RF-8 as unverified and list it under Documented limitations rather than passing it.

This layer proves RF-2 (server rejection and no-JavaScript preselected submission), RF-7 (security-level handling and precedence), RF-8 (responses mandatory; log when accessible), RF-9, RF-13, RF-15, RNF-1, RNF-2 (response part; log when accessible), and RNF-3 (escaping and SQL-injection part), and AC-06, AC-07, AC-08b, and AC-14.

### Requirement-to-layer mapping

| Requirement / clause | Proving layer(s) |
| --- | --- |
| RF-1 account creation | Layer 2 |
| RF-1 POST submission and native submit action | Layer 3 (no-JavaScript run) |
| RF-2 role control, hidden value, toggle, naming, live announcement | Layer 3 |
| RF-2 submitted-role allow-list and missing-role rejection | Layers 1 and 4 |
| RF-2 GET preselection allow-list | Layer 3 |
| RF-3 tutor profile and atomicity | Layer 2 (rollback seam), with gating checked in Layers 0 and 4 |
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

- **AC-01** A valid student registration creates a row in `usuarios` with role `estudiante` and a password value that verifies the submitted password.
- **AC-02** A valid tutor registration creates a row in `usuarios` plus a row in `perfiles_docente` in `pendiente` state.
- **AC-03** Activating the role control toggles "Estudiante" and "Docente", updates the hidden value and accessible name without submitting the form, and the submitted role matches the selected role.
- **AC-04** Submitting with empty fields, with JavaScript enabled, does not navigate and flags every error.
- **AC-05** When all field rules pass, a duplicate email, phone, or Cédula/ID, singly or simultaneously, produces a field-specific Spanish error for each duplicated field and zero new rows; validation errors and duplicate errors are never combined.
- **AC-06** An injected `rol=admin`, an unknown role, or a missing `rol` on a direct request is rejected.
- **AC-07** A POST without a CSRF token or with a foreign token is rejected.
- **AC-08a** A SQL-injection payload composed only of characters the approved rules allow (for example `O'Brien' OR 'a' LIKE 'a'--` in the name field) is stored literally, the application keeps working, and no query is altered; verified by reading the row back.
- **AC-08b** An XSS payload such as `"><script>alert(1)</script>` submitted in a field whose approved rules reject it produces a validation error, writes zero rows, and, when the rejected value is repopulated in the re-rendered form, is output-escaped so no script executes and no markup is injected.
- **AC-09** Successful registration redirects to `login.php?registro=ok` and shows a visible notice only when both the parameter and the unconsumed one-time session flag are present; refreshing hides the notice without resubmitting, a direct visit without the parameter shows no notice and does not consume the flag, and the parameter without the flag shows no notice.
- **AC-10** Student registration still works when JavaScript is disabled.
- **AC-11** The layout at 1440 px and 402 px shows the seven registration text/password input controls and their labels, the role control, the terms checkbox, and the submit action without horizontal overflow, clipping, or overlap, and without visual regressions against the baseline screenshots.
- **AC-12** `php -l` reports no syntax errors for every touched PHP file, and `node --test` runs the explicitly named JavaScript test files with a non-zero test count and all tests passing.
- **AC-13** The registration page's meta description is exactly "Regístrate en ProfeGo como estudiante o docente."
- **AC-14** A non-field failure (missing, tampered, or unknown role, invalid CSRF token, or database failure) re-renders the form with HTTP status 200, only a form-level Spanish error in a `role="alert"` region and no field errors, all non-password values and the terms checked state preserved, the selected role preserved when valid (otherwise the default student role), a valid CSRF token, and no technical detail.
- **AC-15** Activating the homepage "Enseñar" control opens the registration page with visible text "Docente", hidden value `docente`, the corresponding accessible name, and no form submission.

## Edge Cases

- Empty or whitespace-only values in any required field are rejected with a field-level error; no navigation and no account or tutor-profile write occur.
- Names containing accents, ñ, spaces, hyphens, or apostrophes are accepted; names containing digits or other symbols are rejected. A name of 30 characters is accepted and 31 is rejected. A value made only of spaces, hyphens, or apostrophes is rejected because it contains no letter.
- Mixed-case email is accepted and stored in lowercase. A second registration differing only in email case is rejected as a duplicate. An email of 150 characters is accepted and 151 is rejected. Invalid formats and non-ASCII addresses are rejected.
- Phone input containing spaces, hyphens, or parentheses is normalized before validation and storage. An optional leading `+` is removed, so `+50688887777` and `50688887777` are the same number and the second is a duplicate. A `+` anywhere else, or a non-digit character after normalization, is rejected. Lengths of 6, 7, 20, and 21 digits exercise the boundaries; only 7 through 20 are accepted, and the bound applies to stored digits.
- Cédula/ID values at 4, 5, 20, and 21 characters exercise the boundaries. Spaces and underscores are rejected. A value made only of hyphens is rejected because it contains no alphanumeric character. The value is stored as typed after trimming, and a value differing only in case is a duplicate.
- Passwords of 7, 8, 72, and 73 UTF-8 bytes exercise the boundaries. A password containing only letters or only digits is rejected. A password with accented characters counts each character's UTF-8 bytes. A confirmation that does not match is rejected.
- An unchecked terms checkbox blocks registration and is reported as a field error, both in the client and on a direct server request. Acceptance is not persisted.
- Every duplicated field in a single submission receives its own error in the same response. Duplicate pre-checks run only after all field rules pass; validation errors and duplicate errors are never combined. A duplicate detected by the database collision is handled exactly like an application-level duplicate: a Spanish field error, no partial row, and no technical detail.
- A missing role on a direct request, or an unknown or injected role, is rejected with a form-level error. A role selected as `docente` is preserved when the form is re-rendered after a field error. A no-JavaScript POST submits the hidden input's rendered value (RF-2); when that value is `docente`, a tutor account is created.
- A GET preselection of `docente` renders all three role signals as Docente: visible text "Docente", hidden value `docente`, and the accessible name "Tipo de cuenta: Docente, cambiar a Estudiante"; the live region also reflects Docente.
- A GET preselection of `estudiante` renders the same role signals as the default state.
- Any other GET preselection value, including `admin`, an unknown value, a repeated value, or an empty value, renders `estudiante` and writes nothing.
- A form error re-render keeps the role selected on submit; when the visitor did not change it, that is the preselected tutor role.
- Homepage actions that carry no preselection ("Crear Cuenta" in the header, "Aprender" in the CTA, and the login link) continue to open registration/login with the default student role or no role, exactly as today.
- A missing, malformed, or foreign CSRF token produces a form-level Spanish error, HTTP 200, and no account or tutor-profile write; the re-rendered form contains a valid token. The token is stable for the session, does not expire, and is not rotated after success. The same token used from two tabs with different valid data is accepted for both registrations; submitting the same valid registration twice concurrently creates at most one account and rejects the later request as a duplicate.
- A GET request to the registration page, including one with query parameters, performs no account or tutor-profile write; session and CSRF-token initialization are permitted.
- Concurrent or repeated submission of the same valid data creates at most one account. The losing request is rejected as a duplicate with field errors; it never creates a second row and never follows the success redirect.
- If the tutor profile cannot be created, the account is not left behind.
- If JavaScript is unavailable or the script fails to load, the classic POST still registers the role rendered in the hidden input (student by default, or tutor when the page was opened through the homepage preselection), and server-side validation remains fully enforced. Without JavaScript, validation errors appear only after a full POST and page reload.
- Reloading the login page after a successful registration does not resubmit the form and hides the notice. Typing the login URL without the parameter, or requesting `login.php?registro=ok` without the one-time flag, shows no notice; a request without the parameter does not consume a flag that is still present.
- A non-field failure shows only the form-level error, preserves non-password values, preserves the selected role when valid (otherwise the default student role), preserves the terms checked state, and reveals no technical detail.
- Very long error messages wrap within the card at both supported viewport widths without causing horizontal overflow. Error text meets the contrast and minimum-size requirements.
- A SQL-injection payload composed only of approved characters (for example `O'Brien' OR 'a' LIKE 'a'--`) is accepted, stored literally, and does not alter query behavior; reading the row back returns the literal value.
- An XSS payload containing disallowed characters (for example `"><script>alert(1)</script>`) is rejected with a field error and zero rows; when the rejected value is repopulated, it is escaped as inert text and no script executes.

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

### Documented limitations

- Terms acceptance is required but not persisted because the account schema has no column for it.
- Without JavaScript the role cannot be switched in the browser; registration creates the role rendered in the hidden input. A no-JavaScript submission from the homepage "Enseñar" preselection therefore creates a `docente` account. This is documented behavior, not an error.
- No email verification is performed; any syntactically valid ASCII email is accepted.
- No rate limiting or abuse throttling is applied.
- A successful registration does not sign the visitor in.
- Tests that write, including integration and browser end-to-end runs, briefly write to the real `profego` database before cleaning up.
- An aborted integration or browser test run can leave residue; cleanup is by captured identifiers only and tables are never truncated.
- If the database is unavailable, registration fails with the generic form-level error defined in RF-15; the outage is not otherwise handled, retried, or reported in detail.
- Duplicate-field errors allow account enumeration for email, phone, and Cédula/ID.
- Without JavaScript, validation errors appear only after a full POST and page reload.
- Log verification depends on access to the PHP error log; when the sink cannot be read, the "never logged" clause is recorded as unverified and listed here.

## Completion Criteria

- A visitor can create a student or tutor account from the registration page and is redirected to the login page with a visible Spanish confirmation.
- All acceptance criteria AC-01 through AC-15 are verified with recorded evidence, and no AC remains failed or unverified.
- Successful registrations produce exactly one account, and tutor registrations additionally produce exactly one pending tutor profile.
- Duplicate and invalid requests produce zero new rows, and all tests that write, including browser runs, leave zero residue.
- No plaintext password, hash value, SQL error, stack trace, or internal path appears in any response or re-rendered form; response inspection is mandatory.
- When the PHP error log sink is accessible, none of those values or the CSRF token appears there; when it is not accessible, the log clause is explicitly recorded as unverified under Documented limitations and is not claimed as satisfied.
- Any unverified clause that is not one of AC-01 through AC-15 is explicitly listed under Documented limitations and is not claimed as satisfied.
- Registration remains functional for students with JavaScript disabled.
- The layout at 1440 px and 402 px satisfies RF-12 and AC-11.
- The meta description is exactly "Regístrate en ProfeGo como estudiante o docente."
- No new framework, external dependency, build step, or database schema change is introduced.
- The specification can move to `Implemented` only after the above outcomes are demonstrated.

## Open Questions

- None. Every previously open item has an approved decision recorded in this document: phone identity (digits only), Cédula/ID stored as typed, simultaneous duplicate reporting, validation/duplicate precedence, non-field error presentation, login-notice conditions, CSRF lifetime, role-control semantics, password and email rules, re-render preservation, log verification when the sink is accessible, and test cleanup for every writing layer.

### Accepted deviations and limitations

- Visible `*` required markers on the seven text/password input controls and the terms checkbox (RF-4).
- Adjusted error-text color and minimum size for contrast and legibility (RNF-5).
- The remaining known limitations are listed under Documented limitations and do not require further decisions for this increment.
