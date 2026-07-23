# Salud Nexus

Plataforma profesional de gestión sanitaria para pacientes, profesionales, recepción,
administración, seguridad y cumplimiento. El repositorio implementa el plan maestro como un
monolito modular Laravel 13 y dos portales Angular 22, con un laboratorio visual ejecutable.

![Dirección visual de Salud Nexus](docs/design/salud-nexus-design-board.png)

## Estado

La especificación, la arquitectura, el inventario visual y el backlog completo viven en:

- [`docs/specification.md`](docs/specification.md)
- [`docs/design/`](docs/design/)
- [`docs/research/angular-frontend-skills.md`](docs/research/angular-frontend-skills.md)
- [`tasks/plan.md`](tasks/plan.md)
- [`tasks/todo.md`](tasks/todo.md)
- [`tasks/backlog/`](tasks/backlog/)

Todos los datos de demostración son sintéticos. La aplicación no debe utilizar datos sanitarios
reales hasta completar configuración organizativa, evaluación de impacto, proveedores, hardening,
pruebas y autorización de producción.

## Estructura

```text
backend/        Laravel 13, API REST y módulos de dominio
frontend/       Angular 22: patient-portal, staff-portal y design-lab
docs/           producto, arquitectura, diseño, seguridad y operación
tasks/          backlog trazable y verificable
infrastructure/ contenedores, proxy, observabilidad e IaC
```

## Inicio rápido del frontend

```powershell
Set-Location frontend
npm.cmd ci
npm.cmd run start:design
```

Aplicaciones disponibles:

```powershell
npm.cmd run start:patient
npm.cmd run start:staff
npm.cmd run start:design
```

Quality gates:

```powershell
npm.cmd run verify
npm.cmd run lint
npm.cmd run test:unit
npm.cmd run build
npm.cmd run audit
npm.cmd run test:e2e
```

## Contrato OpenAPI y cliente Angular

El contrato autoritativo está en `backend/openapi/openapi.json`. El cliente Angular generado vive en
`frontend/projects/api-client/src/lib/generated` y no se edita manualmente.

```powershell
Set-Location frontend
npm.cmd run api:lint
npm.cmd run api:generate
npm.cmd run api:check
```

`api:check` valida OpenAPI con reglas estrictas, regenera en un directorio temporal y falla si existe
deriva. La verificación Laravel incorpora el mismo lint junto con Pint, Larastan, pruebas y auditoría:

```powershell
Set-Location backend
composer verify
```

## Principios de seguridad

- Sesiones de servidor con cookies `HttpOnly`, `Secure` y `SameSite`; no JWT persistentes en el
  almacenamiento del navegador.
- Autorización siempre en backend con rol, finalidad, ámbito, tiempo y relación asistencial.
- Auditoría append-only para accesos y operaciones sensibles, incluidas denegaciones.
- Datos mínimos por pantalla y fixtures exclusivamente sintéticos.
- Cobertura trazable de OWASP ASVS 5.0 nivel 3 cuando sea aplicable.

La existencia de simuladores locales no equivale a homologación ni autorización para producción.
