import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatButtonHarness } from '@angular/material/button/testing';

import {
  SecurityOverview,
  STAFF_WORKSPACE_REPOSITORY,
  StaffWorkspaceRepository,
} from '../../core/staff-workspace.repository';
import { AdministrationPage } from './administration-page';

const OVERVIEW: SecurityOverview = {
  metrics: [
    {
      id: 'metric-test',
      label: 'MFA del personal',
      value: '99,2 %',
      trend: '+0,4 % esta semana',
      tone: 'positive',
    },
  ],
  alerts: [
    {
      id: 'alert-medium-test',
      title: 'Patrón de acceso para revisar',
      detail: 'Cinco intentos desde una estación autorizada.',
      timestamp: 'Hoy · 08:16',
      severity: 'medium',
    },
    {
      id: 'alert-high-test',
      title: 'Permiso temporal próximo a caducar',
      detail: 'Un acceso delegado requiere revisión.',
      timestamp: 'Hoy · 07:54',
      severity: 'high',
    },
  ],
};

function configure(overview: () => Promise<SecurityOverview>): void {
  const repository: StaffWorkspaceRepository = {
    getSchedule: async () => [],
    getReceptionQueue: async () => [],
    getSecurityOverview: overview,
    checkIn: async () => Promise.reject(new Error('not used')),
  };
  TestBed.configureTestingModule({
    imports: [AdministrationPage],
    providers: [{ provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository }],
  });
}

describe('AdministrationPage', () => {
  it('shows security posture and synthetic alerts', async () => {
    configure(async () => OVERVIEW);
    const fixture = TestBed.createComponent(AdministrationPage);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('.metrics')?.textContent).toContain(
      'MFA del personal',
    );
    expect(fixture.nativeElement.querySelector('.metrics')?.textContent).toContain('99,2 %');
    expect(fixture.nativeElement.querySelector('.alerts')?.textContent).toContain(
      'Patrón de acceso para revisar',
    );
  });

  it('filters high priority alerts and opens a minimised detail panel', async () => {
    configure(async () => OVERVIEW);
    const fixture = TestBed.createComponent(AdministrationPage);
    const loader = TestbedHarnessEnvironment.loader(fixture);
    await fixture.whenStable();
    const highOnly = await loader.getHarness(
      MatButtonHarness.with({ text: /Solo prioridad alta/ }),
    );

    await highOnly.click();
    const alertItems = fixture.nativeElement.querySelectorAll('.alert-item');
    expect(alertItems).toHaveLength(1);
    expect(alertItems[0]?.textContent).toContain('Permiso temporal próximo a caducar');

    const review = await loader.getHarness(MatButtonHarness.with({ text: /Revisar/ }));
    await review.click();
    expect(fixture.nativeElement.querySelector('.alert-detail')?.textContent).toContain(
      'Datos sintéticos de demostración',
    );
  });

  it('uses a reassuring empty state when there is no activity in scope', async () => {
    configure(async () => ({ metrics: [], alerts: [] }));
    const fixture = TestBed.createComponent(AdministrationPage);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('[data-state="empty"]')?.textContent).toContain(
      'Sin actividad para este alcance',
    );
  });
});
