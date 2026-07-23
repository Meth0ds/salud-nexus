# ADR-002: Workspace Angular CLI con dos portales

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

Pacientes y personal tienen densidad, navegación y exposición de datos distintas, pero comparten
tokens, autenticación, contratos y componentes.

## Decisión

Mantener `patient-portal`, `staff-portal` y el catálogo interno `design-lab` en un workspace Angular 22
CLI con librerías `design-system`, `api-client`, `auth`, `shared` y `motion`. Todo es standalone,
zoneless, lazy por feature y con TypeScript estricto.

## Alternativas consideradas

- Una sola aplicación: rechazada por riesgo de incluir código o navegación de personal en el portal
  público y por presupuestos de rendimiento divergentes.
- Repositorios separados: rechazados por duplicar contratos y el sistema visual.
- Nx desde el inicio: pospuesto; Angular CLI cubre el grafo actual con menor complejidad.

## Consecuencias

Los portales pueden desplegarse y aplicar CSP de forma independiente. Nx solo se reconsiderará con
evidencia de que caché, ownership o tamaño del grafo lo necesitan.
