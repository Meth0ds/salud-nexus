# 14 — QA, seguridad ofensiva, accesibilidad y rendimiento

Estado verificable del corte de contrato: `QA-009` está `[x]`. Laravel compara todas sus rutas API
nombradas con OpenAPI y Angular regenera el cliente en un directorio temporal para detectar cualquier
deriva byte a byte. Véase la [evidencia del contrato API](../evidence/contrato-openapi-cliente-angular.md).

| ID       | TODO                                    | Aceptación                                                                          | Verificación               | Dep.                      | Tamaño |
| -------- | --------------------------------------- | ----------------------------------------------------------------------------------- | -------------------------- | ------------------------- | ------ |
| QA-001   | Crear estrategia y pirámide de pruebas  | Responsabilidad, entorno, datos, flakiness, coverage y evidencias definidos         | Review document            | GOV-002                   | M      |
| QA-002   | Crear fixtures sintéticos deterministas | Roles/centro/pacientes/estados/fechas reproducibles, sin PII real                   | Seed twice + DLP scan      | GOV-026, BE-032           | M      |
| QA-003   | Unitarias de dominio                    | Invariantes, límites y errores de todos los agregados críticos                      | Mutation/branch coverage   | Dominios                  | L      |
| QA-004   | Tests de arquitectura                   | Límites Laravel/Angular, dependencias, DTOs y controllers finos                     | CI architecture suites     | BE-004, FE-032            | M      |
| QA-005   | Integración PostgreSQL real             | Constraints, locks, transactions, indexes y roles en versión fijada                 | CI service tests           | TOOL-010                  | L      |
| QA-006   | Integración Redis real                  | Sesiones, locks, throttle, queue, TTL y caída                                       | CI/fault tests             | TOOL-011                  | M      |
| QA-007   | Integración objetos/AV/PDF              | MinIO/S3, quarantine, scanner y renderer                                            | CI/fault tests             | DOC-007, DOC-028          | L      |
| QA-008   | Contract tests de puertos               | OIDC, correo, SMS, push, firma, FHIR y externos                                     | Offline simulators         | INT-009..023              | L      |
| QA-009   | Contract drift OpenAPI                  | Backend, spec y cliente no divergen                                                 | CI regenerate/diff         | BE-033..034               | M      |
| QA-010   | API happy/negative matrix               | Auth, policy, validation, errors, pagination, idempotencia y ETag por ruta          | API suite report           | BE-008..019               | L      |
| QA-011   | E2E paciente completo                   | G/P routes y overlays críticos del inventario cubiertos                             | Playwright report          | Features paciente         | L      |
| QA-012   | E2E profesional completo                | C routes, clínica limitada y agenda cubiertas                                       | Playwright report          | Features staff            | L      |
| QA-013   | E2E recepción/admin completo            | R/A routes y operaciones elevadas cubiertas                                         | Playwright report          | Features admin            | L      |
| QA-014   | E2E seguridad/privacidad completo       | S routes, break-glass, audit, DSR e incidentes cubiertos                            | Playwright report          | Features security         | L      |
| QA-015   | E2E quiosco/público                     | Verificación, check-in, error/expiry y limpieza cubiertos                           | Playwright report          | DOC-022, ROOM-006         | M      |
| QA-016   | Cubrir todos los estados UI             | Loading/empty/error/denied/offline/concurrent/expired/partial por perfil            | Inventory coverage script  | FE-008, DES-020..123      | L      |
| QA-017   | Visual regression                       | Route×viewport×theme×state baselines con revisión explícita                         | Playwright snapshots       | DES-133, FE-039           | L      |
| QA-018   | Test de motion                          | Duración/easing/reduced-motion/cleanup/no keyboard animation                        | Browser/trace tests        | FE-029..030               | M      |
| A11Y-001 | Axe automático por ruta                 | Cero serious/critical; excepciones con caducidad                                    | `npm run test:a11y`        | FE-040                    | L      |
| A11Y-002 | Navegación teclado                      | Orden, foco, skip, overlays, calendario, tabla y dialogs completos                  | Manual+Playwright          | FE-005..016               | L      |
| A11Y-003 | NVDA Windows                            | Recorridos patient/staff críticos y anuncios sin PHI excesiva                       | Checklist/evidence         | A11Y-002                  | L      |
| A11Y-004 | VoiceOver Safari                        | Recorridos paciente móvil críticos                                                  | Checklist/evidence         | A11Y-002                  | L      |
| A11Y-005 | Zoom/reflow                             | 200% y 400%, 320 CSS px, sin pérdida/scroll bidimensional salvo tablas justificadas | Screenshot/manual          | FE-003..008               | M      |
| A11Y-006 | Contraste/forced colors                 | Texto, controles, foco, estados y gráficos WCAG 2.2 AA                              | Automated/manual           | FE-003                    | M      |
| A11Y-007 | Target size/orientation                 | Objetivos táctiles y ambas orientaciones según WCAG 2.2                             | Device matrix              | FE-003                    | M      |
| A11Y-008 | Tiempo/movimiento                       | Sesión ampliable, contadores anunciados y reduced motion completo                   | E2E/manual                 | FE-023, FE-030            | M      |
| A11Y-009 | Formularios accesibles                  | Labels persistentes, help/error, summary y focus first invalid                      | Harness/manual             | FE-014..016               | L      |
| A11Y-010 | Validación WCAG independiente           | Auditor externo sin blockers; plan para restantes                                   | Signed report              | A11Y-001..009             | L      |
| PERF-001 | Definir perfil de carga                 | Usuarios, datos, concurrency, ramp, think time y SLA documentados                   | Review profile             | GOV-014                   | M      |
| PERF-002 | Benchmark lecturas/escrituras           | p95 <300/<500 ms bajo perfil acordado                                               | k6 report                  | PERF-001                  | M      |
| PERF-003 | Benchmark disponibilidad/reserva        | <2 s/<1 s y 0 dobles reservas                                                       | k6 + DB assertions         | APT-024..026              | L      |
| PERF-004 | Benchmark jornada personal              | Agenda, cola, paciente y meds en hora punta                                         | Load report                | PERF-001                  | L      |
| PERF-005 | Benchmark jobs                          | Recordatorios, PDF, export, import, FHIR y recovery de cola                         | Load/failure               | BE-018                    | L      |
| PERF-006 | Benchmark audit/reporting               | Append y consultas no degradan OLTP                                                 | Load/query plans           | AUD-003, REP-002          | L      |
| PERF-007 | Verificar índices/queries               | No N+1/full scans inesperados; planes guardados                                     | Query analysis             | PERF-002..006             | M      |
| PERF-008 | Presupuestos frontend                   | Bundle por ruta, LCP/INP/CLS y memoria dentro de límites                            | Build/Lighthouse           | FE-038                    | M      |
| PERF-009 | Motion 60 fps                           | Sin long tasks/layout thrash; low-end profile y cleanup                             | DevTools traces            | FE-030                    | M      |
| PERF-010 | Soak/recovery                           | Sesiones, workers, pools y memoria estables durante prueba larga                    | Soak report                | PERF-002..009             | L      |
| SEC-001  | Mantener threat model ejecutable        | TM-001..022 enlazadas a controles/pruebas/riesgo residual                           | Threat review              | GOV-009                   | L      |
| SEC-002  | Matriz ASVS 5.0 L3                      | 345 IDs, aplicabilidad, control, evidencia y aprobación N/A                         | Count/hash/review          | COMP-001..002             | L      |
| SEC-003  | SAST PHP/TS                             | Reglas de auth, injection, SSRF, XSS, crypto y datos; canarios bloquean             | CI canary                  | TOOL-032                  | M      |
| SEC-004  | SCA alcanzable                          | npm/composer/container OS; crítico/alto alcanzable bloquea                          | Audit report/VEX           | TOOL-026, TOOL-028        | M      |
| SEC-005  | Secret scan completo                    | Working tree, historial, imágenes y artefactos limpios                              | Canary/history scan        | TOOL-029                  | M      |
| SEC-006  | Supply-chain signatures                 | Lockfiles, provenance, scripts, licencias, SBOM y signed artifacts                  | CI evidence                | TOOL-023..031             | L      |
| SEC-007  | Test autenticación                      | OIDC, passwords, MFA, WebAuthn, recovery, enumeration y sessions                    | Security report            | IAM-001..024              | L      |
| SEC-008  | Test autorización                       | Horizontal, vertical, cross-org, relation, purpose, time, restriction y support     | Matrix/fuzz                | IAM-025..044              | L      |
| SEC-009  | Test CSRF/CORS/headers                  | Cross-site, Origin/Fetch Metadata, cookie y proxy spoof                             | Browser/API report         | BE-021..023, FE-018       | M      |
| SEC-010  | Test XSS/Trusted Types                  | Reflected/stored/DOM, rich content, templates y document preview                    | Corpus/browser             | FE-034, DOC-002           | L      |
| SEC-011  | Test injection                          | SQL, command, template, CSV formula, regex, XML/deserialization según superficie    | Fuzz/SAST                  | BE-010, DOC-002           | L      |
| SEC-012  | Test SSRF/redirect/webhooks             | IPv4/6, metadata, DNS rebind, redirects, replay y signatures                        | Security suite             | INT-004, INT-006, INT-026 | L      |
| SEC-013  | Test archivos                           | EICAR, polyglot, zip bomb, traversal, parser, active content y quota                | Security suite             | DOC-028..030              | L      |
| SEC-014  | Test lógica de citas                    | Idempotency, race, holds, waitlist, recurrence y resource overlaps                  | Concurrency suite          | APT-024                   | L      |
| SEC-015  | Test exfiltración/enumeración           | Search, counts, filters, exports, verify tokens, errors y timings                   | Fuzz/load                  | PAT-007, DOC-022, REP-011 | L      |
| SEC-016  | Test logs/telemetría/DLP                | Corpus sensible no aparece en logs, traces, metrics, browser ni notifications       | Scanner/capture            | BE-024, OBS-003, NOT-001  | L      |
| SEC-017  | Test audit integrity                    | Mutation/delete/reorder/gap/role DB/WORM/clock drift                                | Tamper suite               | AUD-003..008              | L      |
| SEC-018  | Test break-glass/PAM                    | Rol, reason, AAL, scope, TTL, alert, review y patient transparency                  | E2E/security               | BG-001..005               | L      |
| SEC-019  | DAST autenticado                        | Patient/staff/security con datos sintéticos y OpenAPI                               | Triaged report             | QA-011..015               | L      |
| SEC-020  | Fuzz API continuo                       | Schema-aware + corpus Unicode/limits/state; crashes reproducibles                   | CI/nightly artifacts       | BE-010                    | L      |
| SEC-021  | Container/IaC scan                      | Critical misconfig/vuln bloquea y excepciones caducan                               | CI canaries                | INF-001..012              | M      |
| SEC-022  | Deep security scan del repo             | Revisión multi-pass independiente tras implementación completa                      | Informe/canonical findings | Features completas        | L      |
| SEC-023  | Corregir y retestar hallazgos           | Cada finding validado tiene fix, regression y cierre                                | Retest evidence            | SEC-022                   | L      |
| SEC-024  | Pentest externo                         | Sin críticos/altos pendientes; retest firmado                                       | External report            | SEC-001..023              | L      |
| SEC-025  | Privacy adversarial review              | Insider, linkage, screenshots, exports, notification y analytics abuse              | Report/tabletop            | PRIV/AUD/REP              | L      |
| QA-019   | Cero flaky tests ocultos                | Retry no enmascara fallos; quarantine temporal con owner/expiry                     | Flake dashboard            | QA-001..018               | M      |
| QA-020   | Dossier automático por build            | Reports, SBOM, signatures, screenshots, ASVS y hashes reunidos                      | Artifact validation        | QA/SEC/A11Y/PERF          | L      |
