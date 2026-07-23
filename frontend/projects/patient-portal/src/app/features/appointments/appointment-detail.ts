import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';
import { SnIcon, SnStatusChip } from 'design-system';

import type { Appointment, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { AppointmentSelectionStore, DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

interface AppointmentDetailData {
  readonly appointment: Appointment | undefined;
}

@Component({
  selector: 'sn-patient-appointment-detail',
  imports: [DemoStatePanel, MatButtonModule, RouterLink, SnIcon, SnStatusChip],
  templateUrl: './appointment-detail.html',
  styleUrl: './appointment-detail.scss',
})
export class AppointmentDetail {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly selection = inject(AppointmentSelectionStore);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly detail = signal<AppointmentDetailData | undefined>(undefined);

  protected readonly viewState = computed<ViewState<AppointmentDetailData>>(() =>
    resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.detail(),
      (detail) => detail.appointment === undefined,
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
    this.detail.set(undefined);
    const selectedId = this.selection.selectedId();
    this.detail.set({
      appointment: selectedId ? await this.repository.getAppointment(selectedId) : undefined,
    });
  }
}
