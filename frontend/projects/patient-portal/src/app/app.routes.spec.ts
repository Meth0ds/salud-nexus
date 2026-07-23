import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';

import { App } from './app';
import { routes } from './app.routes';
import { provideDemoPatientRepository } from './core/patient-repository';
import { PatientSessionStore } from './core/session.store';

describe('patient portal routes', () => {
  it('protects a lazy feature and renders it after opening the in-memory session', async () => {
    TestBed.configureTestingModule({
      imports: [App],
      providers: [provideRouter(routes), provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(App);
    const router = TestBed.inject(Router);

    await router.navigateByUrl('/documentos');
    await fixture.whenStable();
    expect(router.url).toBe('/iniciar-sesion');

    TestBed.inject(PatientSessionStore).open({
      displayName: 'Laura Martín',
      initials: 'LM',
      runtime: 'demo',
    });
    await router.navigateByUrl('/documentos');
    await fixture.whenStable();

    expect(router.url).toBe('/documentos');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Documentos publicados');
  });
});
