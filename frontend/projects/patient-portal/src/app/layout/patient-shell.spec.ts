import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { provideDemoPatientRepository } from '../core/patient-repository';
import { PatientSessionStore } from '../core/session.store';
import { PatientShell } from './patient-shell';

describe('PatientShell', () => {
  it('provides a skip link, landmarks and the complete primary navigation', async () => {
    TestBed.configureTestingModule({
      imports: [PatientShell],
      providers: [provideRouter([]), provideDemoPatientRepository()],
    });
    TestBed.inject(PatientSessionStore).open({
      displayName: 'Laura Martín',
      initials: 'LM',
      runtime: 'demo',
    });
    const fixture = TestBed.createComponent(PatientShell);

    await fixture.whenStable();

    const shell = fixture.nativeElement as HTMLElement;
    expect(shell.querySelector('a[href="#main-content"]')?.textContent).toContain(
      'Saltar al contenido',
    );
    expect(shell.querySelector('nav[aria-label="Navegación principal"]')).toBeTruthy();
    expect(shell.querySelector('main#main-content')).toBeTruthy();
    expect(shell.textContent).toContain('Citas');
    expect(shell.textContent).toContain('Medicación'.normalize('NFC'));
    expect(shell.textContent).toContain('Privacidad');
  });
});
