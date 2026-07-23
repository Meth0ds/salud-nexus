# Primitivas compartidas de Salud Nexus

Contratos pequeños y puros, sin componentes ni estado global:

- `PublicId` y `parsePublicId()` para UUIDv7 públicos y opacos.
- `ResourceState<T>` para distinguir carga, vacío, error, restricción y datos listos.
- `mapResourceState()` para transformar datos sin perder estados de seguridad o UX.
- `requestReference()` para mostrar referencias de soporte acotadas y validadas.
- `assertNever()` para exhaustividad en uniones discriminadas.

La librería no decide permisos, no guarda datos y no convierte identificadores inválidos en rutas.

```text
ng test shared --watch=false
ng lint shared
ng build shared --configuration production
```
