<?php

declare(strict_types=1);

// Session-bound CSRF primitive (RF-9). The token is generated once per
// session and is never rotated or expired, so it stays valid across multiple
// tabs and concurrent form instances of the same session (approved policy
// trade-off in the specification's Policy rationale for RF-9).

const CSRF_TOKEN_KEY = 'csrf_token';

function ensure_csrf_token(array &$session): string
{
    $stored = $session[CSRF_TOKEN_KEY] ?? null;

    // An existing well-formed token is returned unchanged; a missing or
    // malformed one (e.g. after session regeneration or tampering) is
    // replaced.
    if (is_string($stored) && preg_match('/^[0-9a-f]{64}$/', $stored) === 1) {
        return $stored;
    }

    $token = bin2hex(random_bytes(32));
    $session[CSRF_TOKEN_KEY] = $token;

    return $token;
}

function submitted_csrf_is_valid(array $session, $submitted): bool
{
    $stored = $session[CSRF_TOKEN_KEY] ?? null;

    // Non-string submissions (arrays, ints, null, bools) are rejected before
    // hash_equals so no PHP type error can be triggered; hash_equals itself
    // provides the timing-safe string comparison.
    if (!is_string($stored) || !is_string($submitted)) {
        return false;
    }

    return hash_equals($stored, $submitted);
}
