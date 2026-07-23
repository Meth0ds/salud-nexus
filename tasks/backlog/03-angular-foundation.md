# 03 — Frontend Angular compartido

| ID | TODO | Aceptación | Verificación | Dep. | Tamaño |
| --- | --- | --- | --- | --- | --- |
| FE-001 | Configurar Angular 22 estricto | Strict templates/TS, budgets y configuración moderna sin excepciones opacas | `ng build` | TOOL-006 | S |
| FE-002 | Integrar Material/CDK/Aria | Versiones alineadas, imports específicos y tema M3 público | Build + harness smoke | FE-001 | M |
| FE-003 | Implementar tokens/temas | Light/dark/forced-colors y densidad paciente/personal | Screenshots + contraste | DES-002, FE-002 | M |
| FE-004 | Self-host de tipografías/iconos | CSP compatible, subset y preload razonable | Network/Lighthouse | DES-003, FE-001 | S |
| FE-005 | Implementar layout paciente | Shell responsive, landmarks, skip link y foco de ruta | E2E teclado | DES-013, FE-003 | M |
| FE-006 | Implementar layout personal | Shell denso, contexto activo y navegación de teclado | E2E teclado | DES-014, FE-003 | M |
| FE-007 | Implementar layout seguridad | Separación de navegación y contexto privilegiado | E2E roles | DES-015, FE-003 | M |
| FE-008 | Implementar design-lab | Todas las rutas/estados/viewport/tema seleccionables | Test de inventario | DES-016, FE-003 | L |
| FE-009 | Crear librería de estados | Loading/empty/error/denied/offline/partial/conflict/session-expired | Harness + axe | DES-006, FE-002 | M |
| FE-010 | Crear componentes de acción crítica | Confirmación, motivo, reauth, doble control y progreso | Harness + E2E | DES-008, FE-002 | M |
| FE-011 | Crear componentes de privacidad | SensitiveValue, context banner, purpose badge y safe copy | DOM/storage leak tests | DES-007, FE-002 | M |
| FE-012 | Crear tablas de servidor | Paginación, sort/filter allowlist, selección y keyboard | Harness + API mock | FE-002 | L |
| FE-013 | Crear calendario/agenda accesible | Teclado, alternativa lista, zona horaria y recursos | Harness + NVDA checklist | FE-002 | L |
| FE-014 | Crear sistema de formularios Signal Forms | Schemas, errores de servidor, focus first invalid y submit único | Unit/harness | FE-001 | M |
| FE-015 | Crear adaptador Reactive Forms | Uso documentado solo para dinámicos/CVA complejos | Tests de interoperabilidad | FE-014 | S |
| FE-016 | Crear validadores compartidos | Fecha, contacto, IDs, límites y mensajes en español | Unit tests | FE-014 | M |
| FE-017 | Crear cliente HTTP tipado | Generado, credentials, timeouts y cancelación sin logging sensible | Contract tests | TOOL-022 | M |
| FE-018 | Implementar interceptor CSRF | Obtención/rotación y credenciales same-origin configurables | E2E session | FE-017 | S |
| FE-019 | Implementar correlación | Request ID opaco, sin PHI y visible para soporte | API/E2E | FE-017 | S |
| FE-020 | Implementar manejo Problem Details | Mapeo global y de campos sin mostrar internals | Unit/E2E negativos | FE-017 | M |
| FE-021 | Implementar estado de sesión en memoria | Usuario/capacidades/contexto sin tokens en storage | Storage inspection | FE-017 | M |
| FE-022 | Implementar guards UX | Auth/capability/context guards sin confundirlos con seguridad | Router tests | FE-021 | M |
| FE-023 | Implementar renovación/caducidad | Warning accesible, reauth y limpieza de memoria/DOM al salir | Fake timers + E2E | FE-021 | M |
| FE-024 | Implementar cambio de contexto | Centro/rol/paciente limpia cachés y cancela requests | Unit/E2E | FE-021 | M |
| FE-025 | Implementar caché remota en memoria | TTL corto, claves por contexto y invalidación segura | Unit tests | FE-024 | M |
| FE-026 | Implementar offline/degradación | Solo datos no sensibles permitidos; ninguna persistencia clínica | Offline E2E/storage scan | FE-025 | M |
| FE-027 | Implementar i18n | Español completo, pluralización, fechas/zonas e infraestructura adicional | Build locales + pseudo-locale | FE-001 | M |
| FE-028 | Implementar preferencias a11y | Densidad, contraste, tamaño, reduce motion y persistencia no sensible | E2E preferencias | FE-003 | M |
| FE-029 | Integrar GSAP con lifecycle Angular | Context/DestroyRef, registro único y kill al navegar | Leak/navigation tests | DES-009, FE-001 | M |
| FE-030 | Implementar timelines/ScrollTrigger | Solo patrones aprobados, transforms/opacity y matchMedia | Performance/reduced motion | FE-029, DES-010..012 | M |
| FE-031 | Crear harnesses propios | Componentes compartidos interactivos tienen API de test estable | Unit harness suite | FE-009..016 | M |
| FE-032 | Definir route-level lazy loading | Dominios separados y presupuestos por app | Bundle analysis | FE-005..008 | M |
| FE-033 | Implementar preloading contextual | Sin descargar módulos/PHI innecesarios por rol | Network E2E | FE-032, FE-021 | S |
| FE-034 | Configurar CSP/Trusted Types frontend | Sin inline scripts/eval y sinks controlados | Browser CSP report tests | FE-001 | M |
| FE-035 | Prevenir fuga en título/URL | Ninguna PHI/PII sensible en rutas, history o título | E2E URL/DOM scan | FE-011 | S |
| FE-036 | Implementar telemetría segura | Allowlist de eventos, redacción y consentimiento cuando aplique | Network payload tests | FE-011 | M |
| FE-037 | Configurar service worker opcional seguro | Solo assets públicos; no cache API clínica | Cache inspection | FE-026 | M |
| FE-038 | Configurar budgets web | JS/CSS/fonts/images y CWV objetivos bloquean regresiones | Build budgets/Lighthouse | FE-032 | S |
| FE-039 | Crear suite visual | Component screenshots por tema/viewport/state | Playwright snapshots | FE-008, DES-133 | M |
| FE-040 | Crear suite a11y frontend | Axe por ruta + checklist manual versionado | `npm run test:a11y` | FE-008 | M |
