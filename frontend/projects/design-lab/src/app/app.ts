import { DOCUMENT } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

@Component({
  selector: 'sn-design-root',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly document = inject(DOCUMENT);

  protected readonly darkMode = signal(false);

  protected toggleTheme(): void {
    this.darkMode.update((enabled) => !enabled);
    this.document.documentElement.dataset['theme'] = this.darkMode() ? 'night' : 'day';
  }
}
