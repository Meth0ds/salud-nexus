import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ApiProblemError, provideApiClient } from 'api-client';
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

    await expect(result).resolves.toMatchObject({ identity: { displayName: 'Laura Demo' } });
    expect(store.state().kind).toBe('authenticated');
    expect(JSON.stringify(store.state())).not.toContain('synthetic-password');
    expect(JSON.stringify(store.state())).not.toContain('Laura.Demo@SaludNexus.Test');
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
