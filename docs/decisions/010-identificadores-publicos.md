# ADR-010: UUIDv7 públicos e IDs internos

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

IDs secuenciales expuestos facilitan enumeración. Usar UUID como única clave en todas las relaciones
incrementa índices y no elimina la obligación de autorización por objeto.

## Decisión

Usar claves internas `bigint` para relaciones y UUIDv7 no secuenciales como identificadores públicos.
Todas las tablas multi-organización incluyen `organization_id`; consultas y policies aplican ámbito y
relación. El UUID nunca se considera secreto ni prueba de permiso.

## Alternativas consideradas

- IDs enteros públicos: rechazados por enumeración y fuga de volumen.
- UUIDv4 como clave primaria única: viable, pero con peor localidad y sin necesidad de acoplar API a PK.
- Slugs derivados de PII: rechazados.

## Consecuencias

Se imponen índices únicos por organización, rate limits y respuestas que no revelan existencia cuando
corresponda. Las pruebas intentan acceso horizontal con UUID válidos de otros sujetos.
