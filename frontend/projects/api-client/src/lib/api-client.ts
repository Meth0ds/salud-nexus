import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import {
  inject,
  Injectable,
  InjectionToken,
  makeEnvironmentProviders,
  type EnvironmentProviders,
} from '@angular/core';
import { catchError, map, type Observable, throwError } from 'rxjs';

const DEFAULT_TIMEOUT_MS = 10_000;
const IDEMPOTENCY_KEY_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{15,127}$/;
const APPOINTMENT_ETAG_PATTERN = /^"v([1-9][0-9]{0,9})"$/;
const MAX_APPOINTMENT_VERSION = 4_294_967_295;
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const MEDIA_TYPE_PATTERN = /^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/;

export type RuntimeSchemaResult<T> =
  { readonly success: true; readonly data: T } | { readonly success: false };

export interface RuntimeSchema<T> {
  safeParse(value: unknown): RuntimeSchemaResult<T>;
}

export interface ApiProblem {
  readonly type: string;
  readonly title: string;
  readonly status: number;
  readonly detail: string;
  readonly instance: string;
  readonly request_id: string;
}

export const apiProblemSchema: RuntimeSchema<ApiProblem> = {
  safeParse: parseApiProblem,
};

export const emptyResponseSchema: RuntimeSchema<void> = {
  safeParse: (value) => (value === null ? { success: true, data: undefined } : { success: false }),
};

export interface ApiClientConfig {
  readonly baseUrl: string;
  readonly timeoutMs?: number;
}

interface NormalizedApiClientConfig {
  readonly baseUrl: string;
  readonly timeoutMs: number;
}

export interface ApiReadOptions {
  readonly params?: HttpParams;
}

export interface ApiMutationOptions extends ApiReadOptions {
  readonly idempotencyKey?: string;
  readonly ifMatch?: string;
}

export interface ApiValidatedResponse<T> {
  readonly data: T;
  readonly etag: string | undefined;
  readonly idempotencyReplayed: boolean | undefined;
}

const DEFAULT_CONFIG: NormalizedApiClientConfig = {
  baseUrl: '/api/v1',
  timeoutMs: DEFAULT_TIMEOUT_MS,
};

export const API_CLIENT_CONFIG = new InjectionToken<NormalizedApiClientConfig>(
  'SALUD_NEXUS_API_CLIENT_CONFIG',
  { factory: () => DEFAULT_CONFIG },
);

export class ApiProblemError extends Error {
  override readonly name = 'ApiProblemError';
  readonly requestId: string;
  readonly status: number;

  constructor(readonly problem: ApiProblem) {
    super(problem.title);
    this.requestId = problem.request_id;
    this.status = problem.status;
  }
}

export class ApiContractError extends Error {
  override readonly name = 'ApiContractError';

  constructor() {
    super('La respuesta del servicio no cumple el contrato esperado.');
  }
}

export class ApiTransportError extends Error {
  override readonly name = 'ApiTransportError';

  constructor(
    readonly status: number,
    readonly requestId?: string,
  ) {
    super(
      status === 0
        ? 'No se pudo conectar con el servicio.'
        : status >= 500
          ? 'El servicio no está disponible temporalmente.'
          : 'La solicitud no se pudo completar.',
    );
  }
}

export function provideApiClient(config: ApiClientConfig): EnvironmentProviders {
  return makeEnvironmentProviders([
    {
      provide: API_CLIENT_CONFIG,
      useValue: normalizeConfig(config),
    },
  ]);
}

