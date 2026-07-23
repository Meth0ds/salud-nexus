import type { DemoScenario, ViewState } from './patient.models';

export function resolveDemoViewState<T>(
  scenario: DemoScenario,
  data: T | undefined,
  isEmpty: (value: T) => boolean,
): ViewState<T> {
  switch (scenario) {
    case 'loading':
      return { kind: 'loading' };
    case 'empty':
      return { kind: 'empty' };
    case 'error':
      return { kind: 'error', correlationId: 'demo-correlation-7Q2M' };
    case 'restricted':
      return { kind: 'restricted' };
    case 'ready':
      if (data === undefined) {
        return { kind: 'loading' };
      }
      return isEmpty(data) ? { kind: 'empty' } : { kind: 'ready', data };
  }
}
