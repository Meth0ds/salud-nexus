import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

interface CareMilestone {
  readonly date: string;
  readonly detail: string;
  readonly icon: string;
  readonly id: string;
  readonly label: string;
  readonly state: 'complete' | 'current' | 'upcoming';
}

@Component({
  selector: 'sn-design-patient-care-path',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './patient-care-path.html',
  styleUrl: './patient-care-path.scss',
})
export class PatientCarePath {
  protected readonly milestones: readonly CareMilestone[] = [
    {
      id: 'request',
      icon: 'task_alt',
      label: 'Solicitud revisada',
      date: 'Hoy · 09:10',
      detail: 'El centro confirmó tu petición.',
      state: 'complete',
    },
    {
      id: 'prepare',
      icon: 'fact_check',
      label: 'Preparación',
      date: 'Antes del jueves',
      detail: 'Revisa las indicaciones del centro.',
      state: 'current',
    },
    {
      id: 'visit',
      icon: 'clinical_notes',
      label: 'Próxima cita',
      date: 'Jue 23 · 10:30',
      detail: 'Centro Atlántico · consulta 2.14',
      state: 'upcoming',
    },
    {
      id: 'follow-up',
      icon: 'event_available',
      label: 'Seguimiento',
      date: 'Después de la cita',
      detail: 'Recibirás aquí los siguientes pasos.',
      state: 'upcoming',
    },
  ];
}
