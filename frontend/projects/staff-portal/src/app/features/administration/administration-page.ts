import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { SecurityAlert, SecurityMetric } from '../../core/staff-workspace.repository';
import { StaffWorkspaceStore } from '../../core/staff-workspace.store';

@Component({
  selector: 'sn-staff-administration-page',
  imports: [MatButtonModule, MatIconModule, MatProgressSpinnerModule],
  templateUrl: './administration-page.html',
  styleUrl: './administration-page.scss',
})
export class AdministrationPage {
  protected readonly workspace = inject(StaffWorkspaceStore);
  protected readonly highPriorityOnly = signal(false);
  protected readonly selectedAlertId = signal('');
  protected readonly alerts = computed(() => {
    const alerts = this.workspace.security().data.alerts;
    return this.highPriorityOnly() ? alerts.filter((alert) => alert.severity === 'high') : alerts;
  });
  protected readonly selectedAlert = computed(() =>
    this.workspace.security().data.alerts.find((alert) => alert.id === this.selectedAlertId()),
  );

  constructor() {
    void this.refresh();
  }

  protected refresh(): Promise<void> {
    this.selectedAlertId.set('');
    return this.workspace.loadSecurityOverview();
  }

  protected toggleHighPriority(): void {
    this.highPriorityOnly.update((active) => !active);
    if (this.highPriorityOnly() && this.selectedAlert()?.severity !== 'high') {
      this.selectedAlertId.set('');
    }
  }

  protected openAlert(alert: SecurityAlert): void {
    this.selectedAlertId.set(alert.id);
  }

  protected closeAlert(): void {
    this.selectedAlertId.set('');
  }

  protected metricIcon(tone: SecurityMetric['tone']): string {
    const icons: Readonly<Record<SecurityMetric['tone'], string>> = {
      attention: 'notification_important',
      neutral: 'monitoring',
      positive: 'verified_user',
    };
    return icons[tone];
  }
}
