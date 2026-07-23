import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ApiContractError, ApiProblemError, provideApiClient } from 'api-client';
import { firstValueFrom } from 'rxjs';

import { SessionAuth, SessionStore } from './auth';

const sessionResponse = {
  data: {
    authenticated: true,
    identity: {
      id: '018f47a2-4f4a-7b0f-8b15-9f82558b5924',
      display_name: 'Laura Demo',
    },
    authentication: {
      method: 'password',
      level: 'aal1',
      authenticated_at: '2026-07-19T06:30:00+00:00',
    },
    capabilities: ['session:read', 'session:logout'],
  },
  meta: {
    request_id: '018f47a2-4f4a-7b0f-8b15-9f82558b5925',
  },
} as const;

const unauthenticatedProblem = {
  type: 'https://salud-nexus.example/problems/unauthenticated',
  title: 'Authentication required',
  status: 401,
  detail: 'Authentication is required to access this resource.',
  instance: '/api/v1/auth/login',
  request_id: '018f47a2-4f4a-7b0f-8b15-9f82558b5999',
} as const;

const mfaChallengeResponse = {
  data: {
    challenge_id: '018f47a2-4f4a-7b0f-8b15-9f82558b5930',
    intent: 'login',
    purpose: null,
    methods: ['totp', 'recovery'],
    expires_at: '2099-07-19T06:40:00+00:00',
    attempts_remaining: 5,
  },
  meta: {
    request_id: '018f47a2-4f4a-7b0f-8b15-9f82558b5931',
  },
} as const;

const aal2SessionResponse = {
  ...sessionResponse,
  data: {
    ...sessionResponse.data,
    authentication: {
      method: 'password+totp',
      level: 'aal2',
      authenticated_at: '2026-07-19T06:31:00+00:00',
    },
  },
} as const;

