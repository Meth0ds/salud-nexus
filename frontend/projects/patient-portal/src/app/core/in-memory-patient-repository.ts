import { Service } from '@angular/core';
import type {
  MfaStatusView,
  RecoveryCodesView,
  TotpEnrollmentView,
} from 'auth';

import {
  ACCESS_FIXTURES,
  APPOINTMENT_FIXTURES,
  DOCUMENT_FIXTURES,
  MEDICATION_FIXTURES,
} from './patient.fixtures';
import type {
  AccessEvent,
  Appointment,
  AppointmentBookingRequest,
  AppointmentCancellationRequest,
  AppointmentRescheduleRequest,
  AuthenticationResult,
  BookingOptions,
  DashboardSummary,
  DocumentDownloadAuthorization,
  MedicationDeclarationRequest,
  MedicationItem,
  MedicationRenewalResult,
  PatientCredentials,
  PatientDocument,
} from './patient.models';
import type { PatientRepository } from './patient-repository';

const DEMO_EMAIL = 'laura.demo@saludnexus.test';
const DEMO_ACCESS_CODE = 'NEXUS-2026';
const GENERIC_AUTHENTICATION_ERROR = 'No hemos podido verificar los datos de acceso.';
const DEMO_REQUEST_ID = '019b1234-5678-7abc-8def-1234567890ae';
const DEMO_TOTP_CODE = '123456';
const DEMO_TOTP_QR_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#fff"/><path d="M12 12h32v32H12zM76 12h32v32H76zM12 76h32v32H12zM56 56h12v12H56zM76 76h12v12H76zM96 56h12v12H96z" fill="#082a50"/></svg>';

const SLOT_DETAILS: Readonly<
  Record<string, Pick<Appointment, 'centre' | 'dateIso' | 'dateLabel' | 'room' | 'timeLabel'>>
> = {
  slot_demo_20260729_1130: {
    centre: 'Centro Atlántico',
    dateIso: '2026-07-29',
    dateLabel: 'Miércoles, 29 de julio de 2026',
    room: 'Consulta 2.14',
    timeLabel: '11:30–12:00',
  },
  slot_demo_20260731_1130: {
    centre: 'Centro Atlántico',
    dateIso: '2026-07-31',
    dateLabel: 'Viernes, 31 de julio de 2026',
    room: 'Consulta 2.14',
    timeLabel: '11:30–12:00',
  },
  slot_demo_20260803_0830: {
    centre: 'Centro Atlántico',
    dateIso: '2026-08-03',
    dateLabel: 'Lunes, 3 de agosto de 2026',
    room: 'Consulta 2.14',
    timeLabel: '08:30–09:00',
  },
  slot_demo_20260730_0900: {
    centre: 'Centro Atlántico',
    dateIso: '2026-07-30',
    dateLabel: 'Jueves, 30 de julio de 2026',
    room: 'Consulta 1.08',
    timeLabel: '09:00–09:30',
  },
};

const DEMO_BOOKING_OPTIONS: BookingOptions = {
  generatedAt: '2026-07-19T08:00:00+02:00',
  appointmentTypes: [
    {
      id: 'appointment_type_demo_internal',
      name: 'Consulta de medicina interna',
      serviceName: 'Medicina interna',
      durationMinutes: 30,
      attendanceMode: 'in-person',
      slots: [
        {
          id: 'slot_demo_20260729_1130',
          startsAt: '2026-07-29T11:30:00+02:00',
          endsAt: '2026-07-29T12:00:00+02:00',
          dateLabel: 'Miércoles, 29 de julio de 2026',
          timeLabel: '11:30–12:00',
          locationLabel: 'Consulta 2.14',
          centre: {
            id: 'centre_demo_atlantic',
            name: 'Centro Atlántico',
            timezone: 'Europe/Madrid',
          },
        },
        {
          id: 'slot_demo_20260731_1130',
          startsAt: '2026-07-31T11:30:00+02:00',
          endsAt: '2026-07-31T12:00:00+02:00',
          dateLabel: 'Viernes, 31 de julio de 2026',
          timeLabel: '11:30–12:00',
          locationLabel: 'Consulta 2.14',
          centre: {
            id: 'centre_demo_atlantic',
            name: 'Centro Atlántico',
            timezone: 'Europe/Madrid',
          },
        },
        {
          id: 'slot_demo_20260803_0830',
          startsAt: '2026-08-03T08:30:00+02:00',
          endsAt: '2026-08-03T09:00:00+02:00',
          dateLabel: 'Lunes, 3 de agosto de 2026',
          timeLabel: '08:30–09:00',
          locationLabel: 'Consulta 2.14',
          centre: {
            id: 'centre_demo_atlantic',
            name: 'Centro Atlántico',
            timezone: 'Europe/Madrid',
          },
        },
      ],
    },
    {
      id: 'appointment_type_demo_family',
      name: 'Consulta de medicina de familia',
      serviceName: 'Medicina de familia',
      durationMinutes: 30,
      attendanceMode: 'in-person',
      slots: [
        {
          id: 'slot_demo_20260730_0900',
          startsAt: '2026-07-30T09:00:00+02:00',
          endsAt: '2026-07-30T09:30:00+02:00',
          dateLabel: 'Jueves, 30 de julio de 2026',
          timeLabel: '09:00–09:30',
          locationLabel: 'Consulta 1.08',
          centre: {
            id: 'centre_demo_atlantic',
            name: 'Centro Atlántico',
            timezone: 'Europe/Madrid',
          },
        },
      ],
    },
  ],
};

