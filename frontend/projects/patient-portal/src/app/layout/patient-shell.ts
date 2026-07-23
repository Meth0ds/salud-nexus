import { Component, ElementRef, inject, viewChild } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { SnIcon } from 'design-system';

import type { DemoScenario } from '../core/patient.models';
import { PATIENT_REPOSITORY } from '../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../core/patient-runtime';
import {
  AppointmentSelectionStore,
  DemoScenarioStore,
  PatientSessionStore,
} from '../core/session.store';

interface NavigationItem {
  readonly icon: string;
  readonly label: string;
  readonly path: string;
}

const DEMO_SCENARIOS: readonly DemoScenario[] = [
  'ready',
  'loading',
  'empty',
  'error',
  'restricted',
];

function isDemoScenario(value: string): value is DemoScenario {
  return DEMO_SCENARIOS.includes(value as DemoScenario);
}

@Component({
  selector: 'sn-patient-shell',
  imports: [MatButtonModule, MatMenuModule, RouterLink, RouterLinkActive, RouterOutlet, SnIcon],
  templateUrl: './patient-shell.html',
  styleUrl: './patient-shell.scss',
})
export class PatientShell {
  private readonly appointmentSelection = inject(AppointmentSelectionStore);
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly router = inject(Router);

  protected readonly demoScenario = inject(DemoScenarioStore);
  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly sessionStore = inject(PatientSessionStore);
  protected readonly mainContent = viewChild<ElementRef<HTMLElement>>('mainContent');
  protected readonly navigationItems: readonly NavigationItem[] = [
    { icon: 'space_dashboard', label: 'Inicio', path: '/inicio' },
    { icon: 'calendar_month', label: 'Citas', path: '/citas' },
    { icon: 'medication', label: 'Medicación', path: '/medicacion' },
    { icon: 'folder_open', label: 'Documentos', path: '/documentos' },
    { icon: 'shield_lock', label: 'Privacidad', path: '/privacidad' },
  ];

  protected onRouteActivate(): void {
    queueMicrotask(() => this.mainContent()?.nativeElement.focus());
  }

  protected updateScenario(event: Event): void {
    const target = event.target;
    if (target instanceof HTMLSelectElement && isDemoScenario(target.value)) {
      this.demoScenario.set(target.value);
    }
  }

  protected signOut(): void {
    this.repository.clearSensitiveRuntimeState();
    this.appointmentSelection.clear();
    this.demoScenario.set('ready');
    this.sessionStore.close();
    void this.router.navigateByUrl('/iniciar-sesion', { replaceUrl: true });
  }
}
