import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { STAFF_CENTER_NAME, StaffWorkspaceStore } from './core/staff-workspace.store';

@Component({
  selector: 'sn-staff-root',
  imports: [
    MatButtonModule,
    MatIconModule,
    MatTooltipModule,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
  ],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  protected readonly workspace = inject(StaffWorkspaceStore);
  protected readonly centerName = STAFF_CENTER_NAME;

  protected changeContext(event: Event): void {
    const select = event.target as HTMLSelectElement;
    this.workspace.setContext(select.value);
  }
}
