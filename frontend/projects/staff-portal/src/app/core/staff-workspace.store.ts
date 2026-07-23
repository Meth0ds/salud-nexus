import { computed, inject, Injectable, signal } from '@angular/core';

import {
  Appointment,
  Loadable,
  QueueEntry,
  SecurityOverview,
  STAFF_WORKSPACE_REPOSITORY,
  WorkspaceContext,
} from './staff-workspace.repository';

const EMPTY_SECURITY: SecurityOverview = { metrics: [], alerts: [] };
export const STAFF_CENTER_NAME = 'Centro Atlántico';

export const WORKSPACE_CONTEXTS: readonly WorkspaceContext[] = [
  {
    id: 'ctx-clinical-main',
    service: 'Medicina interna',
    role: 'clinician',
  },
  {
    id: 'ctx-reception-main',
    service: 'Admisión principal',
    role: 'reception',
  },
  {
    id: 'ctx-admin-main',
    service: 'Administración y seguridad',
    role: 'administrator',
  },
];

@Injectable({ providedIn: 'root' })
export class StaffWorkspaceStore {
  private readonly repository = inject(STAFF_WORKSPACE_REPOSITORY);
  private readonly activeContextState = signal(WORKSPACE_CONTEXTS[0]);
  private readonly scheduleState = signal<Loadable<readonly Appointment[]>>({
    status: 'idle',
    data: [],
    message: '',
  });
  private readonly receptionQueueState = signal<Loadable<readonly QueueEntry[]>>({
    status: 'idle',
    data: [],
    message: '',
  });
  private readonly securityState = signal<Loadable<SecurityOverview>>({
    status: 'idle',
    data: EMPTY_SECURITY,
    message: '',
  });
  private readonly pendingCheckInsState = signal<ReadonlySet<string>>(new Set());

  readonly contexts = WORKSPACE_CONTEXTS;
  readonly activeContext = this.activeContextState.asReadonly();
  readonly activeRoleLabel = computed(() => {
    const role = this.activeContext().role;
    return role === 'clinician' ? 'Clínica' : role === 'reception' ? 'Recepción' : 'Administración';
  });
  readonly schedule = this.scheduleState.asReadonly();
  readonly receptionQueue = this.receptionQueueState.asReadonly();
  readonly security = this.securityState.asReadonly();
  readonly pendingCheckIns = this.pendingCheckInsState.asReadonly();

  setContext(contextId: string): WorkspaceContext {
    const next = WORKSPACE_CONTEXTS.find((context) => context.id === contextId);
    if (!next) {
      return this.activeContext();
    }

    this.activeContextState.set(next);
    this.clearSensitiveViews();
    return next;
  }

  async loadSchedule(context = this.activeContext()): Promise<void> {
    this.scheduleState.set({ status: 'loading', data: [], message: 'Cargando agenda segura…' });
    try {
      const data = await this.repository.getSchedule(context);
      this.scheduleState.set({
        status: data.length === 0 ? 'empty' : 'ready',
        data,
        message: data.length === 0 ? 'No hay citas para este turno.' : '',
      });
    } catch {
      this.scheduleState.set({
        status: 'error',
        data: [],
        message: 'No se pudo cargar la agenda. Reintenta con una conexión segura.',
      });
    }
  }

  async loadReceptionQueue(context = this.activeContext()): Promise<void> {
    this.receptionQueueState.set({ status: 'loading', data: [], message: 'Actualizando cola…' });
    try {
      const data = await this.repository.getReceptionQueue(context);
      this.receptionQueueState.set({
        status: data.length === 0 ? 'empty' : 'ready',
        data,
        message: data.length === 0 ? 'No hay personas esperando.' : '',
      });
    } catch {
      this.receptionQueueState.set({
        status: 'error',
        data: [],
        message: 'No se pudo actualizar la cola. Comprueba la conexión e inténtalo de nuevo.',
      });
    }
  }

  async loadSecurityOverview(context = this.activeContext()): Promise<void> {
    this.securityState.set({
      status: 'loading',
      data: EMPTY_SECURITY,
      message: 'Verificando controles…',
    });
    try {
      const data = await this.repository.getSecurityOverview(context);
      const isEmpty = data.metrics.length === 0 && data.alerts.length === 0;
      this.securityState.set({
        status: isEmpty ? 'empty' : 'ready',
        data,
        message: isEmpty ? 'No hay actividad de seguridad para este alcance.' : '',
      });
    } catch {
      this.securityState.set({
        status: 'error',
        data: EMPTY_SECURITY,
        message: 'No se pudo verificar el estado. Usa el identificador de soporte SEC-DEMO.',
      });
    }
  }

  async checkIn(entryId: string): Promise<void> {
    if (this.pendingCheckIns().has(entryId)) {
      return;
    }

    this.pendingCheckInsState.update((pending) => new Set([...pending, entryId]));
    try {
      const updated = await this.repository.checkIn(entryId);
      this.receptionQueueState.update((state) => ({
        ...state,
        data: state.data.map((entry) => (entry.id === entryId ? updated : entry)),
      }));
    } finally {
      this.pendingCheckInsState.update((pending) => {
        const next = new Set(pending);
        next.delete(entryId);
        return next;
      });
    }
  }

  private clearSensitiveViews(): void {
    this.scheduleState.set({ status: 'idle', data: [], message: '' });
    this.receptionQueueState.set({ status: 'idle', data: [], message: '' });
    this.securityState.set({ status: 'idle', data: EMPTY_SECURITY, message: '' });
    this.pendingCheckInsState.set(new Set());
  }
}
