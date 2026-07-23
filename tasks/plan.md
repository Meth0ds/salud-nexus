# Plan de implementación: Salud Nexus

## Enfoque

La plataforma se construirá mediante cortes verticales verificables. Cada corte incluye modelo y migración, reglas de dominio, autorización, API, auditoría, UI, mockups/estados, pruebas y documentación. Las decisiones compartidas se cierran antes de paralelizar consumidores.

## Dependencias principales

```text
Gobierno + threat model + sistema visual
  ├─ Identidad + centro único + autorización + auditoría
  │   ├─ Pacientes + representantes + restricciones
  │   │   ├─ Agendas/recursos + motor de disponibilidad
  │   │   │   ├─ Citas + recepción + sala de espera + lista de espera
  │   │   │   └─ Notificaciones/outbox
  │   │   ├─ Medicación/conciliación
  │   │   ├─ Documentos/PDF/firma/verificación
  │   │   └─ Privacidad/derechos/accesos
  │   └─ Administración/seguridad/informes
  └─ Observabilidad + CI/CD + continuidad + evidencias de cumplimiento
```

## Fases y checkpoints

### Fase 0 — Descubrimiento reproducible

- Congelar requisitos, glosario, actores, datos, riesgos, decisiones y backlog.
- Auditar skills, dependencias y versiones oficiales.
- Crear threat model, clasificación de datos y matriz de permisos inicial.

**Checkpoint:** especificación, arquitectura, TODOs, threat model y fuentes revisados; ninguna ambigüedad crítica sin registrar.

### Fase 1 — Diseño y esqueleto técnico

- Crear tokens, componentes base y tablero visual.
- Crear todos los mockups en `design-lab`, incluidos estados, responsive y motion.
- Crear workspace Angular, Laravel, contratos API y stack local.

**Checkpoint:** los tres proyectos Angular y Laravel construyen; la navegación de mockups cubre el inventario completo; verificación inicial verde.

### Fase 2 — Núcleo de confianza

- Identidad, sesiones, MFA/passkeys/OIDC mediante puertos, centro único y contexto operativo.
- RBAC + ABAC + relación asistencial, auditoría hash-chain y eventos de seguridad.
- Pacientes, identificadores, representantes, restricciones y fusión controlada.

**Checkpoint:** recorridos de autenticación y autorización E2E, pruebas de aislamiento y auditoría inmutable verdes.

### Fase 3 — Citas de extremo a extremo

- Centro, unidades, profesionales, servicios, recursos, horarios y excepciones.
- Disponibilidad, hold, reserva idempotente, historial, recepción y sala de espera.
- Portales paciente/personal completos para citas y notificaciones.

**Checkpoint:** ninguna doble reserva bajo concurrencia; recorridos críticos paciente/recepción/profesional verdes.

### Fase 4 — Medicación y documentos

- Registro autoritativo, declaraciones del paciente, discrepancias, renovación y conciliación.
- Plantillas, PDF, hash, QR, publicación, descarga, versionado y firma por adaptador.

**Checkpoint:** separación clínica/paciente demostrada; documentos verificables y acceso auditado.

### Fase 5 — Privacidad, administración e integraciones

- Derechos, consentimientos, finalidad, restricciones, historial de acceso y break-glass.
- Configuración avanzada, plantillas, reglas, informes y operaciones masivas.
- Adaptadores FHIR, identidad, mensajería, firma y sistemas externos con simuladores.

**Checkpoint:** flujos de privacidad, break-glass e integración superan pruebas de contrato y abuso.

### Fase 6 — Operación y salida

- Observabilidad, SIEM, backups, restauración, contingencia, rendimiento y hardening.
- CI/CD, SBOM, firmas, escaneo, DAST, pentest, accesibilidad y evidencias.

**Checkpoint:** criterios de salida de producción documentados; bloqueos humanos/proveedor explícitos y ningún fallo técnico crítico/alto pendiente.

