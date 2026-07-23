# Especificación de producto: Salud Nexus

## 1. Objetivo

Salud Nexus es una plataforma modular de gestión sanitaria para un único centro, sus pacientes, profesionales, recepción, administración, seguridad y cumplimiento. Debe cubrir de extremo a extremo identidad, pacientes, estructura del centro, agendas, citas, sala de espera, medicación informativa y conciliación, documentos, notificaciones, privacidad, auditoría, informes e integraciones, sin convertirse en una historia clínica integral ni realizar diagnóstico o prescripción autónoma.

La fuente funcional inicial es [el plan maestro](source/plan-maestro.md). Esta especificación puede mejorar sus decisiones técnicas cuando exista una alternativa estable y verificable, pero no puede reducir silenciosamente seguridad, privacidad, trazabilidad, accesibilidad ni alcance funcional.

## 2. Audiencias y superficies

- **Paciente y representante:** experiencia móvil prioritaria, lenguaje sencillo, flujos guiados y acceso transparente a sus datos.
- **Profesional sanitario:** agenda de alta densidad, contexto asistencial mínimo, medicación y documentos según relación, finalidad y ámbito.
- **Recepción y administración:** identidad administrativa, pacientes, citas, recursos, plantillas y reglas sin exposición clínica innecesaria.
- **Seguridad, auditoría y DPD:** accesos, permisos, break-glass, incidentes, solicitudes de derechos, retención y evidencias.
- **Integración técnica:** contratos versionados y adaptadores sin acceso interactivo general a datos clínicos.

## 3. Decisiones de producto

1. Nombre de trabajo: **Salud Nexus**.
2. Dos aplicaciones productivas Angular: `patient-portal` y `staff-portal`; una aplicación interna `design-lab` documentará todos los mockups, estados y componentes.
3. Backend Laravel como monolito modular y API REST `/api/v1` con OpenAPI 3.1.
4. PostgreSQL en entornos compartidos y producción; SQLite puede utilizarse en pruebas locales rápidas sin alterar semántica ni restricciones críticas.
5. Redis para sesiones, caché, rate limiting, colas, reservas temporales y bloqueos distribuidos.
6. Autenticación web mediante sesión de servidor y cookies `HttpOnly`, `Secure` y `SameSite`; nunca JWT persistente en almacenamiento web.
7. OIDC/WebAuthn/MFA detrás de contratos de proveedor. El entorno local dispondrá de simuladores seguros y datos sintéticos.
8. Autorización en backend mediante RBAC + ABAC + relación asistencial + finalidad + ámbito temporal + restricciones del paciente.
9. Angular 22 standalone, TypeScript estricto, Signals para estado y **Signal Forms estables** para formularios nuevos. Reactive Forms se reserva para formularios dinámicos complejos o interoperabilidad no cubierta con igual madurez.
10. Angular Material/CDK/Aria como base; el sistema visual no dependerá del DOM interno de Material ni de `::ng-deep`.
11. GSAP se limita a secuencias y ScrollTrigger con propósito. Las acciones clínicas frecuentes o iniciadas por teclado no tendrán animación bloqueante.
12. WCAG 2.2 AA es requisito de aceptación. `prefers-reduced-motion`, colores forzados, zoom y teclado son variantes de primera clase.
13. Los documentos sensibles se generan en backend, se almacenan fuera del webroot y se descargan con autorización renovada, caducidad y auditoría.
14. Auditoría append-only con encadenamiento criptográfico, cuenta de escritura separable y exportación verificable.
15. Datos y fixtures exclusivamente sintéticos.
16. Cada despliegue corresponde a un único centro sanitario. No existen selección, cambio ni comparación de centros; unidades, servicios y finalidades son los ámbitos operativos visibles.

## 4. Arquitectura del repositorio

```text
backend/                         Laravel 13 y API
  app/Modules/                   Dominios del monolito modular
  tests/                         Unitarias, feature, arquitectura e integración
frontend/                        Workspace Angular 22
  projects/patient-portal/       Portal de pacientes y representantes
  projects/staff-portal/         Profesionales, recepción, admin y seguridad
  projects/design-lab/           Catálogo visual y mockups de rutas/estados
  projects/design-system/        Tokens y componentes compartidos
  projects/api-client/           Cliente generado desde OpenAPI
  projects/auth/                 Sesión, identidad y autorización UX
  projects/shared/               Utilidades y contratos sin lógica de dominio
docs/                            Producto, arquitectura, seguridad y operación
infrastructure/                  Contenedores, proxy, observabilidad e IaC
tasks/                           Plan y backlog verificable
```

## 5. Estilo de código

### Angular

```ts
export const appointmentStatusLabel: Record<AppointmentStatus, string> = {
  booked: "Reservada",
  confirmed: "Confirmada",
  checkedIn: "Llegada registrada",
};

@Component({
  selector: "sn-appointment-summary",
  templateUrl: "./appointment-summary.html",
})
export class AppointmentSummary {
  readonly appointment = input.required<AppointmentView>();
  readonly canCancel = computed(() =>
    this.appointment().actions.includes("cancel"),
  );
}
```

- Sin `any`, sin suscripciones manuales evitables y sin lógica de autorización como control de seguridad.
- Componentes pequeños, standalone, imports específicos, contenido realista en español y estados explícitos.
- No almacenar respuestas clínicas, sesiones o tokens en `localStorage`/`sessionStorage`.

### Laravel

```php
final readonly class BookAppointment
{
    public function __construct(
        private AppointmentRepository $appointments,
        private AuditWriter $audit,
    ) {}

    public function handle(BookAppointmentCommand $command, ActorContext $actor): Appointment
    {
        // La transacción, autorización, idempotencia y auditoría son parte del caso de uso.
    }
}
```

