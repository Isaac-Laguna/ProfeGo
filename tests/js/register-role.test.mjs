/*
 * Layer 1 DOM-free role state-machine tests for spec 001 (create-account), T3.4.
 *
 * The exact visible labels and accessible-name strings are normative product
 * copy from spec.md "Definitions" (role control) and plan.md "Role control and
 * accessibility". This module is DOM-free so the same functions run under
 * node --test and in the browser enhancement (T7.1).
 *
 * Documented unsupported-input behavior: any value that is not exactly one of
 * the supported roles falls back to the default student role for every function.
 * This keeps the state machine inside the allow-list and never emits `admin`.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
  ROLE_STUDENT,
  ROLE_TEACHER,
  ROLE_ORDER,
  nextRole,
  roleLabel,
  roleAccessibleName,
} from '../../js/features/register-role.js';

test('module exports the role constants', () => {
  assert.equal(ROLE_STUDENT, 'estudiante');
  assert.equal(ROLE_TEACHER, 'docente');
  assert.deepStrictEqual(ROLE_ORDER, [ROLE_STUDENT, ROLE_TEACHER]);
  assert.equal(ROLE_ORDER.includes('admin'), false);
});

test('module exports the role functions', () => {
  assert.equal(typeof nextRole, 'function');
  assert.equal(typeof roleLabel, 'function');
  assert.equal(typeof roleAccessibleName, 'function');
});

test('roleLabel returns the exact visible strings', () => {
  assert.equal(roleLabel(ROLE_STUDENT), 'Estudiante');
  assert.equal(roleLabel(ROLE_TEACHER), 'Docente');
});

test('roleAccessibleName returns the exact accessible-name strings', () => {
  assert.equal(
    roleAccessibleName(ROLE_STUDENT),
    'Tipo de cuenta: Estudiante, cambiar a Docente'
  );
  assert.equal(
    roleAccessibleName(ROLE_TEACHER),
    'Tipo de cuenta: Docente, cambiar a Estudiante'
  );
});

test('nextRole toggles student -> teacher -> student (full cycle)', () => {
  const afterFirst = nextRole(ROLE_STUDENT);
  assert.equal(afterFirst, ROLE_TEACHER);
  const afterSecond = nextRole(afterFirst);
  assert.equal(afterSecond, ROLE_STUDENT);
});

test('role signals stay in sync across a full toggle cycle', () => {
  let role = ROLE_STUDENT;
  assert.equal(roleLabel(role), 'Estudiante');
  assert.equal(roleAccessibleName(role), 'Tipo de cuenta: Estudiante, cambiar a Docente');

  role = nextRole(role);
  assert.equal(role, ROLE_TEACHER);
  assert.equal(roleLabel(role), 'Docente');
  assert.equal(roleAccessibleName(role), 'Tipo de cuenta: Docente, cambiar a Estudiante');

  role = nextRole(role);
  assert.equal(role, ROLE_STUDENT);
  assert.equal(roleLabel(role), 'Estudiante');
  assert.equal(roleAccessibleName(role), 'Tipo de cuenta: Estudiante, cambiar a Docente');
});

test('unsupported input falls back to the default student role', () => {
  const unsupported = [
    'admin',
    'ADMIN',
    'unknown',
    '',
    '  ',
    'estudiante ',
    null,
    undefined,
    0,
    1,
    true,
    false,
    {},
    [],
    ['estudiante'],
  ];
  for (const value of unsupported) {
    assert.equal(nextRole(value), ROLE_STUDENT, `nextRole(${String(value)})`);
    assert.equal(roleLabel(value), 'Estudiante', `roleLabel(${String(value)})`);
    assert.equal(
      roleAccessibleName(value),
      'Tipo de cuenta: Estudiante, cambiar a Docente',
      `roleAccessibleName(${String(value)})`
    );
  }
});

test('no function can produce a role outside the allow-list', () => {
  const inputs = [
    ROLE_STUDENT,
    ROLE_TEACHER,
    'admin',
    'ADMIN',
    'docente ',
    'estudiante ',
    'unknown',
    '',
    null,
    undefined,
    42,
    true,
    {},
    [],
  ];
  const observedRoles = new Set();
  for (const value of inputs) {
    observedRoles.add(nextRole(value));
  }
  assert.ok(observedRoles.size > 0);
  for (const role of observedRoles) {
    assert.ok(ROLE_ORDER.includes(role), `unexpected role produced: ${String(role)}`);
  }
  assert.equal(observedRoles.has('admin'), false);
  assert.deepStrictEqual([...observedRoles].sort(), [...ROLE_ORDER].sort());
});
