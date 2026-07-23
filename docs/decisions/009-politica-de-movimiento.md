# ADR-009: Movimiento no bloqueante y ScrollFX limitado

## Estado

Aceptado.

## Fecha

2026-07-19.

## Contexto

El movimiento puede orientar y crear continuidad, pero en tareas clínicas repetitivas puede ralentizar,
distraer o provocar síntomas vestibulares.

## Decisión

Priorizar CSS y View Transitions. GSAP/ScrollTrigger solo se carga de forma diferida en onboarding,
privacidad y contenido explicativo. No se usa parallax. Acciones críticas, teclado y vistas de alta
frecuencia no esperan animación. Toda mejora respeta `prefers-reduced-motion` y deja el contenido visible
si JavaScript o el plugin fallan.

## Alternativas consideradas

- Animar todas las pantallas: rechazado.
- No usar movimiento: pierde continuidad y orientación útiles en recorridos pedagógicos.
- Librería global eager: rechazada por peso y superficie innecesaria.

## Consecuencias

El presupuesto de movimiento es 80–360 ms; solo transform y opacidad en recorridos animados. Playwright
verifica la variante reducida y que las acciones sigan disponibles.