## Estrategia de mockups

Cada ruta tendrá un mockup navegable en `design-lab` con datos sintéticos y, al menos, variantes desktop/móvil, carga, vacío, error, sin permiso, offline, sesión caducada y conflicto cuando proceda. El tablero base está en `docs/design/salud-nexus-design-board.png`.

El movimiento se clasifica por propósito:

- **CSS:** press feedback, hover, focus, overlays y transiciones breves e interrumpibles.
- **GSAP Timeline:** onboarding y explicaciones ocasionales.
- **ScrollTrigger:** cronología de cuidados, ayuda y páginas informativas; nunca navegación clínica repetitiva.
- **Sin movimiento:** comandos de teclado, tablas densas, cambio rápido de paciente y acciones críticas repetidas.

Todas las variantes tienen un modo de movimiento reducido que conserva comprensión sin desplazamiento espacial.

## Riesgos y mitigación

| Riesgo                                | Impacto | Mitigación                                                                      |
| ------------------------------------- | ------- | ------------------------------------------------------------------------------- |
| Alcance de cientos de capacidades     | Alto    | Cortes verticales, backlog trazable y simuladores para dependencias externas    |
| Autorización contextual incorrecta    | Crítico | Motor central de políticas, tests de matriz y deny-by-default                   |
| Doble reserva                         | Crítico | Restricciones PostgreSQL, transacciones, locks, holds e idempotencia            |
| Exposición de datos en navegador/logs | Crítico | Minimización, sanitización central, no storage web y pruebas de fuga            |
| Auditoría alterable                   | Crítico | Append-only, hash chain, rol DB separado, exportación/WORM y verificador        |
| Dependencias externas sin contrato    | Alto    | Ports/adapters, circuit breaker, simuladores y pruebas de contrato              |
| Motion que distrae o causa mareo      | Alto    | Matriz de frecuencia/propósito, reduced motion y gates de rendimiento/a11y      |
| Cumplimiento confundido con código    | Crítico | Gates separados para EIPD, asesoría jurídica, contratos y pentest independiente |

## Regla de cierre

Una tarea solo cambia a completada cuando su aceptación, verificación automática, revisión de seguridad, accesibilidad y documentación están satisfechas. Implementar una pantalla sin backend/autorización/auditoría, o un endpoint sin UI/pruebas cuando forma parte de un corte, no cuenta como función terminada.

## Corte verificado — Reprogramación y cancelación de citas

Especificación viva: [`docs/specifications/patient-appointment-changes.md`](../docs/specifications/patient-appointment-changes.md).

Orden de dependencias:

```text
Mockup P23/P24
  → versión + asignación activa + historial
  → reserva adaptada a asignaciones
  → cancelación y reprogramación transaccionales
  → OpenAPI + cliente generado
  → repositorios Angular
  → UI Material + E2E + evidencia
```

Checkpoints:

1. Persistencia reversible y reserva existente verdes.
2. Mutaciones API, concurrencia, auditoría y contrato verdes.
3. UI conectada, responsive, accesible y verificación integral verde.

## Corte activo — MFA TOTP, recuperación y AAL2

Especificación viva: [`docs/specifications/mfa-aal2.md`](../docs/specifications/mfa-aal2.md).

Orden de dependencias:

```text
Mockups G03/P12 + threat model
  → dependencia TOTP oficial aislada
  → persistencia cifrada + recuperación hasheada
  → alta y confirmación
  → reto de login + replay protection
  → AAL2 + step-up
  → OpenAPI + cliente Angular
  → UI Material conectada
  → E2E de abuso + evidencia
```

Checkpoints:

1. Mockups responsive, contrato y amenazas revisados antes de implementar la ceremonia.
2. Persistencia reversible, TOTP/recovery y pruebas de replay verdes.
3. Login/step-up, auditoría minimizada y OpenAPI verdes.
4. Angular conectado, accesible, sin almacenamiento sensible y verificación integral verde.
