import { describe, expect, it } from 'vitest';

import { AppointmentSelectionStore, PatientSessionStore } from './session.store';

describe('PatientSessionStore', () => {
  it('keeps the synthetic session in memory and removes it on sign out', () => {
    const store = new PatientSessionStore();

    store.open({
      displayName: 'Laura Martín',
      initials: 'LM',
      runtime: 'demo',
    });
    expect(store.isAuthenticated()).toBe(true);

    store.close();
    expect(store.isAuthenticated()).toBe(false);
    expect(store.session()).toBeUndefined();
  });
});

describe('AppointmentSelectionStore', () => {
  it('does not require a sensitive appointment identifier in the URL', () => {
    const store = new AppointmentSelectionStore();

    store.select('appointment_demo_q7V2mP');
    expect(store.selectedId()).toBe('appointment_demo_q7V2mP');

    store.clear();
    expect(store.selectedId()).toBeUndefined();
  });
});
