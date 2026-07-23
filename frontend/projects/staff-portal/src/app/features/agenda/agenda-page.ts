import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { Appointment } from '../../core/staff-workspace.repository';
import { StaffWorkspaceStore } from '../../core/staff-workspace.store';

@Component({
  selector: 'sn-staff-agenda-page',
  imports: [MatButtonModule, MatIconModule, MatProgressSpinnerModule],
  templateUrl: './agenda-page.html',
  styleUrl: './agenda-page.scss',
})
export class AgendaPage {
  protected readonly workspace = inject(StaffWorkspaceStore);
  protected readonly selectedAppointmentId = signal('');
  protected readonly selectedAppointment = computed(() =>
    this.workspace
      .schedule()
      .data.find((appointment) => appointment.id === this.selectedAppointmentId()),
  );
  protected readonly waitingCount = computed(
    () => this.workspace.schedule().data.filter((item) => item.status === 'waiting').length,
  );
  protected readonly completedCount = computed(
    () => this.workspace.schedule().data.filter((item) => item.status === 'finished').length,
  );

  constructor() {
    void this.refresh();
  }

  protected refresh(): Promise<void> {
    this.selectedAppointmentId.set('');
    return this.workspace.loadSchedule();
  }

  protected selectAppointment(appointment: Appointment): void {
    this.selectedAppointmentId.set(appointment.id);
  }

  protected closeDetail(): void {
    this.selectedAppointmentId.set('');
  }

  protected statusLabel(status: Appointment['status']): string {
    const labels: Readonly<Record<Appointment['status'], string>> = {
      finished: 'Finalizada',
      'in-progress': 'En consulta',
      scheduled: 'Programada',
      waiting: 'En espera',
    };
    return labels[status];
  }
}
