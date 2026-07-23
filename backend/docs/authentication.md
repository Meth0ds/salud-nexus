# Autenticación web de primera parte

## Contrato implementado

Esta vertical usa Sanctum 4 en modo SPA stateful sobre el guard de sesión `web` de Laravel, la opción recomendada por Laravel 13 para una SPA Angular. Sanctum no usa tokens para este flujo: autentica con cookie de sesión y CSRF. Los personal access tokens se deshabilitan explícitamente y el modelo autenticable no incorpora `HasApiTokens`. Fortify no se instala porque registro, recuperación y MFA aún no forman parte del contrato. El despliegue sirve Angular y la API bajo el mismo origen o dominio raíz; durante desarrollo Angular puede usar su proxy local hacia Laravel.

Flujo del navegador:

1. `GET /api/v1/auth/csrf`, con credenciales, inicia la sesión y entrega las cookies `XSRF-TOKEN` y de sesión.
2. `POST /api/v1/auth/login` recibe JSON y `X-XSRF-TOKEN`. Las credenciales desconocidas, erróneas, suspendidas o deshabilitadas tienen la misma respuesta pública. El guard de Laravel aplica un timebox y el endpoint limita cuenta normalizada + IP e IP agregada.
3. `GET /api/v1/auth/session` requiere sesión y devuelve solo el identificador público, nombre visible, AAL1 de contraseña y las capacidades reales `session:read` y `session:logout`.
4. `POST /api/v1/auth/logout` requiere CSRF, cierra el guard, invalida todo el contenido de sesión, rota el identificador y regenera el token CSRF.

La cuenta autenticable vive en `identity_accounts`; no contiene `patient_id` ni datos clínicos. La vinculación autorizada con pacientes se implementará mediante contratos del módulo Patients, no mediante herencia ni columnas dentro de la identidad.

## Controles operativos

- `HASH_DRIVER=argon2id`; producción falla al arrancar con otro driver.
- La sesión de producción exige almacenamiento `database` o `redis`, cifrado, `HttpOnly`, `Secure`, SameSite Lax/Strict y una duración de 5 a 480 minutos.
- No existe opción remember-me y no se entrega ningún bearer token a JavaScript.
- El callback de recuperación de tokens de Sanctum devuelve siempre `null`; un bearer arbitrario obtiene 401 y nunca consulta una tabla de tokens.
- CORS mantiene una allowlist exacta y solo admite las cabeceras XSRF previstas.
- Los errores siguen RFC 9457 y nunca incluyen el correo ni indican si una cuenta existe.

## Backlog explícito, no simulado

- Definir el puerto `FederatedIdentityProvider` para OIDC Authorization Code + PKCE y almacenamiento server-side de tokens.
- Definir `StepUpAuthenticator` y políticas por operación antes de declarar AAL2.
- Implementar ceremonias WebAuthn completas (challenge de un solo uso, RP ID/origin, contador y attestation policy) antes de ofrecer passkeys.
- Diseñar recuperación de cuenta, rotación/revocación de sesiones, dispositivos confiables y notificación de riesgo.
- Conectar las capacidades clínicas a Policies con organización, rol, relación con el recurso y propósito. Hasta entonces el endpoint solo publica capacidades de sesión.
