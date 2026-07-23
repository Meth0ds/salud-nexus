import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';
import { SnIcon, SnStatusChip } from 'design-system';

import type { DashboardSummary, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

@Component({
  selector: 'sn-patient-home',
  imports: [DemoStatePanel, MatButtonModule, RouterLink, SnIcon, SnStatusChip],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home {
  private readonly repository = inject(PATIENT_REPOSITORY);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly summary = signal<DashboardSummary | undefined>(undefined);

  protected readonly viewState = computed<ViewState<DashboardSummary>>(() =>
    resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.summary(),
      (summary) =>
        summary.nextAppointment === undefined &&
        summary.medication.length === 0 &&
        summary.recentDocuments.length === 0,
    ),
  );

  constructor() {
    void this.load();
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    void this.load();
  }

  private async load(): Promise<void> {
    this.summary.set(undefined);
    this.summary.set(await this.repository.getDashboardSummary());
  }
}
