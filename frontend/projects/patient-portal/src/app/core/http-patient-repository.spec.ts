import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ApiClient, ApiContractError, provideApiClient } from 'api-client';
import { SessionAuth } from 'auth';

import { HttpPatientRepository } from './http-patient-repository';

const IDS = {
  appointment: '019b1234-5678-7abc-8def-1234567890ab',
  center: '019b1234-5678-7abc-8def-1234567890ac',
  centerOther: '019b1234-5678-7abc-8def-1234567890b4',
  challenge: '019b1234-5678-7abc-8def-1234567890b8',
  document: '019b1234-5678-7abc-8def-1234567890b6',
  identity: '019b1234-5678-7abc-8def-1234567890ad',
  medication: '019b1234-5678-7abc-8def-1234567890b2',
  renewal: '019b1234-5678-7abc-8def-1234567890b3',
  request: '019b1234-5678-7abc-8def-1234567890ae',
  service: '019b1234-5678-7abc-8def-1234567890af',
  slot: '019b1234-5678-7abc-8def-1234567890b0',
  slotOther: '019b1234-5678-7abc-8def-1234567890b5',
  type: '019b1234-5678-7abc-8def-1234567890b1',
  version: '019b1234-5678-7abc-8def-1234567890b7',
} as const;

function appointmentPayload() {
  return {
    id: IDS.appointment,
    status: 'scheduled',
    version: 1,
    change_allowed: true,
    change_deadline: '2026-08-01T06:00:00+00:00',
    attendance_mode: 'in_person',
    location_label: 'Consulta 2',
    professional_display_name: null,
    service: { id: IDS.service, name: 'Medicina interna' },
    appointment_type: { id: IDS.type, name: 'Primera consulta', duration_minutes: 30 },
    center: { id: IDS.center, name: 'Centro Aurora', timezone: 'Europe/Madrid' },
    starts_at: '2026-08-01T08:00:00+00:00',
    local_starts_at: '2026-08-01T10:00:00+02:00',
    ends_at: '2026-08-01T08:30:00+00:00',
    local_ends_at: '2026-08-01T10:30:00+02:00',
  } as const;
}

function medicationPayload(
  overrides: Partial<{
    can_request_renewal: boolean;
    presentation: string | null;
    renewal_request_status: 'submitted' | null;
    source: string;
    status: string;
  }> = {},
) {
  return {
    id: IDS.medication,
    source: 'professional_record',
    name: 'Losartán',
    presentation: '50 mg · comprimidos',
    schedule_label: '1 comprimido cada 24 horas',
    status: 'active',
    can_request_renewal: true,
    renewal_request_status: null,
    updated_at: '2026-07-23T08:00:00+00:00',
    ...overrides,
  };
}

