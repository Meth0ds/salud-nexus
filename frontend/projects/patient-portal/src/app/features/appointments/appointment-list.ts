import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { Router, RouterLink } from '@angular/router';
import { SnIcon, SnStatusChip } from 'design-system';

import type { Appointment, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { AppointmentSelectionStore, DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

@Component({
  selector: 'sn-patient-appointment-list',
  imports: [DemoStatePanel, MatButtonModule, RouterLink, SnIcon, SnStatusChip],
  templateUrl: './appointment-list.html',
  styleUrl: './appointment-list.scss',
})
export class AppointmentList {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly router = inject(Router);
  private readonly selection = inject(AppointmentSelectionStore);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly appointments = signal<readonly Appointment[] | undefined>(undefined);

  protected readonly viewState = computed<ViewState<readonly Appointment[]>>(() =>
    resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.appointments(),
      (appointments) => appointments.length === 0,
    ),
  );
  protected readonly upcoming = computed(
    () => this.appointments()?.filter((appointment) => appointment.status === 'scheduled') ?? [],
  );
  protected readonly history = computed(
    () => this.appointments()?.filter((appointment) => appointment.status === 'completed') ?? [],
  );

  constructor() {
    void this.load();
  }

  protected openDetails(appointment: Appointment): void {
    this.selection.select(appointment.id);
    void this.router.navigateByUrl('/citas/detalle');
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    void this.load();
  }

  private async load(): Promise<void> {
    this.appointments.set(undefined);
    this.appointments.set(await this.repository.listAppointments());
  }
}
