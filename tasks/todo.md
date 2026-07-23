# TODO maestro — Salud Nexus

Estado inicial: todos los elementos están pendientes salvo los marcados expresamente. El detalle y los criterios de aceptación viven en los ficheros enlazados; este documento es el índice único de alcance.

## Convenciones

- `[ ]` pendiente, `[x]` verificado, `[~]` iniciado y `[!]` bloqueado por una decisión/tercero.
- Ningún ítem se completa solo porque exista código. Debe superar aceptación, verificación, seguridad, accesibilidad y documentación.
- Los bloqueos externos se mantienen visibles y no impiden implementar adaptadores, simuladores y pruebas locales.
- Los identificadores no se renumeran: se añaden nuevos IDs para conservar trazabilidad.

## Backlogs de ejecución

- [00 — Gobierno, investigación y arquitectura](backlog/00-governance-architecture.md)
- [01 — Entorno, monorepo y cadena de suministro](backlog/01-tooling-supply-chain.md)
- [02 — Sistema visual, motion y mockups](backlog/02-design-mockups.md)
- [03 — Frontend Angular compartido](backlog/03-angular-foundation.md)
- [04 — Backend Laravel y contratos API](backlog/04-laravel-api-foundation.md)
- [05 — Identidad, sesión y autorización](backlog/05-identity-authorization.md)
- [06 — Centro, profesionales y pacientes](backlog/06-organizations-patients.md)
- [07 — Disponibilidad, citas y sala de espera](backlog/07-scheduling-appointments.md)
- [08 — Medicación y conciliación](backlog/08-medication.md)
- [09 — Documentos, PDF, firma y verificación](backlog/09-documents.md)
- [10 — Privacidad, auditoría y cumplimiento](backlog/10-privacy-audit-compliance.md)
- [11 — Notificaciones e integraciones](backlog/11-notifications-integrations.md)
- [12 — Administración, informes y gobierno del dato](backlog/12-administration-reporting.md)
- [13 — Observabilidad, infraestructura y continuidad](backlog/13-operations-continuity.md)
- [14 — QA, seguridad ofensiva, accesibilidad y rendimiento](backlog/14-quality-security.md)
- [15 — Preparación y salida a producción](backlog/15-production-readiness.md)

## Checkpoints globales

- [ ] **CP-01 Diseño:** inventario de pantallas completo; todos los mockups y estados navegables; revisión visual, responsive, motion y WCAG aprobada.
- [ ] **CP-02 Fundamentos:** builds reproducibles; contratos API; identidad, autorización, pacientes y auditoría verdes.
- [ ] **CP-03 Citas:** reserva/reprogramación/cancelación/check-in completos y sin doble reserva bajo concurrencia.
- [ ] **CP-04 Clínica limitada:** medicación, conciliación y documentos completos con separación de fuentes y trazabilidad.
- [ ] **CP-05 Gobierno:** privacidad, break-glass, administración, integraciones e informes completos.
- [ ] **CP-06 Técnico:** todos los gates automáticos, restauración, carga, accesibilidad y seguridad sin fallos bloqueantes.
- [ ] **CP-07 Organizativo:** EIPD, contratos, retención, pentest, formación, soporte y aprobación de salida completados por responsables autorizados.

## Definition of Done

Para cada TODO funcional:

- [ ] Caso feliz, vacío, carga, error, denegado, sesión caducada y concurrencia/offline cuando aplique.
- [ ] Validación y autorización deny-by-default en backend; frontend solo refleja capacidades.
- [ ] Evento de auditoría con datos mínimos y sin secretos/PHI en logs.
- [ ] OpenAPI, cliente TypeScript, migración, fixtures sintéticos y documentación actualizados cuando aplique.
- [ ] Unitarias, integración, API, Angular harness y E2E apropiadas en verde.
- [ ] Teclado, foco, lector de pantalla, zoom/reflow, contraste y reduced motion verificados.
- [ ] Sin `TODO`, `FIXME`, `console.log`, debug, secretos ni dependencias no revisadas en el cambio.
- [ ] Builds de producción y auditorías de dependencias reproducibles desde lockfiles.

