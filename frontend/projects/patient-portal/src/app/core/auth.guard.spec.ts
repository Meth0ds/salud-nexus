import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { authenticatedGuard } from './auth.guard';
import { PatientSessionStore } from './session.store';

@Component({ template: '<p>Acceso</p>' })
class LoginStub {}

@Component({ template: '<p>Contenido protegido</p>' })
class ProtectedStub {}

describe('authenticatedGuard', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'iniciar-sesion', component: LoginStub },
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
});
