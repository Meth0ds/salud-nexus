# Especificación: cambio y cancelación de citas del paciente

Fecha: 2026-07-23. Estado: aprobado para implementación por la instrucción de ejecución continua del
plan maestro.

## Objetivo

Permitir que una persona autenticada reprograme o cancele una cita futura propia sin perder la
reserva original ante conflictos, reintentos o fallos. El flujo pertenece a un único centro, aplica
política temporal en backend, conserva historial append-only y registra tanto éxitos como rechazos.

Historias principales:

- Como paciente, puedo ver si mi cita admite cambios y hasta cuándo.
- Como paciente, puedo seleccionar otro hueco del mismo tipo de cita y confirmarlo de forma atómica.
- Como paciente, puedo cancelar indicando un motivo administrativo no clínico.
- Como paciente, recibo un resultado inequívoco y puedo recuperarme de conflicto, sesión caducada o
  fallo de red sin duplicar la operación.

## Supuestos y decisiones

- Solo las citas `scheduled` pueden modificarse.
- El margen inicial es de 120 minutos y se configura en backend; la UI nunca decide la autorización.
- Reprogramar conserva tipo de cita, modalidad y centro único; cambiar servicio o modalidad será otro
  flujo con reevaluación de elegibilidad.
- Los motivos de cancelación son una allowlist (`plans_changed`, `feeling_better`, `transport_issue`,
  `other`) y no admiten texto libre ni datos clínicos.
- Toda mutación exige sesión Sanctum, CSRF, `Idempotency-Key` e `If-Match` fuerte.
- La versión de la cita aumenta en cada transición. Una versión obsoleta devuelve conflicto sin
  modificar estado ni liberar el hueco.
- Una asignación activa separada de la cita histórica representa la ocupación exclusiva del hueco.
- La aplicación emite un evento de dominio y auditoría. La entrega multicanal queda conectable al
  futuro outbox de notificaciones y no se simula como si ya se hubiera enviado.

## Diseño profesional previo

### Dirección visual

Concepto: **línea de continuidad**. La cita actual es el ancla; una línea atlántica conduce a la
propuesta y comunica que la reserva vigente continúa protegida hasta la confirmación.

- Atlántico profundo `#062b34`: títulos y ancla de la cita actual.
- Atlántico operativo `#00616c`: selección, línea de continuidad y acción principal.
- Agua clara `#d6edef`: superficie de propuesta y foco contextual.
- Lienzo clínico `#f4f9f9`: fondo calmado y de baja fatiga.
- Blanco `#ffffff`: superficies de lectura.
- Coral controlado `#9e293c`: solo cancelación y consecuencias; nunca como decoración.
- Manrope Variable: encabezados, fechas y datos de agenda.
- Source Sans 3 Variable: cuerpo, ayudas, formularios y errores.

La firma visual es una tarjeta doble conectada, inspirada en el traspaso seguro de una reserva:

```text
┌─ Tu cita sigue reservada ─────┐        ┌─ Nuevo hueco propuesto ─────┐
│ jue 30 jul · 09:00            │ ─────▶ │ vie 31 jul · 11:30          │
│ Centro Atlántico · Consulta 2 │        │ mismo servicio y centro     │
└───────────────────────────────┘        └─────────────────────────────┘
          Política y límite                   Confirmar cambio
```

En cancelación, la segunda tarjeta se convierte en un panel de consecuencias con alternativa visible
“Cambiar la cita”. El color coral queda confinado al botón final y al borde del panel.

### Jerarquía, estados y movimiento

- Encabezado: servicio, estado, fecha y límite de cambio; sin identificadores internos.
- Contenido: cita actual, propuesta o motivo, política y acción final.
- Escritorio: dos columnas conectadas. Móvil: secuencia vertical con la misma lectura semántica.
- Estados obligatorios: carga, cita ausente, restringido, fuera de plazo, sin huecos, error, offline,
  conflicto concurrente, enviando y resultado correcto.
- La selección usa `transform` y `opacity` durante 220 ms; el resultado entra en 360 ms. Las acciones
  críticas nunca dependen del scroll; solo la nota explicativa de privacidad admite una revelación
  progresiva no esencial. `prefers-reduced-motion` reduce todas las transiciones a 1 ms.
- El foco pasa al resumen de error o al resultado; la región viva anuncia una sola frase sin datos
  clínicos.

### Material/CDK

- `mat-button`, `mat-form-field`, `mat-select` y radio nativo enlazado con Signal Forms.
- HTML semántico para resumen, listas y avisos; no se crea un stepper o diálogo innecesario.
- Harnesses oficiales para botones, select y campos. Ninguna regla de autorización depende de estados
  deshabilitados del frontend.

## Contrato API

### Reprogramar

`POST /api/v1/patient/appointments/{appointment}/reschedules`

