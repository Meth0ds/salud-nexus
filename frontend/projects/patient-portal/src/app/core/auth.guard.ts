import { inject } from '@angular/core';
import type { CanActivateFn } from '@angular/router';
import { Router } from '@angular/router';

import { PatientSessionStore } from './session.store';

export const authenticatedGuard: CanActivateFn = () => {
  const session = inject(PatientSessionStore);
  const router = inject(Router);
  return session.isAuthenticated() ? true : router.parseUrl('/iniciar-sesion');
};

export const anonymousOnlyGuard: CanActivateFn = () => {
  const session = inject(PatientSessionStore);
  const router = inject(Router);
  return session.isAuthenticated() ? router.parseUrl('/inicio') : true;
};
