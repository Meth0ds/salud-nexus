# Salud Nexus API

Backend API de Salud Nexus, construido sobre Laravel 13 y PHP 8.5. Esta base no contiene historiales ni usuarios ficticios: fija contratos HTTP, límites modulares y controles seguros sobre los que implementar cada caso de uso real.

## Arranque local

Requisitos: PHP 8.5 con `pdo_sqlite`, Composer 2.10 o posterior y las extensiones requeridas por Laravel.

```powershell
Copy-Item .env.example .env
New-Item database\database.sqlite -ItemType File -Force
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

La API queda en `http://127.0.0.1:8000/api/v1`. Sondas:

- `GET /api/v1/health/live`: vida del proceso, sin dependencias externas.
- `GET /api/v1/health/ready`: disponibilidad de la base de datos.

La autenticación web por sesión y el flujo CSRF para Angular están documentados en [docs/authentication.md](docs/authentication.md).

## Calidad

```powershell
vendor\bin\pint --test
php artisan test
composer validate --strict
composer audit --locked
php artisan migrate --pretend
```

El desarrollo y las pruebas arrancan con SQLite. PostgreSQL es la base obligatoria para staging y producción. La arquitectura, autenticación prevista y controles operativos están descritos en [docs/architecture.md](docs/architecture.md).

## Seguridad por defecto

- Errores de API con `application/problem+json` y formato RFC 9457 sin trazas ni mensajes internos.
- `X-Request-ID` UUID validado y propagado al contexto de logs.
- CORS con lista explícita; credenciales habilitadas solo para los orígenes configurados.
- Sesiones cifradas, `HttpOnly` y `SameSite=Lax`; en producción la cookie segura es obligatoria.
- Cabeceras defensivas, respuestas API `no-store`, rate limiting y validación de configuración de producción.
- No se emiten tokens bearer ni se contempla almacenar credenciales en `localStorage`.

No habilites `APP_DEBUG`, orígenes HTTP ni cookies inseguras en producción: el arranque falla de forma explícita ante esas configuraciones.
