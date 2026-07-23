import { inject } from '@angular/core';
import type { CanActivateFn } from '@angular/router';
import { Router } from '@angular/router';
import { SessionStore as BrowserSessionStore } from 'auth';

import { PatientSessionStore } from './session.store';

export const authenticatedGuard: CanActivateFn = () => {
  const session = inject(PatientSessionStore);
  const router = inject(Router);
  return session.isAuthenticated() ? true : router.parseUrl('/iniciar-sesion');
};

export const anonymousOnlyGuard: CanActivateFn = () => {
  const session = inject(PatientSessionStore);
  const browserSession = inject(BrowserSessionStore);
  const router = inject(Router);

  if (session.isAuthenticated()) {
    return router.parseUrl('/inicio');
  }

  return browserSession.pendingMfaChallenge() ? router.parseUrl('/verificar-segundo-factor') : true;
};

export const mfaChallengeGuard: CanActivateFn = () => {
  const session = inject(PatientSessionStore);
  const browserSession = inject(BrowserSessionStore);
  const router = inject(Router);

  if (session.isAuthenticated()) {
    return router.parseUrl('/inicio');
  }

  return browserSession.pendingMfaChallenge() ? true : router.parseUrl('/iniciar-sesion');
};
