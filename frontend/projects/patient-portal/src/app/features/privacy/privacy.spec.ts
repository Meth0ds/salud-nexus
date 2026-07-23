import { TestBed } from '@angular/core/testing';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { Privacy } from './privacy';

describe('Privacy', () => {
  it('explains each synthetic access using actor, purpose, time and result', async () => {
    TestBed.configureTestingModule({
      imports: [Privacy],
      providers: [provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Privacy);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.textContent).toContain('Quién ha accedido a tu información');
    expect(page.textContent).toContain('Atención asistencial programada');
    expect(page.textContent).toContain('Acceso legítimo');
  });
});
