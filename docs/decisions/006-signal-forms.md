# ADR-006: Signal Forms por defecto

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Angular 22 declara Signal Forms estable. La plataforma es nueva, zoneless y basada en Signals, y exige
tipos estrictos y validación coherente.

## Decisión

Usar `@angular/forms/signals` en formularios nuevos, con modelos no nulos, schemas, validadores y
submission explícito. Reactive Forms solo se acepta cuando un formulario dinámico, un CVA o una
integración carezca de soporte equivalente; la excepción se documenta junto al formulario.

## Alternativas consideradas

- Reactive Forms para todo: estable, pero duplica estado Observable/Signal y añade boilerplate.
- Template-driven Forms: reservadas a ejemplos simples; insuficientes para los flujos regulados.

## Consecuencias

La validación del cliente mejora UX pero nunca sustituye validación ni autorización del servidor. Los
tests verifican touched/dirty/pending, teclado, errores y reintentos.
