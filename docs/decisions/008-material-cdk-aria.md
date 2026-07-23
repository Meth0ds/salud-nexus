# ADR-008: Material/CDK/Aria y design system propio

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

La aplicación necesita patrones accesibles y mantenibles sin adquirir la apariencia genérica ni la
dependencia frágil del DOM interno de una librería.

## Decisión

Usar Angular Material 3 para controles, CDK para comportamiento y Angular Aria para patrones headless.
`design-system` define tokens semánticos, tema Atlantic clinical y componentes propios. Se usan APIs
públicas de tema; no `::ng-deep` ni selectores sobre estructura interna.

## Alternativas consideradas

- Componentes totalmente propios: rechazados para patrones con foco y teclado complejos.
- Otro kit visual completo: no ofrece mejor alineación con Angular 22.
- Material sin capa de diseño: rechazado por identidad, consistencia y portabilidad insuficientes.

## Consecuencias

Cada componente tiene estados, contraste, foco, forced-colors, reduced-motion, harness o tests de
comportamiento y documentación de uso.
