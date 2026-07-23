import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';
import { SnIcon } from 'design-system';

@Component({
  selector: 'sn-patient-not-found',
  imports: [MatButtonModule, RouterLink, SnIcon],
  template: `
    <div class="not-found">
      <span aria-hidden="true"><sn-icon name="explore_off" [size]="38" /></span>
      <p class="sn-eyebrow">Ruta no disponible</p>
      <h1>No encontramos esta pantalla</h1>
      <p>Puede que el enlace haya cambiado o que no corresponda a tu perfil.</p>
      <a matButton="filled" routerLink="/inicio">Volver al inicio</a>
    </div>
  `,
  styles: `
    :host {
      display: grid;
      min-block-size: calc(100dvh - 4.5rem);
      padding: 1rem;
      place-items: center;
    }

    .not-found {
      display: grid;
      justify-items: center;
      max-inline-size: 34rem;
      padding: clamp(1.5rem, 5vw, 3rem);
      border: 1px solid var(--sn-color-border-subtle);
      border-radius: var(--sn-radius-xl);
      background: var(--sn-color-surface);
      box-shadow: var(--sn-elevation-1);
      text-align: center;
    }

    .not-found > span {
      display: grid;
      inline-size: 5rem;
      block-size: 5rem;
      margin-block-end: 1.25rem;
      border-radius: 50%;
      color: var(--sn-color-primary);
      background: var(--sn-color-surface-emphasis);
      place-items: center;
    }

    h1 {
      margin: 0;
      color: var(--sn-color-text-strong);
      font: 730 var(--sn-type-heading-md) / 1.15 var(--sn-font-display);
    }

    .not-found > p:not(.sn-eyebrow) {
      color: var(--sn-color-text-muted);
    }

    a {
      margin-block-start: 0.75rem;
    }
  `,
})
export class NotFound {}