## Corte verificado — Reprogramación y cancelación de citas

- [x] CHG-001 — Especializar los mockups P23/P24 con la línea de continuidad.
  - Aceptación: reprogramación y cancelación tienen jerarquía, estados, responsive y motion definido.
  - Verificación: tests de design-lab, build y captura escritorio/móvil.
- [x] CHG-002 — Crear versión, asignación activa e historial append-only.
  - Aceptación: una sola asignación ocupa un hueco y las citas históricas no bloquean su reutilización.
  - Verificación: migración down/up, backfill y pruebas de constraints.
- [x] CHG-003 — Adaptar la reserva actual al modelo de asignación.
  - Aceptación: reservar crea una asignación y disponibilidad excluye solo asignaciones activas.
  - Verificación: suite de reserva y concurrencia existente.
- [x] CHG-004 — Implementar cancelación paciente transaccional.
  - Aceptación: policy temporal, ETag, idempotencia, liberación, historial, evento y auditoría.
  - Verificación: matriz API positiva/negativa, CSRF y replay.
- [x] CHG-005 — Implementar reprogramación paciente transaccional.
  - Aceptación: cambia a un hueco compatible y el original sobrevive a conflicto/fallo.
  - Verificación: pruebas de rollback, versión obsoleta y carrera por hueco.
- [x] CHG-006 — Extender OpenAPI y regenerar el cliente Angular.
  - Aceptación: rutas, esquemas, headers y errores sin deriva ni `any`.
  - Verificación: `composer openapi` y `npm run api:check`.
- [x] CHG-007 — Extender contratos y repositorios Angular.
  - Aceptación: implementación HTTP y demo son idempotentes y rechazan respuestas inválidas.
  - Verificación: tests de `api-client` y repositorios.
- [x] CHG-008 — Implementar la pantalla Angular Material.
  - Aceptación: Signal Forms, estados completos, foco, teclado, móvil y reduced motion.
  - Verificación: Material Harnesses, lint y build patient-portal.
- [x] CHG-009 — Integrar recorridos E2E y evidencia.
  - Aceptación: cambio/cancelación, conflicto, WCAG y contrato verificados; TODOs trazados.
  - Verificación: `composer verify` y `npm run verify`.

## Corte activo — MFA TOTP, recuperación y AAL2

- [x] MFA-001 — Cerrar especificación, amenazas y fuentes oficiales.
  - Aceptación: contrato, límites de TOTP, replay, recuperación, BFF y centro único quedan explícitos.
  - Verificación: revisión de [`docs/specifications/mfa-aal2.md`](../docs/specifications/mfa-aal2.md).
  - Dependencias: IAM-001, IAM-006, ADR-003.
- [x] MFA-002 — Especializar el componente visual de G03/P12.
  - Aceptación: desafío, método alternativo, alta y guardado de recuperación tienen jerarquía profesional, datos sintéticos y motion reducido.
  - Verificación: unitarias de `design-lab`, build y revisión a 320/1280 px.
  - Dependencias: MFA-001, DES-020, DES-023.
- [x] MFA-003 — Integrar mockups, pruebas accesibles y capturas estables.
  - Aceptación: G03/P12 son navegables, teclado/foco correctos y no muestran semillas/códigos reales.
  - Verificación: Playwright, axe, screenshots desktop/móvil y reduced motion.
  - Dependencias: MFA-002.
- [x] MFA-004 — Incorporar el proveedor TOTP oficial de forma aislada.
  - Aceptación: versión compatible bloqueada, sin rutas Fortify automáticas y con auditoría de dependencias limpia.
  - Verificación: `composer validate --strict`, `composer audit --locked` y prueba de providers/rutas.
  - Dependencias: MFA-001.
- [x] MFA-005 — Crear persistencia reversible de métodos, recuperación y eventos.
  - Aceptación: semilla cifrada, códigos sin plaintext, estados/índices/constraints y rollback seguro.
  - Verificación: migration tests, casts de seguridad y `migrate:fresh`/rollback.
  - Dependencias: MFA-004, IAM-001.
