/*
 * Layer 1 client-side validation tests for spec 001 (create-account), T3.3.
 *
 * The shared JSON corpus is the single source of truth for rule outcomes and
 * Spanish message copy (plan.md decision 6). These tests assert the DOM-free
 * JS validator produces the same normalized values and error arrays as the
 * PHP validator in tests/php/assert_validation.php.
 *
 * Adapter note: the corpus models the terms checkbox as a JSON boolean, while
 * the DOM form delivers a checked checkbox as the scalar value "on" and omits
 * the field when unchecked. toValidatorInput() maps true -> "on" and false ->
 * absent, mirroring the PHP harness adapter.
 *
 * Skipped cases: the shared ASCII structural email pattern may accept values
 * that PHP FILTER_VALIDATE_EMAIL rejects. The 3 cases marked serverOnly are
 * therefore skipped here and asserted only on the server; the server remains
 * authoritative. All other 71 cases are asserted here.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  validateRegistration,
  normalizePhone,
} from '../../js/features/register-validation.js';

const here = dirname(fileURLToPath(import.meta.url));
const corpusPath = join(here, '..', 'fixtures', 'validation-cases.json');
const corpus = JSON.parse(readFileSync(corpusPath, 'utf8'));
const baseRecord = corpus._meta.baseValidRecord;
const cases = corpus.cases;
const encoder = new TextEncoder();

/** Merge a case's input over the base record and adapt the boolean terms field. */
function toValidatorInput(caseInput, base) {
  const input = { ...base, ...caseInput };
  const accepted = Boolean(input.terms ?? false);
  delete input.terms;
  if (accepted) {
    input.terms = 'on';
  }
  return input;
}

test('corpus contains 74 cases and 3 serverOnly cases', () => {
  assert.equal(cases.length, 74);
  assert.equal(cases.filter((item) => item.serverOnly === true).length, 3);
});

test('the module exports validateRegistration() and normalizePhone()', () => {
  assert.equal(typeof validateRegistration, 'function');
  assert.equal(typeof normalizePhone, 'function');
});

for (const caseDef of cases) {
  if (caseDef.serverOnly) {
    continue;
  }

  test(`[${caseDef.id}] normalized values and exact error arrays`, () => {
    const input = toValidatorInput(caseDef.input, baseRecord);
    const result = validateRegistration(input);

    assert.ok(result && typeof result === 'object');
    assert.ok('values' in result && 'errors' in result);

    // Object key order pins the field grouping order; array order pins the
    // per-field rule order from plan.md.
    assert.deepStrictEqual(Object.keys(result.errors), Object.keys(caseDef.expectedErrors));
    assert.deepStrictEqual(result.errors, caseDef.expectedErrors);

    for (const [field, expected] of Object.entries(caseDef.normalized ?? {})) {
      assert.equal(result.values[field], expected);
    }
  });
}

test('expectedBytes match TextEncoder UTF-8 byte lengths for byte-bearing cases', () => {
  for (const caseDef of cases) {
    if (caseDef.serverOnly || !caseDef.expectedBytes) {
      continue;
    }
    const input = toValidatorInput(caseDef.input, baseRecord);
    for (const [field, bytes] of Object.entries(caseDef.expectedBytes)) {
      assert.equal(encoder.encode(String(input[field] ?? '')).length, bytes, `[${caseDef.id}] ${field}`);
    }
  }
});

test('phone normalization makes a leading + equivalent', () => {
  assert.equal(normalizePhone('+506 8888-7777'), '50688887777');
  assert.equal(normalizePhone('+50688887777'), '50688887777');
  assert.equal(normalizePhone('50688887777'), '50688887777');
  assert.equal(normalizePhone('(506) 8888-7777'), '50688887777');
  assert.equal(normalizePhone('506+88887777'), '506+88887777');
});

test('non-string and non-scalar inputs are coerced without throwing', () => {
  const nonScalar = {
    name: ['Ana'],
    lastname: ['Pérez'],
    email: ['ana@example.com'],
    phone: ['88887777'],
    id: ['8-1234'],
    password: ['secreto1'],
    password_confirm: ['secreto1'],
    terms: ['on'],
  };

  let result;
  assert.doesNotThrow(() => {
    result = validateRegistration(nonScalar);
  });
  assert.deepStrictEqual(result.errors, {
    name: ['Ingresa tu nombre.'],
    lastname: ['Ingresa tu apellido.'],
    email: ['Ingresa tu correo electrónico.'],
    phone: ['Ingresa tu teléfono.'],
    id: ['Ingresa tu cédula o ID.'],
    password: ['Ingresa una contraseña.'],
    password_confirm: ['Confirma tu contraseña.'],
    terms: ['Debes aceptar los términos y condiciones.'],
  });

  for (const value of [null, undefined, ['88887777'], { value: '88887777' }, 42]) {
    let normalized;
    assert.doesNotThrow(() => {
      normalized = normalizePhone(value);
    });
    assert.equal(typeof normalized, 'string');
  }
  assert.equal(normalizePhone(['88887777']), '');
  assert.equal(normalizePhone(null), '');
  assert.equal(normalizePhone(undefined), '');
});
