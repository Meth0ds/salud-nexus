import { HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import {
  ApiClient,
  ApiContractError,
  ApiProblemError,
  emptyResponseSchema,
  MfaChallengeEnvelope as GeneratedMfaChallengeEnvelopeSchema,
  MfaStatusEnvelope as GeneratedMfaStatusEnvelopeSchema,
  RecoveryCodesEnvelope as GeneratedRecoveryCodesEnvelopeSchema,
  SessionEnvelope as GeneratedSessionEnvelopeSchema,
  TotpEnrollmentEnvelope as GeneratedTotpEnrollmentEnvelopeSchema,
  type MfaChallengeEnvelopeOutput as ApiMfaChallengeEnvelope,
  type MfaStatusEnvelopeOutput as ApiMfaStatusEnvelope,
  type MfaStepUpPurpose,
  type MfaVerificationMethod,
  type RecoveryCodesEnvelopeOutput as ApiRecoveryCodesEnvelope,
  type RuntimeSchema,
  type SessionEnvelopeOutput as ApiSessionEnvelope,
  type TotpEnrollmentEnvelopeOutput as ApiTotpEnrollmentEnvelope,
} from 'api-client';
import { catchError, concatMap, map, of, tap, throwError, type Observable } from 'rxjs';

type AuthenticationMethod = 'password' | 'password+recovery' | 'password+totp';
type AuthenticationLevel = 'aal1' | 'aal2';
type LoginResponse = ApiMfaChallengeEnvelope | undefined;

const sessionEnvelopeSchema: RuntimeSchema<ApiSessionEnvelope> = {
  safeParse(value) {
    const result = GeneratedSessionEnvelopeSchema.safeParse(value);

    if (
      !result.success ||
      result.data.data.authenticated !== true ||
      new Set(result.data.data.capabilities).size !== result.data.data.capabilities.length
    ) {
      return { success: false };
    }

    return { success: true, data: result.data };
  },
};

const loginResponseSchema: RuntimeSchema<LoginResponse> = {
  safeParse(value) {
    if (value === null) {
      return { success: true, data: undefined };
    }

    const result = GeneratedMfaChallengeEnvelopeSchema.safeParse(value);
    if (
      !result.success ||
      result.data.data.intent !== 'login' ||
      result.data.data.purpose !== null ||
      !Number.isSafeInteger(result.data.data.attempts_remaining) ||
      new Set(result.data.data.methods).size !== result.data.data.methods.length
    ) {
      return { success: false };
    }

    return { success: true, data: result.data };
  },
};

const mfaStatusEnvelopeSchema: RuntimeSchema<ApiMfaStatusEnvelope> = {
  safeParse(value) {
    const result = GeneratedMfaStatusEnvelopeSchema.safeParse(value);

    if (!result.success) {
      return { success: false };
    }

    const status = result.data.data;
    const hasMethod = status.method === 'totp' && status.status !== null;
    const isActive = status.status === 'active';

    if (
      status.enabled !== isActive ||
      (status.method === null) !== (status.status === null) ||
      (!hasMethod && status.confirmed_at !== null) ||
      (!isActive && status.recovery_codes_remaining !== 0) ||
      !Number.isSafeInteger(status.recovery_codes_remaining)
    ) {
      return { success: false };
    }

    return { success: true, data: result.data };
  },
};

const totpEnrollmentEnvelopeSchema: RuntimeSchema<ApiTotpEnrollmentEnvelope> = {
  safeParse(value) {
    const result = GeneratedTotpEnrollmentEnvelopeSchema.safeParse(value);

    return result.success && result.data.data.qr_disclosure_required === true
      ? { success: true, data: result.data }
      : { success: false };
  },
};

const recoveryCodesEnvelopeSchema: RuntimeSchema<ApiRecoveryCodesEnvelope> = {
  safeParse(value) {
    const result = GeneratedRecoveryCodesEnvelopeSchema.safeParse(value);

    if (
      !result.success ||
      new Set(result.data.data.recovery_codes).size !== result.data.data.recovery_codes.length
    ) {
      return { success: false };
    }

    return { success: true, data: result.data };
  },
};

export interface LoginCredentials {
  readonly email: string;
  readonly password: string;
}

export interface MfaChallengeView {
  readonly id: string;
  readonly intent: 'login' | 'step_up';
  readonly purpose: MfaStepUpPurpose | null;
  readonly methods: readonly MfaVerificationMethod[];
  readonly expiresAt: string;
  readonly attemptsRemaining: number;
  readonly requestId: string;
}

export type MfaChallengeVerification =
  | { readonly method: 'totp'; readonly code: string }
  | { readonly method: 'recovery'; readonly code: string };

export interface SessionView {
  readonly identity: {
    readonly id: string;
    readonly displayName: string;
  };
  readonly authentication: {
    readonly method: AuthenticationMethod;
    readonly level: AuthenticationLevel;
    readonly authenticatedAt: string;
  };
  readonly capabilities: readonly string[];
  readonly requestId: string;
}

export interface MfaStatusView {
  readonly enabled: boolean;
  readonly method: 'totp' | null;
  readonly status: 'active' | 'disabled' | 'pending' | null;
  readonly confirmedAt: string | null;
  readonly recoveryCodesRemaining: number;
  readonly requestId: string;
}

export interface TotpEnrollmentView {
  readonly method: 'totp';
  readonly status: 'pending';
  readonly expiresAt: string;
  readonly qrDisclosureRequired: true;
  readonly requestId: string;
}

export interface RecoveryCodesView {
  readonly codes: readonly string[];
  readonly requestId: string;
}

export type LoginOutcome =
  | { readonly kind: 'authenticated'; readonly session: SessionView }
  | { readonly kind: 'mfa-required'; readonly challenge: MfaChallengeView };

export type SessionState =
  | { readonly kind: 'unknown' }
  | { readonly kind: 'loading' }
  | { readonly kind: 'anonymous' }
  | { readonly kind: 'mfa-required'; readonly challenge: MfaChallengeView }
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
  readonly pendingMfaChallenge = computed(() => {
    const state = this.stateSignal();
    return state.kind === 'mfa-required' ? state.challenge : undefined;
  });

  markLoading(): void {
    this.stateSignal.set({ kind: 'loading' });
  }

  markAnonymous(): void {
    this.stateSignal.set({ kind: 'anonymous' });
  }

  markMfaRequired(challenge: MfaChallengeView): void {
    this.stateSignal.set({ kind: 'mfa-required', challenge });
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

  login(credentials: LoginCredentials): Observable<LoginOutcome> {
    this.store.markLoading();

    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.post(
          '/auth/login',
          { email: credentials.email.trim(), password: credentials.password },
          loginResponseSchema,
        ),
      ),
      concatMap((challengeEnvelope): Observable<LoginOutcome> => {
        if (challengeEnvelope !== undefined) {
          return of({
            kind: 'mfa-required',
            challenge: toMfaChallengeView(challengeEnvelope),
          });
        }

        return this.loadSession().pipe(
          map((session): LoginOutcome => ({ kind: 'authenticated', session })),
        );
      }),
      tap((outcome) => {
        if (outcome.kind === 'mfa-required') {
          this.store.markMfaRequired(outcome.challenge);
        } else {
          this.store.markAuthenticated(outcome.session);
        }
      }),
      catchError((error: unknown) => {
        this.store.markAnonymous();
        return throwError(() => error);
      }),
    );
  }

  verifyMfaChallenge(verification: MfaChallengeVerification): Observable<SessionView> {
    const state = this.store.state();

    if (state.kind !== 'mfa-required') {
      return throwError(() => new Error('A pending MFA challenge is required.'));
    }

    const challenge = state.challenge;
    const expiresAt = Date.parse(challenge.expiresAt);

    if (!Number.isFinite(expiresAt) || expiresAt <= Date.now()) {
      this.store.markAnonymous();
      return throwError(() => new Error('The pending MFA challenge has expired.'));
    }

    if (!challenge.methods.includes(verification.method)) {
      return throwError(() => new Error('The selected MFA method is not available.'));
    }

    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.post(
          '/auth/mfa/challenge-verifications',
          {
            challenge_id: challenge.id,
            method: verification.method,
            code: verification.code,
          },
          emptyResponseSchema,
        ),
      ),
      concatMap(() => this.loadSession()),
      tap((session) => this.store.markAuthenticated(session)),
      catchError((error: unknown) => {
        this.store.markMfaRequired(challenge);
        return throwError(() => error);
      }),
    );
  }

  abandonMfaChallenge(): void {
    if (this.store.state().kind === 'mfa-required') {
      this.store.markAnonymous();
    }
  }

  getMfaStatus(): Observable<MfaStatusView> {
    return this.api.get('/auth/mfa', mfaStatusEnvelopeSchema).pipe(
      map((envelope) => ({
        enabled: envelope.data.enabled,
        method: envelope.data.method,
        status: envelope.data.status,
        confirmedAt: envelope.data.confirmed_at,
        recoveryCodesRemaining: envelope.data.recovery_codes_remaining,
        requestId: envelope.meta.request_id,
      })),
    );
  }

  beginTotpEnrollment(): Observable<TotpEnrollmentView> {
    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.post('/auth/mfa/totp/enrollments', undefined, totpEnrollmentEnvelopeSchema),
      ),
      map((envelope) => ({
        method: envelope.data.method,
        status: envelope.data.status,
        expiresAt: envelope.data.expires_at,
        qrDisclosureRequired: true as const,
        requestId: envelope.meta.request_id,
      })),
    );
  }

  discloseTotpEnrollmentQr(): Observable<string> {
    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.postText('/auth/mfa/totp/enrollment-qr-disclosures', 'image/svg+xml', 262_144),
      ),
      map((svg) => {
        if (!isSafeQrSvg(svg)) {
          throw new ApiContractError();
        }

        return svg;
      }),
    );
  }

  confirmTotpEnrollment(code: string): Observable<RecoveryCodesView> {
    return this.establishCsrf().pipe(
      concatMap(() =>
        this.api.post(
          '/auth/mfa/totp/enrollment-confirmations',
          { code },
          recoveryCodesEnvelopeSchema,
        ),
      ),
      concatMap((envelope) =>
        this.loadSession().pipe(
          tap((session) => this.store.markAuthenticated(session)),
          map(() => ({
            codes: envelope.data.recovery_codes,
            requestId: envelope.meta.request_id,
          })),
        ),
      ),
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
      tap(() => this.store.markAnonymous()),
      catchError((error: unknown) => {
        this.store.markAnonymous();
        return throwError(() => error);
      }),
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

function toMfaChallengeView(envelope: ApiMfaChallengeEnvelope): MfaChallengeView {
  return {
    id: envelope.data.challenge_id,
    intent: envelope.data.intent,
    purpose: envelope.data.purpose,
    methods: envelope.data.methods,
    expiresAt: envelope.data.expires_at,
    attemptsRemaining: envelope.data.attempts_remaining,
    requestId: envelope.meta.request_id,
  };
}

function toSessionView(envelope: ApiSessionEnvelope): SessionView {
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

function isSafeQrSvg(svg: string): boolean {
  if (
    svg.length < 20 ||
    /<!DOCTYPE|<!ENTITY|<\?xml-stylesheet/iu.test(svg) ||
    typeof DOMParser === 'undefined'
  ) {
    return false;
  }

  const document = new DOMParser().parseFromString(svg, 'image/svg+xml');
  const root = document.documentElement;
  const elements = [root, ...Array.from(root.querySelectorAll('*'))];
  const allowedElements = new Set(['g', 'path', 'rect', 'svg']);
  const allowedAttributes = new Set([
    'd',
    'fill',
    'height',
    'opacity',
    'preserveaspectratio',
    'shape-rendering',
    'stroke',
    'stroke-width',
    'transform',
    'viewbox',
    'width',
    'x',
    'xmlns',
    'y',
  ]);

  if (
    root.localName !== 'svg' ||
    root.namespaceURI !== 'http://www.w3.org/2000/svg' ||
    document.querySelector('parsererror') !== null ||
    elements.length > 4_096
  ) {
    return false;
  }

  for (const element of elements) {
    if (!allowedElements.has(element.localName)) {
      return false;
    }

    for (const attribute of Array.from(element.attributes)) {
      const name = attribute.name.toLowerCase();

      if (
        !allowedAttributes.has(name) ||
        name.startsWith('on') ||
        (name !== 'xmlns' && /(?:javascript|data|https?):|url\s*\(/iu.test(attribute.value))
      ) {
        return false;
      }
    }

    for (const node of Array.from(element.childNodes)) {
      if (node.nodeType === Node.TEXT_NODE && node.textContent?.trim() !== '') {
        return false;
      }
    }
  }

  return true;
}