describe('HttpPatientRepository', () => {
  let http: HttpTestingController;
  let repository: HttpPatientRepository;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideApiClient({ baseUrl: '/api/v1' }),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    repository = new HttpPatientRepository(TestBed.inject(ApiClient), TestBed.inject(SessionAuth));
  });

  afterEach(() => http.verify());

  it('establishes a server session without persisting or returning credentials', async () => {
    const authentication = repository.authenticate({
      email: ' paciente@example.test ',
      password: 'correct horse battery staple',
    });

    http.expectOne('/api/v1/auth/csrf').flush(null, { status: 204, statusText: 'No Content' });
    const login = http.expectOne('/api/v1/auth/login');
    expect(login.request.body).toEqual({
      email: 'paciente@example.test',
      password: 'correct horse battery staple',
    });
    expect(login.request.headers.has('Authorization')).toBe(false);
    expect(login.request.withCredentials).toBe(true);
    login.flush(null, { status: 204, statusText: 'No Content' });
    http.expectOne('/api/v1/auth/session').flush({
      data: {
        authenticated: true,
        identity: { id: IDS.identity, display_name: 'Laura Martín Pérez' },
        authentication: {
          method: 'password',
          level: 'aal1',
          authenticated_at: '2026-07-23T00:00:00+00:00',
        },
        capabilities: ['session:read', 'session:logout'],
      },
      meta: { request_id: IDS.request },
    });

    await expect(authentication).resolves.toEqual({
      kind: 'authenticated',
      session: { displayName: 'Laura Martín Pérez', initials: 'LM', runtime: 'connected' },
    });
  });

  it('returns an opaque MFA continuation and completes it before creating the patient session', async () => {
    const authentication = repository.authenticate({
      email: 'paciente@example.test',
      password: 'correct horse battery staple',
    });

    http.expectOne('/api/v1/auth/csrf').flush(null, { status: 204, statusText: 'No Content' });
    http.expectOne('/api/v1/auth/login').flush(
      {
        data: {
          challenge_id: IDS.challenge,
          intent: 'login',
          purpose: null,
          methods: ['totp', 'recovery'],
          expires_at: '2026-07-23T00:10:00+00:00',
          attempts_remaining: 5,
        },
        meta: { request_id: IDS.request },
      },
      { status: 202, statusText: 'Accepted' },
    );

    await expect(authentication).resolves.toMatchObject({
      kind: 'mfa-required',
      challenge: {
        id: IDS.challenge,
        methods: ['totp', 'recovery'],
        attemptsRemaining: 5,
      },
    });
    http.expectNone('/api/v1/auth/session');

    const verification = repository.verifyMfaChallenge({ method: 'totp', code: '123456' });
    http.expectOne('/api/v1/auth/csrf').flush(null, { status: 204, statusText: 'No Content' });
    const factor = http.expectOne('/api/v1/auth/mfa/challenge-verifications');
    expect(factor.request.body).toEqual({
      challenge_id: IDS.challenge,
      method: 'totp',
      code: '123456',
    });
    factor.flush(null, { status: 204, statusText: 'No Content' });
    http.expectOne('/api/v1/auth/session').flush({
      data: {
        authenticated: true,
        identity: { id: IDS.identity, display_name: 'Laura Martín Pérez' },
        authentication: {
          method: 'password+totp',
          level: 'aal2',
          authenticated_at: '2026-07-23T00:01:00+00:00',
        },
        capabilities: ['session:read', 'session:logout'],
      },
      meta: { request_id: IDS.request },
    });

    await expect(verification).resolves.toEqual({
      kind: 'authenticated',
      session: { displayName: 'Laura Martín Pérez', initials: 'LM', runtime: 'connected' },
    });
  });

  it('maps the minimized appointment contract and keeps pagination out of the view model', async () => {
    const appointments = repository.listAppointments();
    const request = http.expectOne(
      (candidate) =>
        candidate.url === '/api/v1/patient/appointments' &&
        candidate.params.get('scope') === 'all' &&
        candidate.params.get('per_page') === '50' &&
        candidate.params.get('page') === '1',
    );
    request.flush({
      data: [appointmentPayload()],
      meta: {
        page: 1,
        per_page: 50,
        total: 1,
        last_page: 1,
        request_id: IDS.request,
      },
    });

    await expect(appointments).resolves.toEqual([
      expect.objectContaining({
        id: IDS.appointment,
        title: 'Primera consulta',
        professional: 'Profesional por confirmar',
        specialty: 'Medicina interna',
        centre: 'Centro Aurora',
        room: 'Consulta 2',
        dateIso: '2026-08-01',
        timeLabel: '10:00–10:30',
        attendanceMode: 'in-person',
        status: 'scheduled',
      }),
    ]);
  });

  it('loads live booking options and submits only server-owned identifiers idempotently', async () => {
    const optionsPromise = repository.getBookingOptions();
    http.expectOne('/api/v1/patient/booking-options').flush({
      data: {
        appointment_types: [
          {
            id: IDS.type,
            name: 'Primera consulta',
            duration_minutes: 30,
            attendance_mode: 'in_person',
            service: { id: IDS.service, name: 'Medicina interna' },
            slots: [
              {
                id: IDS.slot,
                starts_at: '2026-08-01T08:00:00+00:00',
                ends_at: '2026-08-01T08:30:00+00:00',
                local_starts_at: '2026-08-01T10:00:00+02:00',
                local_ends_at: '2026-08-01T10:30:00+02:00',
                center: { id: IDS.center, name: 'Centro Aurora', timezone: 'Europe/Madrid' },
                location_label: 'Consulta 2',
              },
            ],
          },
        ],
      },
      meta: { generated_at: '2026-07-23T00:00:00+00:00', request_id: IDS.request },
    });
    const options = await optionsPromise;
    expect(options.appointmentTypes[0]?.slots[0]).toEqual(
      expect.objectContaining({
        id: IDS.slot,
        dateLabel: 'sábado, 1 de agosto de 2026',
        timeLabel: '10:00–10:30',
      }),
    );

    const booking = repository.bookAppointment({
      appointmentTypeId: IDS.type,
      slotId: IDS.slot,
      clientRequestId: 'booking-request-123456',
    });
    const request = http.expectOne('/api/v1/patient/appointments');
    expect(request.request.body).toEqual({
      appointment_type_id: IDS.type,
      slot_id: IDS.slot,
    });
    expect(request.request.headers.get('Idempotency-Key')).toBe('booking-request-123456');
    request.flush({ data: appointmentPayload(), meta: { request_id: IDS.request } });
    await expect(booking).resolves.toEqual(expect.objectContaining({ id: IDS.appointment }));
  });

  it('requires matching version metadata when loading and cancelling an appointment', async () => {
    const detail = repository.getAppointment(IDS.appointment);
    http
      .expectOne(`/api/v1/patient/appointments/${IDS.appointment}`)
      .flush(
        { data: appointmentPayload(), meta: { request_id: IDS.request } },
        { headers: { ETag: '"v1"' } },
      );

    await expect(detail).resolves.toEqual(
      expect.objectContaining({ id: IDS.appointment, version: 1, changeAllowed: true }),
    );

    const cancellation = repository.cancelAppointment({
      appointmentId: IDS.appointment,
      clientRequestId: 'cancel-request-123456789',
      expectedVersion: 1,
      reason: 'plans-changed',
    });
    const request = http.expectOne(`/api/v1/patient/appointments/${IDS.appointment}/cancellations`);
    expect(request.request.body).toEqual({ reason_code: 'plans_changed' });
    expect(request.request.headers.get('Idempotency-Key')).toBe('cancel-request-123456789');
    expect(request.request.headers.get('If-Match')).toBe('"v1"');
    request.flush(
      {
        data: {
          ...appointmentPayload(),
          status: 'cancelled',
          version: 2,
          change_allowed: false,
        },
        meta: { request_id: IDS.request },
      },
      { headers: { ETag: '"v2"', 'Idempotency-Replayed': 'false' } },
    );

    await expect(cancellation).resolves.toEqual(
      expect.objectContaining({ status: 'cancelled', version: 2, changeAllowed: false }),
    );
  });

  it('submits rescheduling headers and rejects a response with mismatched ETag', async () => {
    const reschedule = repository.rescheduleAppointment({
      appointmentId: IDS.appointment,
      clientRequestId: 'reschedule-request-123456',
      expectedVersion: 1,
      slotId: IDS.slotOther,
    });
    const request = http.expectOne(`/api/v1/patient/appointments/${IDS.appointment}/reschedules`);
    expect(request.request.body).toEqual({ slot_id: IDS.slotOther });
    expect(request.request.headers.get('Idempotency-Key')).toBe('reschedule-request-123456');
    expect(request.request.headers.get('If-Match')).toBe('"v1"');
    request.flush(
      {
        data: { ...appointmentPayload(), version: 2 },
        meta: { request_id: IDS.request },
      },
      { headers: { ETag: '"v3"', 'Idempotency-Replayed': 'false' } },
    );

    await expect(reschedule).rejects.toBeInstanceOf(ApiContractError);
  });

  it('rejects booking options that mix centers in a single-center deployment', async () => {
    const options = repository.getBookingOptions();
    http.expectOne('/api/v1/patient/booking-options').flush({
      data: {
        appointment_types: [
          {
            id: IDS.type,
            name: 'Primera consulta',
            duration_minutes: 30,
            attendance_mode: 'in_person',
            service: { id: IDS.service, name: 'Medicina interna' },
            slots: [
              {
                id: IDS.slot,
                starts_at: '2026-08-01T08:00:00+00:00',
                ends_at: '2026-08-01T08:30:00+00:00',
                local_starts_at: '2026-08-01T10:00:00+02:00',
                local_ends_at: '2026-08-01T10:30:00+02:00',
                center: { id: IDS.center, name: 'Centro Aurora', timezone: 'Europe/Madrid' },
                location_label: 'Consulta 2',
              },
              {
                id: IDS.slotOther,
                starts_at: '2026-08-02T08:00:00+00:00',
                ends_at: '2026-08-02T08:30:00+00:00',
                local_starts_at: '2026-08-02T10:00:00+02:00',
                local_ends_at: '2026-08-02T10:30:00+02:00',
                center: {
                  id: IDS.centerOther,
                  name: 'Centro no permitido',
                  timezone: 'Europe/Madrid',
                },
                location_label: 'Consulta 8',
              },
            ],
          },
        ],
      },
      meta: { generated_at: '2026-07-23T00:00:00+00:00', request_id: IDS.request },
    });

    await expect(options).rejects.toBeInstanceOf(ApiContractError);
  });

  it('rejects malformed API data instead of letting it reach a clinical view', async () => {
    const appointments = repository.listAppointments();
    http
      .expectOne((candidate) => candidate.url === '/api/v1/patient/appointments')
      .flush({
        data: [{ ...appointmentPayload(), id: 'sequential-42' }],
        meta: { page: 1, per_page: 50, total: 1, last_page: 1, request_id: IDS.request },
      });

    await expect(appointments).rejects.toBeInstanceOf(ApiContractError);
  });

  it('rejects undocumented response fields through the generated strict contract', async () => {
    const appointments = repository.listAppointments();
    http
      .expectOne((candidate) => candidate.url === '/api/v1/patient/appointments')
      .flush({
        data: [{ ...appointmentPayload(), internal_notes: 'must-never-cross-the-api-boundary' }],
        meta: { page: 1, per_page: 50, total: 1, last_page: 1, request_id: IDS.request },
      });

    await expect(appointments).rejects.toBeInstanceOf(ApiContractError);
  });

  it('maps only minimized published document metadata', async () => {
    const documents = repository.listDocuments();
    http.expectOne('/api/v1/patient/documents').flush({
      data: [
        {
          id: IDS.document,
          title: 'Resumen de atención',
          category: 'care_summary',
          published_at: '2026-07-23T08:00:00+00:00',
          center: { id: IDS.center, name: 'Centro Aurora' },
          file: { mime_type: 'application/pdf', size_bytes: 290_816, version: 2 },
          integrity_status: 'verified',
          can_download: true,
        },
      ],
      meta: { request_id: IDS.request },
    });

    await expect(documents).resolves.toEqual([
      {
        id: IDS.document,
        title: 'Resumen de atención',
        category: 'care-summary',
        categoryLabel: 'Resumen asistencial',
        dateIso: '2026-07-23',
        dateLabel: '23 de julio de 2026',
        publishedAt: '2026-07-23T08:00:00+00:00',
        centre: 'Centro Aurora',
        format: 'PDF',
        mimeType: 'application/pdf',
        sizeBytes: 290_816,
        sizeLabel: '284 KB',
        versionNumber: 2,
        integrityStatus: 'verified',
        canDownload: true,
      },
    ]);
  });

  it('requests a short-lived same-origin document download authorization', async () => {
    const authorization = repository.authorizeDocumentDownload(IDS.document);
    const request = http.expectOne(
      `/api/v1/patient/documents/${IDS.document}/download-authorizations`,
    );
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({});
    request.flush({
      data: {
        document_id: IDS.document,
        download_url:
          '/api/v1/patient/document-downloads/abcdefghijklmnopqrstuvwxyzABCDEF_1234567890',
        expires_at: '2026-07-23T08:01:30+00:00',
      },
      meta: { request_id: IDS.request },
    });

    await expect(authorization).resolves.toEqual({
      documentId: IDS.document,
      downloadUrl: '/api/v1/patient/document-downloads/abcdefghijklmnopqrstuvwxyzABCDEF_1234567890',
      expiresAt: '2026-07-23T08:01:30+00:00',
    });
  });

  it('rejects a foreign or malformed download URL from the API contract', async () => {
    const authorization = repository.authorizeDocumentDownload(IDS.document);
    http.expectOne(`/api/v1/patient/documents/${IDS.document}/download-authorizations`).flush({
      data: {
        document_id: IDS.document,
        download_url: 'https://attacker.example/document.pdf',
        expires_at: '2026-07-23T08:01:30+00:00',
      },
      meta: { request_id: IDS.request },
    });

    await expect(authorization).rejects.toBeInstanceOf(ApiContractError);
  });

  it('maps professional and patient-declared medication without inventing clinical data', async () => {
    const medication = repository.listMedication();
    http.expectOne('/api/v1/patient/medications').flush({
      data: [
        medicationPayload(),
        medicationPayload({
          source: 'patient_declaration',
          presentation: null,
          can_request_renewal: false,
        }),
      ],
      meta: { request_id: IDS.request },
    });

    await expect(medication).resolves.toEqual([
      expect.objectContaining({
        id: IDS.medication,
        source: 'professional-record',
        presentation: '50 mg · comprimidos',
        canRequestRenewal: true,
        renewalRequestStatus: null,
        lastUpdatedLabel: 'Actualizado el 23 de julio de 2026',
      }),
      expect.objectContaining({
        source: 'patient-declaration',
        presentation: 'Presentación no indicada',
        canRequestRenewal: false,
        renewalRequestStatus: null,
      }),
    ]);
  });

  it('declares medication using only user-entered fields and an idempotency header', async () => {
    const declaration = repository.declareMedication({
      name: '  Vitamina D  ',
      presentation: '  1000 UI  ',
      scheduleLabel: '  Una cápsula al día  ',
      clientRequestId: 'medication-declaration-12345',
    });
    const request = http.expectOne('/api/v1/patient/medications/declarations');
    expect(request.request.body).toEqual({
      name: 'Vitamina D',
      presentation: '1000 UI',
      schedule_label: 'Una cápsula al día',
    });
    expect(request.request.headers.get('Idempotency-Key')).toBe('medication-declaration-12345');
    request.flush({
      data: medicationPayload({
        source: 'patient_declaration',
        can_request_renewal: false,
      }),
      meta: { request_id: IDS.request },
    });

    await expect(declaration).resolves.toEqual(
      expect.objectContaining({ source: 'patient-declaration', canRequestRenewal: false }),
    );
  });

  it('submits an eligible renewal with an empty body and validates its response', async () => {
    const renewal = repository.requestMedicationRenewal(
      IDS.medication,
      'medication-renewal-request-12345',
    );
    const request = http.expectOne(
      `/api/v1/patient/medications/${IDS.medication}/renewal-requests`,
    );
    expect(request.request.body).toEqual({});
    expect(request.request.headers.get('Idempotency-Key')).toBe('medication-renewal-request-12345');
    request.flush({
      data: {
        id: IDS.renewal,
        medication_id: IDS.medication,
        status: 'submitted',
        requested_at: '2026-07-23T08:30:00+00:00',
      },
      meta: { request_id: IDS.request },
    });

    await expect(renewal).resolves.toEqual({
      id: IDS.renewal,
      medicationId: IDS.medication,
      status: 'submitted',
      requestedAt: '2026-07-23T08:30:00+00:00',
    });
  });

  it('rejects inconsistent medication eligibility before rendering it', async () => {
    const medication = repository.listMedication();
    http.expectOne('/api/v1/patient/medications').flush({
      data: [medicationPayload({ source: 'patient_declaration', can_request_renewal: true })],
      meta: { request_id: IDS.request },
    });

    await expect(medication).rejects.toBeInstanceOf(ApiContractError);
  });

  it('maps a pending renewal as non-actionable after a fresh load', async () => {
    const medication = repository.listMedication();
    http.expectOne('/api/v1/patient/medications').flush({
      data: [
        medicationPayload({
          can_request_renewal: false,
          renewal_request_status: 'submitted',
        }),
      ],
      meta: { request_id: IDS.request },
    });

    await expect(medication).resolves.toEqual([
      expect.objectContaining({
        canRequestRenewal: false,
        renewalRequestStatus: 'submitted',
      }),
    ]);
  });
});
