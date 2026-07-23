let nextId = 0;

/** Creates stable, human-readable relationships for labels inside one render tree. */
export function createComponentId(scope: string): string {
  nextId += 1;
  return `sn-${scope}-${nextId}`;
}
