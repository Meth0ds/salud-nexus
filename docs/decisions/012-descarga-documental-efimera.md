# ADR-012: descarga documental efímera, ligada a sesión y de un solo uso

## Estado

Aceptado.

## Fecha

2026-07-23.

## Contexto

Los documentos sanitarios no deben exponerse mediante rutas públicas, identificadores predecibles ni
URLs reutilizables. Una comprobación realizada solo al listar el documento queda obsoleta si la
publicación, la sesión o la relación asistencial cambian antes de la descarga. El navegador tampoco
debe recibir rutas internas del almacenamiento ni conservar contenido clínico en estado de Angular.

## Decisión

La descarga se realiza en dos operaciones bajo sesión Sanctum:

1. El paciente solicita una autorización para un documento publicado y propio. El servidor vuelve a
   comprobar organización, paciente, publicación, versión, MIME, tamaño, magic bytes y SHA-256.
2. El servidor entrega una ruta relativa con un token aleatorio de 256 bits. Solo se guarda su hash,
   caduca a los 90 segundos, queda ligada a la identidad y se consume una única vez.

La entrega vuelve a validar sesión, propiedad, publicación e integridad dentro de una transacción.
Solo se sirve `application/pdf` como adjunto, con nombre opaco, `no-store`, `nosniff`, CSP restrictiva
y auditoría minimizada. Los objetos viven en un disco privado fuera del webroot. El modo demo no crea
archivos ni descargas ficticias.

## Alternativas consideradas

- URL S3 larga y reutilizable: rechazada por ampliar la ventana de exposición y desacoplar la entrega
  de la sesión del portal.
- Descargar el PDF como `Blob` mediante Angular: rechazada porque añade copias en memoria y facilita
  persistencia accidental en estado, logs o herramientas del navegador.
- Endpoint directo permanente por UUID: rechazado porque no expresa caducidad ni consumo único.
- Mostrar PDF activo dentro del origin principal: rechazado por el riesgo de contenido activo y por
  ampliar la superficie de CSP.

## Consecuencias

- Un refresco o reintento después del consumo requiere una autorización nueva.
- Un fallo de hash, MIME, magic bytes, publicación o almacenamiento aborta la descarga sin consumir el
  token ni crear un historial exitoso.
- La descarga agrupada, la verificación pública, la firma y el visor aislado necesitarán contratos
  separados; no reutilizarán este token como prueba de autenticidad pública.
