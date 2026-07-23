import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { ZodError } from 'zod';

import { SaludNexusAPIDelCentroSanitarioService } from './generated/salud-nexus-api';

const UUIDS = {
  appointment: '019b1234-5678-7abc-8def-1234567890a0',
  appointmentType: '019b1234-5678-7abc-8def-1234567890a1',
  request: '019b1234-5678-7abc-8def-1234567890a2',
  slot: '019b1234-5678-7abc-8def-1234567890a3',
  center: '019b1234-5678-7abc-8def-1234567890a4',
  service: '019b1234-5678-7abc-8def-1234567890a5',
} as const;

describe('Generated Salud Nexus API client', () => {
  let client: SaludNexusAPIDelCentroSanitarioService;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    client = TestBed.inject(SaludNexusAPIDelCentroSanitarioService);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpTesting.verify());

  it('uses the versioned same-origin base URL and validates a successful response', async () => {
    const response = firstValueFrom(client.getApiIndex());
    const request = httpTesting.expectOne('/api/v1/');

    expect(request.request.method).toBe('GET');
    expect(request.request.headers.has('Authorization')).toBe(false);

    request.flush({
      data: { name: 'Salud Nexus API', status: 'available', version: 'v1' },
      meta: { request_id: UUIDS.request },
    });

    await expect(response).resolves.toMatchObject({
      data: { status: 'available', version: 'v1' },
      meta: { request_id: UUIDS.request },
    });
  });

  it('requires and sends the idempotency header for booking mutations', async () => {
    const response = firstValueFrom(
      client.bookPatientAppointment(
        {
          appointment_type_id: UUIDS.appointmentType,
          slot_id: UUIDS.slot,
        },
        { 'Idempotency-Key': 'booking-request-20260723-0001' },
      ),
    );
    const request = httpTesting.expectOne('/api/v1/patient/appointments');

    expect(request.request.method).toBe('POST');
    expect(request.request.headers.get('Idempotency-Key')).toBe('booking-request-20260723-0001');
    expect(request.request.body).toEqual({
      appointment_type_id: UUIDS.appointmentType,
      slot_id: UUIDS.slot,
    });

    request.flush({ data: { id: UUIDS.request }, meta: { request_id: UUIDS.request } });

    await expect(response).rejects.toBeInstanceOf(ZodError);
  });

  it('sends concurrency headers and preserves the cancellation response ETag', async () => {
    const response = firstValueFrom(
      client.cancelPatientAppointment(
        UUIDS.appointment,
        { reason_code: 'plans_changed' },
        {
          'Idempotency-Key': 'cancel-request-20260723-0001',
          'If-Match': '"v1"',
        },
        { observe: 'response' },
      ),
    );
    const request = httpTesting.expectOne(
      `/api/v1/patient/appointments/${UUIDS.appointment}/cancellations`,
    );

    expect(request.request.method).toBe('POST');
    expect(request.request.headers.get('Idempotency-Key')).toBe('cancel-request-20260723-0001');
    expect(request.request.headers.get('If-Match')).toBe('"v1"');
    expect(request.request.body).toEqual({ reason_code: 'plans_changed' });

    request.flush(appointmentEnvelope('cancelled'), {
      headers: { ETag: '"v2"', 'Idempotency-Replayed': 'false' },
    });

    await expect(response).resolves.toMatchObject({
      body: { data: { id: UUIDS.appointment, status: 'cancelled', version: 2 } },
    });
    expect((await response).headers.get('ETag')).toBe('"v2"');
  });

  it('sends the target slot and strong validator for rescheduling', async () => {
    const response = firstValueFrom(
      client.reschedulePatientAppointment(
        UUIDS.appointment,
        { slot_id: UUIDS.slot },
        {
          'Idempotency-Key': 'reschedule-request-20260723-0001',
          'If-Match': '"v1"',
        },
      ),
    );
    const request = httpTesting.expectOne(
      `/api/v1/patient/appointments/${UUIDS.appointment}/reschedules`,
    );

    expect(request.request.method).toBe('POST');
    expect(request.request.headers.get('Idempotency-Key')).toBe('reschedule-request-20260723-0001');
    expect(request.request.headers.get('If-Match')).toBe('"v1"');
    expect(request.request.body).toEqual({ slot_id: UUIDS.slot });

    request.flush(appointmentEnvelope('scheduled'));

    await expect(response).resolves.toMatchObject({
      data: { id: UUIDS.appointment, status: 'scheduled', version: 2 },
    });
  });
});

function appointmentEnvelope(status: 'scheduled' | 'cancelled') {
  return {
    data: {
      id: UUIDS.appointment,
      status,
      version: 2,
      change_allowed: status === 'scheduled',
      change_deadline: '2026-08-02T07:00:00+00:00',
      attendance_mode: 'in_person',
      location_label: 'Consulta 3',
      professional_display_name: null,
      service: { id: UUIDS.service, name: 'Medicina general' },
      appointment_type: {
        id: UUIDS.appointmentType,
        name: 'Consulta ordinaria',
        duration_minutes: 30,
      },
      center: { id: UUIDS.center, name: 'Centro Aurora', timezone: 'Europe/Madrid' },
      starts_at: '2026-08-02T09:00:00+00:00',
      local_starts_at: '2026-08-02T11:00:00+02:00',
      ends_at: '2026-08-02T09:30:00+00:00',
      local_ends_at: '2026-08-02T11:30:00+02:00',
    },
    meta: { request_id: UUIDS.request },
  };
}
