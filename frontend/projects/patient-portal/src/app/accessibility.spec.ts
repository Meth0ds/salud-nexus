import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import axe from 'axe-core';

import { provideDemoPatientRepository } from './core/patient-repository';
import { PatientSessionStore } from './core/session.store';
import { Login } from './features/auth/login';
import { Home } from './features/home/home';
import { PatientShell } from './layout/patient-shell';

async function expectNoStructuralAxeViolations(element: HTMLElement): Promise<void> {
  const results = await axe.run(element, {
    rules: {
      // jsdom cannot calculate rendered color contrast; the browser audit covers it separately.
      'color-contrast': { enabled: false },
    },
  });
  expect(results.violations).toEqual([]);
}

describe('patient portal accessibility', () => {
  it('has no structural axe violations on the login screen', async () => {
    TestBed.configureTestingModule({
      imports: [Login],
      providers: [provideRouter([]), provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Login);
    await fixture.whenStable();

    await expectNoStructuralAxeViolations(fixture.nativeElement as HTMLElement);
  });

  it('has no structural axe violations on the authenticated shell and home', async () => {
    TestBed.configureTestingModule({
      imports: [PatientShell],
      providers: [provideRouter([{ path: '', component: Home }]), provideDemoPatientRepository()],
    });
    TestBed.inject(PatientSessionStore).open({
      displayName: 'Laura Martín',
      initials: 'LM',
      runtime: 'demo',
    });
    const fixture = TestBed.createComponent(PatientShell);
    await fixture.whenStable();

    await expectNoStructuralAxeViolations(fixture.nativeElement as HTMLElement);
  });
});