@Service({ autoProvided: false })
export class InMemoryPatientRepository implements PatientRepository {
  private readonly runtimeAppointments: Appointment[] = [];
  private readonly appointmentOverrides = new Map<string, Appointment>();
  private readonly activeSlotsByAppointment = new Map<string, string>();
  private readonly bookingsByRequest = new Map<
    string,
    { readonly fingerprint: string; readonly appointment: Appointment }
  >();
  private readonly cancellationsByRequest = new Map<
    string,
    { readonly fingerprint: string; readonly appointment: Appointment }
  >();
  private readonly reschedulesByRequest = new Map<
    string,
    { readonly fingerprint: string; readonly appointment: Appointment }
  >();
  private readonly runtimeMedication: MedicationItem[] = [];
  private readonly medicationDeclarationsByRequest = new Map<
    string,
    { readonly fingerprint: string; readonly medication: MedicationItem }
  >();
  private readonly medicationRenewalsByRequest = new Map<
    string,
    { readonly medicationId: string; readonly renewal: MedicationRenewalResult }
  >();
  private demoMfaState: 'active' | 'disabled' | 'pending' = 'disabled';
  private demoQrDisclosureAvailable = false;

  authenticate(credentials: PatientCredentials): Promise<AuthenticationResult> {
    const emailMatches = credentials.email.trim().toLowerCase() === DEMO_EMAIL;
    const codeMatches = credentials.password === DEMO_ACCESS_CODE;

    if (!emailMatches || !codeMatches) {
      return Promise.resolve({
        kind: 'rejected',
        message: GENERIC_AUTHENTICATION_ERROR,
      });
    }

    return Promise.resolve({
      kind: 'authenticated',
      session: {
        displayName: 'Laura Martín',
        initials: 'LM',
        runtime: 'demo',
      },
    });
  }

  verifyMfaChallenge(): Promise<AuthenticationResult> {
    return Promise.resolve({
      kind: 'rejected',
      message: GENERIC_AUTHENTICATION_ERROR,
    });
  }

  getMfaStatus(): Promise<MfaStatusView> {
    const enabled = this.demoMfaState === 'active';

    return Promise.resolve({
      enabled,
      method: this.demoMfaState === 'disabled' ? null : 'totp',
      status: this.demoMfaState === 'disabled' ? null : this.demoMfaState,
      confirmedAt: enabled ? new Date().toISOString() : null,
      recoveryCodesRemaining: enabled ? 10 : 0,
      requestId: DEMO_REQUEST_ID,
    });
  }

  beginTotpEnrollment(): Promise<TotpEnrollmentView> {
    this.demoMfaState = 'pending';
    this.demoQrDisclosureAvailable = true;

    return Promise.resolve({
      method: 'totp',
      status: 'pending',
      expiresAt: new Date(Date.now() + 5 * 60_000).toISOString(),
      qrDisclosureRequired: true,
      requestId: DEMO_REQUEST_ID,
    });
  }

  discloseTotpEnrollmentQr(): Promise<string> {
    if (this.demoMfaState !== 'pending' || !this.demoQrDisclosureAvailable) {
      return Promise.reject(new Error('The synthetic QR disclosure is available for one use.'));
    }

    this.demoQrDisclosureAvailable = false;

    return Promise.resolve(DEMO_TOTP_QR_SVG);
  }

