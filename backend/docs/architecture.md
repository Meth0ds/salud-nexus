# Arquitectura del backend

## Estado y objetivo

Esta fundación es un monolito modular Laravel 13. SQLite permite arrancar y probar sin servicios externos; PostgreSQL es la fuente de verdad obligatoria en staging y producción. El código actual implementa infraestructura transversal y sondas, no reglas clínicas simuladas.

## Límites modulares

Los módulos registrados bajo `app/Modules` son:

| Módulo | Responsabilidad prevista |
| --- | --- |
| Identity | Identidad federada, sesiones, MFA, autorización y dispositivos |
| Organizations | Organizaciones, centros, profesionales, recursos y pertenencias |
| Patients | Identidad de paciente, contactos y relaciones autorizadas |
| Scheduling | Disponibilidad, citas, conflictos, listas de espera y estados |
| Medication | Planes de medicación y recordatorios no prescriptivos |
| Documents | Metadatos, cargas seguras, versiones, retención y descargas |
| Privacy | Consentimientos, derechos y expedientes de privacidad |
| Audit | Eventos de auditoría inmutables, exportación y verificación |
| Notifications | Preferencias, plantillas, outbox y entregas idempotentes |

Cada módulo crecerá con cuatro capas: `Domain` sin dependencias de Laravel, `Application` para casos de uso y puertos, `Infrastructure` para adaptadores, y `Http` para controladores, requests y resources. Un controlador valida/delega; una acción aplica autorización y transacción; la infraestructura persiste o integra. Los módulos no consultan tablas ajenas directamente: colaboran mediante contratos de aplicación y eventos explícitos.

## Contrato HTTP

- Prefijo estable: `/api/v1`.
- Éxito: cuerpo JSON con `data` y `meta.request_id`.
- Error: RFC 9457, `application/problem+json`, con `type`, `title`, `status`, `detail`, `instance` y `request_id`.
- Las excepciones inesperadas nunca incluyen mensajes, consultas, rutas internas ni trazas.
- El `X-Request-ID` entrante solo se reutiliza si es un UUID válido; de lo contrario se genera UUIDv7.
- CORS usa una lista exacta de orígenes, métodos y cabeceras. No admite comodines cuando hay credenciales.
- Los límites de tasa son configurables y se separan entre API y sondas.

## Autenticación y sesión

La primera vertical implementada usa Sanctum stateful sobre el guard `web` de Laravel, contraseña Argon2id y una cuenta de identidad separada de cualquier ficha de paciente. No se habilitan personal access tokens. El navegador recibe únicamente una sesión rotada, cifrada, `HttpOnly`, `Secure` en producción y con alcance mínimo. Las mutaciones de navegador requieren la protección CSRF/origin nativa de Laravel. La integración federada futura seguirá un patrón BFF con OIDC Authorization Code + PKCE; el backend conservará los tokens del proveedor fuera del navegador.

No se diseñan endpoints que devuelvan bearer tokens a Angular, no se guardan secretos en `localStorage` o `sessionStorage`, y no existe un rol universal que omita políticas. Toda autorización futura combinará organización activa, rol, relación con el recurso y propósito; el filtrado por organización nunca sustituye una Policy.

El contrato y los puertos de autenticación pendientes se detallan en [authentication.md](authentication.md).

## Persistencia: PostgreSQL como objetivo

La producción utilizará PostgreSQL con:

- TLS con validación del certificado (`DB_SSLMODE=verify-full` cuando la plataforma lo soporte).
- Roles distintos para migraciones y ejecución; el rol de la aplicación no crea esquemas ni extensiones.
- Copias cifradas, restauraciones ensayadas, Point-in-Time Recovery y objetivos RPO/RTO medidos.
- `timestamptz` para eventos, índices parciales y restricciones de exclusión para conflictos de agenda.
- Identificador interno eficiente y UUIDv7 público; nunca se exponen secuencias internas.
- `organization_id` explícito en datos multiempresa y Row Level Security como defensa adicional, nunca como único control.
- Transacciones para invariantes, bloqueo/versión para concurrencia y patrón outbox para efectos externos.

SQLite se limita a desarrollo rápido y tests portables. Las pruebas de integración PostgreSQL deberán ejecutarse en CI antes de aceptar migraciones o consultas específicas.

## Datos sanitarios, privacidad y auditoría

Los datos sensibles se minimizan y cifran en tránsito y reposo; los secretos y claves viven en un gestor externo con rotación. Los logs no incluyen cuerpo de petición, tokens, documentos, consultas/bindings ni mensajes arbitrarios de excepción: registran clase, fingerprint y request-id saneados. El módulo Audit se implementará con eventos append-only, sellado/hash encadenado y almacenamiento con retención protegida. Descargas y exportaciones usarán autorización puntual, URL de vida corta y registro de propósito.

## Operación

- `/api/v1/health/live` prueba el proceso sin tocar dependencias.
- `/api/v1/health/ready` comprueba la base de datos y devuelve 503 saneado si falla.
- `APP_DEBUG=false`, clave presente, `APP_URL` HTTPS, cookie segura, hosts/CORS HTTPS explícitos y HSTS validado son puertas de producción.
- Los proxies de confianza permanecen vacíos salvo configuración explícita de rangos de la plataforma; nunca se confía en `X-Forwarded-*` desde cualquier origen.
- Las migraciones se prueban hacia delante y atrás, se revisan con `migrate --pretend` y no destruyen datos sin estrategia de copia/rollback.
- Tras cada cambio: Pint, tests, validación de Composer, auditoría de dependencias, cachés y listado de rutas.
