import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { SessionStore } from 'auth';

import { anonymousOnlyGuard, authenticatedGuard, mfaChallengeGuard } from './auth.guard';
import { PatientSessionStore } from './session.store';

@Component({ template: '<p>Acceso</p>' })
class LoginStub {}

@Component({ template: '<p>Contenido protegido</p>' })
class ProtectedStub {}

@Component({ template: '<p>Segundo factor</p>' })
class MfaChallengeStub {}

describe('authenticatedGuard', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'iniciar-sesion', component: LoginStub, canActivate: [anonymousOnlyGuard] },
          {
            path: 'verificar-segundo-factor',
            component: MfaChallengeStub,
            canActivate: [mfaChallengeGuard],
          },
          { path: 'inicio', component: ProtectedStub, canActivate: [authenticatedGuard] },
        ]),
      ],
    });
  });

  it('redirects an anonymous visitor without exposing the requested path as a query parameter', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/inicio');

    expect(TestBed.inject(Router).url).toBe('/iniciar-sesion');
  });

  it('allows a synthetic in-memory session to open protected routes', async () => {
    const harness = await RouterTestingHarness.create();
    TestBed.inject(PatientSessionStore).open({
      displayName: 'Laura Martín',
      initials: 'LM',
      runtime: 'demo',
    });

    const page = await harness.navigateByUrl('/inicio', ProtectedStub);

    expect(page).toBeInstanceOf(ProtectedStub);
  });

  it('allows only the tab holding an opaque MFA challenge to open the ceremony', async () => {
    const harness = await RouterTestingHarness.create();
    TestBed.inject(SessionStore).markMfaRequired({
      id: '019b1234-5678-7abc-8def-1234567890b8',
      intent: 'login',
      purpose: null,
      methods: ['totp'],
      expiresAt: '2099-07-23T00:10:00+00:00',
      attemptsRemaining: 5,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });

    const page = await harness.navigateByUrl('/verificar-segundo-factor', MfaChallengeStub);

    expect(page).toBeInstanceOf(MfaChallengeStub);
  });

  it('redirects a second-factor URL without local challenge state back to login', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/verificar-segundo-factor');

    expect(TestBed.inject(Router).url).toBe('/iniciar-sesion');
  });

  it('returns a pending challenge to its ceremony instead of restarting password login', async () => {
    const harness = await RouterTestingHarness.create();
    TestBed.inject(SessionStore).markMfaRequired({
      id: '019b1234-5678-7abc-8def-1234567890b8',
      intent: 'login',
      purpose: null,
      methods: ['totp'],
      expiresAt: '2099-07-23T00:10:00+00:00',
      attemptsRemaining: 5,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });

    await harness.navigateByUrl('/iniciar-sesion');

    expect(TestBed.inject(Router).url).toBe('/verificar-segundo-factor');
  });
});
