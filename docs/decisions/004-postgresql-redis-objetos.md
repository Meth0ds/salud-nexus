# ADR-004: PostgreSQL, Redis y almacenamiento de objetos

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Las reservas necesitan exclusión concurrente, la auditoría requiere integridad transaccional y los
documentos no deben residir en el webroot. Sesiones, colas y bloqueos necesitan semánticas distintas de
la base relacional.

## Decisión

PostgreSQL es la fuente de verdad; Redis gestiona sesiones, caché, rate limits, colas y locks; un
almacenamiento S3-compatible guarda objetos cifrados y versionados. SQLite solo se permite en pruebas
rápidas que no pretendan validar restricciones específicas de PostgreSQL.

## Alternativas consideradas

- MySQL: viable, pero PostgreSQL ofrece mejores restricciones de exclusión y tipos para este dominio.
- Archivos locales en producción: rechazados por escalado, durabilidad y control de acceso.
- Redis como fuente de verdad de reservas: rechazado; la restricción final pertenece a PostgreSQL.

## Consecuencias

La suite incluye pruebas reales de PostgreSQL/Redis. Backups, restore, RPO/RTO y cifrado forman parte del
criterio de producción.
