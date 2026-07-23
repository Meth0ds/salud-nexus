# MFA TOTP y sesiones AAL2

## Objetivo

Implementar un segundo factor TOTP y códigos de recuperación de un solo uso para elevar una sesión
de contraseña a AAL2. El flujo pertenece a un único centro, mantiene el patrón BFF y nunca entrega
tokens de acceso al navegador.

Este corte cubre IAM-009, IAM-010 e IAM-012 y prepara IAM-011/IAM-013. No declara que TOTP sea
resistente al phishing: el acceso del personal requerirá además WebAuthn/passkeys u OIDC con un
autenticador resistente al phishing antes de producción.

## Fuentes y decisiones verificadas

- [Laravel Fortify 13.x](https://laravel.com/docs/13.x/fortify#two-factor-authentication) define el
  flujo frontend-agnostic de alta, confirmación, desafío y códigos de recuperación que se usará como
  referencia de integración.
- [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238) exige una clave única por autenticador y define
  el algoritmo TOTP y su ventana temporal.
- [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b/authenticators/) exige aceptar cada
  OTP una sola vez durante su validez, limitar intentos y proteger fuertemente las claves simétricas.
  También trata los códigos guardados como secretos de consulta de un solo uso.
- [OWASP MFA Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html)
  recomienda códigos de recuperación de un solo uso y recuerda que TOTP es susceptible al phishing.

Se instalará `laravel/fortify` en una versión bloqueada compatible con Laravel 13 para reutilizar su
proveedor TOTP/QR mantenido oficialmente. Sus rutas y persistencia automáticas no se expondrán: el
módulo Identity conservará contratos propios, tablas normalizadas, replay protection y auditoría.

## Modelo de amenaza del corte

| Amenaza | Control |
| --- | --- |
| Robo de la semilla TOTP | Cifrado autenticado mediante el cifrador Laravel, acceso mínimo y nunca incluida en logs/auditoría. |
| Reutilización del mismo TOTP | Paso temporal consumido de forma atómica bajo bloqueo de fila. |
| Fuerza bruta de seis dígitos | Límite cuenta/sesión/IP, máximo de intentos por reto, expiración breve y bloqueo progresivo compatible con anti-enumeración. |
| Robo de un código de recuperación en base de datos | Código aleatorio de alta entropía, digest de búsqueda y hash Argon2id independiente; plaintext mostrado una sola vez. |
| Fijación o mezcla de sesiones | Regeneración del identificador tras contraseña y tras MFA; reto ligado a la sesión BFF. |
| Enumeración de cuentas o estado MFA | Las credenciales inválidas conservan una respuesta uniforme; el reto solo aparece después de una contraseña válida. |
| Exfiltración desde el frontend | Semillas/códigos solo en memoria, sin Web Storage, URL, telemetría ni caché; cabeceras `no-store`. |
| Phishing | Aviso explícito de la limitación de TOTP y backlog WebAuthn/passkeys para personal. |

## Persistencia

### `identity_mfa_methods`

- Cuenta y tipo (`totp`) únicos.
- Identificador público UUIDv7.
- Semilla con cast cifrado, nunca hash porque el verificador debe recuperarla.
- Estado `pending`, `active` o `disabled`.
- Caducidad de alta, instante de revelado del QR y confirmación.
- `last_used_step` para impedir replay incluso dentro de la ventana tolerada.
- Contadores/fechas de fallo sin guardar códigos.

### `identity_recovery_codes`

- Relación con el método y UUIDv7.
- Digest HMAC para localizar de forma constante y hash Argon2id para verificar.
- `used_at` inmutable tras el primer consumo.
- Nunca se persiste el valor legible.

### `identity_security_events`

- Eventos minimizados de alta, confirmación, reto, éxito, fallo, replay y consumo de recuperación.
- Identidad nullable, request ID, tipo, resultado, AAL y metadatos allowlist sin correo, IP legible,
  secreto, código ni datos sanitarios.
- Preparado para exportación posterior al SIEM y correlación con la cadena de auditoría del centro.

## Ceremonias

### Alta TOTP

1. Una sesión AAL1 reciente solicita el alta.
2. El servidor crea o rota un método `pending`, genera una semilla única y una URL QR opaca.
3. El SVG se revela una sola vez mediante un `POST` protegido por CSRF, con
   sesión/propietario verificados y `Cache-Control: no-store`.
4. El usuario confirma un TOTP dentro de una ventana de ±1 paso de 30 segundos.
5. La confirmación activa el método y devuelve una única lista de códigos de recuperación.
6. La UI permite copiar o descargar desde memoria y obliga a confirmar que se guardaron.

### Login con MFA

1. `POST /api/v1/auth/login` verifica contraseña y estado sin autenticar aún al guard.
2. Sin MFA activo, conserva el contrato `204` y crea una sesión AAL1.
3. Con MFA activo, devuelve `202` con un reto opaco, métodos permitidos y caducidad; la identidad
   sigue siendo anónima.
4. `POST /api/v1/auth/mfa/challenge-verifications` acepta TOTP o código de recuperación.
5. Solo tras una prueba válida inicia el guard, rota la sesión y fija AAL2.

### Step-up

Una sesión AAL1 puede crear un reto ligado a una finalidad de allowlist. Al verificarlo, se rota la
sesión y se fija AAL2 con `authenticated_at` renovado. El middleware de nivel rechaza por defecto una
operación cuando el nivel o la frescura no cumplen su política.

## Contrato HTTP

| Método y ruta | Resultado |
| --- | --- |
| `POST /api/v1/auth/login` | `204` AAL1 o `202 MfaChallengeEnvelope`. |
| `POST /api/v1/auth/mfa/challenge-verifications` | `204`; crea/eleva la sesión o problema RFC 9457 uniforme. |
| `GET /api/v1/auth/mfa` | Estado mínimo del método para la cuenta autenticada. |
| `POST /api/v1/auth/mfa/totp/enrollments` | `201 TotpEnrollmentEnvelope`; no incluye la semilla. |
| `POST /api/v1/auth/mfa/totp/enrollment-qr-disclosures` | SVG de un solo uso, autenticado, protegido por CSRF y no cacheable. |
| `POST /api/v1/auth/mfa/totp/enrollment-confirmations` | `200 RecoveryCodesEnvelope` una sola vez. |
| `POST /api/v1/auth/mfa/step-up-challenges` | `201 MfaChallengeEnvelope` ligado a finalidad. |

Los errores de código incorrecto, usado o caducado no distinguen públicamente la causa. Los
identificadores opacos se envían en JSON, no en la URL. Todas las mutaciones requieren CSRF.

## Diseño de experiencia

- G03 muestra seis casillas visuales respaldadas por un único input semántico, pegado completo,
  método alternativo, estado de intentos y ayuda segura.
- P12 guía alta, escaneo, confirmación y guardado de recuperación sin revelar secretos en capturas
  de catálogo.
- El foco entra en el título/error apropiado y no salta durante la validación.
- Las animaciones son de opacidad/4 px, interrumpibles y anuladas con `prefers-reduced-motion`.
- En 320 CSS px no hay scroll horizontal; objetivos táctiles y mensajes cumplen WCAG 2.2 AA.

## Criterios de salida

- No se puede reutilizar un TOTP ni un código de recuperación.
- Un reto caducado, agotado, de otra sesión o de otra cuenta no autentica.
- La base de datos, logs, problemas, auditoría, URLs y Web Storage no contienen secretos legibles.
- OpenAPI y cliente TypeScript no usan `any` ni divergen del servidor.
- Pruebas Laravel, Angular, Material Harness, Playwright, accesibilidad, build y auditorías de
  dependencias quedan verdes.