describe('session authentication', () => {
  let auth: SessionAuth;
  let store: SessionStore;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideApiClient({ baseUrl: '/api/v1' }),
      ],
    });

    auth = TestBed.inject(SessionAuth);
    store = TestBed.inject(SessionStore);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpTesting.verify());

  it('establishes CSRF before login, then loads only the minimal session view', async () => {
    const result = firstValueFrom(
      auth.login({ email: ' Laura.Demo@SaludNexus.Test ', password: 'synthetic-password' }),
    );

    const csrf = httpTesting.expectOne('/api/v1/auth/csrf');
    expect(csrf.request.method).toBe('GET');
    expect(csrf.request.withCredentials).toBe(true);
    csrf.flush(null, { status: 204, statusText: 'No Content' });

    const login = httpTesting.expectOne('/api/v1/auth/login');
    expect(login.request.method).toBe('POST');
    expect(login.request.body).toEqual({
      email: 'Laura.Demo@SaludNexus.Test',
      password: 'synthetic-password',
    });
    expect(login.request.headers.has('Authorization')).toBe(false);
    login.flush(null, { status: 204, statusText: 'No Content' });

    const session = httpTesting.expectOne('/api/v1/auth/session');
    expect(session.request.method).toBe('GET');
    session.flush(sessionResponse);

    await expect(result).resolves.toMatchObject({
      kind: 'authenticated',
      session: { identity: { displayName: 'Laura Demo' } },
    });
    expect(store.state().kind).toBe('authenticated');
    expect(JSON.stringify(store.state())).not.toContain('synthetic-password');
    expect(JSON.stringify(store.state())).not.toContain('Laura.Demo@SaludNexus.Test');
  });

  it('keeps the browser anonymous and exposes only an opaque MFA challenge after password login', async () => {
    const result = firstValueFrom(
      auth.login({ email: 'patient@example.test', password: 'synthetic-password' }),
    );

    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/login').flush(mfaChallengeResponse, {
      status: 202,
      statusText: 'Accepted',
    });

    await expect(result).resolves.toEqual({
      kind: 'mfa-required',
      challenge: {
        id: mfaChallengeResponse.data.challenge_id,
        intent: 'login',
        purpose: null,
        methods: ['totp', 'recovery'],
        expiresAt: mfaChallengeResponse.data.expires_at,
        attemptsRemaining: 5,
        requestId: mfaChallengeResponse.meta.request_id,
      },
    });
    expect(store.state()).toEqual(await result);
    expect(store.isAuthenticated()).toBe(false);
    expect(JSON.stringify(store.state())).not.toContain('synthetic-password');
    expect(JSON.stringify(store.state())).not.toContain('patient@example.test');
    httpTesting.expectNone('/api/v1/auth/session');
  });

  it('verifies the pending TOTP challenge before loading and storing the AAL2 session', async () => {
    const login = firstValueFrom(
      auth.login({ email: 'patient@example.test', password: 'synthetic-password' }),
    );
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/login').flush(mfaChallengeResponse, {
      status: 202,
      statusText: 'Accepted',
    });
    await login;

    const verification = firstValueFrom(
      auth.verifyMfaChallenge({ method: 'totp', code: '123456' }),
    );
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    const request = httpTesting.expectOne('/api/v1/auth/mfa/challenge-verifications');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({
      challenge_id: mfaChallengeResponse.data.challenge_id,
      method: 'totp',
      code: '123456',
    });
    request.flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/session').flush(aal2SessionResponse);

    await expect(verification).resolves.toMatchObject({
      authentication: { method: 'password+totp', level: 'aal2' },
    });
    expect(store.state()).toMatchObject({
      kind: 'authenticated',
      session: { authentication: { level: 'aal2' } },
    });
    expect(JSON.stringify(store.state())).not.toContain('123456');
  });

  it('rejects a challenge that is already expired without sending a factor', async () => {
    store.markMfaRequired({
      id: mfaChallengeResponse.data.challenge_id,
      intent: 'login',
      purpose: null,
      methods: ['totp'],
      expiresAt: '2000-01-01T00:00:00+00:00',
      attemptsRemaining: 5,
      requestId: mfaChallengeResponse.meta.request_id,
    });

    const result = firstValueFrom(auth.verifyMfaChallenge({ method: 'totp', code: '123456' }));

    httpTesting.expectNone('/api/v1/auth/csrf');
    await expect(result).rejects.toThrow('expired');
    expect(store.state()).toEqual({ kind: 'anonymous' });
  });

  it('rejects a semantically invalid login challenge as a contract failure', async () => {
    const result = firstValueFrom(
      auth.login({ email: 'patient@example.test', password: 'synthetic-password' }),
    );

    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/login').flush({
      ...mfaChallengeResponse,
      data: {
        ...mfaChallengeResponse.data,
        intent: 'step_up',
        purpose: 'patient_data_export',
      },
    });

    await expect(result).rejects.toBeInstanceOf(ApiContractError);
    expect(store.state()).toEqual({ kind: 'anonymous' });
  });

  it('loads the minimum MFA status without accepting secret material', async () => {
    const result = firstValueFrom(auth.getMfaStatus());
    const request = httpTesting.expectOne('/api/v1/auth/mfa');
    expect(request.request.method).toBe('GET');
    request.flush({
      data: {
        enabled: true,
        method: 'totp',
        status: 'active',
        confirmed_at: '2026-07-19T06:30:00+00:00',
        recovery_codes_remaining: 8,
      },
      meta: { request_id: mfaChallengeResponse.meta.request_id },
    });

    await expect(result).resolves.toEqual({
      enabled: true,
      method: 'totp',
      status: 'active',
      confirmedAt: '2026-07-19T06:30:00+00:00',
      recoveryCodesRemaining: 8,
      requestId: mfaChallengeResponse.meta.request_id,
    });
  });

  it('starts enrollment and validates a script-free one-use SVG disclosure', async () => {
    const enrollment = firstValueFrom(auth.beginTotpEnrollment());
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/mfa/totp/enrollments').flush(
      {
        data: {
          method: 'totp',
          status: 'pending',
          expires_at: '2099-07-19T06:40:00+00:00',
          qr_disclosure_required: true,
        },
        meta: { request_id: mfaChallengeResponse.meta.request_id },
      },
      { status: 201, statusText: 'Created' },
    );

    await expect(enrollment).resolves.toMatchObject({
      method: 'totp',
      status: 'pending',
      qrDisclosureRequired: true,
    });

    const disclosure = firstValueFrom(auth.discloseTotpEnrollmentQr());
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    const qr = httpTesting.expectOne('/api/v1/auth/mfa/totp/enrollment-qr-disclosures');
    expect(qr.request.headers.get('Accept')).toBe('image/svg+xml');
    qr.flush(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="0" y="0" width="10" height="10" fill="#fff"/><path d="M1 1h8v8H1z" fill="#082a50"/></svg>',
      { headers: { 'Content-Type': 'image/svg+xml; charset=UTF-8' } },
    );

    await expect(disclosure).resolves.toContain('<svg');
  });

  it('rejects executable markup in a TOTP QR disclosure', async () => {
    const disclosure = firstValueFrom(auth.discloseTotpEnrollmentQr());
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting
      .expectOne('/api/v1/auth/mfa/totp/enrollment-qr-disclosures')
      .flush('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', {
        headers: { 'Content-Type': 'image/svg+xml' },
      });

    await expect(disclosure).rejects.toBeInstanceOf(ApiContractError);
  });

  it('confirms enrollment, returns recovery codes once, and stores only the AAL2 session', async () => {
    const recoveryCodes = '23456789ABCDEFGHJKMNPQRSTVWXYZ'
      .slice(0, 10)
      .split('')
      .map((suffix) => `234567-89ABCD-EFGHJK-MNPQR${suffix}`);
    const confirmation = firstValueFrom(auth.confirmTotpEnrollment('123456'));
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    const request = httpTesting.expectOne('/api/v1/auth/mfa/totp/enrollment-confirmations');
    expect(request.request.body).toEqual({ code: '123456' });
    request.flush({
      data: {
        method: 'totp',
        status: 'active',
        recovery_codes: recoveryCodes,
      },
      meta: { request_id: mfaChallengeResponse.meta.request_id },
    });
    httpTesting.expectOne('/api/v1/auth/session').flush(aal2SessionResponse);

    await expect(confirmation).resolves.toEqual({
      codes: recoveryCodes,
      requestId: mfaChallengeResponse.meta.request_id,
    });
    expect(store.state()).toMatchObject({
      kind: 'authenticated',
      session: { authentication: { level: 'aal2' } },
    });
    expect(JSON.stringify(store.state())).not.toContain(recoveryCodes[0]);
  });

  it('keeps authentication failures uniform and leaves no local session', async () => {
    const result = firstValueFrom(
      auth.login({ email: 'unknown@example.test', password: 'wrong-password' }),
    );
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    httpTesting.expectOne('/api/v1/auth/login').flush(unauthenticatedProblem, {
      status: 401,
      statusText: 'Unauthorized',
    });

    await expect(result).rejects.toBeInstanceOf(ApiProblemError);
    expect(store.state()).toEqual({ kind: 'anonymous' });
    httpTesting.expectNone('/api/v1/auth/session');
  });

  it('treats an unauthenticated session refresh as a normal anonymous state', async () => {
    const result = firstValueFrom(auth.refresh());
    httpTesting.expectOne('/api/v1/auth/session').flush(unauthenticatedProblem, {
      status: 401,
      statusText: 'Unauthorized',
    });

    await expect(result).resolves.toBeUndefined();
    expect(store.state()).toEqual({ kind: 'anonymous' });
  });

  it('refreshes CSRF for logout and clears memory after server invalidation', async () => {
    store.markAuthenticated({
      identity: {
        id: '018f47a2-4f4a-7b0f-8b15-9f82558b5924',
        displayName: 'Laura Demo',
      },
      authentication: {
        method: 'password',
        level: 'aal1',
        authenticatedAt: '2026-07-19T06:30:00+00:00',
      },
      capabilities: ['session:read', 'session:logout'],
      requestId: '018f47a2-4f4a-7b0f-8b15-9f82558b5925',
    });

    const result = firstValueFrom(auth.logout());
    httpTesting
      .expectOne('/api/v1/auth/csrf')
      .flush(null, { status: 204, statusText: 'No Content' });
    const logout = httpTesting.expectOne('/api/v1/auth/logout');
    expect(logout.request.method).toBe('POST');
    expect(logout.request.body).toBeNull();
    logout.flush(null, { status: 204, statusText: 'No Content' });

    await expect(result).resolves.toBeUndefined();
    expect(store.state()).toEqual({ kind: 'anonymous' });
  });
});
