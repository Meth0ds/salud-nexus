import { Component, input } from '@angular/core';
import { SnIcon, SnStatusChip } from 'design-system';

export type AppointmentChangeMockupMode = 'cancel' | 'reschedule';

@Component({
  selector: 'sn-design-appointment-change-mockup',
  imports: [SnIcon, SnStatusChip],
  templateUrl: './appointment-change-mockup.html',
  styleUrl: './appointment-change-mockup.scss',
})
export class AppointmentChangeMockup {
  readonly mode = input.required<AppointmentChangeMockupMode>();
}
