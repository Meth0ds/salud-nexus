# 10 — Privacidad, auditoría y cumplimiento

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| PRIV-001 | Modelar bases jurídicas/finalidades | Versionadas, aplicabilidad y propietario; consentimiento no es base universal | Domain/legal review | GOV-008, IAM-030 | M |
| PRIV-002 | Modelar consentimiento | Tipo, texto/versión/idioma, otorgamiento, retirada y evidencia | Domain/migration | PRIV-001 | M |
| PRIV-003 | Implementar consentimiento | Solo donde procede; retirada no borra tratamientos con otra base | E2E/legal cases | PRIV-002 | M |
| PRIV-004 | Modelar avisos de privacidad | Versión, vigencia, audiencia, idioma y aceptación | Domain tests | PRIV-001 | M |
| PRIV-005 | Implementar publicación/aceptación de aviso | Historial verificable y no dark patterns | E2E/a11y | PRIV-004 | M |
| PRIV-006 | Modelar solicitud de derechos | Acceso, rectificación, limitación, portabilidad, identidad, SLA y estados | Domain tests | PAT-001 | M |
| PRIV-007 | Implementar verificación del solicitante | Proporcional, no recoge evidencia excesiva y admite representación | Security/E2E | PRIV-006, PAT-016 | M |
| PRIV-008 | Implementar workflow DSR | Asignación, tareas, pause legal, decisión, evidencia y comunicación | Clock/E2E | PRIV-006..007 | L |
| PRIV-009 | Implementar paquete de acceso | Snapshot autorizado, minimizado, cifrado, caduca y audita | Security/E2E | PRIV-008, BE-018 | L |
| PRIV-010 | Implementar portabilidad | Formato acordado/FHIR donde aplique, provenance y entrega segura | Contract/E2E | PRIV-008 | L |
| PRIV-011 | Implementar limitación/restricción | Policy efectiva inmediata y excepciones justificadas | Matrix tests | PAT-023, IAM-031 | L |
| PRIV-012 | Implementar contacto DPD | Canal seguro, categorías, SLA y sin mensajería clínica libre | E2E | PRIV-008 | M |
| AUD-001 | Definir esquema canónico de auditoría | Actor, org/centro, session hash, action, resource, patient, purpose, result, reason, break-glass y correlation | Schema tests | GOV-027, BE-005 | M |
| AUD-002 | Crear writer transversal obligatorio | API explícita, redacción y obligaciones desde decisión de auth | Architecture/unit tests | AUD-001, IAM-028 | M |
| AUD-003 | Crear almacenamiento append-only | Runtime INSERT, sin UPDATE/DELETE; rol/migración separados | PostgreSQL permission tests | TOOL-010, AUD-002 | L |
| AUD-004 | Encadenar hashes | previous/event hash canónico, segmentos org+periodo y versionado algorítmico | Property/tamper tests | AUD-003 | L |
| AUD-005 | Crear verificador de integridad | Detecta cambio, borrado, reordenación, duplicado y hueco | Mutation suite | AUD-004 | M |
| AUD-006 | Anclar/exportar a WORM | Lote firmado, manifiesto, custodia y restauración mediante puerto | Contract/restore test | AUD-005 | L |
| AUD-007 | Garantizar completitud transaccional | Intento/resultado según riesgo; negocio y audit/outbox no divergen | Crash/rollback tests | AUD-002, BE-015 | L |
| AUD-008 | Sincronizar tiempo y detectar deriva | UTC, fuente confiable y alerta por desviación | Time drift test | BE-007 | M |
| AUD-009 | Auditar lectura sensible | Pacientes, medicación, docs, privacidad, búsquedas y exportaciones | Route/use-case coverage | AUD-002 | L |
| AUD-010 | Auditar denegaciones/fallos | Sin filtrar existencia o payload; correlación preservada | Negative tests | AUD-002, BE-009 | M |
| AUD-011 | Implementar explorador de auditoría | Cursor/filtros allowlist, permisos, query auditada y sin mutación | API/E2E/performance | AUD-003, DES-115 | L |
| AUD-012 | Implementar historial visible paciente | Eventos legalmente permitidos, comprensibles y exportables | E2E/legal review | AUD-009, DES-070 | L |
| AUD-013 | Crear detecciones de abuso | Insider, descarga, break-glass, permisos, MFA y denegaciones anómalas | Simulation tests | AUD-009..011 | L |
| BG-001 | Modelar break-glass | Solicitud, motivo, justificación, scope, paciente, AAL, inicio/fin y revisión | Domain tests | IAM-012, IAM-028 | M |
| BG-002 | Implementar acceso excepcional | Solo roles clínicos autorizados; reauth; TTL; todo acceso marcado | Policy/E2E | BG-001, IAM-013 | L |
| BG-003 | Implementar alerta inmediata | Seguridad/supervisión recibe evento mínimo fiable | Integration tests | BG-002, BE-017 | M |
| BG-004 | Implementar revisión posterior | SLA, supervisor independiente, decisión e incidente si procede | Clock/E2E | BG-003 | M |
| BG-005 | Mostrar transparencia al paciente | Solo cuando legalmente corresponda, con redacción aprobada | Legal/E2E | BG-004, AUD-012 | M |
| COMP-001 | Importar ASVS 5.0.0 oficial | 345 requisitos L3 con ID/hash/version | Script/count/hash | GOV-010 | M |
| COMP-002 | Resolver aplicabilidad ASVS L3 | Cada requisito tiene Sí/N/A, razón, owner, control y evidencia | Revisión AppSec independiente | COMP-001 | L |
| COMP-003 | Crear ROPA | Todos los tratamientos, proveedores y transferencias mapeados | Reconciliar con módulos/DFD | GOV-006 | L |
| COMP-004 | Ejecutar EIPD | Riesgos/medidas/residual aprobados antes de datos reales | Firma responsable+DPD | GOV-009, COMP-003 | L |
| COMP-005 | Crear declaración ENS si aplica | Categoría, medidas y auditoría según alcance | Aprobación formal | GOV-008 | L |
| COMP-006 | Crear gap analysis EHDS | Acceso, representación, restricciones, rectificación, transmisión y logs | Matriz artículo-capacidad-prueba | PRIV-006..012, AUD-012 | L |
| COMP-007 | Crear registro de proveedores/DPA | Rol, datos, región, subencargados, SLA y salida | Revisión anual simulada | GOV-028 | M |
| COMP-008 | Crear procedimiento de brecha 72 h | Detectar, evaluar, contener, decidir/notificar y documentar | Tabletop exercise | AUD-013 | M |
| COMP-009 | Crear expediente de evidencia | Código/config/test/runtime/aprobación/fecha por control | Muestreo automatizado | COMP-002..008 | L |
| COMP-010 | Bloquear datos reales | Gate verifica EIPD, MFA, audit y restore antes de habilitar import productivo | Test de gate | COMP-004, IAM-011, AUD-006 | M |
