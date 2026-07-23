import { LiveAnnouncer } from '@angular/cdk/a11y';
import { Component, computed, inject, signal } from '@angular/core';
import { FormField, form, minLength, submit } from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { QueueEntry } from '../../core/staff-workspace.repository';
import { StaffWorkspaceStore } from '../../core/staff-workspace.store';

@Component({
  selector: 'sn-staff-reception-page',
  imports: [
    FormField,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './reception-page.html',
  styleUrl: './reception-page.scss',
})
export class ReceptionPage {
  protected readonly workspace = inject(StaffWorkspaceStore);
  private readonly liveAnnouncer = inject(LiveAnnouncer);
  protected readonly searchModel = signal({ query: '' });
  protected readonly searchForm = form(this.searchModel, (path) => {
    minLength(path.query, 2, { message: 'Escribe al menos 2 caracteres.' });
  });
  protected readonly activeQuery = signal('');
  protected readonly actionMessage = signal('');
  protected readonly filteredQueue = computed(() => {
    const query = this.activeQuery().toLocaleLowerCase('es').trim();
    if (!query) {
      return this.workspace.receptionQueue().data;
    }

    return this.workspace
      .receptionQueue()
      .data.filter((entry) =>
        `${entry.displayName} ${entry.maskedDocument}`.toLocaleLowerCase('es').includes(query),
      );
  });
  protected readonly waitingCount = computed(
    () => this.workspace.receptionQueue().data.filter((entry) => entry.status === 'waiting').length,
  );

  constructor() {
    void this.refresh();
  }

  protected refresh(): Promise<void> {
    this.actionMessage.set('');
    return this.workspace.loadReceptionQueue();
  }

  protected searchQueue(): void {
    submit(this.searchForm, async () => {
      this.activeQuery.set(this.searchModel().query.trim());
      await this.liveAnnouncer.announce(
        `${this.filteredQueue().length} resultados en la cola de recepción.`,
        'polite',
      );
    });
  }

  protected clearSearch(): void {
    this.searchModel.set({ query: '' });
    this.activeQuery.set('');
    this.searchForm().reset();
  }

  protected async confirmCheckIn(entry: QueueEntry): Promise<void> {
    this.actionMessage.set('');
    try {
      await this.workspace.checkIn(entry.id);
      const message = `Admisión registrada para ${entry.displayName}.`;
      this.actionMessage.set(message);
      await this.liveAnnouncer.announce(message, 'polite');
    } catch {
      const message = 'No se pudo confirmar la admisión. Reintenta sin duplicar la solicitud.';
      this.actionMessage.set(message);
      await this.liveAnnouncer.announce(message, 'assertive');
    }
  }

  protected statusLabel(status: QueueEntry['status']): string {
    const labels: Readonly<Record<QueueEntry['status'], string>> = {
      called: 'Avisada',
      'checked-in': 'Admisión confirmada',
      waiting: 'En espera',
    };
    return labels[status];
  }
}
