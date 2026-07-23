# Evidencia del contrato OpenAPI y cliente Angular

Fecha de corte: 2026-07-23.

## Resultado implementado

- Contrato autoritativo OpenAPI 3.1.1 con dialecto JSON Schema 2020-12 en
  `backend/openapi/openapi.json`.
- Las 20 operaciones API implementadas están descritas con esquemas, respuestas de éxito, errores
  RFC 9457, sesión Sanctum, CSRF, idempotencia, paginación y descarga PDF privada cuando aplica.
- `OpenApiContractTest` compara exactamente las rutas Laravel nombradas con los Path Items, exige
  `operationId` únicos, referencias internas, respuesta de error común y seguridad por operación.
- Orval 8.22.0 genera un servicio Angular `HttpClient`, modelos TypeScript sin `any` y validadores Zod
  4 estrictos en `frontend/projects/api-client/src/lib/generated`.
- `scripts/finalize-generated-api.mjs` completa de forma determinista cuatro tipos de operación que el
  generador referencia pero no emite en su combinación Angular/Zod.
- `scripts/check-generated-api.mjs` regenera en una carpeta temporal controlada y compara el árbol
  byte a byte para bloquear deriva sin modificar primero el cliente versionado.
- `HttpPatientRepository` compone los esquemas generados con validaciones semánticas propias: centro
  único, relaciones de elegibilidad y URL de descarga relativa, entre otras invariantes.

## Seguridad y cadena de suministro

- URL base same-origin `/api/v1`; no se incrustan hostnames ni credenciales en el cliente.
- Cookies de sesión y cabecera XSRF están declaradas explícitamente en cada operación aplicable.
- Las mutaciones de reserva y medicación requieren `Idempotency-Key` tipada.
- Los esquemas Zod estrictos rechazan campos no documentados y respuestas malformadas.
- La descarga documental se tipa como `Blob`; el bearer token efímero nunca forma parte de un modelo
  persistente del navegador.
- Se descartó `openapi-typescript` 7.13.0 porque su peer dependency no admite TypeScript 6. Se eligió
  Orval sin forzar un árbol npm incompatible.
- `npm audit` informa 0 vulnerabilidades; 661 paquetes tienen firma de registro verificada y 209
  disponen de attestations verificadas.

## Evidencia automática

- `composer verify`: Pint correcto, Larastan 193/193, 84 pruebas Laravel con 953 aserciones, OpenAPI
  válido, Composer audit sin avisos y manifiesto Composer válido.
- `npm run api:check`: Redocly `recommended-strict` válido y regeneración sin deriva.
- `npm run verify`: Prettier y lint de ocho proyectos, 105 pruebas unitarias, cinco librerías y tres
  aplicaciones compiladas, 30/30 Playwright E2E y auditoría npm completa.
- Pruebas específicas del cliente: URL versionada, respuesta válida, cabecera de idempotencia
  obligatoria y rechazo Zod de respuestas malformadas.

## Estado de TODOs relacionados

- `[x]` TOOL-022 — especificación y cliente reproducibles.
- `[x]` BE-033 — todas las rutas API actuales descritas en OpenAPI 3.1.1.
- `[x]` BE-034 — cliente TypeScript generado, estricto y compilable.
- `[x]` BE-039 — un solo `composer verify` ejecuta los gates del backend y del contrato.
- `[x]` QA-009 — deriva backend/especificación/cliente bloqueada por pruebas y regeneración.
- `[x]` DOC-018 — la descarga autorizada está incluida en el contrato y en el cliente generado.

## Fuentes técnicas oficiales

- [OpenAPI Specification 3.1.1](https://spec.openapis.org/oas/v3.1.1.html)
- [Redocly CLI: lint](https://redocly.com/docs/cli/commands/lint)
- [Orval: Angular](https://orval.dev/docs/guides/angular/)
- [Orval: output configuration](https://orval.dev/docs/reference/configuration/output/)

Los avisos actuales de presupuesto de bundles y estilos Angular no bloquean este corte contractual;
permanecen abiertos bajo `PERF-008` y deben cerrarse antes del checkpoint técnico de producción.
