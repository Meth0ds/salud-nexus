# Evidencia — Reprogramación y cancelación de citas

Fecha de verificación: 2026-07-23.

## Resultado

El corte CHG-001..CHG-009 está implementado para un único centro. El paciente puede reprogramar una
cita a un hueco compatible o cancelarla con un motivo administrativo, conservando aislamiento por
organización y paciente, concurrencia optimista, idempotencia, historial append-only y auditoría.

## Superficies verificadas

- Persistencia Laravel: versión de cita, asignación activa exclusiva e historial inmutable.
- Casos de uso: reserva, cancelación y reprogramación transaccionales con replay exacto.
- HTTP: Sanctum, CSRF, `If-Match`, `Idempotency-Key`, rate limit y Problem Details RFC 9457.
- Contrato: OpenAPI 3.1.1 y cliente Angular/Zod reproducible.
- Angular: repositorios HTTP/demo, Signal Forms, Angular Material y estados completos.
- Accesibilidad: foco, región viva, teclado, reflow móvil, contraste y reduced motion.
- E2E: reprogramación y cancelación en escritorio y móvil con axe.

## Evidencia automática

### Backend

- `composer verify`: correcto.
- Laravel Pint: correcto.
- PHPStan: 218 archivos, sin errores.
- PHPUnit: 109 tests y 1.193 aserciones, todos correctos.
- OpenAPI: contrato válido.
- Composer audit: sin avisos de seguridad.
- Composer validate: válido en modo estricto.
- Auditoría PHPDoc: todos los bloques del código de aplicación tienen descripción en inglés terminada
  en punto.

### Frontend

- `npm run verify`: correcto.
- Unitarias: 119 tests correctos en ocho proyectos.
- Playwright: 34 recorridos correctos en escritorio y móvil.
- Builds de producción, lint, formato y reproducibilidad del cliente: correctos.
- npm audit: cero vulnerabilidades.
- Firmas del registro: 661 verificadas; attestations: 209 verificadas.

## Evidencia visual

- `output/playwright/captures/patient-gestionar-cambio-desktop.png`.
- `output/playwright/captures/patient-gestionar-cambio-mobile.png`.
- `output/playwright/captures/patient-gestionar-cambio-confirmed-desktop.png`.
- `output/playwright/captures/patient-gestionar-cambio-cancelled-desktop.png`.

## Invariantes demostradas

- Una cita ajena o un hueco incompatible obtiene una respuesta neutral y no se modifica.
- Una versión obsoleta no libera la reserva original.
- Perder una carrera por el hueco destino revierte el cambio completo.
- Una cancelación válida elimina una sola asignación activa y conserva la cita histórica.
- Repetir una clave devuelve el resultado original; cambiar su intención produce conflicto.
- Una clave antigua reconstruye su versión original aunque la cita haya cambiado después.
- Los eventos, auditorías y logs no incorporan contenido clínico libre ni secretos.
