import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { SessionStore } from 'auth';
import { vi } from 'vitest';

import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PatientSessionStore } from '../../core/session.store';
import { MfaChallenge } from './mfa-challenge';

@Component({ template: '<p>Inicio protegido</p>' })
class ProtectedHomeStub {}

describe('MfaChallenge', () => {
  const verifyMfaChallenge = vi.fn();

  beforeEach(() => {
    verifyMfaChallenge.mockReset();
    TestBed.configureTestingModule({
      imports: [MfaChallenge],
      providers: [
        {
          provide: PATIENT_REPOSITORY,
          useValue: { verifyMfaChallenge },
        },
        provideRouter([{ path: 'inicio', component: ProtectedHomeStub }]),
      ],
    });
    TestBed.inject(SessionStore).markMfaRequired({
      id: '019b1234-5678-7abc-8def-1234567890b8',
      intent: 'login',
      purpose: null,
      methods: ['totp', 'recovery'],
      expiresAt: '2099-07-23T00:10:00+00:00',
      attemptsRemaining: 5,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });
  });

  it('presents the TOTP ceremony with accessible one-time-code semantics', async () => {
    const fixture = TestBed.createComponent(MfaChallenge);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;
    const input = page.querySelector<HTMLInputElement>('#mfa-code');

    expect(page.querySelector('h1')?.textContent).toContain('Confirma que eres tú');
    expect(input?.inputMode).toBe('numeric');
    expect(input?.autocomplete).toBe('one-time-code');
    expect(input?.maxLength).toBe(6);
    expect(page.textContent).toContain('código de recuperación');
    expect(page.querySelector('[aria-current="step"]')).toBeTruthy();
  });

  it('verifies a six-digit code, opens the patient session, and removes the code from memory', async () => {
    verifyMfaChallenge.mockResolvedValue({
      kind: 'authenticated',
      session: { displayName: 'Laura Martín', initials: 'LM', runtime: 'connected' },
    });
    const fixture = TestBed.createComponent(MfaChallenge);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;
    const input = page.querySelector<HTMLInputElement>('#mfa-code');

    if (input) {
      input.value = '123456';
      input.dispatchEvent(new Event('input'));
    }
    page
      .querySelector<HTMLFormElement>('form')
      ?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();

    expect(verifyMfaChallenge).toHaveBeenCalledWith({ method: 'totp', code: '123456' });
    expect(TestBed.inject(PatientSessionStore).isAuthenticated()).toBe(true);
    expect(TestBed.inject(Router).url).toBe('/inicio');
    expect(input?.value).toBe('');
  });

  it('switches to recovery without submitting and clears a rejected code', async () => {
    verifyMfaChallenge.mockResolvedValue({
      kind: 'rejected',
      message: 'No hemos podido verificar los datos de acceso.',
    });
    const fixture = TestBed.createComponent(MfaChallenge);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;

    page
      .querySelector<HTMLButtonElement>('[data-testid="use-recovery"]')
      ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    fixture.detectChanges();
    const input = page.querySelector<HTMLInputElement>('#mfa-code');
    expect(input?.autocomplete).toBe('off');

    if (input) {
      input.value = 'ABCDEF-GHJKLM-NPQRST-VWXYZ2';
      input.dispatchEvent(new Event('input'));
    }
    page
      .querySelector<HTMLFormElement>('form')
      ?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();

    expect(verifyMfaChallenge).toHaveBeenCalledWith({
      method: 'recovery',
      code: 'ABCDEF-GHJKLM-NPQRST-VWXYZ2',
    });
    expect(page.querySelector('[role="alert"]')?.textContent).toContain(
      'No hemos podido verificar',
    );
    expect(input?.value).toBe('');
    expect(TestBed.inject(PatientSessionStore).isAuthenticated()).toBe(false);
  });
});
