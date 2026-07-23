# ADR-003: Sesión BFF y OIDC

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Los navegadores son clientes públicos y los datos tratados son de categoría especial. Persistir access
tokens en JavaScript amplía el impacto de XSS y complica revocación, rotación y cierre de sesión.

## Decisión

El backend actúa como BFF. OIDC, WebAuthn y MFA se integran mediante puertos de proveedor; los tokens
permanecen en backend. El navegador recibe una sesión opaca con cookies `HttpOnly`, `Secure`,
`SameSite` y rotación. CSRF, reautenticación y step-up se aplican según riesgo.

## Alternativas consideradas

- JWT en `localStorage` o `sessionStorage`: rechazado.
- SPA con tokens solo en memoria: reduce persistencia, pero mantiene el token en un contexto alcanzable
  por JavaScript y complica recuperación.
- Autenticación propia con contraseña como única opción: rechazada para producción.

## Consecuencias

El frontend nunca interpreta un token para autorizar. El entorno local usa un proveedor simulado seguro;
su existencia no homologa la identidad productiva.