  confirmTotpEnrollment(code: string): Promise<RecoveryCodesView> {
    if (this.demoMfaState !== 'pending' || code !== DEMO_TOTP_CODE) {
      return Promise.reject(new Error('Expected the documented synthetic authentication code.'));
    }

    this.demoMfaState = 'active';

    return Promise.resolve({
      codes: '23456789AB'.split('').map((suffix) => `234567-89ABCD-EFGHJK-MNPQR${suffix}`),
      requestId: DEMO_REQUEST_ID,
    });
  }

  authorizeDocumentDownload(documentId: string): Promise<DocumentDownloadAuthorization> {
    if (documentId.trim() === '') {
      return Promise.reject(new Error('El identificador del documento no es válido.'));
    }
    return Promise.reject(
      new Error('La demostración no crea descargas ni autorizaciones de documentos.'),
    );
  }

  bookAppointment(request: AppointmentBookingRequest): Promise<Appointment> {
    const fingerprint = JSON.stringify({
      appointmentTypeId: request.appointmentTypeId,
      slotId: request.slotId,
    });
    const existingBooking = this.bookingsByRequest.get(request.clientRequestId);
    if (existingBooking) {
      if (existingBooking.fingerprint !== fingerprint) {
        return Promise.reject(
          new Error('La clave de idempotencia ya se utilizó con otra reserva.'),
        );
      }
      return Promise.resolve(cloneAppointment(existingBooking.appointment));
    }

    const slot = SLOT_DETAILS[request.slotId] ?? SLOT_DETAILS['slot_demo_20260729_1130'];
    const option = findDemoSlot(request.slotId);
    if (
      !slot ||
      option === undefined ||
      option.appointmentType.id !== request.appointmentTypeId ||
      this.isSlotOccupied(request.slotId)
    ) {
      throw new Error('The synthetic booking fixture is not available.');
    }

    const appointment: Appointment = {
      id: `appointment_demo_reserved_${this.runtimeAppointments.length + 1}`,
      appointmentTypeId: option.appointmentType.id,
      centreId: option.slot.centre.id,
      title: option.appointmentType.name,
      professional: 'Dra. Elena Robles',
      specialty: option.appointmentType.serviceName,
      centre: slot.centre,
      room: slot.room,
      dateIso: slot.dateIso,
      startsAt: option.slot.startsAt,
      endsAt: option.slot.endsAt,
      dateLabel: slot.dateLabel,
      timeLabel: slot.timeLabel,
      timezone: 'Hora peninsular',
      attendanceMode: 'in-person',
      status: 'scheduled',
      version: 1,
      changeAllowed: true,
      changeDeadline: changeDeadlineFor(option.slot.startsAt),
      preparation: ['El centro mostrará aquí cualquier indicación confirmada.'],
    };

    this.runtimeAppointments.push(appointment);
    this.activeSlotsByAppointment.set(appointment.id, request.slotId);
    this.bookingsByRequest.set(request.clientRequestId, { fingerprint, appointment });
    return Promise.resolve(cloneAppointment(appointment));
  }

  cancelAppointment(request: AppointmentCancellationRequest): Promise<Appointment> {
    const fingerprint = JSON.stringify({
      appointmentId: request.appointmentId,
      expectedVersion: request.expectedVersion,
      reason: request.reason,
    });
    const previous = this.cancellationsByRequest.get(request.clientRequestId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        return Promise.reject(
          new Error('La clave de idempotencia ya se utilizó con otra intención.'),
        );
      }
      return Promise.resolve(cloneAppointment(previous.appointment));
    }

    const appointment = this.appointmentById(request.appointmentId);
    const conflict = validateDemoChange(appointment, request.expectedVersion);
    if (conflict !== undefined || appointment === undefined) {
      return Promise.reject(conflict ?? new Error('No se encontró la cita sintética.'));
    }

    const cancelled: Appointment = {
      ...appointment,
      status: 'cancelled',
      version: appointment.version + 1,
      changeAllowed: false,
    };
    this.appointmentOverrides.set(cancelled.id, cancelled);
    this.activeSlotsByAppointment.delete(cancelled.id);
    this.cancellationsByRequest.set(request.clientRequestId, {
      fingerprint,
      appointment: cancelled,
    });

