# Layer 1 Evidence: Shared Validation Case Corpus (T3.1)

Date: 2026-11-18
Spec: 001-create-account
Task: T3.1 (RF-4, RF-5, RF-6; RNF-6)
Artifact: `tests/fixtures/validation-cases.json`

## 1. Purpose

`tests/fixtures/validation-cases.json` is the single shared rule-case corpus consumed by
both the PHP validator assertion script (T3.2) and the JavaScript validation tests (T3.3).
Each case supplies an input fragment, the expected normalized values, and the expected
per-field error map. Recording these once prevents the two validators from drifting apart.

Message strings in the corpus are the exact approved Spanish copy from
`specs/001-create-account/plan.md`, "Validation and messages". To keep this document ASCII,
messages are referenced by ASCII rule key (for example `name.length`) instead of repeating
product copy. Case count: 74 (`_meta` and `baseValidRecord` are excluded).

## 2. Parse checks

Working directory: repository root. The known `php_gd2.dll` startup warning is ignored;
PHP stderr was discarded with `2>$null`.

PHP 8.1.3 (`C:\Users\isaac\Documents\USBWebServer\php\php.exe`):

    php.exe -r '$d = json_decode(file_get_contents(''tests/fixtures/validation-cases.json''), true); echo ''json_last_error='' . json_last_error() . '' ('' . json_last_error_msg() . '');'' . PHP_EOL; echo ''cases='' . count($d[''cases'']) . PHP_EOL;'

Result (exit 0):

    json_last_error=0 (No error)
    cases=74

Node v24.21.0:

    node -e "const d = JSON.parse(require('fs').readFileSync('tests/fixtures/validation-cases.json','utf8')); console.log('JSON.parse OK; cases=' + d.cases.length);"

Result (exit 0):

    JSON.parse OK; cases=74

Both runtimes parse the final corpus without error and agree on the case count.

## 3. Length and byte verification

Three independent checks were run with both runtimes (inline Node scripts and inline PHP
`php -r` scripts). No validator, test harness, or other product code was created by T3.1.

| Check | Node method | PHP method | Checks | Result |
| --- | --- | --- | --- | --- |
| Password UTF-8 bytes (`expectedBytes`, both fields) | `Buffer.byteLength(v, 'utf8')` | `strlen(v)` | 30 | PASS |
| Email character length (150/151/multi) | `[...v].length` | `mb_strlen(v)` | 3 | PASS |
| Name/lastname character length (30/31/multi) | `[...v].length` | `mb_strlen(v)` | 7 | PASS |

Both runtimes printed `ALL ... ASSERTIONS PASS`, exit 0. Accent-boundary cases confirm the
intended semantics: `name-30-accents` is 30 code points but 60 UTF-8 bytes, while
`password-accents-7-chars-12-bytes` is 7 characters but 12 bytes and is accepted by the
byte-based password rule. This pins name length to code points and password length to bytes.

Additional integrity checks (Node, all passing):

- 63 expected message strings checked against a per-field allow-list taken from the plan
  message table: no unapproved or field-mismatched string found.
- 29 recorded normalized values recomputed with the documented rules (trim for
  name/lastname/email/id, lowercase email, digit normalization for phone, passwords
  unchanged): all matched.
- 13 cases with identical `password` and `password_confirm` inputs checked for the
  "both fields invalid but only one reports errors" defect: none remains.

### 3.1 Corpus defects found and corrected

| # | Case id | Defect | Correction |
| --- | --- | --- | --- |
| 1 | `password-accents-7-bytes` | Both fields carried the same invalid value but only `password` listed errors | Added the same two `password_confirm` messages (byte bound, then composition) |
| 2 | `password-accents-72-bytes` | Value had 33 U+00F1 characters (68 bytes), not the documented 35 (72 bytes) | Added two U+00F1 characters in `input` and `normalized` |
| 3 | `password-accents-73-bytes` | Value had 33 U+00F1 characters (69 bytes), not 35 (73 bytes) | Added two U+00F1 characters in `input` and `normalized` |
| 4 | `email-150-chars` | 142-character local part (154 total), not 138 (150 total) | Removed four `a` characters in `input` and `normalized` |
| 5 | `email-151-chars` | 143-character local part (155 total), not 139 (151 total) | Removed four `a` characters in `input` |
| 6 | `email-multi-length-format` | 155 characters, not the documented 151 | Removed four `a` characters in `input` |
| 7 | `name-31-accents` | 33 U+00E1 characters, not 31 | Removed two U+00E1 characters in `input` |
| 8 | `password-confirm-match` (new) | Confirmation rule had only a negative case | Added an explicit positive confirmation case to complete rule coverage |

