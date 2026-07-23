import { Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { PatientActionBoard } from './patient-action-board';
import { PatientCarePath } from './patient-care-path';

interface PatientNavigationItem {
  readonly icon: string;
  readonly label: string;
}

@Component({
  selector: 'sn-design-patient-dashboard',
  imports: [MatButtonModule, MatIconModule, PatientActionBoard, PatientCarePath],
  templateUrl: './patient-dashboard.html',
  styleUrl: './patient-dashboard.scss',
})
export class PatientDashboard {
  protected readonly navigationItems: readonly PatientNavigationItem[] = [
    { icon: 'space_dashboard', label: 'Resumen' },
    { icon: 'calendar_month', label: 'Mis citas' },
    { icon: 'medication', label: 'Medicación' },
    { icon: 'folder_open', label: 'Documentos' },
    { icon: 'forum', label: 'Mensajes' },
  ];

  protected readonly activeNavigation = signal('Resumen');
  protected readonly privacyNoticeVisible = signal(true);
  protected readonly announcement = signal('');

  protected selectNavigation(label: string): void {
    this.activeNavigation.set(label);
    this.announcement.set(`${label} seleccionado en esta demostración.`);
  }

  protected dismissPrivacyNotice(): void {
    this.privacyNoticeVisible.set(false);
    this.announcement.set('Aviso de privacidad ocultado.');
  }
}