    return Promise.resolve(cloneAppointment(cancelled));
  }

  clearSensitiveRuntimeState(): void {
    this.runtimeAppointments.splice(0);
    this.appointmentOverrides.clear();
    this.activeSlotsByAppointment.clear();
    this.bookingsByRequest.clear();
    this.cancellationsByRequest.clear();
    this.reschedulesByRequest.clear();
    this.runtimeMedication.splice(0);
    this.medicationDeclarationsByRequest.clear();
    this.medicationRenewalsByRequest.clear();
    this.demoMfaState = 'disabled';
    this.demoQrDisclosureAvailable = false;
  }

  declareMedication(request: MedicationDeclarationRequest): Promise<MedicationItem> {
    const fingerprint = JSON.stringify({
      name: request.name.trim(),
      presentation: request.presentation.trim(),
      scheduleLabel: request.scheduleLabel.trim(),
    });
    const previous = this.medicationDeclarationsByRequest.get(request.clientRequestId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        return Promise.reject(new Error('La clave de idempotencia ya se utilizó con otros datos.'));
      }
      return Promise.resolve({ ...previous.medication });
    }

    const medication: MedicationItem = {
      id: `medication_demo_declared_${this.runtimeMedication.length + 1}`,
      name: request.name.trim(),
      presentation: request.presentation.trim() || 'Presentación no indicada',
      scheduleLabel: request.scheduleLabel.trim(),
      source: 'patient-declaration',
      sourceLabel: 'Declarada por ti en este portal',
      status: 'active',
      canRequestRenewal: false,
      renewalRequestStatus: null,
      lastUpdatedLabel: 'Añadida ahora en la demostración',
    };
    this.runtimeMedication.unshift(medication);
    this.medicationDeclarationsByRequest.set(request.clientRequestId, {
      fingerprint,
      medication,
    });

    return Promise.resolve({ ...medication });
  }

  getAppointment(id: string): Promise<Appointment | undefined> {
    const appointment = this.appointmentById(id);
    return Promise.resolve(appointment ? cloneAppointment(appointment) : undefined);
  }

  getBookingOptions(): Promise<BookingOptions> {
    const options = structuredClone(DEMO_BOOKING_OPTIONS);
    return Promise.resolve({
      ...options,
      appointmentTypes: options.appointmentTypes.map((appointmentType) => ({
        ...appointmentType,
        slots: appointmentType.slots.filter((slot) => !this.isSlotOccupied(slot.id)),
      })),
    });
  }

  getDashboardSummary(): Promise<DashboardSummary> {
    const nextAppointment = this.appointmentSnapshot().find(
      (appointment) => appointment.status === 'scheduled',
    );
    return Promise.resolve({
      nextAppointment: nextAppointment
        ? { ...nextAppointment, preparation: [...nextAppointment.preparation] }
        : undefined,
      medication: this.medicationSnapshot().slice(0, 2),
      recentDocuments: DOCUMENT_FIXTURES.slice(0, 2).map((document) => ({ ...document })),
      recentAccesses: ACCESS_FIXTURES.slice(0, 2).map((event) => ({ ...event })),
    });
  }

  listAccessEvents(): Promise<readonly AccessEvent[]> {
    return Promise.resolve(ACCESS_FIXTURES.map((event) => ({ ...event })));
  }

  listAppointments(): Promise<readonly Appointment[]> {
    return Promise.resolve(this.appointmentSnapshot().map(cloneAppointment));
  }

  listDocuments(): Promise<readonly PatientDocument[]> {
    return Promise.resolve(DOCUMENT_FIXTURES.map((document) => ({ ...document })));
  }

  listMedication(): Promise<readonly MedicationItem[]> {
    return Promise.resolve(this.medicationSnapshot());
  }

  requestMedicationRenewal(
    medicationId: string,
    clientRequestId: string,
  ): Promise<MedicationRenewalResult> {
    const previous = this.medicationRenewalsByRequest.get(clientRequestId);
    if (previous) {
      if (previous.medicationId !== medicationId) {
        return Promise.reject(
          new Error('La clave de idempotencia ya se utilizó para otra solicitud.'),
        );
      }
      return Promise.resolve({ ...previous.renewal });
    }

    const medication = [...MEDICATION_FIXTURES, ...this.runtimeMedication].find(
      (candidate) => candidate.id === medicationId,
    );
    if (!medication?.canRequestRenewal) {
      return Promise.reject(new Error('La medicación no admite una solicitud de renovación.'));
    }
    if (
      [...this.medicationRenewalsByRequest.values()].some(
        (request) => request.medicationId === medicationId,
      )
    ) {
      return Promise.reject(new Error('Ya existe una solicitud de renovación pendiente.'));
    }

    const renewal: MedicationRenewalResult = {
      id: `renewal_demo_${this.medicationRenewalsByRequest.size + 1}`,
      medicationId,
      requestedAt: new Date().toISOString(),
      status: 'submitted',
    };
    this.medicationRenewalsByRequest.set(clientRequestId, { medicationId, renewal });

    return Promise.resolve({ ...renewal });
  }

  rescheduleAppointment(request: AppointmentRescheduleRequest): Promise<Appointment> {
    const fingerprint = JSON.stringify({
      appointmentId: request.appointmentId,
      expectedVersion: request.expectedVersion,
      slotId: request.slotId,
    });
    const previous = this.reschedulesByRequest.get(request.clientRequestId);
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        return Promise.reject(
          new Error('La clave de idempotencia ya se utilizó con otra intención.'),
        );
      }
      return Promise.resolve(cloneAppointment(previous.appointment));
    }

    const appointment = this.appointmentById(request.appointmentId);
    const conflict = validateDemoChange(appointment, request.expectedVersion);
    if (conflict !== undefined || appointment === undefined) {
      return Promise.reject(conflict ?? new Error('No se encontró la cita sintética.'));
    }

    const option = findDemoSlot(request.slotId);
    if (
      option === undefined ||
      option.appointmentType.id !== appointment.appointmentTypeId ||
      option.slot.centre.id !== appointment.centreId ||
      this.isSlotOccupied(request.slotId, appointment.id)
    ) {
      return Promise.reject(new Error('La franja seleccionada ya no está disponible.'));
    }

    const changed: Appointment = {
      ...appointment,
      centre: option.slot.centre.name,
      room: option.slot.locationLabel,
      dateIso: option.slot.startsAt.slice(0, 10),
      startsAt: option.slot.startsAt,
      endsAt: option.slot.endsAt,
      dateLabel: option.slot.dateLabel,
      timeLabel: option.slot.timeLabel,
      version: appointment.version + 1,
      changeAllowed: true,
      changeDeadline: changeDeadlineFor(option.slot.startsAt),
    };
    this.appointmentOverrides.set(changed.id, changed);
    this.activeSlotsByAppointment.set(changed.id, option.slot.id);
    this.reschedulesByRequest.set(request.clientRequestId, {
      fingerprint,
      appointment: changed,
    });

    return Promise.resolve(cloneAppointment(changed));
  }

  private appointmentById(id: string): Appointment | undefined {
    return this.appointmentSnapshot().find((appointment) => appointment.id === id);
  }

  private appointmentSnapshot(): readonly Appointment[] {
    return [...APPOINTMENT_FIXTURES, ...this.runtimeAppointments].map(
      (appointment) => this.appointmentOverrides.get(appointment.id) ?? appointment,
    );
  }

  private isSlotOccupied(slotId: string, exceptAppointmentId?: string): boolean {
    return [...this.activeSlotsByAppointment.entries()].some(
      ([appointmentId, activeSlotId]) =>
        appointmentId !== exceptAppointmentId && activeSlotId === slotId,
    );
  }

  private medicationSnapshot(): readonly MedicationItem[] {
    const pendingIds = new Set(
      [...this.medicationRenewalsByRequest.values()].map((request) => request.medicationId),
    );
    return [...this.runtimeMedication, ...MEDICATION_FIXTURES].map((item) =>
      pendingIds.has(item.id)
        ? { ...item, canRequestRenewal: false, renewalRequestStatus: 'submitted' as const }
        : { ...item },
    );
  }
}

