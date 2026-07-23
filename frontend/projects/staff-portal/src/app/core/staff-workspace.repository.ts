import { InjectionToken } from '@angular/core';

export type StaffRole = 'administrator' | 'clinician' | 'reception';
export type LoadStatus = 'empty' | 'error' | 'idle' | 'loading' | 'ready';

export interface WorkspaceContext {
  readonly id: string;
  readonly service: string;
  readonly role: StaffRole;
}

export interface Appointment {
  readonly id: string;
  readonly time: string;
  readonly durationMinutes: number;
  readonly patientDisplayName: string;
  readonly initials: string;
  readonly visitType: string;
  readonly status: 'finished' | 'in-progress' | 'scheduled' | 'waiting';
  readonly priority: 'attention' | 'routine';
  readonly room: string;
}

export interface QueueEntry {
  readonly id: string;
  readonly position: number;
  readonly displayName: string;
  readonly maskedDocument: string;
  readonly arrivalTime: string;
  readonly waitMinutes: number;
  readonly status: 'called' | 'checked-in' | 'waiting';
}

export interface SecurityMetric {
  readonly id: string;
  readonly label: string;
  readonly value: string;
  readonly trend: string;
  readonly tone: 'attention' | 'neutral' | 'positive';
}

export interface SecurityAlert {
  readonly id: string;
  readonly title: string;
  readonly detail: string;
  readonly timestamp: string;
  readonly severity: 'high' | 'medium';
}

export interface SecurityOverview {
  readonly metrics: readonly SecurityMetric[];
  readonly alerts: readonly SecurityAlert[];
}

export interface Loadable<T> {
  readonly status: LoadStatus;
  readonly data: T;
  readonly message: string;
}

export interface StaffWorkspaceRepository {
  getSchedule(context: WorkspaceContext): Promise<readonly Appointment[]>;
  getReceptionQueue(context: WorkspaceContext): Promise<readonly QueueEntry[]>;
  getSecurityOverview(context: WorkspaceContext): Promise<SecurityOverview>;
  checkIn(entryId: string): Promise<QueueEntry>;
}

export const STAFF_WORKSPACE_REPOSITORY = new InjectionToken<StaffWorkspaceRepository>(
  'STAFF_WORKSPACE_REPOSITORY',
);
