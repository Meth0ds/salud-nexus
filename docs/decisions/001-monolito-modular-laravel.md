# ADR-001: Monolito modular Laravel

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

La plataforma reúne identidad, pacientes, agenda, medicación informativa, documentos, privacidad y
auditoría. Los límites de dominio deben ser claros, pero un sistema distribuido elevaría la superficie
de ataque, la consistencia operativa y el coste de observabilidad antes de existir carga que lo exija.

## Decisión

Usar Laravel 13 como monolito modular. Cada dominio vive en `app/Modules/<Domain>` y expone casos de
uso, políticas y contratos; los controladores solo adaptan HTTP. La comunicación síncrona entre
módulos usa interfaces explícitas y la asíncrona usa eventos/outbox.

## Alternativas consideradas

- Microservicios: rechazados inicialmente por transacciones distribuidas, más secretos y mayor coste
  operativo.
- Laravel organizado solo por capas globales: rechazado porque diluye propiedad y límites de dominio.
- Backend TypeScript: no aporta una ventaja que compense cambiar la tecnología requerida por el plan.

## Consecuencias

Los límites se verifican con tests de arquitectura. Un módulo solo se extraerá si tiene frontera estable,
necesidad operativa independiente y métricas que justifiquen el coste.
