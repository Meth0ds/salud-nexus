import { Component, computed, ElementRef, inject, input, output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { SnIcon } from 'design-system';

@Component({
  selector: 'sn-patient-appointment-change-feedback',
  imports: [MatButtonModule, SnIcon],
  templateUrl: './appointment-change-feedback.html',
  styleUrl: './appointment-change-feedback.scss',
})
export class AppointmentChangeFeedback {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly kind = input.required<'blocked' | 'conflict' | 'error' | 'offline'>();
  readonly message = input.required<string>();
  readonly requestId = input<string | undefined>();
  readonly title = input.required<string>();
  readonly recover = output<void>();

  protected readonly actionLabel = computed(() =>
    this.kind() === 'blocked' || this.kind() === 'conflict'
      ? 'Recargar cita'
      : 'Reintentar con seguridad',
  );

  focus(): void {
    this.host.nativeElement.querySelector<HTMLElement>('aside')?.focus();
  }
}
