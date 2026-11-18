/*
 * DOM-free registration validation for spec 001 (create-account), RF-5/RF-6.
 *
 * Mirrors includes/validation.php so client and server produce the same
 * normalized values and Spanish messages from the shared corpus. No DOM
 * access, no global mutable state, and no imports. Rule order within a field
 * is fixed: required/empty, length/bytes, character composition/content,
 * format, confirmation (plan.md, "Validation and messages").
 */

const encoder = new TextEncoder();

/** UTF-8 byte length, matching PHP strlen() on a UTF-8 string. */
function byteLength(value) {
  return encoder.encode(value).length;
}

/** Coerce an untrusted value to a string; arrays, objects and null become ''. */
function scalarString(value) {
  if (typeof value === 'string') {
    return value;
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }
  if (typeof value === 'boolean') {
    return value ? '1' : '';
  }
  return '';
}

/** Checked terms arrive as a scalar 'on'; false-like scalars are not accepted. */
function termsIsAccepted(raw) {
  if (typeof raw === 'boolean') {
    return raw;
  }
  if (typeof raw !== 'string' && typeof raw !== 'number') {
    return false;
  }
  const value = String(raw).trim().toLowerCase();
  return value !== '' && !['0', 'false', 'off', 'no'].includes(value);
}

/**
 * Remove an optional single leading '+', then spaces, hyphens and parentheses.
 * The 7-20 ASCII-digit check is a separate rule in validateRegistration().
 */
export function normalizePhone(raw) {
  let value = scalarString(raw).trim();
  if (value.startsWith('+')) {
    value = value.slice(1);
  }
  return value.replace(/[ \-()]/g, '');
}

/** Name/lastname messages: required -> length (code points) -> composition -> content. */
function validateNameLike(value, field, requiredMessage) {
  if (value === '') {
    return [requiredMessage];
  }

  const subject = field === 'name' ? 'El nombre' : 'El apellido';
  const messages = [];
  const length = [...value].length;
  if (length < 1 || length > 30) {
    messages.push(`${subject} debe tener entre 1 y 30 caracteres.`);
  }
  if (!/^[\p{L} '-]+$/u.test(value)) {
    messages.push(`${subject} solo puede contener letras, espacios, guiones y apóstrofes.`);
  }
  if (!/\p{L}/u.test(value)) {
    messages.push(`${subject} debe incluir al menos una letra.`);
  }
  return messages;
}

/*
 * Shared ASCII structural email pattern. It is intentionally permissive: it
 * may accept values PHP FILTER_VALIDATE_EMAIL rejects (the serverOnly corpus
 * cases), because the server is authoritative and surfaces those rejections
 * after re-render. ASCII-only and the length bound are enforced separately.
 */
const ASCII_EMAIL_PATTERN = /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/;

/** Email messages: required -> length -> format. */
function validateEmail(value) {
  if (value === '') {
    return ['Ingresa tu correo electrónico.'];
  }

  const messages = [];
  if (byteLength(value) > 150) {
    messages.push('El correo electrónico debe tener como máximo 150 caracteres.');
  }
  const isAscii = /^[\x00-\x7F]*$/.test(value);
  if (!isAscii || !ASCII_EMAIL_PATTERN.test(value)) {
    messages.push('El correo electrónico no es válido.');
  }
  return messages;
}

/** Phone messages: required -> digits after normalization. */
function validatePhone(normalized, empty) {
  if (empty) {
    return ['Ingresa tu teléfono.'];
  }
  return /^[0-9]{7,20}$/.test(normalized)
    ? []
    : ['El teléfono debe tener entre 7 y 20 dígitos.'];
}

/** Cédula/ID messages: required -> length -> single composition rule. */
function validateId(value) {
  if (value === '') {
    return ['Ingresa tu cédula o ID.'];
  }

  const messages = [];
  const length = byteLength(value);
  if (length < 5 || length > 20) {
    messages.push('La cédula o ID debe tener entre 5 y 20 caracteres.');
  }
  const allowed = /^[A-Za-z0-9-]+$/.test(value);
  const hasAlphanumeric = /[A-Za-z0-9]/.test(value);
  if (!allowed || !hasAlphanumeric) {
    messages.push('La cédula o ID solo puede contener letras, números y guiones.');
  }
  return messages;
}

/** Password messages: required -> UTF-8 bytes -> ASCII letter and digit. */
function validatePassword(value, requiredMessage) {
  if (value === '') {
    return [requiredMessage];
  }

  const messages = [];
  const bytes = byteLength(value);
  if (bytes < 8 || bytes > 72) {
    messages.push('La contraseña debe tener entre 8 y 72 caracteres.');
  }
  const hasLetter = /[A-Za-z]/.test(value);
  const hasDigit = /[0-9]/.test(value);
  if (!hasLetter || !hasDigit) {
    messages.push('La contraseña debe incluir al menos una letra y un número.');
  }
  return messages;
}

/**
 * Validate one registration submission and return normalized values plus
 * field-grouped messages in the fixed rule order. Passwords are never trimmed.
 *
 * @param {object} values untrusted values keyed by form field name
 * @returns {{values: object, errors: object}}
 */
export function validateRegistration(values) {
  const input = values && typeof values === 'object' ? values : {};

  const name = scalarString(input.name).trim();
  const lastname = scalarString(input.lastname).trim();
  const email = scalarString(input.email).trim().toLowerCase();
  const phoneRaw = scalarString(input.phone);
  const phone = normalizePhone(phoneRaw);
  const id = scalarString(input.id).trim();
  const password = scalarString(input.password);
  const passwordConfirm = scalarString(input.password_confirm);
  const terms = termsIsAccepted(input.terms);

  const normalized = {
    name,
    lastname,
    email,
    phone,
    id,
    password,
    password_confirm: passwordConfirm,
    terms,
  };
  const errors = {};

  const nameMessages = validateNameLike(name, 'name', 'Ingresa tu nombre.');
  if (nameMessages.length > 0) {
    errors.name = nameMessages;
  }

  const lastnameMessages = validateNameLike(lastname, 'lastname', 'Ingresa tu apellido.');
  if (lastnameMessages.length > 0) {
    errors.lastname = lastnameMessages;
  }

  const emailMessages = validateEmail(email);
  if (emailMessages.length > 0) {
    errors.email = emailMessages;
  }

  const phoneMessages = validatePhone(phone, phoneRaw.trim() === '');
  if (phoneMessages.length > 0) {
    errors.phone = phoneMessages;
  }

  const idMessages = validateId(id);
  if (idMessages.length > 0) {
    errors.id = idMessages;
  }

  const passwordMessages = validatePassword(password, 'Ingresa una contraseña.');
  if (passwordMessages.length > 0) {
    errors.password = passwordMessages;
  }

  const confirmMessages = validatePassword(passwordConfirm, 'Confirma tu contraseña.');
  if (passwordConfirm !== '' && passwordConfirm !== password) {
    confirmMessages.push('Las contraseñas no coinciden.');
  }
  if (confirmMessages.length > 0) {
    errors.password_confirm = confirmMessages;
  }

  if (!terms) {
    errors.terms = ['Debes aceptar los términos y condiciones.'];
  }

  return { values: normalized, errors };
}
