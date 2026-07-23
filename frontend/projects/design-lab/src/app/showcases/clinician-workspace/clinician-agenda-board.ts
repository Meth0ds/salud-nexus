import { computed, Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

interface DemoAppointment {
  readonly end: string;
  readonly id: string;
  readonly patientCode: string;
  readonly purpose: string;
  readonly room: string;
  readonly start: string;
  readonly status: 'En espera' | 'Confirmada' | 'Teleconsulta';
  readonly tone: 'amber' | 'blue' | 'teal';
}

@Component({
  selector: 'sn-design-clinician-agenda-board',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './clinician-agenda-board.html',
  styleUrl: './clinician-agenda-board.scss',
})
export class ClinicianAgendaBoard {
  protected readonly appointments: readonly DemoAppointment[] = [
    {
      id: 'demo-1042',
      start: '08:30',
      end: '09:00',
      patientCode: 'Paciente DEMO-1042',
      purpose: 'Seguimiento programado',
      room: 'Consulta 2.14',
      status: 'Confirmada',
      tone: 'teal',
    },
    {
      id: 'demo-2048',
      start: '09:15',
      end: '10:00',
      patientCode: 'Paciente DEMO-2048',
      purpose: 'Primera consulta',
      room: 'Consulta 2.14',
      status: 'En espera',
      tone: 'amber',
    },
    {
      id: 'demo-4096',
      start: '10:30',
      end: '11:00',
      patientCode: 'Paciente DEMO-4096',
      purpose: 'Revisión remota',
      room: 'Canal seguro 03',
      status: 'Teleconsulta',
      tone: 'blue',
    },
    {
      id: 'demo-8192',
      start: '12:15',
      end: '12:45',
      patientCode: 'Paciente DEMO-8192',
      purpose: 'Seguimiento programado',
      room: 'Consulta 2.14',
      status: 'Confirmada',
      tone: 'teal',
    },
  ];
  protected readonly selectedAppointmentId = signal('demo-2048');
  protected readonly contextExpanded = signal(true);
  protected readonly announcement = signal('');
  protected readonly selectedAppointment = computed(
    () =>
      this.appointments.find((appointment) => appointment.id === this.selectedAppointmentId()) ??
      this.appointments[0],
  );

  protected selectAppointment(appointment: DemoAppointment): void {
    this.selectedAppointmentId.set(appointment.id);
    this.contextExpanded.set(true);
    this.announcement.set(`${appointment.patientCode} seleccionado.`);
  }

  protected toggleContext(): void {
    this.contextExpanded.update((expanded) => !expanded);
    this.announcement.set(
      this.contextExpanded() ? 'Contexto asistencial abierto.' : 'Contexto asistencial cerrado.',
    );
  }
}