- [x] MFA-006 — Implementar primitivas TOTP y códigos de recuperación con TDD.
  - Aceptación: ventana ±1, paso de 30 s, consumo atómico, entropía suficiente y hash Argon2id.
  - Verificación: vector RFC 6238, reloj inyectable, replay y bloqueo transaccional; la carrera real sobre PostgreSQL se repite en MFA-016.
  - Dependencias: MFA-005.
- [x] MFA-007 — Implementar alta, QR de un uso y confirmación.
  - Aceptación: sesión reciente, ownership, expiración, `no-store`, activación y códigos entregados una vez.
  - Verificación: matriz API/CSRF, segundo acceso QR, secreto ausente de logs/problemas.
  - Dependencias: MFA-006.
- [x] MFA-008 — Separar contraseña válida de sesión autenticada cuando exista MFA.
  - Aceptación: cuenta con MFA recibe un reto ligado a sesión y permanece guest; cuenta sin MFA conserva AAL1.
  - Verificación: fixation, enumeración, cuenta suspendida y mezcla de sesión.
  - Dependencias: MFA-006.
- [x] MFA-009 — Implementar verificación TOTP/recovery y sesión AAL2.
  - Aceptación: reto válido rota sesión; replay, expiración, intento agotado y código usado son denegados.
  - Verificación: tests API positivos/negativos, carrera y evento de seguridad minimizado.
  - Dependencias: MFA-008.
- [x] MFA-010 — Implementar step-up y middleware de nivel/frescura.
  - Aceptación: finalidad allowlist, elevación AAL2 temporal y deny-by-default.
  - Verificación: unitarias de middleware y matriz API por nivel/caducidad.
  - Dependencias: MFA-009, IAM-012.
- [ ] MFA-011 — Extender OpenAPI y regenerar el cliente TypeScript.
  - Aceptación: 202/201/204, esquemas, errores y headers sin deriva ni `any`.
  - Verificación: `composer openapi`, `npm run api:generate` y `npm run api:check`.
  - Dependencias: MFA-007..010.
- [ ] MFA-012 — Extender la librería Angular de sesión.
  - Aceptación: estados anónimo/reto/autenticado, schemas runtime estrictos y ningún secreto persistido.
  - Verificación: unitarias de `auth`, contrato corrupto y expiración.
  - Dependencias: MFA-011.
- [ ] MFA-013 — Adaptar repositorios conectado/demo al reto MFA.
  - Aceptación: ambos runtimes reproducen login, TOTP, recuperación y limpieza de memoria.
  - Verificación: tests de repositorio y ausencia en Web Storage/serialización.
  - Dependencias: MFA-012.
- [ ] MFA-014 — Implementar G03 real con Angular Material y Signal Forms.
  - Aceptación: código TOTP/recuperación, pegado, errores uniformes, foco, teclado, móvil y reduced motion.
  - Verificación: Material Harnesses, unitarias, lint y build.
  - Dependencias: MFA-003, MFA-013.
- [ ] MFA-015 — Implementar P12 real para alta y custodia de recuperación.
  - Aceptación: QR, confirmación, descarga desde memoria y descarte seguro al salir.
  - Verificación: unitarias, Harnesses, CSP/no-store y recarga sin secretos.
  - Dependencias: MFA-007, MFA-013.
- [ ] MFA-016 — Cerrar E2E, abuso, documentación y evidencia.
  - Aceptación: alta→logout→login AAL2, recovery, replay, bloqueo, WCAG y trazabilidad demostrados.
  - Verificación: carrera concurrente sobre PostgreSQL, `composer verify`, `npm run verify`, auditorías y evidencia reproducible.
  - Dependencias: MFA-014, MFA-015.

## Alcance que requiere un proyecto separado

No son TODOs ocultos de esta entrega: diagnóstico o recomendación autónoma, prescripción electrónica integral, historia clínica completa, imagen diagnóstica, IA clínica, chat clínico permanente, videconsulta compleja, aplicaciones nativas, analítica predictiva, investigación secundaria y despliegue multinacional. Cualquier incorporación exige nueva especificación, evaluación clínica, jurídica y de riesgos.
