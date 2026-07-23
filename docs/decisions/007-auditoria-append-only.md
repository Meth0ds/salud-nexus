# ADR-007: Auditoría append-only verificable

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Accesos, denegaciones, exportaciones y break-glass requieren trazabilidad resistente a manipulación sin
convertir los logs en una copia innecesaria de datos sanitarios.

## Decisión

Registrar eventos mínimos en un almacén append-only con actor, sujeto pseudonimizado cuando proceda,
finalidad, organización, resultado, timestamp, correlation ID y hash encadenado. Una cuenta separable
solo añade; los lotes se sellan y exportan a almacenamiento WORM verificable.

## Alternativas consideradas

- Logs de aplicación mutables: rechazados.
- Blockchain: rechazada por complejidad, privacidad y falta de beneficio sobre firmas y WORM.
- Guardar payloads completos: rechazado por minimización y mayor impacto de una brecha.

## Consecuencias

Se prueban integridad, secuencia, sellado, retención, acceso del auditor y detección de huecos. Los datos
de auditoría no se modifican para atender rectificaciones: se añade un evento vinculado.
