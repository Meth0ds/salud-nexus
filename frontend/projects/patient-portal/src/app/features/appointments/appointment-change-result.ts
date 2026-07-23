import { Component, ElementRef, inject, input } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';
import { SnIcon } from 'design-system';

import type { Appointment } from '../../core/patient.models';

@Component({
  selector: 'sn-patient-appointment-change-result',
  imports: [MatButtonModule, RouterLink, SnIcon],
  templateUrl: './appointment-change-result.html',
  styleUrl: './appointment-change-result.scss',
})
export class AppointmentChangeResult {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly after = input.required<Appointment>();
  readonly before = input.required<Appointment>();
  readonly isDemo = input.required<boolean>();
  readonly mode = input.required<'cancel' | 'reschedule'>();

  focus(): void {
    this.host.nativeElement.querySelector<HTMLElement>('section')?.focus();
  }
}
