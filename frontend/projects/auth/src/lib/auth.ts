import { HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import {
  ApiClient,
  ApiProblemError,
  emptyResponseSchema,
  type RuntimeSchema,
  type RuntimeSchemaResult,
} from 'api-client';
import { catchError, concatMap, finalize, map, of, tap, throwError, type Observable } from 'rxjs';

const UUID_V7_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const CAPABILITY_PATTERN = /^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/;
const OFFSET_DATETIME_PATTERN =
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/;

interface SessionEnvelope {
  readonly data: {
    readonly authenticated: true;
    readonly identity: {
      readonly id: string;
      readonly display_name: string;
    };
    readonly authentication: {
      readonly method: 'oidc' | 'password' | 'webauthn';
      readonly level: 'aal1' | 'aal2' | 'aal3';
      readonly authenticated_at: string;
    };
    readonly capabilities: readonly string[];
  };
  readonly meta: {
    readonly request_id: string;
  };
}

const sessionEnvelopeSchema: RuntimeSchema<SessionEnvelope> = {
  safeParse: parseSessionEnvelope,
};

export interface LoginCredentials {
  readonly email: string;
  readonly password: string;
}

export interface SessionView {
  readonly identity: {
    readonly id: string;
    readonly displayName: string;
  };
  readonly authentication: {
    readonly method: 'oidc' | 'password' | 'webauthn';
    readonly level: 'aal1' | 'aal2' | 'aal3';
    readonly authenticatedAt: string;
  };
  readonly capabilities: readonly string[];
  readonly requestId: string;
}

export type SessionState =
  | { readonly kind: 'unknown' }
  | { readonly kind: 'loading' }
  | { readonly kind: 'anonymous' }
  | { readonly kind: 'authenticated'; readonly session: SessionView }
  | { readonly kind: 'error'; readonly requestId?: string };

@Injectable({ providedIn: 'root' })
export class SessionStore {
  private readonly stateSignal = signal<SessionState>({ kind: 'unknown' });

  readonly state = this.stateSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.stateSignal().kind === 'authenticated');
  readonly session = computed(() => {
    const state = this.stateSignal();
    return state.kind === 'authenticated' ? state.session : undefined;
  });

  markLoading(): void {
    this.stateSignal.set({ kind: 'loading' });
  }

  markAnonymous(): void {
    this.stateSignal.set({ kind: 'anonymous' });
  }

  markAuthenticated(session: SessionView): void {
    this.stateSignal.set({ kind: 'authenticated', session });
  }

  markError(requestId?: string): void {
    this.stateSignal.set({ kind: 'error', requestId });
  }
}

@Injectable({ providedIn: 'root' })
export class SessionAuth {
  private readonly api = inject(ApiClient);
  private readonly store = inject(SessionStore);

  login(credentials: LoginCredentials): Observable<SessionView> {
    this.store.markLoading();

    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.post(
          '/auth/login',
          { email: credentials.email.trim(), password: credentials.password },
          emptyResponseSchema,
        ),
      ),
      concatMap(() => this.loadSession()),
      tap((session) => this.store.markAuthenticated(session)),
      catchError((error: unknown) => {
        this.store.markAnonymous();
        return throwError(() => error);
      }),
    );
  }

  refresh(): Observable<SessionView | undefined> {
    this.store.markLoading();

    return this.loadSession().pipe(
      tap((session) => this.store.markAuthenticated(session)),
      catchError((error: unknown) => {
        if (error instanceof ApiProblemError && error.status === 401) {
          this.store.markAnonymous();
          return of(undefined);
        }

        this.store.markError(requestIdOf(error));
        return throwError(() => error);
      }),
    );
  }

  logout(): Observable<void> {
    return this.establishCsrf().pipe(
      concatMap(() => this.api.post('/auth/logout', undefined, emptyResponseSchema)),
      finalize(() => this.store.markAnonymous()),
    );
  }

  private establishCsrf(): Observable<void> {
    return this.api.get('/auth/csrf', emptyResponseSchema);
  }

  private loadSession(): Observable<SessionView> {
    return this.api.get('/auth/session', sessionEnvelopeSchema).pipe(map(toSessionView));
  }
}

export const sessionExpiryInterceptor: HttpInterceptorFn = (request, next) => {
  const store = inject(SessionStore);

  return next(request).pipe(
    tap({
      error: (error: unknown) => {
        if (error instanceof HttpErrorResponse && error.status === 401) {
          store.markAnonymous();
        }
      },
    }),
  );
};

export const authenticatedSessionGuard: CanActivateFn = () => {
  const store = inject(SessionStore);

  return store.isAuthenticated() ? true : inject(Router).parseUrl('/iniciar-sesion');
};

function toSessionView(envelope: SessionEnvelope): SessionView {
  return {
    identity: {
      id: envelope.data.identity.id,
      displayName: envelope.data.identity.display_name,
    },
    authentication: {
      method: envelope.data.authentication.method,
      level: envelope.data.authentication.level,
      authenticatedAt: envelope.data.authentication.authenticated_at,
    },
    capabilities: envelope.data.capabilities,
    requestId: envelope.meta.request_id,
  };
}

function requestIdOf(error: unknown): string | undefined {
  return error instanceof ApiProblemError ? error.requestId : undefined;
}

function parseSessionEnvelope(value: unknown): RuntimeSchemaResult<SessionEnvelope> {
  if (!isRecord(value)) {
    return { success: false };
  }

  const data = value['data'];
  const meta = value['meta'];
  if (!isRecord(data) || !isRecord(meta)) {
    return { success: false };
  }

  const identity = data['identity'];
  const authentication = data['authentication'];
  const capabilities = data['capabilities'];
  const requestId = meta['request_id'];
  if (
    data['authenticated'] !== true ||
    !isRecord(identity) ||
    !isRecord(authentication) ||
    !Array.isArray(capabilities) ||
    capabilities.length > 200 ||
    !capabilities.every(
      (capability) => typeof capability === 'string' && CAPABILITY_PATTERN.test(capability),
    ) ||
    typeof requestId !== 'string' ||
    !UUID_PATTERN.test(requestId)
  ) {
    return { success: false };
  }

  const id = identity['id'];
  const displayName = identity['display_name'];
  const method = authentication['method'];
  const level = authentication['level'];
  const authenticatedAt = authentication['authenticated_at'];
  if (
    typeof id !== 'string' ||
    !UUID_V7_PATTERN.test(id) ||
    typeof displayName !== 'string' ||
    displayName.length < 1 ||
    displayName.length > 160 ||
    !isAuthenticationMethod(method) ||
    !isAuthenticationLevel(level) ||
    typeof authenticatedAt !== 'string' ||
    !OFFSET_DATETIME_PATTERN.test(authenticatedAt) ||
    !Number.isFinite(Date.parse(authenticatedAt))
  ) {
    return { success: false };
  }

  return {
    success: true,
    data: {
      data: {
        authenticated: true,
        identity: { id, display_name: displayName },
        authentication: { method, level, authenticated_at: authenticatedAt },
        capabilities,
      },
      meta: { request_id: requestId },
    },
  };
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isAuthenticationMethod(value: unknown): value is SessionView['authentication']['method'] {
  return value === 'password' || value === 'oidc' || value === 'webauthn';
}

function isAuthenticationLevel(value: unknown): value is SessionView['authentication']['level'] {
  return value === 'aal1' || value === 'aal2' || value === 'aal3';
}