No approved message string was reworded. The intentional single-message decisions for
`phone.digits` and `id.composition` were left unchanged.

## 4. Case inventory

Sign means positive (no errors expected) or negative (exactly `expectedErrors` expected).
"expectedBytes" means per-field UTF-8 byte counts are recorded; "normalized expected"
means the case records normalized output values.

### name (12 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| name-empty | negative | required | - |
| name-whitespace-only | negative | required | - |
| name-valid-accents-enye | positive | composition, content, length | normalized expected |
| name-trimmed | positive | required | normalized expected |
| name-30-chars | positive | length | normalized expected |
| name-31-chars | negative | length | - |
| name-30-accents | positive | length | normalized expected; code points |
| name-31-accents | negative | length | - |
| name-punctuation-only | negative | content | - |
| name-contains-digits | negative | composition | - |
| name-multi-length-composition | negative | length, composition | order pin |
| name-multi-length-content | negative | length, content | order pin |

### lastname (6 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| lastname-empty | negative | required | - |
| lastname-whitespace-only | negative | required | - |
| lastname-valid-accents | positive | composition, content, length | normalized expected |
| lastname-31-chars | negative | length | - |
| lastname-contains-digits | negative | composition | - |
| lastname-punctuation-only | negative | content | - |

### email (13 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| email-empty | negative | required | - |
| email-whitespace-only | negative | required | - |
| email-valid-lowercase | positive | format, length | normalized expected |
| email-trim-and-lowercase | positive | format, length | normalized expected; lowercase |
| email-150-chars | positive | length, format | normalized expected; 150 characters |
| email-151-chars | negative | length, format | 151 characters |
| email-non-ascii | negative | format | - |
| email-missing-at | negative | format | - |
| email-double-at | negative | format | - |
| email-multi-length-format | negative | length, format | order pin; 151 characters |
| email-serveronly-double-dot-local | negative | format | serverOnly |
| email-serveronly-leading-dot | negative | format | serverOnly |
| email-serveronly-double-dot-domain | negative | format | serverOnly |

### phone (13 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| phone-empty | negative | required | - |
| phone-whitespace-only | negative | required | - |
| phone-plain-digits | positive | digits | normalized expected |
| phone-plus-space-hyphen | positive | digits | normalized expected |
| phone-leading-plus-equivalent | positive | digits | normalized expected; `+` equivalence |
| phone-plain-equivalent | positive | digits | normalized expected; same digits as previous |
| phone-parentheses | positive | digits | normalized expected |
| phone-7-digits | positive | digits | lower boundary |
| phone-20-digits | positive | digits | upper boundary |
| phone-6-digits | negative | digits | below lower boundary |
| phone-21-digits | negative | digits | above upper boundary |
| phone-plus-non-leading | negative | digits | non-leading `+` rejected |
| phone-letters | negative | digits | - |

### id (13 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| id-empty | negative | required | - |
| id-whitespace-only | negative | required | - |
| id-valid-hyphens | positive | length, composition | normalized expected |
| id-trimmed-case-preserved | positive | required | normalized expected; case preserved |
| id-4-chars | negative | length | below lower boundary |
| id-5-chars | positive | length, composition | lower boundary |
| id-20-chars | positive | length, composition | upper boundary |
| id-21-chars | negative | length | above upper boundary |
| id-internal-spaces | negative | composition | - |
| id-underscore | negative | composition | - |
| id-hyphen-only | negative | composition | no alphanumeric character |
| id-non-ascii | negative | composition | - |
| id-multi-length-composition | negative | length, composition | order pin |

### password (12 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| password-empty | negative | required | expectedBytes |
| password-whitespace-not-trimmed | negative | bytes, composition | expectedBytes; never trimmed |
| password-7-bytes | negative | bytes | expectedBytes |
| password-8-bytes | positive | bytes, composition | expectedBytes; normalized expected |
| password-72-bytes | positive | bytes, composition | expectedBytes; normalized expected |
| password-73-bytes | negative | bytes | expectedBytes |
| password-accents-7-chars-12-bytes | positive | bytes, composition | expectedBytes; bytes, not characters |
| password-accents-72-bytes | positive | bytes, composition | expectedBytes; U+00F1 boundary |
| password-accents-73-bytes | negative | bytes | expectedBytes; U+00F1 boundary |
| password-accents-7-bytes | negative | bytes, composition | expectedBytes; both fields report errors |
| password-letters-only | negative | composition | expectedBytes |
| password-digits-only | negative | composition | expectedBytes |

