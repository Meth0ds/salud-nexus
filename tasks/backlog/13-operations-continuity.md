# 13 — Observabilidad, infraestructura y continuidad

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| OBS-001 | Instrumentar OpenTelemetry backend | HTTP, DB, Redis, queues y externos con context propagation | Trace integration | BE-025 | L |
| OBS-002 | Instrumentar frontend seguro | Web vitals/errores/rutas normalizadas sin IDs ni PHI | Network/DLP tests | FE-036 | M |
| OBS-003 | Crear logging JSON allowlist | Campos permitidos, niveles, correlation y sanitizer central | Canary leak tests | BE-024 | M |
| OBS-004 | Separar audit de logs técnicos | Storage, permisos, retención y consultas distintos | Architecture/permission tests | AUD-003 | M |
| OBS-005 | Crear métricas RED | Rate/errors/duration por endpoint normalizado | Metrics tests | OBS-001 | M |
| OBS-006 | Crear métricas USE | CPU/mem/pools/DB/Redis/queues/objects/workers | Dashboard validation | OBS-001 | M |
| OBS-007 | Crear métricas negocio sin IDs | Citas, holds, conflictos, docs, waits y notifs sin cardinalidad sensible | Label scanner | REP-001 | M |
| OBS-008 | Definir SLO dashboards | Disponibilidad/latencia/error/colas/RPO con ventanas | SLO calculations | GOV-014, OBS-005..007 | M |
| OBS-009 | Crear alertas por síntomas | Error/latencia/saturación/cola/dependency/storage/backups | Alert simulation | OBS-008 | L |
| OBS-010 | Crear alertas de seguridad | Denials, downloads, break-glass, audit tamper y privileges | Attack simulation | AUD-013, OBS-009 | L |
| OBS-011 | Aplicar egress seguro a telemetría | TLS, allowlist, identity y redaction before export | Packet/policy tests | OBS-001..003 | M |
| OBS-012 | Implementar UI estado/contingencia | Mockup DES-123 con degradación y procedimientos | Playwright | OBS-008..010 | M |
| INF-001 | Crear imágenes multistage | Versiones/digests, non-root, mínimas y sin build deps runtime | Container tests | TOOL-013 | M |
| INF-002 | Endurecer contenedores | Read-only, tmpfs, dropped caps, seccomp y resource limits | Policy-as-code | INF-001 | M |
| INF-003 | Separar workloads | API, scheduler y workers por cola con identidades/permisos propios | Integration/policy | INF-001 | L |
| INF-004 | Configurar gateway/proxy | TLS local, headers, body/time limits, routing de dos portales | Integration/header tests | TOOL-013, BE-021..023 | L |
| INF-005 | Diseñar CDN/WAF/DDoS | Solo assets cacheables; API no-store; rate/rules/runbook | Config tests/tabletop | INF-004 | M |
| INF-006 | Segmentar red | DB/Redis/S3 privados, egress mínimo y admin vía ZTNA/VPN | Network scan | GOV-013 | L |
| INF-007 | Configurar PostgreSQL HA/roles | Runtime/migration/audit/backup/read roles, TLS, pool y timeouts | Permission/failover tests | TOOL-010, AUD-003 | L |
| INF-008 | Configurar Redis seguro | Private/TLS/ACL/maxmemory/no dangerous runtime commands | ACL/network tests | TOOL-011 | M |
| INF-009 | Configurar objetos privados | Public block, versioning, encryption, lifecycle y object lock opcional | Policy analyzer | TOOL-012 | M |
| INF-010 | Configurar KMS/secret manager | Separación por env/uso, workload identity, rotation y audit | Rotation/policy tests | BE-028 | L |
| INF-011 | Crear IaC | Entornos, red, compute, data, observabilidad y policies revisables | IaC validate/scan | INF-004..010 | L |
| INF-012 | Detectar drift | Plan periódico, alertas y remediación aprobada | Simulated drift | INF-011 | M |
| INF-013 | Separar entornos/cuentas | Dev/int/staging/preprod/prod sin credenciales/datos compartidos | Inventory policy | INF-011 | L |
| INF-014 | Crear identidad administrativa | MFA phishing-resistant, dispositivo gestionado y acceso JIT | Access denial tests | IAM-008, IAM-037 | M |
| INF-015 | Configurar parcheo/EOL | SLA por severidad, mantenimiento y rollback | Compliance report | GOV-022 | M |
| BCP-001 | Aprobar BIA/RPO/RTO | 99.9%, RPO15/RTO2 o valores sustitutos firmados | BIA approval | GOV-014 | M |
| BCP-002 | Configurar backups PostgreSQL/PITR | Automáticos, cifrados, cuenta separada y dentro de RPO | Restore to known txn | INF-007, INF-010 | L |
| BCP-003 | Configurar backups de objetos/config/audit | Versiones, manifiestos, claves recuperables y copia inmutable | Restore/integrity | INF-009, AUD-006 | L |
| BCP-004 | Crear restore integral automatizado | Entorno aislado, DB+objects+config+audit y validación | Timed restore | BCP-002..003 | L |
| BCP-005 | Crear runbook frontend/API | Detectar, degradar, rollback y verificar | Tabletop/chaos | INF-004 | M |
| BCP-006 | Crear runbook PostgreSQL/Redis | Failover/rebuild sin perder verdad o duplicar jobs | Chaos test | INF-007..008 | M |
| BCP-007 | Crear runbook IdP/mensajería/firma | Modo seguro y procedimientos alternativos sin bypass | Provider outage test | INT-010..012 | M |
| BCP-008 | Crear runbook pérdida regional | DNS, data, objects, keys y dependencias ordenados | Timed exercise | BCP-004 | L |
| BCP-009 | Crear plan ransomware | Aislar, preservar, rotar, reconstruir limpio y comunicar | Tabletop | BCP-004, COMP-008 | M |
| BCP-010 | Crear plan corrupción de datos | Detectar, detener, reconciliar y conservar evidencia | Injected corruption | BCP-004 | L |
| BCP-011 | Probar replay de colas | Outbox/retries no duplican citas/docs/notifs | Recovery test | BE-017, BE-018 | M |
| BCP-012 | Crear respuesta a incidentes | Severidad, roles, canales alternativos, custodia y comunicación | Simulation | COMP-008 | M |
| BCP-013 | Calendarizar simulacros | Restore, provider, ransomware, insider y fuga con acciones | Calendar/evidence | BCP-004..012 | S |
| BCP-014 | Crear canal de vulnerabilidades | security.txt, triage, SLA, safe harbor y disclosure | Test report | — | S |
