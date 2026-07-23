# 12 — Administración, informes y gobierno del dato

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| ADM-001 | Modelar reglas versionadas | Nombre, scope, prioridad, vigencia, versión, aprobador y rollback | Domain tests | ORG-001 | M |
| ADM-002 | Implementar antelación/cancelación/máximos | Reglas explicables y probadas en límites | Decision table tests | ADM-001, SCH-007 | M |
| ADM-003 | Implementar duración/buffers/capacidad | Servicio/tipo/profesional/recursos y precedencia determinista | Property tests | ADM-001, SCH-002 | M |
| ADM-004 | Implementar sobreagenda/prioridad | Permiso, límites, motivo y audit | Policy/domain | ADM-001, APT-014 | M |
| ADM-005 | Implementar lista de espera/confirmación | Política versionada y snapshot en cada caso | Clock/domain | ADM-001, WAIT-001 | M |
| ADM-006 | Implementar requisitos/edad/derivación/aseguradora | Mensaje administrativo, sin inferencia clínica indebida | Decision table tests | ADM-001, SCH-007 | L |
| ADM-007 | Implementar editor/simulador de reglas | Preview con fixtures sintéticos y diff de impacto | E2E/property | ADM-001..006 | L |
| ADM-008 | Implementar aprobación/publicación de reglas | Doble rol, programación, invalidate approvals al cambiar | E2E/concurrency | ADM-007 | M |
| ADM-009 | Implementar UI reglas | Mockup DES-105 con versiones, preview y rollback | Playwright/axe | ADM-007..008 | L |
| ADM-010 | Implementar UI plantillas multicanal | Mockup DES-104 integrado con NOT/DOC y aprobaciones | Playwright | NOT-002, DOC-003 | L |
| ADM-011 | Implementar UI usuarios/roles/ámbitos | Mockup DES-110 con impacto y doble control | Playwright/policy | IAM-025..040 | L |
| ADM-012 | Implementar UI sesiones/dispositivos personal | Mockup DES-111 y revocación | Playwright | IAM-015..018 | M |
| ADM-013 | Implementar UI certificación de accesos | Mockup DES-112 y campañas | Playwright | IAM-040 | M |
| ADM-014 | Implementar UI soporte/exportaciones críticas | Mockups DES-117/118 | Playwright/security | IAM-041, PAT-026 | M |
| REP-001 | Definir catálogo de métricas | Fórmula, fuente, owner, ventana, dimensión permitida y privacidad | Review/test query | GOV-014, GOV-006 | M |
| REP-002 | Crear read models operativos | Actualización eventual documentada y sin bloquear OLTP | Integration/performance | BE-016 | L |
| REP-003 | Implementar informe de citas | Volumen, ocupación, próxima cita, cancelación, ausencia y reprogramación | Query/API tests | REP-001..002, APT-003 | M |
| REP-004 | Implementar informe de espera/recursos | Espera, sala, lista y utilización | Query/API tests | REP-001..002, ROOM-003, WAIT-001 | M |
| REP-005 | Implementar informe de comunicaciones | Envíos, fallos, supresiones y latencia | Query/API tests | REP-001..002, NOT-005 | M |
| REP-006 | Implementar informe de activación portal | Invitación, activación y abandono sin datos personales en agregado | Query/privacy tests | REP-001..002, PAT-014 | M |
| REP-007 | Implementar informe de seguridad | Fallos, bloqueo, MFA, break-glass, exportaciones, permisos y anomalías | Query/security | REP-001..002, AUD-013 | L |
| REP-008 | Implementar informe de privacidad | DSR, SLA, accesos, incidentes, consentimientos y retención | Query/privacy | REP-001..002, PRIV-008 | L |
| REP-009 | Aplicar minimización/supresión estadística | Umbrales, redacción de dimensiones y no reidentificación | Privacy tests | REP-003..008 | M |
| REP-010 | Implementar filtros autorizados | Scope backend, límites de fecha/cardinalidad y query budget | Auth/performance | REP-003..009 | M |
| REP-011 | Implementar exportación de informe | Async, snapshot, step-up según riesgo, cifra/caduca/audita | E2E/security | REP-010, BE-018 | L |
| REP-012 | Crear vistas materializadas/réplica | Refresh controlado y fallback; nunca consulta pesada no limitada en primary | Load/failure | REP-002 | L |
| REP-013 | Preparar almacén analítico | Puerto/eventos seudonimizados; feature off hasta EIPD específica | Architecture/privacy | REP-002, COMP-004 | M |
| REP-014 | Implementar UI informes operativos | Mockup DES-120 con tabla accesible equivalente | Playwright/axe | REP-003..006 | L |
| REP-015 | Implementar UI seguridad/privacidad | Mockup DES-121 con drill-down autorizado | Playwright/policy | REP-007..010 | L |
| DATA-001 | Implementar catálogo de datos UI/API | Definición, owner, clasificación, fuente, finalidad, base, retención y calidad | Reconciliar schema/OpenAPI | GOV-006 | L |
| DATA-002 | Implementar clasificación | Público/interno/confidencial/protegido/crítico y policy de manejo | Static/schema tests | DATA-001 | M |
| DATA-003 | Implementar matriz de retención | Por clínica, citas, docs, facturas, audit, comunicaciones, consentimientos, DSR y cuentas | Legal review/time tests | GOV-007, DATA-001 | L |
| DATA-004 | Implementar legal hold | Scope, motivo, aprobación, vigencia y audit | Policy/time tests | DATA-003 | M |
| DATA-005 | Implementar motor de retención | Archive/delete/anonymize según regla, dry-run y evidencia | Time-travel/failure | DATA-003..004 | L |
| DATA-006 | Implementar calidad/reconciliación | Reglas, incidencias, owner y cierre sin corrección silenciosa | Domain/E2E | DATA-001 | M |
| DATA-007 | Implementar UI catálogo/retención | Administración + seguridad con scopes separados | Playwright/policy | DATA-001..006 | L |

