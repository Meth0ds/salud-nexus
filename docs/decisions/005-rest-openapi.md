# ADR-005: REST `/api/v1` y OpenAPI 3.1

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Dos portales, integraciones y pruebas necesitan un contrato único, versionable y generable. Los errores
deben ser consistentes y no filtrar detalles internos.

## Decisión

Exponer REST bajo `/api/v1`, documentado con OpenAPI 3.1. El cliente Angular se genera y cualquier
extensión manual queda fuera del código generado. Errores HTTP usan `application/problem+json`,
correlation ID y códigos de dominio estables. Las escrituras críticas admiten idempotency keys.

## Alternativas consideradas

- GraphQL: rechazado inicialmente por autorización de campos, caché y complejidad innecesaria.
- Contratos TypeScript escritos a mano: rechazados por deriva entre PHP y Angular.
- RPC ad hoc: rechazado por semántica y tooling inferiores.

## Consecuencias

CI bloquea breaking changes no versionados y deriva entre OpenAPI, implementación, cliente y pruebas de
contrato.
