# Baseline Capture Notes - Spec 001 (Create Account)

- Date: 2026-11-17
- URL probed and captured: `http://localhost/pages/register.php` (USBWebServer 8.6.5; the project folder is the Apache document root)
- Tool: Chrome DevTools MCP (viewport emulation + `take_screenshot`); screenshots saved as real browser captures, not hand-crafted images

## Viewports

| File | Viewport | Emulation |
| --- | --- | --- |
| `baseline-1440.png` | 1440x900, deviceScaleFactor 1 | Desktop (no mobile/touch) |
| `baseline-402.png` | 402x874, deviceScaleFactor 1 | `mobile,touch` |

Both captures show the current static registration page: title "Crear cuenta | ProfeGo", heading "Crear cuenta", role label "Estudiante", fields Nombre, Apellido, Correo electronico o cedula/ID*, Crear contrasena*, Confirmar contrasena*, the terms checkbox, and the "Crear Cuenta" button.

## Probe outcome (pre-capture state)

The current `pages/register.php` contains a pre-plan PROTOTYPE backend that attempts to INSERT an empty row into `usuarios` on ANY request, GET included:

- The very first GET today returned 303 to `login.php?registro=ok` and inserted an empty `usuarios` row (correo/telefono/documento all empty strings).
- Because that empty row now exists, GET renders the static registration form with HTTP 200 (the insert fails on the unique `correo=''` collision, falls into the catch, and the page renders). All captures above show this state.
- The user approved proceeding under "Option A".
- Every GET to `register.php` writes a duplicate-insert error line to the PHP error log. That is prototype noise, not test residue. The empty `usuarios` row is prototype-era residue and will be addressed when task T5.2 replaces the prototype.

## Source state

No source file was modified during this task. The only files created are this notes file and the two baseline screenshots, captured before any source change so that RF-12 / RNF-5 / AC-11 verification (task T9.2) has a pre-change visual reference.
