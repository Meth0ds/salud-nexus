# Salud Nexus Design System

Base visual accesible para los portales Angular 22 de Salud Nexus. Incluye el tema Material 3
Atlantic clinical, tokens semánticos, tipografías locales y cinco componentes de presentación
standalone.

## Activar el tema

En una aplicación del workspace, importar una sola vez desde su `styles.scss` global:

```scss
@use '../../design-system/src/styles/theme';
```

En una instalación del paquete compilado:

```scss
@use 'design-system/theme';
```

El entry point carga Manrope, Source Sans 3 y Material Symbols Rounded desde dependencias locales;
no solicita fuentes a servicios externos. También emite el tema Material 3, los tokens `--sn-*`,
el foco visible y las adaptaciones para `prefers-reduced-motion` y colores forzados.

Para componer un tema alternativo sin emitir estilos globales, usar los mixins de
`design-system/styles`:

```scss
@use 'design-system/styles' as sn;

.my-scope {
  @include sn.product-tokens();
}
```

## Componentes

```ts
import { SnEmptyState, SnIcon, SnMetricCard, SnShellCard, SnStatusChip } from 'design-system';
```

```html
<sn-status-chip label="Confirmada" tone="success" />

<sn-metric-card
  label="Citas confirmadas"
  [value]="24"
  trendDirection="up"
  trendLabel="8 % más que ayer"
/>

<sn-shell-card heading="Próxima cita" eyebrow="Hoy">
  <button shell-actions type="button">Cambiar</button>
  <p>09:30 con Medicina de familia</p>
</sn-shell-card>
```

`SnIcon` es decorativo por defecto. Solo se debe proporcionar `accessibleLabel` cuando el icono
transmite información que no está repetida en texto visible:

```html
<sn-icon name="verified_user" accessibleLabel="Protección de datos activa" />
```

Los chips siempre mantienen etiqueta visible e icono para no depender exclusivamente del color.
Las tarjetas no son interactivas por defecto; los consumidores deben usar enlaces o botones nativos
para cualquier acción.

## Verificación

```bash
ng test design-system --watch=false
ng build design-system
```

Las pruebas usan Vitest en modo zoneless y esperan la estabilización de Angular antes de comprobar
el DOM.
