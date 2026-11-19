/*
 * Progressive enhancement for the registration form (spec 001, RF-2.1 / RNF-4).
 *
 * The server-rendered view remains authoritative: it provides the role button,
 * the hidden `rol` input and the polite live region. This controller only wires
 * the role toggle; it does not create those elements, submit the form, or touch
 * the CSRF token. Submit-time validation is added in T7.2 as a separate init.
 */

import {
  ROLE_TEACHER,
  nextRole,
  roleLabel,
  roleAccessibleName,
} from './register-role.js';

const ROLE_BUTTON_ID = 'register-role';
const ROLE_VALUE_ID = 'register-role-value';
const LIVE_REGION_SELECTOR = '[role="status"][aria-live="polite"]';

const LIVE_TEACHER = 'Tipo de cuenta seleccionado: Docente.';
const LIVE_STUDENT = 'Tipo de cuenta seleccionado: Estudiante.';

/** Canonical live-region copy for a role (the only copy not owned by register-role.js). */
function liveRegionCopy(role) {
  return role === ROLE_TEACHER ? LIVE_TEACHER : LIVE_STUDENT;
}

/** Wire the type="button" role toggle to all four role signals. */
function initRoleControl() {
  const button = document.getElementById(ROLE_BUTTON_ID);
  const roleInput = document.getElementById(ROLE_VALUE_ID);
  const liveRegion = document.querySelector(LIVE_REGION_SELECTOR);
  if (!button || !roleInput || !liveRegion) {
    return;
  }

  // Native button activation covers click, Enter and Space; a type="button"
  // never submits. Reading the hidden input on each activation keeps a
  // preselected `docente` render (or an error re-render) as the starting role.
  button.addEventListener('click', () => {
    const currentRole = roleInput.value;
    const nextRoleValue = nextRole(currentRole);

    button.textContent = roleLabel(nextRoleValue);
    button.setAttribute('aria-label', roleAccessibleName(nextRoleValue));
    roleInput.value = nextRoleValue;
    liveRegion.textContent = liveRegionCopy(nextRoleValue);
  });
}

/** Extension point for T7.2 submit-time validation and focus management. */
function initSubmitValidation() {
  // Added in T7.2; the native POST remains the submission path.
}

function init() {
  initRoleControl();
  initSubmitValidation();
}

init();
