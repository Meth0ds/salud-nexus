import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { InMemoryStaffWorkspaceRepository } from './core/in-memory-staff-workspace.repository';
import { STAFF_WORKSPACE_REPOSITORY } from './core/staff-workspace.repository';
import { StaffWorkspaceStore } from './core/staff-workspace.store';
import { App } from './app';

describe('Staff portal shell', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [App],
      providers: [
        provideRouter([]),
        InMemoryStaffWorkspaceRepository,
        {
          provide: STAFF_WORKSPACE_REPOSITORY,
          useExisting: InMemoryStaffWorkspaceRepository,
        },
      ],
    });
  });

  it('provides a skip link, named navigation, and a protected session indicator', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector<HTMLAnchorElement>('.skip-link')?.getAttribute('href')).toBe(
      '#staff-main',
    );
    expect(element.querySelector('nav')?.getAttribute('aria-label')).toBe('Áreas de trabajo');
    expect(element.querySelector('nav')?.textContent).toContain('Agenda');
    expect(element.querySelector('nav')?.textContent).toContain('Recepción');
    expect(element.querySelector('nav')?.textContent).toContain('Administración');
    expect(element.querySelector('.session-state')?.textContent).toContain('Sesión protegida');
    expect(element.querySelector('.facility-context')?.textContent).toContain('Centro Atlántico');
  });

  it('changes the active staff context in memory', async () => {
    const fixture = TestBed.createComponent(App);
    const store = TestBed.inject(StaffWorkspaceStore);
    await fixture.whenStable();
    const contextSelect = fixture.nativeElement.querySelector(
      '#workspace-context',
    ) as HTMLSelectElement;

    contextSelect.value = 'ctx-reception-main';
    contextSelect.dispatchEvent(new Event('change'));
    await fixture.whenStable();

    expect(store.activeContext().role).toBe('reception');
    expect(fixture.nativeElement.querySelector('.role-tag')?.textContent).toContain('Recepción');
  });
});