@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);
  private readonly config = inject(API_CLIENT_CONFIG);

  get<T>(path: string, schema: RuntimeSchema<T>, options: ApiReadOptions = {}): Observable<T> {
    return this.request('GET', path, undefined, schema, options);
  }

  getResponse<T>(
    path: string,
    schema: RuntimeSchema<T>,
    options: ApiReadOptions = {},
  ): Observable<ApiValidatedResponse<T>> {
    return this.requestResponse('GET', path, undefined, schema, options);
  }

  post<TResponse, TBody>(
    path: string,
    body: TBody,
    schema: RuntimeSchema<TResponse>,
    options: ApiMutationOptions = {},
  ): Observable<TResponse> {
    return this.request('POST', path, body, schema, options);
  }

  postResponse<TResponse, TBody>(
    path: string,
    body: TBody,
    schema: RuntimeSchema<TResponse>,
    options: ApiMutationOptions = {},
  ): Observable<ApiValidatedResponse<TResponse>> {
    return this.requestResponse('POST', path, body, schema, options);
  }

  postText(path: string, expectedMediaType: string, maximumBytes: number): Observable<string> {
    const normalizedMediaType = expectedMediaType.trim().toLowerCase();

    if (!MEDIA_TYPE_PATTERN.test(normalizedMediaType)) {
      throw new Error('Expected one concrete response media type.');
    }

    if (!Number.isSafeInteger(maximumBytes) || maximumBytes < 1 || maximumBytes > 1_048_576) {
      throw new Error('The maximum text response size must be between 1 byte and 1 MiB.');
    }

    const url = `${this.config.baseUrl}${normalizeEndpointPath(path)}`;
    const headers = this.headers('POST').set('Accept', normalizedMediaType);

    return this.http
      .request('POST', url, {
        body: undefined,
        headers,
        timeout: this.config.timeoutMs,
        transferCache: false,
        withCredentials: true,
        observe: 'response',
        responseType: 'text',
      })
      .pipe(
        map((response) => {
          const body = response.body;
          const responseMediaType = response.headers
            .get('Content-Type')
            ?.split(';', 1)[0]
            ?.trim()
            .toLowerCase();

          if (
            typeof body !== 'string' ||
            responseMediaType !== normalizedMediaType ||
            new TextEncoder().encode(body).byteLength > maximumBytes
          ) {
            throw new ApiContractError();
          }

          return body;
        }),
        catchError((error: unknown) => throwError(() => normalizeError(error))),
      );
  }

  put<TResponse, TBody>(
    path: string,
    body: TBody,
    schema: RuntimeSchema<TResponse>,
    options: ApiMutationOptions = {},
  ): Observable<TResponse> {
    return this.request('PUT', path, body, schema, options);
  }

  patch<TResponse, TBody>(
    path: string,
    body: TBody,
    schema: RuntimeSchema<TResponse>,
    options: ApiMutationOptions = {},
  ): Observable<TResponse> {
    return this.request('PATCH', path, body, schema, options);
  }

  delete<T>(
    path: string,
    schema: RuntimeSchema<T>,
    options: ApiMutationOptions = {},
  ): Observable<T> {
    return this.request('DELETE', path, undefined, schema, options);
  }

  private request<T>(
    method: 'DELETE' | 'GET' | 'PATCH' | 'POST' | 'PUT',
    path: string,
    body: unknown,
    schema: RuntimeSchema<T>,
    options: ApiMutationOptions,
  ): Observable<T> {
    return this.requestResponse(method, path, body, schema, options).pipe(
      map((response) => response.data),
    );
  }

  private requestResponse<T>(
    method: 'DELETE' | 'GET' | 'PATCH' | 'POST' | 'PUT',
    path: string,
    body: unknown,
    schema: RuntimeSchema<T>,
    options: ApiMutationOptions,
  ): Observable<ApiValidatedResponse<T>> {
    const url = `${this.config.baseUrl}${normalizeEndpointPath(path)}`;
    const headers = this.headers(method, options.idempotencyKey, options.ifMatch);

    return this.http
      .request<unknown>(method, url, {
        body,
        headers,
        params: options.params,
        timeout: this.config.timeoutMs,
        transferCache: false,
        withCredentials: true,
        observe: 'response',
      })
      .pipe(
        map((response) => {
          const result = schema.safeParse(response.body);

          if (!result.success) {
            throw new ApiContractError();
          }

          const replayHeader = response.headers.get('Idempotency-Replayed');
          if (replayHeader !== null && replayHeader !== 'true' && replayHeader !== 'false') {
            throw new ApiContractError();
          }

          return {
            data: result.data,
            etag: response.headers.get('ETag') ?? undefined,
            idempotencyReplayed: replayHeader === null ? undefined : replayHeader === 'true',
          };
        }),
        catchError((error: unknown) => throwError(() => normalizeError(error))),
      );
  }

  private headers(method: string, idempotencyKey?: string, ifMatch?: string): HttpHeaders {
    let headers = new HttpHeaders({ Accept: 'application/json' });

    if (method !== 'GET' && method !== 'DELETE') {
      headers = headers.set('Content-Type', 'application/json');
    }

    if (idempotencyKey !== undefined) {
      if (!IDEMPOTENCY_KEY_PATTERN.test(idempotencyKey)) {
        throw new Error('The idempotency key must be 16–128 safe ASCII characters.');
      }
      headers = headers.set('Idempotency-Key', idempotencyKey);
    }

    if (ifMatch !== undefined) {
      const match = APPOINTMENT_ETAG_PATTERN.exec(ifMatch);
      const version = match ? Number(match[1]) : Number.NaN;
      if (!Number.isSafeInteger(version) || version > MAX_APPOINTMENT_VERSION) {
        throw new Error('The If-Match value must be one strong ETag for an appointment version.');
      }
      headers = headers.set('If-Match', ifMatch);
    }

    return headers;
  }
}

