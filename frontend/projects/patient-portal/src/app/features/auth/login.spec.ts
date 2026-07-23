import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { PatientSessionStore } from '../../core/session.store';
import { Login } from './login';

@Component({ template: '<p>Inicio protegido</p>' })
class ProtectedHomeStub {}

describe('Login', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Login],
      providers: [
        provideDemoPatientRepository(),
        provideRouter([{ path: 'inicio', component: ProtectedHomeStub }]),
      ],
    });
  });

  it('explains that authentication and all visible records are synthetic', async () => {
    const fixture = TestBed.createComponent(Login);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.querySelector('h1')?.textContent).toContain('Accede a tu espacio de salud');
    expect(page.textContent).toContain('Entorno de demostración');
    expect(page.querySelector('input[autocomplete="username"]')).toBeTruthy();
    expect(page.querySelector('input[autocomplete="current-password"]')).toBeTruthy();
  });

  it('opens an in-memory session and navigates after valid demo credentials', async () => {
    const fixture = TestBed.createComponent(Login);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;
    const email = page.querySelector<HTMLInputElement>('#demo-email');
    const accessCode = page.querySelector<HTMLInputElement>('#access-secret');
    const form = page.querySelector<HTMLFormElement>('form');

    email?.setAttribute('value', 'laura.demo@saludnexus.test');
    if (email) {
      email.value = 'laura.demo@saludnexus.test';
      email.dispatchEvent(new Event('input'));
    }
    if (accessCode) {
      accessCode.value = 'NEXUS-2026';
      accessCode.dispatchEvent(new Event('input'));
    }
    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();

    expect(TestBed.inject(PatientSessionStore).isAuthenticated()).toBe(true);
    expect(TestBed.inject(Router).url).toBe('/inicio');
  });
});
