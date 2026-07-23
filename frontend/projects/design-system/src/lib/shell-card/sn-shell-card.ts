import { Component, input } from '@angular/core';

import { createComponentId } from '../internal/component-id';

@Component({
  selector: 'sn-shell-card',
  template: `
    <section class="sn-shell-card" [attr.aria-labelledby]="headingId">
      <header class="sn-shell-card__header">
        <div class="sn-shell-card__titles">
          @if (eyebrow()) {
            <p class="sn-shell-card__eyebrow">{{ eyebrow() }}</p>
          }
          <h2 [id]="headingId">{{ heading() }}</h2>
          @if (supportingText()) {
            <p class="sn-shell-card__supporting">{{ supportingText() }}</p>
          }
        </div>
        <div class="sn-shell-card__actions">
          <ng-content select="[shell-actions]" />
        </div>
      </header>
      <div class="sn-shell-card__content">
        <ng-content />
      </div>
    </section>
  `,
  styleUrl: './sn-shell-card.scss',
})
export class SnShellCard {
  readonly heading = input.required<string>();
  readonly eyebrow = input('');
  readonly supportingText = input('');

  protected readonly headingId = createComponentId('card-heading');
}
