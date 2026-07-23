# 09 — Documentos, PDF, firma y verificación

Estado del último corte verificable: [evidencia del vertical de documentos del
paciente](../evidence/vertical-documentos-paciente.md). El corte deja DOC-004, DOC-005, DOC-016,
DOC-020, DOC-026, DOC-029, DOC-030, DOC-032 y DOC-033 en `[~]`, y DOC-018 en `[x]`. El contrato
OpenAPI ya cubre la descarga autorizada; las capacidades documentales avanzadas indicadas en la
evidencia continúan pendientes.

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| DOC-001 | Modelar plantilla documental | Tipo, versión, idioma, centro, variables allowlist y estado | Domain/migration | BE-031, ORG-001 | M |
| DOC-002 | Implementar editor seguro de plantilla | Sin ejecución/HTML arbitrario; preview solo sintético | Injection/security tests | DOC-001 | L |
| DOC-003 | Implementar aprobación/publicación de plantilla | Revisor distinto, vigencia, programación y rollback | E2E/clock | DOC-001 | M |
| DOC-004 | Modelar Document | Paciente, emisor, tipo, estado, retención y public ID | Domain tests | PAT-001, ORG-001 | M |
| DOC-005 | Modelar versiones inmutables | Nueva versión, relación de sustitución, hash y causa | Property tests | DOC-004 | M |
| DOC-006 | Modelar anexos | Orden, hash, MIME, origen y autorización | Domain/security | DOC-004 | M |
| DOC-007 | Crear pipeline PDF backend | Datos autorizados→plantilla→render→normalizar→hash→store→audit | Golden/failure tests | DOC-001, DOC-004, BE-027 | L |
| DOC-008 | Aislar renderizador | No root, límites, timeout, no red y filesystem temporal | Sandbox/security tests | DOC-007 | L |
| DOC-009 | Crear justificante de cita/asistencia | Datos mínimos, número, centro, fecha, CSV/QR y privacidad | Golden PDF/clinical review | DOC-007, APT-001 | M |
| DOC-010 | Crear resumen de medicación | Solo datos autorizados y advertencia de fecha/fuente | Golden PDF/policy | DOC-007, MED-008 | M |
| DOC-011 | Crear formularios/consentimientos/informes/facturas | Tipos versionados y gated por alcance | Golden/contract tests | DOC-007 | L |
| DOC-012 | Modelar firma/sello | Proveedor, nivel, certificado, timestamp, resultado y revocación | Domain tests | DOC-004 | M |
| DOC-013 | Crear puerto de firma | Submit/status/validate/revoke con idempotencia y simulador | Contract tests | DOC-012 | M |
| DOC-014 | Implementar solicitud/firma | Step-up, permiso, hash exacto y fallo no ambiguo | E2E/security | DOC-013, IAM-013 | L |
| DOC-015 | Validar firma independientemente | Cadena, sello de tiempo y revocación verificables | Fixture signed/tampered | DOC-014 | M |
| DOC-016 | Modelar publicación | Audiencia, fecha, notificación, retirada y versión | Domain tests | DOC-004 | M |
| DOC-017 | Implementar publicar/retirar | Policy, step-up configurable, no borra objeto y notifica | API/E2E | DOC-016 | M |
| DOC-018 | Implementar descarga autorizada | Reautoriza, no-store, URL breve, disposition/nosniff y audit | Browser/security | BE-027, DOC-016 | M |
| DOC-019 | Implementar descarga agrupada | Job snapshot, alcance, cifrado, TTL y límite | Load/security/E2E | DOC-018, BE-018 | L |
| DOC-020 | Modelar historial de descarga/entrega | Actor, canal, versión, resultado y correlación sin contenido | Audit tests | DOC-018 | M |
| DOC-021 | Crear token de verificación pública | Aleatorio hash, scope autenticidad, estado y rate limit | Security tests | DOC-004, BE-019 | M |
| DOC-022 | Implementar página/API de verificación | Válido/revocado/sustituido, info mínima y anti-enumeración | E2E/fuzz | DOC-021 | M |
| DOC-023 | Implementar QR seguro | Solo URL/token opaco, sin DNI/PHI y nivel de corrección probado | QR decode/security | DOC-021 | S |
| DOC-024 | Modelar solicitud/corrección documental | Tipo, motivo, SLA, decisión y nueva versión si procede | Domain/API | DOC-004 | M |
| DOC-025 | Implementar publicación profesional | Crear/revisar/firmar/publicar/retirar/versionar según capacidades | API/policy matrix | DOC-001..024 | L |
| DOC-026 | Implementar UI paciente | Mockups DES-060..064, preview y descargas | Playwright/axe | DOC-016..024 | L |
| DOC-027 | Implementar UI profesional | Mockup DES-087, edición/revisión/firma/publicación | Playwright/policy | DOC-025 | L |
| DOC-028 | Crear pipeline de upload | Cuarentena, magic bytes, MIME/ext, cuotas, AV, CDR/metadatos | Malware/polyglot/zip bomb tests | BE-027 | L |
| DOC-029 | Almacenar siempre fuera del webroot | Objetos privados, nombres opacos, versioning y public access block | Direct access tests | DOC-028 | M |
| DOC-030 | Servir archivos activos con aislamiento | PDF/imagen segura; HTML/SVG no confiable nunca ejecuta en origin app | Browser security tests | DOC-028..029 | M |
| DOC-031 | Implementar retención/legal hold | Regla por tipo, excepción, evidencia y borrado controlado | Time-travel tests | DOC-004, GOV-007 | L |
| DOC-032 | Auditar ciclo completo | Render/firma/publicación/lectura/descarga/revocación/fallos | Audit coverage | DOC-007..031 | M |
| DOC-033 | Probar manipulación/falsificación | Un byte, token, QR, firma o estado alterado se detecta | Security suite | DOC-005, DOC-015, DOC-022 | M |
| DOC-034 | Medir generación masiva | Trabajo asíncrono no bloquea API y respeta cuotas | Load/queue report | DOC-007, DOC-019 | M |
