/*
 * Progressive enhancement for the registration form (spec 001, RF-2.1 / RF-5 /
 * RNF-4).
 *
 * The server-rendered view remains authoritative: it provides the role button,
 * the hidden `rol` input, the CSRF token, and the polite live region. This
 * controller only enhances behavior: it wires the role toggle and validates on
 * submit with the DOM-free module, rendering Spanish, field-associated errors
 * and focusing the first invalid control. It never creates or reorders the
 * server's controls, never touches the token, and never submits via AJAX: a
 * valid form is left alone so the native POST proceeds unchanged.
 */

import {
  ROLE_TEACHER,
  nextRole,
  roleLabel,
  roleAccessibleName,
} from './register-role.js';
import { validateRegistration } from './register-validation.js';

const ROLE_BUTTON_ID = 'register-role';
const ROLE_VALUE_ID = 'register-role-value';
const LIVE_REGION_SELECTOR = '[role="status"][aria-live="polite"]';
const FORM_SELECTOR = 'form.register-form';

const LIVE_TEACHER = 'Tipo de cuenta seleccionado: Docente.';
const LIVE_STUDENT = 'Tipo de cuenta seleccionado: Estudiante.';

// Document order of the eight validated controls; also the focus order.
const FIELD_ORDER = [
  'name', 'lastname', 'email', 'phone', 'id',
  'password', 'password_confirm', 'terms',
];

// Stable control ids from the server-rendered view (error id = control id + '-error').
const CONTROL_IDS = {
  name: 'register-name',
  lastname: 'register-lastname',
  email: 'register-email',
  phone: 'register-phone',
  id: 'register-id',
  password: 'register-password',
  password_confirm: 'register-password-confirm',
  terms: 'register-terms',
};

const ERROR_CLASS = 'register-field__error';
const FIELD_ERROR_CLASS = 'register-field--error';

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

/**
 * Read the eight validated values from the live controls. The hidden `rol` and
 * `csrf_token` inputs are deliberately left in the form: they are not needed by
 * the pure validator and are submitted by the native POST when the form is valid.
 */
function readFormValues(form) {
  const values = {};
  for (const field of FIELD_ORDER) {
    const control = form.elements.namedItem(field);
    if (!control) {
      continue;
    }
    values[field] = field === 'terms' ? control.checked === true : control.value;
  }
  return values;
}

/** Remove one id from aria-describedby, preserving any other tokens. */
function removeDescribedbyToken(control, token) {
  const describedby = control.getAttribute('aria-describedby');
  if (!describedby) {
    return;
  }
  const tokens = describedby.split(/\s+/).filter((entry) => entry && entry !== token);
  if (tokens.length > 0) {
    control.setAttribute('aria-describedby', tokens.join(' '));
  } else {
    control.removeAttribute('aria-describedby');
  }
}

/** Find or create the field's error container using the server's id/class shape. */
function ensureErrorContainer(control, errorId) {
  let container = document.getElementById(errorId);
  if (!container) {
    container = document.createElement('div');
    container.className = ERROR_CLASS;
    container.id = errorId;
    control.parentElement.appendChild(container);
  }
  return container;
}

/** Render one message per failing rule into the field's error container. */
function renderFieldErrors(field, messages) {
  const control = document.getElementById(CONTROL_IDS[field]);
  if (!control) {
    return null;
  }

  const errorId = `${CONTROL_IDS[field]}-error`;
  const container = ensureErrorContainer(control, errorId);
  container.textContent = '';
  for (const message of messages) {
    const paragraph = document.createElement('p');
    paragraph.className = ERROR_CLASS;
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  const wrapper = control.closest('.register-field, .register-terms');
  if (wrapper) {
    wrapper.classList.add(FIELD_ERROR_CLASS);
  }
  control.setAttribute('aria-invalid', 'true');
  control.setAttribute('aria-describedby', errorId);
  return control;
}

/** Remove a previously rendered error (client or server) from a now-valid field. */
function clearFieldError(field) {
  const control = document.getElementById(CONTROL_IDS[field]);
  if (!control) {
    return;
  }

  const errorId = `${CONTROL_IDS[field]}-error`;
  const container = document.getElementById(errorId);
  if (container) {
    container.remove();
  }
  const wrapper = control.closest('.register-field, .register-terms');
  if (wrapper) {
    wrapper.classList.remove(FIELD_ERROR_CLASS);
  }
  control.removeAttribute('aria-invalid');
  removeDescribedbyToken(control, errorId);
}

/** Validate on submit; block and focus only when at least one field is invalid. */
function handleSubmit(event) {
  const form = event.currentTarget;
  const { errors } = validateRegistration(readFormValues(form));
  let firstInvalidControl = null;

  for (const field of FIELD_ORDER) {
    const messages = errors[field];
    if (Array.isArray(messages) && messages.length > 0) {
      const control = renderFieldErrors(field, messages);
      if (control && firstInvalidControl === null) {
        firstInvalidControl = control;
      }
    } else {
      clearFieldError(field);
    }
  }

  if (firstInvalidControl) {
    event.preventDefault();
    firstInvalidControl.focus();
  }
  // With no errors, do not prevent default: the native POST carries rol and
  // csrf_token intact and follows the server's PRG redirect.
}

/** Wire submit-time validation on the server-rendered form. */
function initSubmitValidation() {
  const form = document.querySelector(FORM_SELECTOR);
  if (!form) {
    return;
  }
  form.addEventListener('submit', handleSubmit);
}

function init() {
  initRoleControl();
  initSubmitValidation();
}

init();
