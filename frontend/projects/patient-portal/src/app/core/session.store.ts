import { computed, Service, signal } from '@angular/core';

import type { DemoScenario, PatientSession } from './patient.models';

@Service()
export class PatientSessionStore {
  private readonly sessionState = signal<PatientSession | undefined>(undefined);

  readonly session = this.sessionState.asReadonly();
  readonly isAuthenticated = computed(() => this.sessionState() !== undefined);

  open(session: PatientSession): void {
    this.sessionState.set(session);
  }

  close(): void {
    this.sessionState.set(undefined);
  }
}

@Service()
export class AppointmentSelectionStore {
  private readonly selectedIdState = signal<string | undefined>(undefined);

  readonly selectedId = this.selectedIdState.asReadonly();

  select(id: string): void {
    this.selectedIdState.set(id);
  }

  clear(): void {
    this.selectedIdState.set(undefined);
  }
}

@Service()
export class DemoScenarioStore {
  private readonly scenarioState = signal<DemoScenario>('ready');

  readonly scenario = this.scenarioState.asReadonly();

  set(scenario: DemoScenario): void {
    this.scenarioState.set(scenario);
  }
}
