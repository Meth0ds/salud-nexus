import { Component, computed, input } from '@angular/core';

import { createComponentId } from '../internal/component-id';
import { SnIcon } from '../icon/sn-icon';
import type { SnStatusTone } from '../status-chip/sn-status-chip';

export type SnTrendDirection = 'down' | 'neutral' | 'up';

const TREND_ICONS: Readonly<Record<SnTrendDirection, string>> = {
  down: 'trending_down',
  neutral: 'trending_flat',
  up: 'trending_up',
};

@Component({
  selector: 'sn-metric-card',
  imports: [SnIcon],
  template: `
    <section class="sn-metric-card" [attr.aria-labelledby]="labelId" [attr.data-tone]="tone()">
      <p class="sn-metric-card__label" [id]="labelId">{{ label() }}</p>
      <p class="sn-metric-card__value">{{ value() }}</p>

      @if (trendLabel()) {
        <p class="sn-metric-card__trend" [attr.data-direction]="trendDirection()">
          <sn-icon [name]="trendIcon()" [size]="18" aria-hidden="true" />
          <span>{{ trendLabel() }}</span>
        </p>
      }

      @if (supportingText()) {
        <p class="sn-metric-card__supporting">{{ supportingText() }}</p>
      }
    </section>
  `,
  styleUrl: './sn-metric-card.scss',
})
export class SnMetricCard {
  readonly label = input.required<string>();
  readonly value = input.required<number | string>();
  readonly supportingText = input('');
  readonly tone = input<SnStatusTone>('neutral');
  readonly trendDirection = input<SnTrendDirection>('neutral');
  readonly trendLabel = input('');

  protected readonly labelId = createComponentId('metric-label');
  protected readonly trendIcon = computed(() => TREND_ICONS[this.trendDirection()]);
}
