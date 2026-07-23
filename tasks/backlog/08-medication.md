# 08 — Medicación y conciliación

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| MED-001 | Definir alcance clínico limitado | Informativo/conciliación, sin prescripción autónoma ni promesas terapéuticas | Revisión clínica/jurídica | GOV-005, GOV-025 | S |
| MED-002 | Modelar catálogo de medicación | Principio, comercial, presentación, códigos, versión y procedencia | Migration/domain | BE-031 | M |
| MED-003 | Crear adaptador de catálogo | Búsqueda versionada, timeout, cache no clínica y simulador | Contract tests | MED-002, INT-002 | M |
| MED-004 | Modelar MedicationStatement | Paciente, fuente, estado, fechas, prescriptor y versión | Domain tests | PAT-001, ORG-009 | M |
| MED-005 | Modelar dosis/pauta | Cantidad, frecuencia, vía, timing, instrucciones y unidades tipadas | Property tests | MED-004 | L |
| MED-006 | Modelar historial de estado | Ninguna modificación sobrescribe la evolución anterior | Transition tests | MED-004 | M |
| MED-007 | Modelar procedencia/validación | Paciente, profesional, sistema externo y nivel de validación inequívocos | Domain/API | MED-004 | M |
| MED-008 | Implementar lectura paciente | Solo medicación autorizada, campos mínimos y fuente visible | API/policy/E2E | MED-004..007, IAM-032 | M |
| MED-009 | Implementar listado/histórico paciente | Activa, suspendida e histórica con paginación de servidor | API/E2E | MED-008 | M |
| MED-010 | Implementar resumen exportable | Backend, step-up según riesgo, caduca y audita | Security/E2E | MED-008, IAM-013 | M |
| MED-011 | Modelar solicitud de renovación | Estado, mensaje administrativo, SLA y decisión profesional | Domain tests | MED-004 | M |
| MED-012 | Implementar renovación paciente | No crea prescripción; limita frecuencia y notifica estado | API/E2E/abuse | MED-011 | M |
| MED-013 | Modelar discrepancia | Tipo, declaración, evidencia, prioridad no clínica y resolución | Domain tests | MED-004 | M |
| MED-014 | Implementar discrepancia/no toma | Entrada paciente separada; no cambia statement autoritativo | API/E2E | MED-013 | M |
| MED-015 | Modelar recordatorio personal | Hora/zona/canal sin convertirlo en pauta clínica | Clock tests | MED-004 | M |
| MED-016 | Implementar recordatorios/tomas | Bienestar, opt-in, privacidad y borrado por paciente | E2E/notification tests | MED-015 | M |
| MED-017 | Implementar alta profesional | Permiso clínico, relación, FormRequest y evento versionado | Policy/API | MED-004..007, IAM-029 | L |
| MED-018 | Implementar cambio de pauta | Motivo obligatorio, ETag, nueva versión y audit | Concurrency/API | MED-005..007, BE-014 | L |
| MED-019 | Implementar suspensión/histórico | Transición, motivo, fecha y no borrado | Transition/API | MED-006 | M |
| MED-020 | Implementar procedencia externa | Mapping y provenance sin aceptar datos como validados por defecto | Contract/security tests | MED-003, MED-007 | M |
| MED-021 | Crear caso de conciliación | Compara fuentes, resuelve ítem a ítem y conserva decisiones | Domain tests | MED-007, MED-013 | L |
| MED-022 | Implementar conciliación profesional | Reauth cuando aplique, concurrencia, resultado parcial explícito | E2E/concurrency | MED-021, IAM-013 | L |
| MED-023 | Implementar bandeja de discrepancias | Scope, prioridad, filtros y SLA sin descargar universo clínico | API/performance | MED-013 | M |
| MED-024 | Implementar bandeja de renovaciones | Estados, asignación, decisión y comunicación no clínica | API/E2E | MED-011 | M |
| MED-025 | Modelar alergias/intolerancias gated | Módulo desactivado hasta liderazgo clínico; fuente/historial obligatorios | Feature flag + domain tests | MED-001 | M |
| MED-026 | Implementar documentos asociados | Vínculo autorizado sin duplicar contenido clínico | API/policy | MED-004, DOC-004 | S |
| MED-027 | Implementar UI paciente | Mockups DES-050..054 conectados con todos los estados | Playwright/axe | MED-008..016 | L |
| MED-028 | Implementar UI profesional | Mockup DES-086: lectura/alta/cambio/suspensión/conciliación | Playwright/policy | MED-017..024 | L |
| MED-029 | Implementar diferenciación visual de fuente | Texto, icono y estructura; nunca solo color | Visual/a11y tests | MED-007, DES-086 | M |
| MED-030 | Auditar accesos/cambios | Read/export/create/change/suspend/reconcile permitidos y denegados | Audit coverage | MED-008..029 | M |
| MED-031 | Probar aislamiento y enumeración | Ningún ID, filtro o conteo revela medicación ajena | Fuzz/auth tests | MED-008..030 | L |
| MED-032 | Probar historial inmutable | Cambios concurrentes/fallos no pierden versiones | Property/failure tests | MED-018..022 | L |
