import { Injectable } from '@angular/core';

import {
  Appointment,
  QueueEntry,
  SecurityOverview,
  StaffWorkspaceRepository,
} from './staff-workspace.repository';

const SCHEDULE: readonly Appointment[] = [
  {
    id: 'apt-synthetic-01',
    time: '08:30',
    durationMinutes: 30,
    patientDisplayName: 'Nora V. (demo)',
    initials: 'NV',
    visitType: 'Primera consulta',
    status: 'finished',
    priority: 'routine',
    room: 'Consulta 4',
  },
  {
    id: 'apt-synthetic-02',
    time: '09:20',
    durationMinutes: 25,
    patientDisplayName: 'Elena M. (demo)',
    initials: 'EM',
    visitType: 'Seguimiento',
    status: 'in-progress',
    priority: 'attention',
    room: 'Consulta 4',
  },
  {
    id: 'apt-synthetic-03',
    time: '10:05',
    durationMinutes: 20,
    patientDisplayName: 'Hugo S. (demo)',
    initials: 'HS',
    visitType: 'Revisión breve',
    status: 'waiting',
    priority: 'routine',
    room: 'Consulta 4',
  },
  {
    id: 'apt-synthetic-04',
    time: '11:10',
    durationMinutes: 40,
    patientDisplayName: 'Lara P. (demo)',
    initials: 'LP',
    visitType: 'Interconsulta',
    status: 'scheduled',
    priority: 'routine',
    room: 'Consulta 7',
  },
];

const INITIAL_QUEUE: readonly QueueEntry[] = [
  {
    id: 'queue-synthetic-01',
    position: 1,
    displayName: 'Bruno R. (demo)',
    maskedDocument: '*** 4821',
    arrivalTime: '08:42',
    waitMinutes: 8,
    status: 'waiting',
  },
  {
    id: 'queue-synthetic-02',
    position: 2,
    displayName: 'Ariadna C. (demo)',
    maskedDocument: '*** 9350',
    arrivalTime: '08:47',
    waitMinutes: 3,
    status: 'called',
  },
  {
    id: 'queue-synthetic-03',
    position: 3,
    displayName: 'Mateo L. (demo)',
    maskedDocument: '*** 1067',
    arrivalTime: '08:49',
    waitMinutes: 1,
    status: 'waiting',
  },
];

const SECURITY_OVERVIEW: SecurityOverview = {
  metrics: [
    {
      id: 'active-sessions',
      label: 'Sesiones activas',
      value: '128',
      trend: 'Dentro del patrón',
      tone: 'neutral',
    },
    {
      id: 'strong-auth',
      label: 'MFA del personal',
      value: '99,2 %',
      trend: '+0,4 % esta semana',
      tone: 'positive',
    },
    {
      id: 'open-reviews',
      label: 'Revisiones pendientes',
      value: '3',
      trend: '1 vence hoy',
      tone: 'attention',
    },
  ],
  alerts: [
    {
      id: 'alert-synthetic-01',
      title: 'Patrón de acceso para revisar',
      detail: 'Cinco intentos consecutivos desde una estación autorizada.',
      timestamp: 'Hoy · 08:16',
      severity: 'medium',
    },
    {
      id: 'alert-synthetic-02',
      title: 'Permiso temporal próximo a caducar',
      detail: 'Un acceso delegado requiere revisión antes de las 14:00.',
      timestamp: 'Hoy · 07:54',
      severity: 'high',
    },
  ],
};

@Injectable()
export class InMemoryStaffWorkspaceRepository implements StaffWorkspaceRepository {
  private queue = [...INITIAL_QUEUE];

  async getSchedule(): Promise<readonly Appointment[]> {
    await Promise.resolve();
    return SCHEDULE.map((appointment) => ({ ...appointment }));
  }

  async getReceptionQueue(): Promise<readonly QueueEntry[]> {
    await Promise.resolve();
    return this.queue.map((entry) => ({ ...entry }));
  }

  async getSecurityOverview(): Promise<SecurityOverview> {
    await Promise.resolve();
    return {
      metrics: SECURITY_OVERVIEW.metrics.map((metric) => ({ ...metric })),
      alerts: SECURITY_OVERVIEW.alerts.map((alert) => ({ ...alert })),
    };
  }

  async checkIn(entryId: string): Promise<QueueEntry> {
    await Promise.resolve();
    const current = this.queue.find((entry) => entry.id === entryId);
    if (!current) {
      throw new Error('Queue entry not found');
    }

    const updated: QueueEntry = { ...current, status: 'checked-in' };
    this.queue = this.queue.map((entry) => (entry.id === entryId ? updated : entry));
    return { ...updated };
  }
}