### password_confirm (3 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| password-confirm-match | positive | confirmation | expectedBytes; normalized expected |
| password-confirm-mismatch | negative | confirmation | expectedBytes |
| password-confirm-empty | negative | required | expectedBytes |

### terms (2 cases)

| Case id | Sign | Rules | Notes |
| --- | --- | --- | --- |
| terms-unchecked | negative | required | - |
| terms-checked | positive | required | - |

## 5. Coverage matrix (RF-6 rule table to case ids)

Each RF-6 table rule maps to at least one positive and one negative case. Rule keys are the
ASCII identifiers used in `_meta.fieldRuleOrder` and in each case's `rules` array.

| RF-6 rule (key) | Positive case ids | Negative case ids |
| --- | --- | --- |
| Name/lastname length, 1-30 after trim (`length`) | name-valid-accents-enye, name-30-chars, name-30-accents, lastname-valid-accents | name-31-chars, name-31-accents, name-multi-length-composition, name-multi-length-content, lastname-31-chars |
| Name/lastname allowed characters (`composition`) | name-valid-accents-enye, lastname-valid-accents | name-contains-digits, lastname-contains-digits, name-multi-length-composition |
| Name/lastname at least one letter (`content`) | name-valid-accents-enye, lastname-valid-accents | name-punctuation-only, lastname-punctuation-only, name-multi-length-content |
| Email validity/ASCII format (`format`) | email-valid-lowercase, email-trim-and-lowercase, email-150-chars | email-non-ascii, email-missing-at, email-double-at, email-multi-length-format, email-serveronly-* (server only) |
| Email at most 150 characters (`length`) | email-valid-lowercase, email-trim-and-lowercase, email-150-chars | email-151-chars, email-multi-length-format |
| Phone 7-20 digits after normalization (`digits`) | phone-plain-digits, phone-plus-space-hyphen, phone-leading-plus-equivalent, phone-plain-equivalent, phone-parentheses, phone-7-digits, phone-20-digits | phone-6-digits, phone-21-digits, phone-plus-non-leading, phone-letters |
| Cedula/ID length, 5-20 after trim (`length`) | id-valid-hyphens, id-5-chars, id-20-chars | id-4-chars, id-21-chars, id-multi-length-composition |
| Cedula/ID allowed characters and at least one alphanumeric (`composition`) | id-valid-hyphens, id-5-chars, id-20-chars | id-internal-spaces, id-underscore, id-hyphen-only, id-non-ascii, id-multi-length-composition |
| Password 8-72 UTF-8 bytes (`bytes`) | password-8-bytes, password-72-bytes, password-accents-7-chars-12-bytes, password-accents-72-bytes | password-7-bytes, password-73-bytes, password-accents-73-bytes, password-accents-7-bytes, password-whitespace-not-trimmed |
| Password letter plus digit (`composition`) | password-8-bytes, password-72-bytes, password-accents-7-chars-12-bytes, password-accents-72-bytes | password-letters-only, password-digits-only, password-accents-7-bytes, password-whitespace-not-trimmed |
| Password confirmation equality (`confirmation`) | password-confirm-match | password-confirm-mismatch |
| Terms checked (`required`) | terms-checked | terms-unchecked |

Required/empty (`required`, RF-4 rather than an RF-6 table row) has negative cases for
every field: name-empty, name-whitespace-only, lastname-empty, lastname-whitespace-only,
email-empty, email-whitespace-only, phone-empty, phone-whitespace-only, id-empty,
id-whitespace-only, password-empty, password-confirm-empty, terms-unchecked. Positive
non-empty presence is implicit in every positive case; explicit positives exist for
name (`name-trimmed`), id (`id-trimmed-case-preserved`), and terms (`terms-checked`).

## 6. Scope note

The uniqueness clauses in the RF-6 table (email, phone, and Cedula/ID uniqueness,
including case-insensitive equivalence) are not unit-testable in this shared corpus: the
plan states that uniqueness is never checked on the client, and the spec's requirement map
assigns RF-6 uniqueness to Layers 2 and 4. This corpus covers format, composition,
normalization, confirmation, and mandatory-value rules only, as recorded in `_meta.scope`.
The three `serverOnly` email cases are retained and asserted on the server only, as
required by the plan's client/server rule split.
