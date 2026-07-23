import { mapResourceState, parsePublicId, requestReference, type ResourceState } from './shared';

describe('shared domain primitives', () => {
  it('accepts only canonicalizable UUIDv7 public identifiers', () => {
    expect(parsePublicId('018F47A2-4F4A-7B0F-8B15-9F82558B5924')).toBe(
      '018f47a2-4f4a-7b0f-8b15-9f82558b5924',
    );

    for (const invalid of [
      '',
      '550e8400-e29b-41d4-a716-446655440000',
      '00000000-0000-0000-0000-000000000000',
      '018f47a2-4f4a-7b0f-7b15-9f82558b5924',
      '../018f47a2-4f4a-7b0f-8b15-9f82558b5924',
    ]) {
      expect(parsePublicId(invalid), invalid).toBeUndefined();
    }
  });

  it('maps ready data without collapsing loading, error or restricted states', () => {
    const ready: ResourceState<number> = { kind: 'ready', data: 3 };
    expect(mapResourceState(ready, (value) => `total:${value}`)).toEqual({
      kind: 'ready',
      data: 'total:3',
    });

    const restricted: ResourceState<number> = {
      kind: 'restricted',
      message: 'El contexto no permite mostrar este recurso.',
    };
    expect(mapResourceState(restricted, String)).toBe(restricted);
  });

  it('formats a safe support reference without exposing arbitrary input', () => {
    expect(requestReference('018f47a2-4f4a-7b0f-8b15-9f82558b5924')).toBe(
      'Referencia 018f47a2…5924',
    );
    expect(requestReference('<script>secret</script>')).toBe('Referencia no disponible');
    expect(requestReference(undefined)).toBe('Referencia no disponible');
  });
});
