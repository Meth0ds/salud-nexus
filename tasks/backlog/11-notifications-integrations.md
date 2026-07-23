# 11 — Notificaciones e integraciones

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| NOT-001 | Modelar plantilla de notificación | Canal, evento, idioma, centro, versión y variables allowlist | Domain/migration | BE-031 | M |
| NOT-002 | Implementar editor/preview/aprobación | Solo datos sintéticos en preview; doble rol editor/revisor | Injection/E2E | NOT-001 | L |
| NOT-003 | Modelar preferencias/supresiones | Canal/evento/usuario, obligatorio vs opcional y vigencia | Domain tests | PAT-004 | M |
| NOT-004 | Implementar centro de preferencias | No permite desactivar avisos legales/seguridad obligatorios | E2E/policy | NOT-003 | M |
| NOT-005 | Modelar Notification/Delivery | Evento, destinatario, plantilla snapshot, canal, intentos y estado | Domain tests | NOT-001 | M |
| NOT-006 | Producir notificación desde outbox | Mismo commit de negocio, consumidor idempotente y sin payload clínico | Integration/crash | BE-017, NOT-005 | L |
| NOT-007 | Crear puerto de correo | Send/status/webhook, timeout, idempotencia y simulador Mailpit | Contract tests | NOT-005 | M |
| NOT-008 | Crear puerto SMS | Send/status/webhook, números enmascarados y simulador | Contract tests | NOT-005 | M |
| NOT-009 | Crear bandeja interna | Contenido autorizado, lectura/archivo y paginación | API/E2E | NOT-005 | M |
| NOT-010 | Crear puerto push | Opt-in, tokens cifrados, revocación y payload sin PHI | Contract/security | NOT-005, BE-029 | M |
| NOT-011 | Implementar recordatorios de cita | Ventanas/reglas/zonas, dedup y cancelación/reprogramación correctas | Clock/integration | APT-004..010, NOT-006 | L |
| NOT-012 | Implementar oferta de lista de espera | Canal seguro, expiración y no revela detalles clínicos | E2E/clock | WAIT-002, NOT-006 | M |
| NOT-013 | Implementar avisos de documento | Solo existencia/acción; sin tipo clínico sensible en canal externo | Snapshot/DLP tests | DOC-017, NOT-006 | M |
| NOT-014 | Implementar avisos de seguridad | Nuevo dispositivo, credencial, privilegio y acceso delegado | E2E | IAM-018, IAM-039, NOT-006 | M |
| NOT-015 | Implementar avisos de medicación administrativos | Estado de solicitud sin medicamento/diagnóstico por canal externo | DLP tests | MED-011..014, NOT-006 | M |
| NOT-016 | Implementar retries/backoff/DLQ | Políticas por canal, jitter, fallo final y reproceso autorizado | Fault injection | NOT-007..010, BE-018 | M |
| NOT-017 | Procesar webhooks de entrega | Firma/timestamp/replay, mapeo idempotente y audit | Security/contract | NOT-007..010 | L |
| NOT-018 | Implementar suppressions/bounces | Evita loops y permite revisión autorizada | Integration tests | NOT-017 | M |
| NOT-019 | Implementar UI bandeja paciente | Mockups DES-073/074 y estados reales | Playwright/axe | NOT-009 | M |
| NOT-020 | Implementar UI administración de entregas | Fallos, DLQ, supresiones y reintentos sin contenido sensible | Playwright/security | NOT-016..018 | L |
| INT-001 | Crear registro de integraciones | Propietario, datos, finalidad, SLA, región, contrato y salida | Inventory review | GOV-028 | M |
| INT-002 | Crear arquitectura ports/adapters | Lógica externa fuera de controllers/models y contratos versionados | Architecture tests | BE-003 | M |
| INT-003 | Implementar configuración segura | Secret refs, no valores; versión, endpoints allowlist y feature flag | Security tests | INT-001, BE-028 | M |
| INT-004 | Implementar HTTP client endurecido | TLS verify, timeout, tamaño, redirects off, egress allowlist y redacción | SSRF/fault tests | INT-002 | M |
| INT-005 | Implementar resiliencia | Circuit breaker, backoff+jitter, bulkhead, idempotencia y métricas | Fault injection | INT-004 | M |
| INT-006 | Implementar webhooks/callbacks | Firma, timestamp, nonce, replay cache y schema estricto | Negative suite | INT-002, TOOL-011 | L |
| INT-007 | Implementar mapeos versionados | Provenance, transformación, validación y rollback | Contract/property | INT-002 | M |
| INT-008 | Implementar DLQ/reproceso | Mensajes mínimos/cifrados, permisos y resultado idempotente | Failure/security | INT-005, BE-018 | M |
| INT-009 | Crear simulador por integración | Happy/error/timeout/rate-limit/invalid/replay sin red externa | Offline contract CI | INT-002 | L |
| INT-010 | Implementar adaptador OIDC | Conforme IAM-022 y con discovery/keys cache seguros | Contract/security | IAM-022, INT-004 | M |
| INT-011 | Implementar adaptadores correo/SMS/push | Reutilizan puertos NOT y webhooks seguros | Contract tests | NOT-007..010, INT-006 | M |
| INT-012 | Implementar adaptador firma | Conforme DOC-013 y validación de callback | Contract/security | DOC-013, INT-006 | M |
| INT-013 | Implementar adaptador calendario | ICS y proveedor opcional con scope mínimo | Contract tests | APT-018, INT-004 | M |
| INT-014 | Crear capa anticorrupción FHIR | Modelo interno→R4/R4B/R5 por perfil elegido, nunca ORM directo | Architecture/contract | GOV-015, INT-007 | L |
| INT-015 | Mapear recursos FHIR | Patient, Practitioner/Role, Organization, Location, Service, Schedule/Slot/Appointment, Medication*, DocumentReference, Consent, AuditEvent | Profile tests | INT-014 | L |
| INT-016 | Validar FHIR | Perfiles, terminología, cardinalidad, references, provenance y auth | Invalid/cross-ref tests | INT-015 | L |
| INT-017 | Implementar export/import FHIR jobs | Snapshot, async, idempotencia, reconciliación y rollback | Contract/load/security | INT-016, BE-018 | L |
| INT-018 | Implementar adaptador sistema clínico | Contrato genérico y simulador; datos entrantes no se validan automáticamente | Contract/security | INT-007 | L |
| INT-019 | Implementar adaptador medicación/receta | Read/reconcile only hasta aprobación de prescripción | Contract/clinical review | MED-020, INT-007 | L |
| INT-020 | Implementar adaptador laboratorio | DocumentReference/resultado gated, minimizado y simulado | Contract/policy | INT-018 | M |
| INT-021 | Implementar adaptador aseguradora | Elegibilidad/autorización con timeout y resultado versionado | Contract/failure | INT-004 | M |
| INT-022 | Implementar adaptador facturación | Facturas básicas, idempotencia y scope financiero | Contract/security | INT-007 | L |
| INT-023 | Implementar adaptador teleconsulta | Sala/enlace opaco, acceso temporal; feature off hasta gobernanza | Contract/security | INT-004 | M |
| INT-024 | Implementar UI salud de integraciones | Mockup DES-122, fallos, breaker y reproceso | Playwright | INT-005..009 | M |
| INT-025 | Auditar todas las integraciones | Actor/sistema/finalidad/resultado/correlation sin payload | Audit coverage/DLP | INT-010..024 | M |
| INT-026 | Probar egress/SSRF | DNS rebinding, IPv4/6 privadas, metadata, redirects y puertos bloqueados | Security suite | INT-004 | L |

