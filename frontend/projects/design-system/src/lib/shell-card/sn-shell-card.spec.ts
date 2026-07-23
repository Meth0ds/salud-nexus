import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SnShellCard } from './sn-shell-card';

@Component({
  imports: [SnShellCard],
  template: `
    <sn-shell-card heading="Próxima cita" eyebrow="Hoy">
      <button shell-actions type="button">Cambiar</button>
      <p>09:30 con Medicina de familia</p>
    </sn-shell-card>
  `,
})
class ShellCardTestHost {}

describe('SnShellCard', () => {
  let fixture: ComponentFixture<ShellCardTestHost>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [ShellCardTestHost] });
    fixture = TestBed.createComponent(ShellCardTestHost);
  });

  it('projects actions and content into a labelled semantic section', async () => {
    await fixture.whenStable();

    const section = fixture.nativeElement.querySelector('.sn-shell-card') as HTMLElement;
    const heading = fixture.nativeElement.querySelector('h2') as HTMLElement;
    const action = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    expect(section.getAttribute('aria-labelledby')).toBe(heading.id);
    expect(heading.textContent?.trim()).toBe('Próxima cita');
    expect(action.textContent?.trim()).toBe('Cambiar');
    expect(section.textContent).toContain('09:30 con Medicina de familia');
  });
});
