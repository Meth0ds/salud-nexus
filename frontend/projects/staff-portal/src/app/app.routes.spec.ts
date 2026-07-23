import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { routes } from './app.routes';
import { InMemoryStaffWorkspaceRepository } from './core/in-memory-staff-workspace.repository';
import { STAFF_WORKSPACE_REPOSITORY } from './core/staff-workspace.repository';

describe('staff portal routes', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter(routes),
        InMemoryStaffWorkspaceRepository,
        {
          provide: STAFF_WORKSPACE_REPOSITORY,
          useExisting: InMemoryStaffWorkspaceRepository,
        },
      ],
    });
  });

  it.each([
    ['/agenda', 'Agenda clínica'],
    ['/recepcion', 'Recepción y llegada'],
    ['/administracion', 'Administración y seguridad'],
  ])('lazy loads %s with a specific page heading', async (url, heading) => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl(url);
    await harness.fixture.whenStable();

    expect(harness.fixture.nativeElement.querySelector('h1')?.textContent).toContain(heading);
  });
});
