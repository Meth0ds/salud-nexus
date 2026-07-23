import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { App } from './app';

describe('App', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [App],
      providers: [provideRouter([])],
    });
  });

  it('presenta la identidad del laboratorio de diseño', async () => {
    const fixture = TestBed.createComponent(App);

    await fixture.whenStable();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelector('[data-testid="brand"]')?.textContent).toContain('Salud Nexus');
    expect(element.querySelector('main')).not.toBeNull();
  });

  it('expone navegación directa a catálogo y pantallas insignia', async () => {
    const fixture = TestBed.createComponent(App);

    await fixture.whenStable();

    const links = [...(fixture.nativeElement as HTMLElement).querySelectorAll('nav a')].map(
      (link) => link.textContent?.trim(),
    );
    expect(links).toEqual(expect.arrayContaining(['Catálogo', 'Paciente', 'Clínico', 'Seguridad']));
  });
});
