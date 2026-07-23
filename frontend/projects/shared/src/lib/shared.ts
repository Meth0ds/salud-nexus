const UUID_V7_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

declare const publicIdBrand: unique symbol;

export type PublicId = string & { readonly [publicIdBrand]: 'PublicId' };

export interface IdleResourceState {
  readonly kind: 'idle';
}

export interface LoadingResourceState {
  readonly kind: 'loading';
  readonly message?: string;
}

export interface ReadyResourceState<T> {
  readonly kind: 'ready';
  readonly data: T;
}

export interface EmptyResourceState {
  readonly kind: 'empty';
  readonly message?: string;
}

export interface ErrorResourceState {
  readonly kind: 'error';
  readonly message: string;
  readonly requestId?: string;
}

export interface RestrictedResourceState {
  readonly kind: 'restricted';
  readonly message: string;
}

export type ResourceState<T> =
  | IdleResourceState
  | LoadingResourceState
  | ReadyResourceState<T>
  | EmptyResourceState
  | ErrorResourceState
  | RestrictedResourceState;

export function parsePublicId(value: string): PublicId | undefined {
  const normalized = value.toLowerCase();

  return UUID_V7_PATTERN.test(normalized) ? (normalized as PublicId) : undefined;
}

export function mapResourceState<TSource, TResult>(
  state: ResourceState<TSource>,
  project: (value: TSource) => TResult,
): ResourceState<TResult> {
  switch (state.kind) {
    case 'ready':
      return { kind: 'ready', data: project(state.data) };
    case 'idle':
    case 'loading':
    case 'empty':
    case 'error':
    case 'restricted':
      return state;
  }
}

export function requestReference(value: string | undefined): string {
  const id = value === undefined ? undefined : parsePublicId(value);

  return id === undefined
    ? 'Referencia no disponible'
    : `Referencia ${id.slice(0, 8)}…${id.slice(-4)}`;
}

export function assertNever(value: never): never {
  throw new Error(`Unexpected variant: ${String(value)}`);
}
