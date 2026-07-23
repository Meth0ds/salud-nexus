# 15 — Preparación y salida a producción

Estos ítems separan claramente lo que puede implementar el repositorio de lo que exige autoridad, contratos, proveedores o revisión independiente. Un simulador verde no convierte un proveedor en homologado.

| ID      | TODO                                      | Aceptación                                                                | Verificación / evidencia   | Dep.                  | Tamaño |
| ------- | ----------------------------------------- | ------------------------------------------------------------------------- | -------------------------- | --------------------- | ------ |
| REL-001 | Cerrar trazabilidad funcional             | Cada requisito no excluido tiene tarea, código, mockup, endpoint y prueba | Coverage report            | GOV-020               | L      |
| REL-002 | Cerrar TODOs técnicos                     | Cero TODO/FIXME/skip/quarantine/feature stub no registrado                | Repo scan                  | Todos                 | M      |
| REL-003 | Congelar versiones/locks/SBOM             | Toolchain soportado, lockfiles y SBOM firmados                            | Release artifacts          | TOOL-024..035         | M      |
| REL-004 | Aprobar modelo de autorización            | Clínico, seguridad y DPD revisan roles/ABAC/break-glass                   | Signed decision            | IAM-044, BG-005       | L      |
| REL-005 | Completar EIPD                            | Riesgo residual aceptado y medidas verificadas                            | Signed EIPD                | COMP-004              | L      |
| REL-006 | Completar ROPA/proveedores/DPA            | Tratamientos, encargados, subencargados y transferencias completos        | Signed register/contracts  | COMP-003, COMP-007    | L      |
| REL-007 | Aprobar bases/avisos/consentimientos      | Textos y flujos por asesoría/DPD                                          | Legal sign-off             | PRIV-001..005         | M      |
| REL-008 | Aprobar retención autonómica/legal hold   | Matriz por categoría y ejecución probada                                  | Legal sign-off + tests     | DATA-003..005         | L      |
| REL-009 | Determinar/aprobar ENS                    | Categoría/SoA/auditoría cuando corresponda                                | Formal evidence            | COMP-005              | L      |
| REL-010 | Aprobar EHDS roadmap                      | Gaps, fechas y propietarios                                               | Signed matrix              | COMP-006              | M      |
| REL-011 | Seleccionar/homologar IdP                 | MFA/passkeys/SLA/regions/logs/recovery y contrato                         | Provider assessment        | INT-010, IAM-001..024 | L      |
| REL-012 | Seleccionar mensajería productiva         | Email/SMS/push, DPA, delivery, opt-out y security                         | Provider assessment        | INT-011               | M      |
| REL-013 | Seleccionar firma/prestador               | Nivel eIDAS por documento, certificados y validación                      | Legal/provider evidence    | DOC-012..015          | L      |
| REL-014 | Seleccionar AV/S3/KMS/WORM/SIEM           | Contratos, región, encryption, logging y exit plan                        | Provider assessments       | INF-009..010, AUD-006 | L      |
| REL-015 | Negociar perfiles FHIR                    | Versión/perfiles/terminología/conformance y sandbox                       | Signed interface agreement | INT-014..017          | L      |
| REL-016 | Validar migración de datos                | Mapping, muestras sintéticas/anonimizadas, reconciliación y rollback      | Migration rehearsal        | PAT-024..025          | L      |
| REL-017 | Completar pruebas funcionales             | Todas suites, estados y roles verdes                                      | QA reports                 | QA-001..020           | L      |
| REL-018 | Completar WCAG 2.2 AA                     | Automatizado + revisión manual/independiente sin blockers                 | A11Y report                | A11Y-001..010         | L      |
| REL-019 | Completar rendimiento/capacidad           | SLOs y headroom aprobados; degradación conocida                           | PERF report                | PERF-001..010         | L      |
| REL-020 | Completar AppSec/ASVS L3                  | Requisitos aplicables satisfechos y N/A aprobados                         | ASVS dossier               | SEC-001..023          | L      |
| REL-021 | Completar pentest/retest externo          | Cero críticos/altos; medios con owner/fecha/riesgo                        | Signed report              | SEC-024               | L      |
| REL-022 | Probar backups/restore                    | RPO/RTO e integridad DB/objects/audit/keys demostrados                    | Timed restore report       | BCP-002..004          | L      |
| REL-023 | Ejecutar simulacros                       | IdP, DB/Redis, provider, ransomware, insider, fuga y región               | Exercise reports           | BCP-005..013          | L      |
| REL-024 | Aprobar incident response/breach          | Guardias, canales, custodia, comunicación y 72 h                          | Tabletop sign-off          | BCP-012, COMP-008     | M      |
| REL-025 | Preparar runbooks operativos              | Deploy, rollback, scale, queue, data, provider y security                 | On-call walkthrough        | BCP-005..012          | L      |
| REL-026 | Configurar monitorización/alertas/SIEM    | Dashboards, routing, escalation y canarios verificados                    | Alert fire drills          | OBS-008..011          | L      |
| REL-027 | Formar usuarios por rol                   | Privacidad, seguridad, flujos, contingencia y no emergencias              | Attendance/exam            | GOV-024               | L      |
| REL-028 | Preparar soporte                          | SLA, identidad, soporte limitado, conocimiento y escalado                 | Support rehearsal          | IAM-041..045          | M      |
| REL-029 | Completar offboarding/revisión de accesos | Altas/bajas/sustituciones/certificación ejecutables                       | Access review drill        | IAM-036..040          | M      |
| REL-030 | Preparar despliegue progresivo            | Canary/blue-green, migrations expand-contract, health y abort             | Staging rehearsal          | INF-011..015          | L      |
| REL-031 | Probar rollback completo                  | App/config/schema compatible y recovery de jobs                           | Timed rollback             | REL-030               | L      |
| REL-032 | Firmar artefactos/provenance              | Deploy solo acepta digest firmado y verificable                           | Admission test             | TOOL-028, SEC-006     | M      |
| REL-033 | Crear dossier de release                  | Spec, ADR, SBOM, ASVS, tests, pentest, approvals y hashes                 | Signature validation       | QA-020, REL-001..032  | L      |
| REL-034 | Ejecutar go/no-go                         | Responsables producto/clínico/DPD/security/ops aceptan o bloquean         | Signed decision            | REL-033               | M      |
| REL-035 | Ejecutar piloto controlado                | Cohorte limitada del centro, soporte reforzado, métricas y rollback       | Pilot report               | REL-034               | L      |
| REL-036 | Corregir hallazgos del piloto             | Cero bloqueantes y riesgos actualizados                                   | Retest/go-no-go            | REL-035               | L      |
| REL-037 | Desplegar producción progresivamente      | Observabilidad reforzada, cambios auditados y abort automático            | Deployment record          | REL-036               | L      |
| REL-038 | Verificar post-deploy                     | SLO, auth, booking, audit, notifs, docs y backups sanos                   | Smoke/synthetic tests      | REL-037               | M      |
| REL-039 | Revisar permisos tras salida              | Cuentas/roles temporales y accesos de despliegue revocados                | Access report              | REL-038               | M      |
| REL-040 | Programar mejora continua                 | Parches, ASVS, riesgos, acceso, restore, a11y y proveedores con cadencia  | Calendar/owners            | REL-038               | M      |

## Gate absoluto

No se habilitan datos reales ni usuarios productivos mientras REL-004 a REL-024 no estén completados por las autoridades correspondientes. Codificar una pantalla, un adapter o un documento preliminar no satisface por sí solo estos controles.
