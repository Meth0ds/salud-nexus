# Convención de módulos

Cada carpeta representa una frontera de negocio, no una agrupación técnica global. Al añadir funcionalidad se usa esta forma:

```text
Module/
  Domain/          Entidades, value objects, reglas y eventos puros
  Application/     Casos de uso, DTO y contratos/puertos
  Infrastructure/  Eloquent, colas, almacenamiento e integraciones
  Http/            Controllers, Form Requests, Resources y rutas
  ModuleServiceProvider.php
```

Reglas:

1. `Domain` no importa Laravel ni otro módulo.
2. `Application` depende de Domain y de contratos propios, no de adaptadores.
3. `Infrastructure` implementa contratos y se registra en el service provider.
4. `Http` autentica, autoriza, valida y delega; no contiene reglas de negocio.
5. Las consultas siempre incluyen alcance de organización y se vuelven a proteger con Policy.
6. La colaboración entre módulos usa un contrato explícito o evento; no se consulta una tabla ajena desde un modelo.
7. Ningún módulo inventa permisos, usuarios o datos clínicos de demostración en código de producción.
