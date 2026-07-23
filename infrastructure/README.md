# Infraestructura de Salud Nexus

Esta carpeta contiene una base local reproducible para la API Laravel 13, los portales Angular y sus dependencias. El objetivo es aproximar el comportamiento operativo de produccion sin introducir datos clinicos, credenciales fijas ni servicios accesibles fuera del equipo local.

## Diseno operativo

- `patient-web`, `staff-web` y `design-lab` son builds Angular estaticos servidos por NGINX sin privilegios.
- `api-proxy` es el unico proceso que alcanza PHP-FPM; PHP no publica ningun puerto al host.
- PostgreSQL y Redis solo viven en la red interna `data` y no publican puertos.
- Los contenedores eliminan todas las capabilities, activan `no-new-privileges`, limitan procesos/memoria/CPU y usan raiz de solo lectura cuando el servicio lo permite.
- Los cuatro secretos locales se montan mediante Docker secrets. Laravel los carga desde archivos y no se pasan como argumentos ni se imprimen.
- Los logs de acceso de NGINX y PHP-FPM estan desactivados para no convertir rutas o identificadores en un canal de datos personales. La aplicacion debe mantener logging estructurado y sanitizado.

Compose es una herramienta de desarrollo local. Produccion debe ejecutarse en un orquestador con TLS de extremo a extremo, secretos administrados, backups cifrados y despliegue progresivo/rollback probado.

## Requisitos

- PowerShell 5.1 o posterior (`pwsh` o `powershell.exe`).
- Docker Engine/Desktop reciente con Compose v2 y BuildKit (`docker buildx`).
- Al menos 6 GB libres para construir las tres aplicaciones y descargar las imagenes base.

Las imagenes se fijaron por version y digest multi-arquitectura el 19 de julio de 2026:

- [PHP Official Image](https://hub.docker.com/_/php): `8.5.8-fpm-alpine3.24`.
- [Composer Official Image](https://hub.docker.com/_/composer): `2.10.1`.
- [Node Official Image](https://hub.docker.com/_/node): `24.18.0-alpine3.24`.
- [NGINX Unprivileged](https://github.com/nginx/docker-nginx-unprivileged): `1.30.4-alpine3.24`.
- [PostgreSQL Official Image](https://hub.docker.com/_/postgres): `18.4-alpine3.24`.
- [Redis Official Image](https://hub.docker.com/_/redis): `8.8.0-alpine3.23`.

Los digests hacen las compilaciones repetibles; deben actualizarse mediante una revision explicita con auditoria, pruebas y lectura de notas de version. No se usa `latest`.

## Primer arranque

Desde la raiz del workspace:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Start-Local.ps1
```

El script:

1. Comprueba Docker antes de modificar estado.
2. Crea `infrastructure/.env` si no existe.
3. Genera `APP_KEY`, la clave HMAC de auditoria y las contrasenas de PostgreSQL/Redis con un generador criptografico, sin mostrarlas.
4. Construye y arranca los servicios con `--wait`.
5. Ejecuta `php artisan migrate --force` de forma explicita.
6. Valida liveness, readiness y los dos portales.

Opciones:

```powershell
# Incluye el laboratorio de diseno
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Start-Local.ps1 -IncludeDesignLab

# Incluye queue worker y scheduler
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Start-Local.ps1 -IncludeWorkers

# Reutiliza imagenes ya construidas
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Start-Local.ps1 -SkipBuild
```

URLs por defecto:

- Paciente: `http://127.0.0.1:4200`
- Profesionales: `http://127.0.0.1:4201`
- API: `http://127.0.0.1:8080/api/v1`
- Design Lab (perfil opcional): `http://127.0.0.1:4300`

Para detener sin borrar datos:

```powershell
docker compose --env-file infrastructure/.env --file infrastructure/compose.yaml down
```

No uses `down --volumes` salvo que quieras eliminar de forma consciente e irreversible la base local y tengas la copia necesaria.

## Verificacion

La comprobacion estatica funciona aunque Docker no este instalado:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Test-Infrastructure.ps1
```

Cuando Docker esta disponible, el mismo script ejecuta `docker compose config`. La opcion siguiente anade los checks estaticos de BuildKit sin publicar imagenes:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Test-Infrastructure.ps1 -DockerBuildCheck
```

La puerta global de calidad ejecuta infraestructura, formato/lint/tests/build/auditoria npm, verificacion Composer y `git diff --check`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Verify-Workspace.ps1 -ComposerPath composer
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Verify-Workspace.ps1 -ComposerPath composer -IncludeE2E -DockerBuildCheck
```

Las sondas se pueden repetir sin alterar estado:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Test-LocalHealth.ps1
```

## Seguridad y limites de entorno

- La terminacion TLS no vive en Compose. En staging/produccion, el balanceador debe forzar HTTPS, fijar HSTS y pasar un protocolo confiable; configura `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `HSTS_ENABLED=true`, una allowlist de hosts/origenes y CIDR de proxies concretos.
- `style-src 'unsafe-inline'` se limita a estilos porque Angular inserta estilos de componentes en tiempo de ejecucion. Scripts, conexiones, frames, objetos, fuentes e imagenes conservan allowlists cerradas.
- `DB_SSLMODE=prefer` es solo para la red interna local. Produccion debe exigir TLS con validacion de identidad (`verify-full` o equivalente administrado).
- Las imagenes no ejecutan scripts o plugins de dependencias durante la instalacion inicial. El unico lifecycle equivalente aprobado es `artisan package:discover`; CI repite auditorias sobre los lockfiles.
- PostgreSQL 18 persiste el volumen padre `/var/lib/postgresql`, conforme al [cambio oficial de `PGDATA` en PostgreSQL 18](https://github.com/docker-library/docs/blob/master/postgres/README.md#pgdata).
- Si se elimina `infrastructure/.env` conservando los volumenes, no regeneres contrasenas a ciegas: recupera/rota las credenciales de forma coordinada. Los scripts nunca borran volumenes automaticamente.
- `AUDIT_INTEGRITY_KEY` debe mantenerse fuera de PostgreSQL y rotarse mediante un procedimiento versionado; perderla impide demostrar la integridad historica y cambiarla sin migracion invalida la cadena existente.
- No hay certificados, backups, registro centralizado, escaneo de imagenes en runtime ni despliegue productivo incluidos en este compose local. Son puertas obligatorias antes de manejar datos reales.

En esta entrega no se construyeron ni arrancaron contenedores localmente porque Docker no esta instalado en la estacion. La sintaxis YAML, los contratos estaticos y los proyectos de aplicacion se validan localmente; GitHub Actions ejecuta `docker compose config` y los checks de BuildKit en un runner con Docker.
