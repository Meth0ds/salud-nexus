import { TestBed } from '@angular/core/testing';

import {
  Appointment,
  SecurityOverview,
  STAFF_WORKSPACE_REPOSITORY,
  StaffWorkspaceRepository,
} from '../../core/staff-workspace.repository';
import { AgendaPage } from './agenda-page';

const APPOINTMENTS: readonly Appointment[] = [
  {
    id: 'apt-synthetic-test',
    time: '09:20',
    durationMinutes: 25,
    patientDisplayName: 'Elena M. (demo)',
    initials: 'EM',
    visitType: 'Seguimiento',
    status: 'waiting',
    priority: 'attention',
    room: 'Consulta 4',
  },
];

const EMPTY_SECURITY: SecurityOverview = { metrics: [], alerts: [] };

function configure(schedule: () => Promise<readonly Appointment[]>): void {
  const repository: StaffWorkspaceRepository = {
    getSchedule: schedule,
    getReceptionQueue: async () => [],
    getSecurityOverview: async () => EMPTY_SECURITY,
    checkIn: async () => Promise.reject(new Error('not used')),
  };
  TestBed.configureTestingModule({
    imports: [AgendaPage],
    providers: [{ provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository }],
  });
}

describe('AgendaPage', () => {
  it('opens the operational detail for a selected appointment', async () => {
    configure(async () => APPOINTMENTS);
    const fixture = TestBed.createComponent(AgendaPage);
    await fixture.whenStable();

    const appointment = fixture.nativeElement.querySelector(
      '[data-appointment-id="apt-synthetic-test"]',
    ) as HTMLButtonElement;
    appointment.click();
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('.patient-detail')?.textContent).toContain(
      'Elena M. (demo)',
    );
    expect(fixture.nativeElement.querySelector('.patient-detail')?.textContent).toContain(
      'Datos sintéticos de demostración',
    );
  });

  it('explains an empty shift and offers a refresh action', async () => {
    configure(async () => []);
    const fixture = TestBed.createComponent(AgendaPage);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('[data-state="empty"]')?.textContent).toContain(
      'No hay citas para este turno',
    );
    expect(
      fixture.nativeElement.querySelector('[data-state="empty"] button')?.textContent,
    ).toContain('Actualizar agenda');
  });

  it('shows a safe retry state without rendering the underlying exception', async () => {
    configure(async () => Promise.reject(new Error('internal synthetic details')));
    const fixture = TestBed.createComponent(AgendaPage);
    await fixture.whenStable();
    const errorState = fixture.nativeElement.querySelector('[data-state="error"]') as HTMLElement;

    expect(errorState.textContent).toContain('No se pudo cargar la agenda');
    expect(errorState.textContent).not.toContain('internal synthetic details');
  });
});
