import { Component, computed, input } from '@angular/core';
import { SnIcon } from 'design-system';

import type { Appointment } from '../../core/patient.models';

@Component({
  selector: 'sn-patient-appointment-current-card',
  imports: [SnIcon],
  templateUrl: './appointment-current-card.html',
  styleUrl: './appointment-current-card.scss',
})
export class AppointmentCurrentCard {
  readonly appointment = input.required<Appointment>();
  readonly deadlineLabel = input.required<string>();

  protected readonly date = computed(() => dateTileParts(this.appointment().startsAt));
  protected readonly attendanceModeLabel = computed(() => {
    switch (this.appointment().attendanceMode) {
      case 'in-person':
        return 'Presencial';
      case 'phone':
        return 'Consulta telefónica';
      case 'video':
        return 'Videoconsulta';
    }
  });
}

function dateTileParts(value: string): { day: string; month: string; weekday: string } {
  const date = new Date(value);
  const part = (options: Intl.DateTimeFormatOptions): string =>
    new Intl.DateTimeFormat('es-ES', { ...options, timeZone: 'Europe/Madrid' }).format(date);
  return {
    day: part({ day: '2-digit' }),
    month: part({ month: 'short' }).replace('.', '').toLocaleUpperCase('es-ES'),
    weekday: part({ weekday: 'short' }).replace('.', '').toLocaleUpperCase('es-ES'),
  };
}
