import { Component, input } from '@angular/core';

import { SnIcon } from '../icon/sn-icon';
import { createComponentId } from '../internal/component-id';

@Component({
  selector: 'sn-empty-state',
  imports: [SnIcon],
  template: `
    <section
      class="sn-empty-state"
      [attr.aria-describedby]="descriptionId"
      [attr.aria-labelledby]="headingId"
    >
      <div class="sn-empty-state__icon" aria-hidden="true">
        <sn-icon [name]="icon()" [size]="32" />
      </div>
      <h2 [id]="headingId">{{ heading() }}</h2>
      <p [id]="descriptionId">{{ description() }}</p>
      <div class="sn-empty-state__actions">
        <ng-content select="[empty-actions]" />
      </div>
    </section>
  `,
  styleUrl: './sn-empty-state.scss',
})
export class SnEmptyState {
  readonly heading = input.required<string>();
  readonly description = input.required<string>();
  readonly icon = input('inbox');

  protected readonly descriptionId = createComponentId('empty-description');
  protected readonly headingId = createComponentId('empty-heading');
}