Cabeceras: `Idempotency-Key`, `If-Match`, XSRF y cookie de sesión. Cuerpo:

```json
{ "slot_id": "uuid-v7" }
```

Devuelve `200` con `AppointmentEnvelope`, `ETag` nuevo e `Idempotency-Replayed`. El servidor bloquea
cita, asignación y nuevo hueco; actualiza la asignación y la cita en una sola transacción.

### Cancelar

`POST /api/v1/patient/appointments/{appointment}/cancellations`

Cabeceras: `Idempotency-Key`, `If-Match`, XSRF y cookie de sesión. Cuerpo:

```json
{ "reason_code": "plans_changed" }
```

Devuelve `200` con la cita `cancelled`, `ETag` nuevo e `Idempotency-Replayed`. La asignación activa se
elimina dentro de la misma transacción y el hueco vuelve a estar disponible.

### Errores

- `404`: cita o hueco ajeno/inexistente, sin enumeración.
- `409`: versión obsoleta, cita no modificable, fuera de plazo o hueco ocupado.
- `419`: prueba CSRF ausente o inválida.
- `422`: cabecera o cuerpo inválido/campos desconocidos.
- `429`: límite de mutaciones.
- Todos usan Problem Details RFC 9457 y un request ID opaco.

## Modelo y estructura del proyecto

```text
backend/database/migrations/         Asignaciones activas, versión e historial
backend/app/Modules/Scheduling/      Casos de uso, DTOs, eventos y persistencia
backend/app/Modules/Patients/Http/   Requests, controlador, resources y rutas
backend/openapi/                     Contrato OpenAPI 3.1.1
frontend/projects/api-client/        Cliente Angular/Zod generado
frontend/projects/patient-portal/    Repositorios, modelos y pantalla de gestión
frontend/projects/design-lab/        Mockups P23/P24 especializados
backend/tests/                       Migración, API, concurrencia y seguridad
frontend/e2e/                        Recorrido paciente y accesibilidad
```

## Estilo de código

Laravel mantiene controladores finos, acciones de aplicación tipadas y comentarios en inglés que
explican intención o invariantes, no sintaxis obvia:

```php
/**
 * Move the active slot allocation without releasing the original reservation on failure.
 */
final readonly class ReschedulePatientAppointment
{
    public function handle(...): AppointmentChangeResult
    {
        return DB::transaction(...);
    }
}
```

Angular usa componentes standalone, Signals/`computed`, Signal Forms, imports específicos y modelos
readonly. No se usa `effect` para propagar estado derivable ni se persisten citas en Web Storage.

## Comandos

```powershell
Set-Location backend
php artisan test --filter=PatientAppointmentChange
composer verify

Set-Location ..\frontend
npm.cmd run api:generate
npm.cmd run api:check
npx.cmd ng test patient-portal --watch=false
npx.cmd ng build patient-portal
npm.cmd run test:e2e:patient
npm.cmd run verify
```

## Estrategia de pruebas

- Migración: `down/up`, backfill de asignaciones y restricciones únicas.
- Aplicación/API: propiedad horizontal, estado, plazo, ETag, idempotencia, campos desconocidos, CSRF,
  auditoría y evento.
- Concurrencia: un único hueco ganador; conflicto o fallo conserva la reserva original.
- Angular: repositorio HTTP, fake en memoria y componente con Material Harnesses.
- E2E: reprogramación, cancelación, conflicto recuperable, móvil, teclado y axe WCAG 2.2 A/AA.

## Límites

- Siempre: filtrar por organización y paciente, bloquear filas, usar UUIDv7 públicos, UTC y zona IANA,
  auditar resultado, minimizar respuestas y conservar una ruta de recuperación.
- Requiere una decisión posterior: cambio de servicio/modalidad, cancelación por el centro,
  representantes con política diferenciada y entrega multicanal real.
- Nunca: borrar la cita, aceptar motivo clínico libre, liberar primero el hueco original, usar solo la
  UI como autorización, incluir PHI en URL/log/evento o fingir que una notificación externa se envió.

## Criterios de éxito

- El hueco original permanece reservado si la reprogramación falla o pierde una carrera.
- Una cancelación válida libera exactamente una asignación y deja historial inmutable.
- Reintentar la misma mutación devuelve el mismo resultado; cambiar el payload con la misma clave
  produce conflicto.
- Una versión obsoleta, una cita ajena o una operación fuera de plazo no cambia ninguna fila clínica.
- OpenAPI, cliente generado, backend, demo Angular y UI conectada comparten el mismo contrato.
- Todos los gates backend/frontend y los recorridos E2E quedan verdes.

## Preguntas abiertas no bloqueantes

- El margen de 120 minutos debe validarse con responsables operativos antes de producción.
- La taxonomía final de motivos y el contenido de notificaciones requieren revisión organizativa y de
  privacidad.
