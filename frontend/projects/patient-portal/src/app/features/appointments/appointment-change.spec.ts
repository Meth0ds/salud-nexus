import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { ViewportScroller } from '@angular/common';
import { TestBed } from '@angular/core/testing';
import { MatButtonHarness } from '@angular/material/button/testing';
import { MatSelectHarness } from '@angular/material/select/testing';
import { provideRouter } from '@angular/router';
import { ApiProblemError } from 'api-client';

import { InMemoryPatientRepository } from '../../core/in-memory-patient-repository';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { AppointmentSelectionStore } from '../../core/session.store';
import { AppointmentChange } from './appointment-change';

const SCHEDULED_APPOINTMENT_ID = 'appointment_demo_q7V2mP';
const COMPLETED_APPOINTMENT_ID = 'appointment_demo_b3K8tN';

function configure(repository = new InMemoryPatientRepository()): InMemoryPatientRepository {
  TestBed.configureTestingModule({
    imports: [AppointmentChange],
    providers: [
      provideRouter([]),
      { provide: PATIENT_REPOSITORY, useValue: repository },
      { provide: ViewportScroller, useValue: { scrollToPosition: vi.fn() } },
    ],
  });
  return repository;
}

describe('AppointmentChange', () => {
  it('reschedules the selected appointment through Angular Material controls', async () => {
    const repository = configure();
    TestBed.inject(AppointmentSelectionStore).select(SCHEDULED_APPOINTMENT_ID);
    const fixture = TestBed.createComponent(AppointmentChange);
    const loader = TestbedHarnessEnvironment.loader(fixture);

    await fixture.whenStable();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Tu cita sigue reservada');
    const slot = await loader.getHarness(MatSelectHarness.with({ selector: '#new-slot' }));
    await slot.open();
    await slot.clickOptions({ text: /29 de julio de 2026/ });
    const confirm = await loader.getHarness(
      MatButtonHarness.with({ selector: '[data-action="confirm-reschedule"]' }),
    );
    expect(await confirm.isDisabled()).toBe(false);

    await confirm.click();
    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.querySelector('[role="status"]')?.textContent).toContain('Cita cambiada');
    expect(page.textContent).toContain('11:30–12:00');
    expect(page.textContent).not.toContain(SCHEDULED_APPOINTMENT_ID);
    await expect(repository.getAppointment(SCHEDULED_APPOINTMENT_ID)).resolves.toEqual(
      expect.objectContaining({ version: 2, timeLabel: '11:30–12:00' }),
    );
  });

  it('requires a reason and records an idempotent cancellation', async () => {
    const repository = configure();
    TestBed.inject(AppointmentSelectionStore).select(SCHEDULED_APPOINTMENT_ID);
    const fixture = TestBed.createComponent(AppointmentChange);
    const loader = TestbedHarnessEnvironment.loader(fixture);

    await fixture.whenStable();
    const cancellationMode = await loader.getHarness(
      MatButtonHarness.with({ selector: '[data-mode="cancel"]' }),
    );
    await cancellationMode.click();
    const confirm = await loader.getHarness(
      MatButtonHarness.with({ selector: '[data-action="confirm-cancellation"]' }),
    );
    expect(await confirm.isDisabled()).toBe(true);

    const reason = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(
      'input[value="plans-changed"]',
    );
    reason?.click();
    await fixture.whenStable();
    expect(await confirm.isDisabled()).toBe(false);

    await confirm.click();
    await fixture.whenStable();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Cita cancelada');
    await expect(repository.getAppointment(SCHEDULED_APPOINTMENT_ID)).resolves.toEqual(
      expect.objectContaining({ status: 'cancelled', version: 2, changeAllowed: false }),
    );
  });

  it('does not offer mutation controls when the server marks the appointment ineligible', async () => {
    configure();
    TestBed.inject(AppointmentSelectionStore).select(COMPLETED_APPOINTMENT_ID);
    const fixture = TestBed.createComponent(AppointmentChange);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.textContent).toContain('Esta cita ya no admite cambios');
    expect(page.querySelector('[data-action="confirm-reschedule"]')).toBeNull();
    expect(page.querySelector('[data-action="confirm-cancellation"]')).toBeNull();
  });

  it('offers an authoritative reload when optimistic concurrency detects a conflict', async () => {
    const repository = configure();
    vi.spyOn(repository, 'rescheduleAppointment').mockRejectedValueOnce(
      new ApiProblemError({
        type: 'https://salud-nexus.test/problems/appointment-conflict',
        title: 'Appointment conflict',
        status: 409,
        detail: 'The appointment version is stale.',
        instance: '/api/v1/patient/appointments/change',
        request_id: '019c0000-0000-7000-8000-000000000001',
      }),
    );
    TestBed.inject(AppointmentSelectionStore).select(SCHEDULED_APPOINTMENT_ID);
    const fixture = TestBed.createComponent(AppointmentChange);
    const loader = TestbedHarnessEnvironment.loader(fixture);

    await fixture.whenStable();
    const slot = await loader.getHarness(MatSelectHarness.with({ selector: '#new-slot' }));
    await slot.open();
    await slot.clickOptions({ text: /29 de julio de 2026/ });
    await (
      await loader.getHarness(
        MatButtonHarness.with({ selector: '[data-action="confirm-reschedule"]' }),
      )
    ).click();
    await fixture.whenStable();

    const alert = (fixture.nativeElement as HTMLElement).querySelector('[role="alert"]');
    expect(alert?.textContent).toContain('La cita ha cambiado');
    expect(alert?.textContent).not.toContain('The appointment version is stale');
    expect(await loader.hasHarness(MatButtonHarness.with({ text: /Recargar cita/ }))).toBe(true);
  });
});
