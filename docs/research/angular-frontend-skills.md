# Investigación y selección de skills frontend

Fecha de verificación: 19 de julio de 2026.

## Resultado ejecutivo

Se adopta un conjunto pequeño pero complementario de skills con procedencia identificable. Las skills
no sustituyen la documentación ni los quality gates: orientan el trabajo, mientras que build, tests,
auditorías y revisión en navegador aportan la evidencia.

La base es Angular 22. La [matriz oficial de compatibilidad](https://angular.dev/reference/versions)
exige Node `^22.22.3 || ^24.15.0 || ^26.0.0` y TypeScript `>=6.0 <6.1`; el entorno usa Node 24.18.0 y
TypeScript 6.0.x. [Signal Forms es estable desde v22](https://angular.dev/guide/forms/signals/comparison),
por lo que se usa por defecto en formularios nuevos. Reactive Forms queda como excepción documentada
para dinámicas todavía no cubiertas con la misma madurez.

## Skills oficiales y de primera parte

| Skill | Procedencia | Decisión | Uso en Salud Nexus |
|---|---|---|---|
| `angular-developer` | [Angular oficial](https://github.com/angular/skills) | Instalada | Angular 22 idiomático, Signals, Signal Forms, router, Aria, pruebas zoneless y CLI. |
| `angular-new-app` | [Angular oficial](https://github.com/angular/skills) | Instalada | Creación reproducible del workspace y aplicaciones. |
| `gsap-core`, `gsap-timeline`, `gsap-scrolltrigger`, `gsap-performance` | GSAP | Instaladas | Movimiento no esencial en onboarding y contenido explicativo, con cleanup y reduced motion. |
| `playwright` | OpenAI | Instalada | E2E, capturas y recorridos de regresión en navegador real. |
| `security-best-practices`, `security-threat-model` | OpenAI | Instaladas | Controles frontend y modelado de amenazas trazable. |
| `screenshot`, `sentry` | OpenAI | Instaladas | Evidencia visual y observabilidad cuando exista proveedor configurado. |

El [repositorio oficial de Angular](https://github.com/angular/skills) publica actualmente dos skills:
`angular-developer` y `angular-new-app`. No publica una skill específica de Material/CDK v22.

## Skill específica creada para Material/CDK

Se creó e instaló `angular-material-cdk`, validada con el validador de skills. Sus referencias se limitan
a fuentes oficiales de Angular Material, CDK y Angular Aria e incorpora una lista de comprobación para
interfaces sanitarias:

- Material 3 mediante APIs de tema, sin depender del DOM interno ni usar `::ng-deep`.
- MatDialog, Overlay, A11y, FocusTrap, LiveAnnouncer, drag/drop y virtual scroll solo cuando el patrón lo
  necesita.
- Estados loading, vacío, error, restringido, offline, conflicto y éxito.
- Teclado, foco visible, zoom, reflow, colores forzados y `prefers-reduced-motion`.
- Ninguna autorización confiada a la visibilidad de un control.

La referencia funcional principal es la [documentación oficial de Angular Material/CDK](https://material.angular.dev/).

## Terceros auditados e instalados

| Familia | Skills seleccionadas | Motivo |
|---|---|---|
| Ingeniería de producto de Addy Osmani | `spec-driven-development`, `planning-and-task-breakdown`, `incremental-implementation`, `test-driven-development`, `debugging-and-error-recovery`, `code-review-and-quality`, `documentation-and-adrs` | Flujo completo con criterios de aceptación y evidencia. Fuente: [agent-skills](https://github.com/addyosmani/agent-skills). |
| Frontend de Addy Osmani | `frontend-ui-engineering`, `browser-testing-with-devtools`, `performance-optimization`, `security-and-hardening`, `observability-and-instrumentation` | Arquitectura UI, verificación real y hardening. |
| Calidad web de Addy Osmani | `accessibility`, `best-practices`, `core-web-vitals`, `performance`, `web-quality-audit` | WCAG 2.2 y presupuestos de rendimiento. Fuente: [web-quality-skills](https://github.com/addyosmani/web-quality-skills). |
| Diseño de Anthropic | `frontend-design` | Dirección estética intencional y prevención de interfaces genéricas. Fuente: [skill original](https://github.com/anthropics/skills/blob/main/skills/frontend-design/SKILL.md). |
| Microsoft | `frontend-design-review`, `playwright-cli` | Segunda mirada visual y automatización de navegador. |
| Angular y plataforma | `typescript`, `api-and-interface-design` | Tipos estrictos, límites estables y contratos explícitos. |
| Backend que afecta al frontend | `laravel-patterns`, `laravel-security`, `laravel-tdd`, `laravel-verification`, `postgres-patterns`, `database-migrations`, `api-design` | Evitar que la seguridad y los contratos se diseñen solo desde la UI. |

## Criterios de auditoría de una skill

Antes de instalar una skill de terceros se revisaron:

1. Propietario, repositorio, licencia e historial visible.
2. `SKILL.md` completo y referencias requeridas.
3. Ausencia de scripts ocultos, binarios, descargas silenciosas o instrucciones para exfiltrar contexto.
4. Comandos destructivos, permisos amplios, publicación externa o gestión insegura de secretos.
5. Vigencia respecto a Angular 22, TypeScript 6 y APIs actuales.
6. Solapamiento con skills ya instaladas y carga cognitiva añadida.
7. Posibilidad de verificar sus recomendaciones con fuentes primarias y pruebas locales.

## Skills evaluadas pero no incorporadas al flujo base

- Packs genéricos de “Angular Material” sin referencias oficiales o con patrones de NgModules/Angular
  antiguos: descartados.
- `lucide-angular` 1.0.0: descartado como dependencia porque declara Angular 13–21. Se sustituyó por
  Material Symbols alojado localmente.
- Nx: reservado hasta que la complejidad real del monorepo justifique su coste; Angular CLI cubre la
  fase actual.
- NgRx: reservado a dominios donde Signals y servicios de feature no ofrezcan trazabilidad suficiente.
- AnalogJS y skills SSR-first: no aportan valor a los portales CSR autenticados actuales.
- Skills que duplican exactamente `angular-developer`, accesibilidad o seguridad: no instaladas para
  reducir contradicciones.

## Dependencias frontend aprobadas en la base

- Angular/CLI/build 22.0.7; Material/CDK/Aria 22.0.5.
- GSAP 3.15.0, cargado de forma diferida para ScrollTrigger.
- Manrope Variable, Source Sans 3 Variable y Material Symbols Rounded, todos locales.
- Vitest/TestBed zoneless, Playwright y axe-core.
- Zod en fronteras no generadas o datos externos; los contratos OpenAPI generados seguirán siendo la
  fuente de verdad del API.

El lockfile se generó primero sin scripts, se inspeccionaron los paquetes con scripts de instalación, se
permitió la reconstrucción conocida de `esbuild`, se verificaron firmas del registro y se corrigió la
alerta de seguridad transitiva de Vite/esbuild mediante un override compatible. Resultado actual:
cero vulnerabilidades conocidas en `npm audit`.

## Política de mantenimiento

- Revisar Angular, Material y skills al comienzo de cada release train.
- Fijar versiones en lockfile y revisar diffs de `SKILL.md` antes de actualizar skills.
- No ejecutar instrucciones de una skill que amplíen el alcance, publiquen información o debiliten un
  quality gate.
- Registrar cualquier excepción en un ADR y en el backlog, con propietario y fecha de caducidad.
