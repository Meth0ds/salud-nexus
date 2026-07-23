# 04 — Backend Laravel y contratos API

Estado verificable del corte de contrato: `BE-033`, `BE-034` y `BE-039` están `[x]`; la
[evidencia](../evidence/contrato-openapi-cliente-angular.md) incluye deriva ruta/especificación,
generación reproducible, compilación, pruebas y el resultado de `composer verify`.

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| BE-001 | Configurar Laravel 13/PHP 8.5 | App mínima, config cacheable y extensiones documentadas | `php artisan about`; tests | TOOL-008 | S |
| BE-002 | Activar strictness de modelos | Lazy loading, atributos descartados y acceso ausente fallan en no-prod | Tests canario | BE-001 | S |
| BE-003 | Crear estructura modular | Dominios con Domain/Application/Infrastructure/Http/Policies/Tests | Test de arquitectura | GOV-012, BE-001 | M |
| BE-004 | Aplicar reglas de dependencia | Domain no depende de framework; módulos solo por contratos/eventos permitidos | Architecture tests | BE-003 | M |
| BE-005 | Crear contexto de actor | Usuario, identidad, sesión, org, centro, rol, propósito y nivel auth inmutables | Unit/feature tests | BE-003 | M |
| BE-006 | Crear IDs opacos | UUIDv7/ULID consistentes en modelos, rutas y eventos | Property tests | GOV-016, BE-003 | M |
| BE-007 | Crear manejo temporal | UTC, zonas IANA, DST y clocks inyectables | Property/DST tests | GOV-017, BE-003 | M |
| BE-008 | Crear API `/api/v1` | Versionado, media types y deprecation headers definidos | Contract tests | TOOL-009 | S |
| BE-009 | Implementar Problem Details | Códigos estables, request/correlation ID y mensajes sin internals | Negative API tests | GOV-018, BE-008 | M |
| BE-010 | Implementar validación estricta | Form Requests, allowlists, límites y rechazo de campos desconocidos sensibles | Fuzz/API tests | BE-008 | M |
| BE-011 | Implementar serialización segura | Resources exponen allowlist por rol/contexto; sin atributos accidentales | Snapshot/security tests | BE-005 | M |
| BE-012 | Implementar paginación cursor/offset | Límites máximos, filtros/sorts allowlist y enlaces opacos | API/performance tests | BE-008 | M |
| BE-013 | Implementar idempotencia | Scope actor+ruta+clave, hash request, replay y conflicto | Concurrency/API tests | BE-008 | L |
| BE-014 | Implementar control optimista | ETag/version y respuestas 412/409 coherentes | Parallel update tests | BE-008 | M |
| BE-015 | Implementar transacciones de caso de uso | Operación, outbox y auditoría atómicas donde corresponda | Failure injection tests | BE-003 | M |
| BE-016 | Crear event bus interno | Eventos versionados, post-commit y listeners idempotentes | Unit/integration | GOV-027, BE-015 | M |
| BE-017 | Implementar transactional outbox | Claim seguro, retries, backoff, DLQ y deduplicación | Crash/restart tests | BE-015 | L |
| BE-018 | Implementar jobs seguros | Payload mínimo/encriptado, timeout, retryUntil y redacción de fallos | Queue tests | BE-017 | M |
| BE-019 | Implementar rate limiters | Por endpoint/cuenta/IP/dispositivo/actor con respuestas no enumerables | API limit tests | BE-005 | M |
| BE-020 | Implementar request size/timeouts | Límites por contenido, uploads y dependencias | Oversize/slow tests | BE-008 | M |
| BE-021 | Implementar security headers | CSP, HSTS prod, nosniff, frame-ancestors, referrer y permissions policy | Header integration tests | BE-008 | M |
| BE-022 | Configurar CORS restrictivo | Orígenes exactos, credentials y preflight mínimos | Browser/API tests | BE-008 | S |
| BE-023 | Configurar proxy/HTTPS fiable | Proxies allowlist; spoofed forwarded headers rechazados | Proxy tests | BE-021 | M |
| BE-024 | Crear sanitizador de logs | Headers, cookies, DNI, tarjetas, medicación, documentos y URL sensibles redactados | Canary leak tests | BE-005 | M |
| BE-025 | Implementar correlación/tracing | IDs opacos propagados a jobs/integraciones sin PHI | Trace integration tests | BE-024 | M |
| BE-026 | Crear health/readiness endpoints | Dependencias críticas, sin secretos ni topología interna pública | Failure tests | BE-001 | S |
| BE-027 | Crear puerto de almacenamiento privado | Put/read/delete/version/temporary URL con autorización externa al driver | Contract tests local/S3 | TOOL-012, BE-003 | M |
| BE-028 | Crear puerto de criptografía/KMS | Versionado de claves y envelope encryption sin crypto propia | Fake KMS contract tests | BE-003 | M |
| BE-029 | Implementar casts cifrados seleccionados | Búsqueda/rotación/índices documentados y claves separadas | Roundtrip/rotation tests | BE-028 | M |
| BE-030 | Configurar hashing Argon2id | Parámetros medidos, rehash y límites de contraseña seguros | Benchmark/auth tests | BE-001 | S |
| BE-031 | Crear migraciones seguras | FKs, checks, índices, reversión y patrón zero-downtime | migrate fresh/rollback PostgreSQL | BE-003 | M |
| BE-032 | Crear factories sintéticas | Escenarios por rol/centro/estado sin datos reales | Seed determinista + PII scan | GOV-026, BE-031 | M |
| BE-033 | Crear OpenAPI 3.1 | Todos los endpoints, schemas, errores, auth e idempotencia descritos | OpenAPI lint | BE-008 | L |
| BE-034 | Generar cliente TypeScript | Sin `any`, fechas/errores tipados y diff reproducible | Contract compilation | BE-033, TOOL-022 | M |
| BE-035 | Configurar static analysis máximo viable | Sin baseline que silencie código nuevo | `composer analyse` | TOOL-018, BE-003 | M |
| BE-036 | Crear prueba de arquitectura modular | Bloquea dependencias prohibidas y controladores gruesos | `php artisan test --testsuite=Architecture` | BE-004 | S |
| BE-037 | Crear prueba de fugas en respuestas | Lista deny/allow de campos y scanning recursivo | Security suite | BE-011 | M |
| BE-038 | Crear prueba de errores sin internals | Producción simulada nunca devuelve stack/query/path | Security suite | BE-009 | S |
| BE-039 | Crear comando de verificación backend | Format, analyse, tests, migration, OpenAPI y audit en un comando | `composer verify` | BE-031..038 | M |
| BE-040 | Documentar patrones backend | Caso de uso, policy, evento, outbox, auditoría e integración de ejemplo | Docs tests/enlaces | BE-003..039 | M |
