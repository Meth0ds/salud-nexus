import { Component, computed, input } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';
import { SnIcon } from 'design-system';

import type { Appointment } from '../../core/patient.models';

@Component({
  selector: 'sn-patient-appointment-change-eligibility',
  imports: [MatButtonModule, RouterLink, SnIcon],
  templateUrl: './appointment-change-eligibility.html',
  styleUrl: './appointment-change-eligibility.scss',
})
export class AppointmentChangeEligibility {
  readonly appointment = input.required<Appointment>();
  readonly deadlineLabel = input.required<string>();

  protected readonly statusLabel = computed(() => {
    switch (this.appointment().status) {
      case 'completed':
        return 'completada';
      case 'cancelled':
        return 'cancelada';
      case 'no-show':
        return 'no realizada';
      case 'scheduled':
        return 'fuera del periodo de cambios';
    }
  });
}
