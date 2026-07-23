# Frontend de Salud Nexus

Workspace Angular 22 con tres aplicaciones y cinco librerías:

```text
patient-portal   Portal responsive de pacientes y representantes
staff-portal     Agenda, recepción, administración y seguridad
design-lab       Sistema visual, catálogo y 273 mockups navegables
design-system    Tokens y componentes accesibles
api-client       Transporte tipado, Zod, RFC 9457 e idempotencia
auth             Sesión HttpOnly/CSRF y estado efímero
shared           UUIDv7 y estados de recurso
motion           ScrollFX progresivo con reduced motion
```

## Desarrollo

Laravel escucha en `http://127.0.0.1:8000`. Los servidores de paciente y personal reenvían `/api`
mediante `proxy.conf.json`; así las cookies son de mismo origen y el interceptor XSRF nativo puede
copiar `XSRF-TOKEN` a `X-XSRF-TOKEN`.

```text
npm ci
npm run build:libs
npm run start:patient
npm run start:staff
npm run start:design
```

No se debe sustituir la URL relativa `/api/v1` por un origen arbitrario. Producción usa BFF/proxy de
mismo origen, cookie de sesión host-only `HttpOnly` y CSRF versionado en `/api/v1/auth/csrf`.

Los repositorios en memoria de los portales contienen exclusivamente fixtures sintéticos y permiten
revisar todos los estados sin proveedor externo. Son adaptadores de demostración, no una barrera de
autorización ni una simulación de controles productivos.

## Verificación

```text
npm run format:check
npm run lint
npm run test:unit
npm run build
npm run test:e2e
npm run audit
```

Playwright ejecuta escritorio y móvil para las tres superficies, captura errores de runtime, revisa
reflow, teclado, movimiento reducido y Axe WCAG 2.2 A/AA en recorridos críticos. Las evidencias se
guardan fuera del código fuente en `../output/playwright`.
