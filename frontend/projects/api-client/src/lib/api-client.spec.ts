import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { z } from 'zod';

import {
  ApiClient,
  ApiContractError,
  ApiProblemError,
  ApiTransportError,
  provideApiClient,
} from './api-client';

const personSchema = z.object({
  id: z.string().min(1),
  displayName: z.string().min(1),
});

describe('ApiClient', () => {
  let client: ApiClient;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideApiClient({ baseUrl: '/api/v1', timeoutMs: 6_500 }),
      ],
    });

    client = TestBed.inject(ApiClient);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpTesting.verify());

  it('validates successful JSON and always uses the server session cookie', async () => {
    const response = firstValueFrom(client.get('/people/current', personSchema));
    const request = httpTesting.expectOne('/api/v1/people/current');

    expect(request.request.method).toBe('GET');
    expect(request.request.withCredentials).toBe(true);
    expect(request.request.headers.get('Accept')).toBe('application/json');
    expect(request.request.headers.has('Authorization')).toBe(false);

    request.flush({ id: 'person_demo_01', displayName: 'Laura Demo' });

    await expect(response).resolves.toEqual({
      id: 'person_demo_01',
      displayName: 'Laura Demo',
    });
  });

  it('adds a validated idempotency key only to an explicit mutation', async () => {
    const response = firstValueFrom(
      client.post('/appointments', { slotId: 'slot_demo_01' }, personSchema, {
        idempotencyKey: 'request-demo-01_20260719',
      }),
    );
    const request = httpTesting.expectOne('/api/v1/appointments');

    expect(request.request.method).toBe('POST');
    expect(request.request.headers.get('Content-Type')).toBe('application/json');
    expect(request.request.headers.get('Idempotency-Key')).toBe('request-demo-01_20260719');
    expect(request.request.withCredentials).toBe(true);
    request.flush({ id: 'appointment_demo_01', displayName: 'Consulta demo' });

    await expect(response).resolves.toEqual({
      id: 'appointment_demo_01',
      displayName: 'Consulta demo',
    });
  });

  it('sends a strong validator and returns validated mutation metadata', async () => {
    const response = firstValueFrom(
      client.postResponse('/appointments/example/cancellations', {}, personSchema, {
        idempotencyKey: 'request-demo-02_20260723',
        ifMatch: '"v1"',
      }),
    );
    const request = httpTesting.expectOne('/api/v1/appointments/example/cancellations');

    expect(request.request.headers.get('Idempotency-Key')).toBe('request-demo-02_20260723');
    expect(request.request.headers.get('If-Match')).toBe('"v1"');
    request.flush(
      { id: 'appointment_demo_01', displayName: 'Consulta demo' },
      { headers: { ETag: '"v2"', 'Idempotency-Replayed': 'false' } },
    );

    await expect(response).resolves.toEqual({
      data: { id: 'appointment_demo_01', displayName: 'Consulta demo' },
      etag: '"v2"',
      idempotencyReplayed: false,
    });
  });

  it('rejects absolute, protocol-relative and traversing endpoint paths', () => {
    for (const path of [
      'https://attacker.example/collect',
      '//attacker.example/collect',
      '/../admin',
      '/people/../admin',
    ]) {
      expect(() => client.get(path, personSchema), path).toThrowError('relative API path');
    }

    httpTesting.expectNone(() => true);
  });

  it('rejects malformed idempotency keys before sending a request', () => {
    expect(() =>
      client.post('/appointments', {}, personSchema, { idempotencyKey: 'short key' }),
    ).toThrowError('idempotency key');

    httpTesting.expectNone(() => true);
  });

  it('rejects weak or malformed appointment validators before sending a request', () => {
    for (const ifMatch of ['W/"v1"', 'v1', '"v0"']) {
      expect(() =>
        client.post('/appointments/example/cancellations', {}, personSchema, {
          idempotencyKey: 'request-demo-03_20260723',
          ifMatch,
        }),
      ).toThrowError('strong ETag');
    }

    httpTesting.expectNone(() => true);
  });

  it('fails closed when a successful response violates its schema', async () => {
    const response = firstValueFrom(client.get('/people/current', personSchema));
    const request = httpTesting.expectOne('/api/v1/people/current');
    request.flush({ id: 123, displayName: '' });

    await expect(response).rejects.toBeInstanceOf(ApiContractError);
  });

  it('normalizes RFC 9457 responses without losing the request correlation id', async () => {
    const response = firstValueFrom(client.get('/people/current', personSchema));
    const request = httpTesting.expectOne('/api/v1/people/current');
    request.flush(
      {
        type: 'https://salud-nexus.example/problems/forbidden',
        title: 'Access denied',
        status: 403,
        detail: 'You are not allowed to perform this action.',
        instance: '/api/v1/people/current',
        request_id: '018f47a2-4f4a-7b0f-8b15-9f82558b5924',
      },
      { status: 403, statusText: 'Forbidden' },
    );

    await expect(response).rejects.toBeInstanceOf(ApiProblemError);
    await expect(response).rejects.toMatchObject({
      name: 'ApiProblemError',
      requestId: '018f47a2-4f4a-7b0f-8b15-9f82558b5924',
      status: 403,
    });
  });

  it('never reflects an untrusted HTML or text error body', async () => {
    const response = firstValueFrom(client.get('/people/current', personSchema));
    const request = httpTesting.expectOne('/api/v1/people/current');
    request.flush('<h1>database-password-must-never-leak</h1>', {
      status: 500,
      statusText: 'Internal Server Error',
    });

    await expect(response).rejects.toSatisfy(
      (error: unknown) =>
        error instanceof ApiTransportError &&
        error.status === 500 &&
        !error.message.includes('database-password') &&
        !error.message.includes('<h1>'),
    );
  });
});
