import { describe, expect, it } from 'vitest';

import { InMemoryPatientRepository } from './in-memory-patient-repository';

describe('InMemoryPatientRepository', () => {
  it('returns the same generic rejection for invalid demo credentials', async () => {
    const repository = new InMemoryPatientRepository();

    const result = await repository.authenticate({
      email: 'persona@example.test',
      password: 'incorrecto',
    });

    expect(result).toEqual({
      kind: 'rejected',
      message: 'No hemos podido verificar los datos de acceso.',
    });
  });

  it('creates an ephemeral session for the documented synthetic demo account', async () => {
    const repository = new InMemoryPatientRepository();

    const result = await repository.authenticate({
      email: 'laura.demo@saludnexus.test',
      password: 'NEXUS-2026',
    });

    expect(result.kind).toBe('authenticated');
    if (result.kind === 'authenticated') {
      expect(result.session.displayName).toBe('Laura Martín');
      expect(result.session.runtime).toBe('demo');
    }
  });

  it('reproduces TOTP enrollment without retaining the synthetic recovery codes', async () => {
    const repository = new InMemoryPatientRepository();

    await expect(repository.getMfaStatus()).resolves.toMatchObject({
      enabled: false,
      method: null,
      status: null,
    });
    await expect(repository.beginTotpEnrollment()).resolves.toMatchObject({
      method: 'totp',
      status: 'pending',
      qrDisclosureRequired: true,
    });
    await expect(repository.discloseTotpEnrollmentQr()).resolves.toContain('<svg');
    await expect(repository.discloseTotpEnrollmentQr()).rejects.toThrow('one use');
    await expect(repository.confirmTotpEnrollment('000000')).rejects.toThrow(
      'synthetic authentication code',
    );

    const confirmation = await repository.confirmTotpEnrollment('123456');
    expect(confirmation.codes).toHaveLength(10);
    expect(JSON.stringify(repository)).not.toContain(confirmation.codes[0]);
    await expect(repository.getMfaStatus()).resolves.toMatchObject({
      enabled: true,
      method: 'totp',
      status: 'active',
      recoveryCodesRemaining: 10,
    });

    repository.clearSensitiveRuntimeState();
    await expect(repository.getMfaStatus()).resolves.toMatchObject({
      enabled: false,
      method: null,
      status: null,
    });
  });

  it('books only once when the same client request is submitted twice', async () => {
    const repository = new InMemoryPatientRepository();
    const initialAppointments = await repository.listAppointments();
    const request = {
      clientRequestId: 'request_demo_4Zx8pQ',
      appointmentTypeId: 'appointment_type_demo_internal',
      slotId: 'slot_demo_20260729_1130',
    };

    const firstBooking = await repository.bookAppointment(request);
    const repeatedBooking = await repository.bookAppointment(request);
    const finalAppointments = await repository.listAppointments();

    expect(repeatedBooking.id).toBe(firstBooking.id);
    expect(finalAppointments).toHaveLength(initialAppointments.length + 1);
  });

  it('clears runtime bookings without modifying the immutable fixture baseline', async () => {
    const repository = new InMemoryPatientRepository();
    const initialAppointments = await repository.listAppointments();

    await repository.bookAppointment({
      clientRequestId: 'request_demo_clear_7Ly2',
      appointmentTypeId: 'appointment_type_demo_internal',
      slotId: 'slot_demo_20260729_1130',
    });
    repository.clearSensitiveRuntimeState();

    expect(await repository.listAppointments()).toHaveLength(initialAppointments.length);
  });

  it('cancels once per idempotency key and rejects changed intent', async () => {
    const repository = new InMemoryPatientRepository();
    const appointment = (await repository.listAppointments()).find(
      (candidate) => candidate.status === 'scheduled',
    );
    expect(appointment).toBeDefined();
    const request = {
      appointmentId: appointment!.id,
      clientRequestId: 'cancel_demo_request_9Ts4',
      expectedVersion: appointment!.version,
      reason: 'plans-changed' as const,
    };

    const cancellation = await repository.cancelAppointment(request);
    const replay = await repository.cancelAppointment(request);

    expect(cancellation).toEqual(
      expect.objectContaining({ status: 'cancelled', version: 2, changeAllowed: false }),
    );
    expect(replay).toEqual(cancellation);
    await expect(repository.cancelAppointment({ ...request, reason: 'other' })).rejects.toThrow(
      'idempotencia',
    );
  });

  it('reschedules atomically and rejects a stale expected version', async () => {
    const repository = new InMemoryPatientRepository();
    const appointment = (await repository.listAppointments()).find(
      (candidate) => candidate.status === 'scheduled',
    );
    const target = (await repository.getBookingOptions()).appointmentTypes[0]?.slots[0];
    expect(appointment).toBeDefined();
    expect(target).toBeDefined();

    const changed = await repository.rescheduleAppointment({
      appointmentId: appointment!.id,
      clientRequestId: 'reschedule_demo_request_8Lm3',
      expectedVersion: appointment!.version,
      slotId: target!.id,
    });

    expect(changed).toEqual(
      expect.objectContaining({
        status: 'scheduled',
        version: 2,
        dateLabel: target!.dateLabel,
      }),
    );
    await expect(
      repository.rescheduleAppointment({
        appointmentId: appointment!.id,
        clientRequestId: 'reschedule_demo_request_stale_4Px2',
        expectedVersion: 1,
        slotId: target!.id,
      }),
    ).rejects.toThrow('actualizado');
  });

  it('declares patient medication once per idempotency key without enabling renewal', async () => {
    const repository = new InMemoryPatientRepository();
    const initialMedication = await repository.listMedication();
    const request = {
      clientRequestId: 'declaration_demo_request_8Px4',
      name: 'Vitamina D',
      presentation: '',
      scheduleLabel: 'Una cápsula al día',
    };

    const declaration = await repository.declareMedication(request);
    const replay = await repository.declareMedication(request);

    expect(replay.id).toBe(declaration.id);
    expect(declaration).toEqual(
      expect.objectContaining({
        source: 'patient-declaration',
        presentation: 'Presentación no indicada',
        canRequestRenewal: false,
      }),
    );
    expect(await repository.listMedication()).toHaveLength(initialMedication.length + 1);
  });

  it('submits an idempotent renewal only for professional active medication', async () => {
    const repository = new InMemoryPatientRepository();
    const professionalMedication = (await repository.listMedication())[0];
    expect(professionalMedication).toBeDefined();

    const first = await repository.requestMedicationRenewal(
      professionalMedication!.id,
      'renewal_demo_request_3Kp9',
    );
    const replay = await repository.requestMedicationRenewal(
      professionalMedication!.id,
      'renewal_demo_request_3Kp9',
    );

    expect(replay.id).toBe(first.id);
    expect(first.status).toBe('submitted');

    const declaration = await repository.declareMedication({
      clientRequestId: 'declaration_demo_request_2Lm7',
      name: 'Magnesio',
      presentation: 'cápsulas',
      scheduleLabel: 'Una al día',
    });
    await expect(
      repository.requestMedicationRenewal(declaration.id, 'renewal_demo_request_6Ts1'),
    ).rejects.toThrow('no admite');
  });

  it('never fabricates a browser download in demonstration mode', async () => {
    const repository = new InMemoryPatientRepository();
    const document = (await repository.listDocuments())[0];
    expect(document).toBeDefined();

    await expect(repository.authorizeDocumentDownload(document!.id)).rejects.toThrow(
      'demostración',
    );
  });
});
