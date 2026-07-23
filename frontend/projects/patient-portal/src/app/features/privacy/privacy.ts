import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { SnIcon, SnStatusChip } from 'design-system';

import type { AccessEvent, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

@Component({
  selector: 'sn-patient-privacy',
  imports: [DemoStatePanel, MatButtonModule, SnIcon, SnStatusChip],
  templateUrl: './privacy.html',
  styleUrl: './privacy.scss',
})
export class Privacy {
  private readonly repository = inject(PATIENT_REPOSITORY);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly accesses = signal<readonly AccessEvent[] | undefined>(undefined);
  protected readonly otherSessions = signal(2);
  protected readonly announcement = signal('');

  protected readonly viewState = computed<ViewState<readonly AccessEvent[]>>(() =>
    resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.accesses(),
      (accesses) => accesses.length === 0,
    ),
  );

  constructor() {
    void this.load();
  }

  protected closeOtherSessions(): void {
    this.otherSessions.set(0);
    this.announcement.set(
      'Sesiones sintéticas cerradas. En producción esta acción exigiría autorización del servidor.',
    );
  }

  protected requestRightsHelp(): void {
    this.announcement.set(
      'Solicitud simulada. En producción se abrirá un flujo seguro de derechos de protección de datos.',
    );
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    void this.load();
  }

  private async load(): Promise<void> {
    this.accesses.set(undefined);
    this.accesses.set(await this.repository.listAccessEvents());
  }
}
