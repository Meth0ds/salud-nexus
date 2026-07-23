import { Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { SecurityAuditStream } from './security-audit-stream';
import { SecurityPosture } from './security-posture';

interface SecurityNavigationItem {
  readonly icon: string;
  readonly label: string;
}

@Component({
  selector: 'sn-design-security-center',
  imports: [MatButtonModule, MatIconModule, SecurityAuditStream, SecurityPosture],
  templateUrl: './security-center.html',
  styleUrl: './security-center.scss',
})
export class SecurityCenter {
  protected readonly navigationItems: readonly SecurityNavigationItem[] = [
    { icon: 'shield', label: 'Resumen' },
    { icon: 'policy', label: 'Accesos' },
    { icon: 'history', label: 'Auditoría' },
    { icon: 'key', label: 'Sesiones' },
    { icon: 'data_loss_prevention', label: 'Privacidad' },
    { icon: 'monitor_heart', label: 'Salud del sistema' },
  ];

  protected readonly activeNavigation = signal('Resumen');
  protected readonly announcement = signal('');

  protected selectNavigation(label: string): void {
    this.activeNavigation.set(label);
    this.announcement.set(`${label} seleccionado en esta demostración.`);
  }
}
