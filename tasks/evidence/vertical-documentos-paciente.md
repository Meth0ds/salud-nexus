# Evidencia del vertical de documentos del paciente

Fecha de corte: 2026-07-23.

## Resultado implementado

- Modelo inicial de documento, versión inmutable, publicación, autorización efímera e historial de
  descarga, todos con UUIDv7 público y frontera interna de organización/paciente.
- Objetos PDF en el disco privado `documents`, fuera del webroot, con ruta opaca.
- `GET /api/v1/patient/documents` limitado a publicaciones activas del paciente autenticado.
- `POST /api/v1/patient/documents/{id}/download-authorizations` con CSRF, rate limit, token aleatorio
  hasheado, 90 segundos de TTL y vínculo a identidad.
- `GET /api/v1/patient/document-downloads/{token}` con consumo único, reautorización, SHA-256, magic
  bytes, MIME/tamaño, `attachment`, `no-store`, `nosniff` y auditoría.
- Angular Material conectado al contrato, estados de carga/vacío/error/restringido, ficha segura y
  flujo demo sin descargas falsas.
- Mockups verificados en escritorio y móvil:
  `output/playwright/captures/patient-documentos-activos-desktop.png` y
  `output/playwright/captures/patient-documentos-activos-mobile.png`.

## Evidencia automática

- Backend: Pint, Larastan 193/193, 84 pruebas y 953 aserciones, OpenAPI estricto y Composer audit sin
  avisos.
- Frontend: generación API reproducible, lint completo, 105 pruebas unitarias y 30/30 E2E; los 10
  recorridos de paciente incluyen escritorio, móvil y axe WCAG A/AA.
- Contrato: las tres operaciones documentales están descritas en OpenAPI 3.1.1 y el cliente Angular
  generado valida respuestas estrictamente con Zod.
- Casos documentales: propiedad horizontal, publicación/retirada, expiración, replay, CSRF, token sin
  PHI, manipulación de bytes, inmutabilidad y cabeceras de descarga.

## Estado de TODOs relacionados

- `[~]` DOC-004, DOC-005 y DOC-016: núcleo implementado; faltan emisor/sustitución completa,
  retención gobernada y notificación.
- `[x]` DOC-018: reautorización, URL breve, consumo único, cabeceras defensivas, auditoría, OpenAPI y
  cliente Angular generado verificados.
- `[~]` DOC-020: historial mínimo implementado; falta modelar canal/entrega multicanal.
- `[~]` DOC-026: biblioteca, ficha y descarga implementadas; faltan búsqueda, solicitudes,
  correcciones, versiones y descarga agrupada.
- `[~]` DOC-029, DOC-030, DOC-032 y DOC-033: almacenamiento privado, PDF adjunto, auditoría y
  manipulación cubiertos para este corte; upload/AV/CDR, firma, QR y ciclo profesional siguen
  pendientes.
- El resto de `tasks/backlog/09-documents.md` permanece `[ ]`.

No se considera completado el módulo documental avanzado ni el checkpoint CP-04.
