# 01 — Entorno, monorepo y cadena de suministro

Estado verificable del corte de contrato: `TOOL-022` está `[x]`. La especificación OpenAPI 3.1.1,
el cliente Angular y su comprobación de regeneración están documentados en la
[evidencia del contrato API](../evidence/contrato-openapi-cliente-angular.md).

| ID | TODO | Aceptación | Verificación / evidencia | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| TOOL-001 | Inicializar Git y atributos | Rama inicial, finales de línea y binarios coherentes | `git status`; `git check-attr` | GOV-002 | XS |
| TOOL-002 | Crear README operativo | Instalación, comandos, arquitectura y seguridad local explicados | Seguir README desde entorno limpio | TOOL-001 | S |
| TOOL-003 | Fijar Node/npm | Versión compatible con Angular 22 y package manager declarado | `node -v`; `npm -v`; `engines` | GOV-022 | XS |
| TOOL-004 | Fijar PHP/Composer | PHP compatible con Laravel 13 y Composer verificado | `composer diagnose` | GOV-022 | XS |
| TOOL-005 | Crear workspace Angular vacío | Workspace estricto sin app accidental | `ng config`; build workspace | TOOL-003 | S |
| TOOL-006 | Generar las tres aplicaciones Angular | patient, staff y design-lab standalone con routing y tests | Build individual | TOOL-005 | M |
| TOOL-007 | Generar librerías Angular | design-system, api-client, auth y shared con límites claros | Tests/build de librerías | TOOL-006 | M |
| TOOL-008 | Crear Laravel 13 | App arranca, prueba por defecto verde y `.env` ignorado | `php artisan test` | TOOL-004 | S |
| TOOL-009 | Instalar API/Sanctum | API versionable y sesión SPA disponible | Test CSRF/session | TOOL-008 | S |
| TOOL-010 | Configurar PostgreSQL | Conexión, extensiones elegidas y migración limpia | migrate:fresh en PostgreSQL | TOOL-008 | M |
| TOOL-011 | Configurar Redis | Sesión, caché, locks, throttle y colas aislados por prefijo | Tests de cada uso | TOOL-010 | M |
| TOOL-012 | Configurar almacenamiento S3 compatible | Bucket privado, URLs temporales y simulador local | Upload/download autorizado | TOOL-008 | M |
| TOOL-013 | Crear Docker Compose local | API, workers, scheduler, PostgreSQL, Redis, objetos, mail y proxy saludables | `docker compose up`; healthchecks | TOOL-010 | L |
| TOOL-014 | Crear scripts PowerShell multiplataforma documentados | Bootstrap, dev, test y verify son idempotentes en Windows | Ejecutar dos veces | TOOL-005, TOOL-008 | M |
| TOOL-015 | Configurar formato frontend | Prettier/Angular format sin diffs posteriores | `npm run format:check` | TOOL-005 | S |
| TOOL-016 | Configurar lint frontend | ESLint estricto y reglas de imports/boundaries | `npm run lint` | TOOL-007 | M |
| TOOL-017 | Configurar formato backend | Pint con configuración bloqueada | `composer format:check` | TOOL-008 | S |
| TOOL-018 | Configurar análisis PHP | PHPStan/Larastan al nivel acordado sin baseline opaco | `composer analyse` | TOOL-008 | M |
| TOOL-019 | Configurar Vitest Angular | Tests unitarios y cobertura por proyecto | `npm run test:unit` | TOOL-006 | M |
| TOOL-020 | Configurar Playwright | Proyectos patient/staff/mobile/a11y y trazas en fallo | `npm run test:e2e -- --list` | TOOL-006 | M |
| TOOL-021 | Configurar Pest/PHPUnit | Suites unit/feature/integration/architecture separadas | `php artisan test` | TOOL-008 | M |
| TOOL-022 | Generar OpenAPI/cliente | Spec 3.1 y cliente TypeScript reproducibles, sin diff al regenerar | `npm run api:check` | TOOL-009, TOOL-007 | M |
| TOOL-023 | Revisar scripts de instalación npm | Política documentada; solo scripts necesarios aprobados | Clean install fail-closed | TOOL-003 | M |
| TOOL-024 | Bloquear lockfiles | Un lockfile autoritativo por boundary y CI con install congelado | `npm ci`; `composer install` | TOOL-005, TOOL-008 | S |
| TOOL-025 | Verificar firmas/provenance npm | Resultado guardado y ausencias triadas | `npm audit signatures` | TOOL-024 | S |
| TOOL-026 | Auditar Composer/npm | Cero críticos/altos alcanzables no mitigados | `composer audit --locked`; `npm audit` | TOOL-024 | S |
| TOOL-027 | Configurar Renovate/Dependabot | Actualizaciones agrupadas, revisión y ventanas definidas | Dry run/config validation | TOOL-024 | M |
| TOOL-028 | Generar SBOM frontend/backend/contenedores | CycloneDX/SPDX por artefacto con hashes | Validación SBOM | TOOL-024 | M |
| TOOL-029 | Configurar detección de secretos | Pre-commit y CI detectan fixtures de prueba | Test canario de secreto | TOOL-001 | M |
| TOOL-030 | Crear inventario de dependencias | Dueño, finalidad, licencia, runtime/dev y fecha de revisión | Comparar manifests/lockfiles | TOOL-024 | M |
| TOOL-031 | Configurar licencia allowlist | Build bloquea licencias no aprobadas | Fixture de licencia denegada | TOOL-030 | S |
| TOOL-032 | Crear verificación global | Un comando ejecuta formato, lint, tipos, tests, builds y auditorías | `scripts/verify.ps1` verde | TOOL-015..031 | M |
| TOOL-033 | Configurar hooks locales opcionales | Hooks rápidos; CI sigue siendo autoridad | Commit de prueba | TOOL-032 | S |
| TOOL-034 | Configurar artefactos reproducibles | Builds limpios equivalentes y con metadatos/versiones | Comparar checksums permitidos | TOOL-024 | M |
| TOOL-035 | Documentar actualización de toolchain | Procedimiento, rollback y matriz de compatibilidad | Simular bump patch | GOV-022, TOOL-032 | S |
