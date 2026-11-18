/*
 * DOM-free role state machine for spec 001 (create-account), RF-2.1 / RNF-4.
 *
 * Single source of truth for the role control's three signals: the visible
 * text, the hidden `rol` value, and the accessible name. The exact Spanish
 * strings come from spec.md "Definitions" (role control) and plan.md
 * "Role control and accessibility". No DOM access, no globals, no side effects,
 * and no imports, so the module runs under node --test and in the browser.
 *
 * Unsupported input: any value that is not exactly one of the supported roles
 * falls back to the default student role in every function. This keeps the
 * state machine inside the allow-list and never produces `admin`.
 */

export const ROLE_STUDENT = 'estudiante';
export const ROLE_TEACHER = 'docente';
export const ROLE_ORDER = [ROLE_STUDENT, ROLE_TEACHER];

const LABEL_STUDENT = 'Estudiante';
const LABEL_TEACHER = 'Docente';
const NAME_STUDENT = 'Tipo de cuenta: Estudiante, cambiar a Docente';
const NAME_TEACHER = 'Tipo de cuenta: Docente, cambiar a Estudiante';

/** Return the other supported role; unsupported input yields the default student role. */
export function nextRole(role) {
  const index = ROLE_ORDER.indexOf(role);
  if (index === -1) {
    return ROLE_STUDENT;
  }
  return ROLE_ORDER[(index + 1) % ROLE_ORDER.length];
}

/** Exact visible label for a role; unsupported input yields the student label. */
export function roleLabel(role) {
  return role === ROLE_TEACHER ? LABEL_TEACHER : LABEL_STUDENT;
}

/** Exact accessible name for a role; unsupported input yields the student name. */
export function roleAccessibleName(role) {
  return role === ROLE_TEACHER ? NAME_TEACHER : NAME_STUDENT;
}