function normalizeConfig(config: ApiClientConfig): NormalizedApiClientConfig {
  const baseUrl = config.baseUrl.trim().replace(/\/+$/u, '');
  const timeoutMs = config.timeoutMs ?? DEFAULT_TIMEOUT_MS;

  if (!isAllowedBaseUrl(baseUrl)) {
    throw new Error('The API base URL must be same-origin, HTTPS, or local development HTTP.');
  }

  if (!Number.isSafeInteger(timeoutMs) || timeoutMs < 500 || timeoutMs > 60_000) {
    throw new Error('The API timeout must be an integer between 500 and 60000 milliseconds.');
  }

  return { baseUrl, timeoutMs };
}

function isAllowedBaseUrl(value: string): boolean {
  if (value.startsWith('/') && !value.startsWith('//')) {
    return !value.includes('?') && !value.includes('#') && !value.includes('\\');
  }

  try {
    const url = new URL(value);
    const isLocal = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);

    return (
      (url.protocol === 'https:' || (url.protocol === 'http:' && isLocal)) &&
      url.username === '' &&
      url.password === '' &&
      url.search === '' &&
      url.hash === ''
    );
  } catch {
    return false;
  }
}

function normalizeEndpointPath(path: string): string {
  if (!path.startsWith('/') || path.startsWith('//') || path.includes('?') || path.includes('#')) {
    throw new Error('Expected a relative API path beginning with one slash.');
  }

  let decodedPath: string;
  try {
    decodedPath = decodeURIComponent(path);
  } catch {
    throw new Error('Expected a valid relative API path.');
  }

  if (
    decodedPath.includes('\\') ||
    decodedPath.split('/').some((segment) => segment === '.' || segment === '..')
  ) {
    throw new Error('Expected a relative API path without traversal segments.');
  }

  return path;
}

function parseApiProblem(value: unknown): RuntimeSchemaResult<ApiProblem> {
  if (!isRecord(value)) {
    return { success: false };
  }

  const type = value['type'];
  const title = value['title'];
  const status = value['status'];
  const detail = value['detail'];
  const instance = value['instance'];
  const requestId = value['request_id'];

  if (
    typeof type !== 'string' ||
    !isHttpUrl(type) ||
    typeof title !== 'string' ||
    title.length < 1 ||
    title.length > 200 ||
    typeof status !== 'number' ||
    !Number.isInteger(status) ||
    status < 400 ||
    status > 599 ||
    typeof detail !== 'string' ||
    detail.length < 1 ||
    detail.length > 2_000 ||
    typeof instance !== 'string' ||
    !instance.startsWith('/') ||
    typeof requestId !== 'string' ||
    !UUID_PATTERN.test(requestId)
  ) {
    return { success: false };
  }

  return {
    success: true,
    data: {
      type,
      title,
      status,
      detail,
      instance,
      request_id: requestId.toLowerCase(),
    },
  };
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isHttpUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' || url.protocol === 'http:';
  } catch {
    return false;
  }
}

function normalizeError(error: unknown): Error {
  if (
    error instanceof ApiProblemError ||
    error instanceof ApiContractError ||
    error instanceof ApiTransportError
  ) {
    return error;
  }

  if (error instanceof HttpErrorResponse) {
    const problem = apiProblemSchema.safeParse(error.error);
    if (problem.success) {
      return new ApiProblemError(problem.data);
    }

    const requestId = error.headers.get('X-Request-ID') ?? undefined;
    return new ApiTransportError(error.status, requestId);
  }

  return new ApiTransportError(0);
}
