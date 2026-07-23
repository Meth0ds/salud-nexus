import { computed, Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

@Component({
  selector: 'sn-design-patient-action-board',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './patient-action-board.html',
  styleUrl: './patient-action-board.scss',
})
export class PatientActionBoard {
  protected readonly remindersEnabled = signal(true);
  protected readonly announcement = signal('');
  protected readonly reminderLabel = computed(() =>
    this.remindersEnabled() ? 'Recordatorios activados' : 'Recordatorios desactivados',
  );

  protected toggleReminders(): void {
    this.remindersEnabled.update((enabled) => !enabled);
    this.announcement.set(this.reminderLabel());
  }
}
