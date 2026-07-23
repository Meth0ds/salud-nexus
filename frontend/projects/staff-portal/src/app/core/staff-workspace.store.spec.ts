import { TestBed } from '@angular/core/testing';

import {
  Appointment,
  QueueEntry,
  SecurityOverview,
  STAFF_WORKSPACE_REPOSITORY,
  StaffWorkspaceRepository,
  WorkspaceContext,
} from './staff-workspace.repository';
import { StaffWorkspaceStore } from './staff-workspace.store';

const CONTEXT: WorkspaceContext = {
  id: 'ctx-clinical-main',
  service: 'Medicina interna',
  role: 'clinician',
};

const APPOINTMENT: Appointment = {
  id: 'apt-synthetic-01',
  time: '09:20',
  durationMinutes: 25,
  patientDisplayName: 'Elena M. (demo)',
  initials: 'EM',
  visitType: 'Seguimiento',
  status: 'waiting',
  priority: 'routine',
  room: 'Consulta 4',
};

const QUEUE_ENTRY: QueueEntry = {
  id: 'queue-synthetic-01',
  position: 1,
  displayName: 'Bruno R. (demo)',
  maskedDocument: '*** 4821',
  arrivalTime: '08:42',
  waitMinutes: 8,
  status: 'waiting',
};

const SECURITY_OVERVIEW: SecurityOverview = {
  metrics: [
    { id: 'metric-1', label: 'Sesiones activas', value: '128', trend: 'Estable', tone: 'neutral' },
  ],
  alerts: [],
};

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}

describe('StaffWorkspaceStore', () => {
  it('moves the schedule from loading to empty without inventing records', async () => {
    const schedule = deferred<readonly Appointment[]>();
    const repository: StaffWorkspaceRepository = {
      getSchedule: () => schedule.promise,
      getReceptionQueue: async () => [QUEUE_ENTRY],
      getSecurityOverview: async () => SECURITY_OVERVIEW,
      checkIn: async () => ({ ...QUEUE_ENTRY, status: 'checked-in' }),
    };
    TestBed.configureTestingModule({
      providers: [
        StaffWorkspaceStore,
        { provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository },
      ],
    });
    const store = TestBed.inject(StaffWorkspaceStore);

    const loading = store.loadSchedule(CONTEXT);

    expect(store.schedule().status).toBe('loading');
    schedule.resolve([]);
    await loading;
    expect(store.schedule().status).toBe('empty');
    expect(store.schedule().data).toEqual([]);
  });

  it('exposes a safe recoverable state when schedule loading fails', async () => {
    const repository: StaffWorkspaceRepository = {
      getSchedule: async () => Promise.reject(new Error('synthetic record leaked')),
      getReceptionQueue: async () => [QUEUE_ENTRY],
      getSecurityOverview: async () => SECURITY_OVERVIEW,
      checkIn: async () => ({ ...QUEUE_ENTRY, status: 'checked-in' }),
    };
    TestBed.configureTestingModule({
      providers: [
        StaffWorkspaceStore,
        { provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository },
      ],
    });
    const store = TestBed.inject(StaffWorkspaceStore);

    await store.loadSchedule(CONTEXT);

    expect(store.schedule().status).toBe('error');
    expect(store.schedule().message).toBe(
      'No se pudo cargar la agenda. Reintenta con una conexión segura.',
    );
    expect(store.schedule().message).not.toContain('synthetic record leaked');
  });

  it('replaces a reception entry with the confirmed check-in state', async () => {
    const repository: StaffWorkspaceRepository = {
      getSchedule: async () => [APPOINTMENT],
      getReceptionQueue: async () => [QUEUE_ENTRY],
      getSecurityOverview: async () => SECURITY_OVERVIEW,
      checkIn: async (entryId) => ({
        ...QUEUE_ENTRY,
        id: entryId,
        status: 'checked-in',
      }),
    };
    TestBed.configureTestingModule({
      providers: [
        StaffWorkspaceStore,
        { provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository },
      ],
    });
    const store = TestBed.inject(StaffWorkspaceStore);

    await store.loadReceptionQueue(CONTEXT);
    await store.checkIn(QUEUE_ENTRY.id);

    expect(store.receptionQueue().data[0]?.status).toBe('checked-in');
  });
});