- `declare(strict_types=1)`, clases finales por defecto, DTOs inmutables y casos de uso explícitos.
- Form Requests rechazan datos desconocidos en operaciones sensibles y las Policies llaman reglas de dominio contextual.
- Ningún controlador contiene reglas de negocio o integración externa.

## 6. Comandos previstos

```text
Frontend instalación:  npm ci
Frontend desarrollo:   npm run start:patient | npm run start:staff | npm run start:design
Frontend build:        npm run build
Frontend unitarias:    npm run test:unit
Frontend E2E:          npm run test:e2e
Frontend lint:         npm run lint
Frontend auditoría:    npm audit --audit-level=high

Backend instalación:   composer install --no-interaction
Backend desarrollo:    php artisan serve
Backend workers:       php artisan queue:work --tries=3
Backend pruebas:       php artisan test
Backend análisis:      composer analyse
Backend formato:       composer format:check
Backend auditoría:     composer audit --locked

Stack local:           docker compose up --build
Verificación global:   scripts/verify.ps1
```

Los scripts se implementarán y mantendrán como interfaz estable del repositorio.

## 7. Estrategia de pruebas

- **Dominio:** reglas puras, transiciones, permisos, retención, disponibilidad e integridad de auditoría.
- **Integración:** PostgreSQL, Redis, almacenamiento, outbox, colas y adaptadores simulados.
- **API:** autenticación, autorización horizontal/vertical, validación, idempotencia, concurrencia, paginación, errores y rate limits.
- **Angular:** Vitest/TestBed, harnesses Material/CDK, Signal Forms, rutas, foco, permisos y estados.
- **E2E:** Playwright para todos los recorridos críticos y variantes por rol.
- **Accesibilidad:** axe automatizado y matriz manual con teclado, NVDA, VoiceOver, zoom, reflow, contraste y movimiento reducido.
- **Seguridad:** SAST, SCA, secretos, DAST, fuzzing, subida de archivos, SSRF, CSRF, XSS, SQLi, enumeración y abuso lógico.
- **Rendimiento:** p95 de API, concurrencia de reservas, listados, generación PDF, colas y presupuestos web.

## 8. Límites operativos

### Siempre

- Validar toda entrada y autorizar cada recurso en backend.
- Auditar accesos y operaciones sensibles incluso cuando terminen denegados o fallen.
- Aplicar minimización de datos por rol, finalidad, unidad/servicio y relación.
- Ejecutar build, pruebas, análisis y auditorías antes de marcar una tarea como completada.
- Mantener OpenAPI, cliente y pruebas de contrato sincronizados.

### Requiere decisión organizativa o proveedor

- Identidad OIDC real, SMS, correo productivo, firma cualificada, antivirus, KMS, WORM, SIEM y sistemas FHIR concretos.
- Matrices legales de retención, bases jurídicas, textos de privacidad y normativa autonómica.
- Creación de roles de alto privilegio y políticas de break-glass.
- Datos reales, migración productiva, piloto clínico o despliegue público.

Para que el producto siga siendo ejecutable sin esos contratos, cada puerto externo tendrá un simulador local y pruebas de contrato. El simulador no equivale a homologación productiva.

### Nunca

- Secretos o datos reales en Git, fixtures, capturas, logs o telemetría.
- Autorización confiada al frontend, IDs secuenciales expuestos o tokens de sesión en almacenamiento web.
- Criptografía propia, edición destructiva de documentos emitidos o modificación/borrado de auditoría.
- Diagnóstico automatizado, prescripción autónoma o mensajería clínica sin gobernanza aprobada.

## 9. Criterios globales de éxito

- Todas las capacidades no excluidas del plan tienen ruta, API, autorización, auditoría, pruebas y documentación trazables.
- Todos los mockups y estados figuran en `design-lab` y coinciden con las aplicaciones productivas.
- No quedan TODOs técnicos ocultos en código; el backlog es la fuente única y cada excepción tiene propietario y evidencia.
- Cero fallos críticos/altos alcanzables en auditorías de dependencias, análisis estático, DAST y pentest antes de producción.
- Cobertura trazable de OWASP ASVS 5.0 nivel 3 para todos los requisitos aplicables; cualquier no aplicabilidad queda justificada y aprobada.
- No se reproduce una doble reserva bajo las pruebas de concurrencia definidas.
- Todos los recorridos críticos cumplen WCAG 2.2 AA y funcionan con movimiento reducido.
- Auditoría, backups y restauración superan pruebas de integridad y recuperación.
- Objetivos iniciales: lecturas p95 < 300 ms, escrituras p95 < 500 ms, reserva < 1 s y disponibilidad < 2 s en el perfil acordado.
- La salida real a producción permanece bloqueada hasta EIPD, contratos, revisión jurídica, pentest independiente, formación y aprobación operativa.

## 10. Exclusiones de producto

Se mantienen fuera del alcance base: diagnóstico o recomendación terapéutica autónoma, prescripción electrónica completa, historia clínica integral, imagen diagnóstica, IA clínica, chat clínico 24 h, videconsulta compleja, aplicaciones nativas independientes, analítica predictiva, investigación secundaria y despliegue multinacional. Requieren una especificación y evaluación regulatoria propias.

## 11. Supuestos revisables

- España y zona horaria Europe/Madrid como primera jurisdicción.
- Un único centro sanitario por despliegue, con una organización interna y una sola fila de centro permitida; el aislamiento organizativo se conserva como defensa de seguridad.
- Español como idioma inicial con infraestructura i18n preparada.
- El nombre, proveedores y textos jurídicos son de trabajo hasta validación humana.
