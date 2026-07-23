import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { AppointmentList } from './appointment-list';

describe('AppointmentList', () => {
  it('separates upcoming appointments from the completed history', async () => {
    TestBed.configureTestingModule({
      imports: [AppointmentList],
      providers: [provideRouter([]), provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(AppointmentList);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.textContent).toContain('Próximas citas');
    expect(page.textContent).toContain('Historial');
    expect(page.textContent).toContain('Consulta de seguimiento');
  });
});
