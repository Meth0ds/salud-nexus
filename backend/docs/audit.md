# Auditoría íntegra y minimizada

El módulo `Audit` mantiene una cadena append-only independiente por organización. Cada evento incluye identificadores públicos UUIDv7, finalidad, rol, nivel de autenticación, resultado, referencia de petición y metadatos escalares estrictamente limitados. No admite nombres, correos, diagnósticos, medicación, notas ni otro contenido clínico en los metadatos.

## Integridad

- `AUDIT_INTEGRITY_KEY` es una clave aleatoria de al menos 256 bits, codificada en Base64 y almacenada fuera de PostgreSQL.
- Cada evento contiene `previous_hash` y un `event_hash` HMAC-SHA-256 sobre una representación JSON canónica versionada.
- `audit_chain_heads` se bloquea dentro de la misma transacción que inserta el evento, por lo que la secuencia por organización es lineal incluso con escritores concurrentes.
- `AuditChainVerifier` comprueba versión, secuencia, enlace anterior, HMAC y cabecera final sin devolver datos del evento.

La clave no se registra, no se incluye en contenedores y se monta como Docker secret. La rotación necesita un procedimiento de migración con versiones de clave; no debe sustituirse una clave en caliente sobre una cadena existente.

## Inmutabilidad y controles de despliegue

El modelo Eloquent rechaza `UPDATE` y `DELETE`, y el caso de uso público solo expone `record()`. Esto protege frente a errores de aplicación, pero una cuenta SQL con permisos amplios aún podría saltarse Eloquent. Antes de usar datos reales, producción debe añadir:

1. un rol de escritura que solo pueda insertar eventos y actualizar la cabecera mediante una función/procedimiento controlado;
2. un rol de lectura/verificación separado;
3. exportación periódica firmada a almacenamiento WORM o SIEM;
4. alertas ante fallos del verificador y una ceremonia documentada de rotación/recuperación de claves;
5. retención y acceso aprobados por DPD/seguridad.

No existe endpoint administrativo en esta fase: publicarlo antes del motor RBAC/ABAC y del step-up MFA ampliaría innecesariamente la superficie de exposición.

