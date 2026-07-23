import { Component, computed, input, output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { SnIcon } from 'design-system';

type NonReadyState = 'empty' | 'error' | 'loading' | 'restricted';

@Component({
  selector: 'sn-patient-demo-state-panel',
  imports: [MatButtonModule, MatProgressSpinnerModule, SnIcon],
  templateUrl: './demo-state-panel.html',
  styleUrl: './demo-state-panel.scss',
})
export class DemoStatePanel {
  readonly kind = input.required<NonReadyState>();
  readonly heading = input.required<string>();
  readonly description = input.required<string>();
  readonly correlationId = input('');
  readonly action = output<void>();

  protected readonly icon = computed(() => {
    switch (this.kind()) {
      case 'empty':
        return 'inbox';
      case 'error':
        return 'error';
      case 'loading':
        return 'progress_activity';
      case 'restricted':
        return 'lock';
    }
  });

  protected readonly actionLabel = computed(() =>
    this.kind() === 'error' ? 'Reintentar' : 'Mostrar datos de demo',
  );
}
