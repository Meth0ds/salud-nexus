import { Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { ClinicianAgendaBoard } from './clinician-agenda-board';

interface ClinicianNavigationItem {
  readonly icon: string;
  readonly label: string;
}

@Component({
  selector: 'sn-design-clinician-workspace',
  imports: [MatButtonModule, MatIconModule, ClinicianAgendaBoard],
  templateUrl: './clinician-workspace.html',
  styleUrl: './clinician-workspace.scss',
})
export class ClinicianWorkspace {
  protected readonly views: readonly ('Día' | 'Semana')[] = ['Día', 'Semana'];
  protected readonly navigationItems: readonly ClinicianNavigationItem[] = [
    { icon: 'today', label: 'Agenda' },
    { icon: 'group', label: 'Pacientes' },
    { icon: 'inbox', label: 'Bandeja' },
    { icon: 'task', label: 'Tareas' },
    { icon: 'folder_managed', label: 'Documentos' },
  ];

  protected readonly activeNavigation = signal('Agenda');
  protected readonly activeView = signal<'Día' | 'Semana'>('Día');
  protected readonly announcement = signal('');

  protected selectNavigation(label: string): void {
    this.activeNavigation.set(label);
    this.announcement.set(`${label} seleccionado en esta demostración.`);
  }

  protected setView(view: 'Día' | 'Semana'): void {
    this.activeView.set(view);
    this.announcement.set(`Vista de ${view.toLocaleLowerCase('es')} seleccionada.`);
  }
}
