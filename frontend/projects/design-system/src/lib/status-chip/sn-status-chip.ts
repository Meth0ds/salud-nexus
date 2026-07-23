import { Component, computed, input } from '@angular/core';

import { SnIcon } from '../icon/sn-icon';

export type SnStatusTone = 'danger' | 'info' | 'neutral' | 'pending' | 'success' | 'warning';

const DEFAULT_ICONS: Readonly<Record<SnStatusTone, string>> = {
  danger: 'error',
  info: 'info',
  neutral: 'radio_button_checked',
  pending: 'schedule',
  success: 'check_circle',
  warning: 'warning',
};

@Component({
  selector: 'sn-status-chip',
  imports: [SnIcon],
  template: `
    <span class="sn-status-chip" [attr.data-tone]="tone()">
      <sn-icon [name]="resolvedIcon()" [size]="16" aria-hidden="true" />
      <span class="sn-status-chip__label">{{ label() }}</span>
    </span>
  `,
  styleUrl: './sn-status-chip.scss',
})
export class SnStatusChip {
  readonly label = input.required<string>();
  readonly tone = input<SnStatusTone>('neutral');
  readonly icon = input('');

  protected readonly resolvedIcon = computed(
    () => this.icon().trim() || DEFAULT_ICONS[this.tone()],
  );
}
