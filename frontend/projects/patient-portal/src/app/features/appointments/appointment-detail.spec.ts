import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { AppointmentSelectionStore } from '../../core/session.store';
import { AppointmentDetail } from './appointment-detail';

describe('AppointmentDetail', () => {
  it('renders the selected appointment without putting its identifier in the route', async () => {
    TestBed.configureTestingModule({
      imports: [AppointmentDetail],
      providers: [provideRouter([]), provideDemoPatientRepository()],
    });
    TestBed.inject(AppointmentSelectionStore).select('appointment_demo_q7V2mP');
    const fixture = TestBed.createComponent(AppointmentDetail);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.querySelector('h1')?.textContent).toContain('Consulta de seguimiento');
    expect(page.textContent).toContain('Cómo prepararte');
    expect(page.querySelector('a[href="/citas/gestionar"]')?.textContent).toContain(
      'Gestionar cita',
    );
    expect(page.textContent).not.toContain('appointment_demo_q7V2mP');
  });
});