function findDemoSlot(slotId: string):
  | {
      readonly appointmentType: BookingOptions['appointmentTypes'][number];
      readonly slot: BookingOptions['appointmentTypes'][number]['slots'][number];
    }
  | undefined {
  for (const appointmentType of DEMO_BOOKING_OPTIONS.appointmentTypes) {
    const slot = appointmentType.slots.find((candidate) => candidate.id === slotId);
    if (slot !== undefined) {
      return { appointmentType, slot };
    }
  }
  return undefined;
}

function validateDemoChange(
  appointment: Appointment | undefined,
  expectedVersion: number,
): Error | undefined {
  if (appointment === undefined) {
    return new Error('No se encontró la cita sintética.');
  }
  if (appointment.version !== expectedVersion) {
    return new Error('La cita se ha actualizado; vuelve a cargarla antes de continuar.');
  }
  if (appointment.status !== 'scheduled' || !appointment.changeAllowed) {
    return new Error('La cita ya no admite cambios.');
  }
  return undefined;
}

function cloneAppointment(appointment: Appointment): Appointment {
  return { ...appointment, preparation: [...appointment.preparation] };
}

function changeDeadlineFor(startsAt: string): string {
  return new Date(new Date(startsAt).getTime() - 120 * 60_000).toISOString();
}
