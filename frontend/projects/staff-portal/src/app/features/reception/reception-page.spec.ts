import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatButtonHarness } from '@angular/material/button/testing';
import { MatInputHarness } from '@angular/material/input/testing';

import {
  QueueEntry,
  SecurityOverview,
  STAFF_WORKSPACE_REPOSITORY,
  StaffWorkspaceRepository,
} from '../../core/staff-workspace.repository';
import { ReceptionPage } from './reception-page';

const QUEUE: readonly QueueEntry[] = [
  {
    id: 'queue-synthetic-test-1',
    position: 1,
    displayName: 'Bruno R. (demo)',
    maskedDocument: '*** 4821',
    arrivalTime: '08:42',
    waitMinutes: 8,
    status: 'waiting',
  },
  {
    id: 'queue-synthetic-test-2',
    position: 2,
    displayName: 'Ariadna C. (demo)',
    maskedDocument: '*** 9350',
    arrivalTime: '08:47',
    waitMinutes: 3,
    status: 'called',
  },
];

const EMPTY_SECURITY: SecurityOverview = { metrics: [], alerts: [] };

function configure(queue: () => Promise<readonly QueueEntry[]>): void {
  const repository: StaffWorkspaceRepository = {
    getSchedule: async () => [],
    getReceptionQueue: queue,
    getSecurityOverview: async () => EMPTY_SECURITY,
    checkIn: async (entryId) => ({
      ...QUEUE.find((entry) => entry.id === entryId)!,
      status: 'checked-in',
    }),
  };
  TestBed.configureTestingModule({
    imports: [ReceptionPage],
    providers: [{ provide: STAFF_WORKSPACE_REPOSITORY, useValue: repository }],
  });
}

describe('ReceptionPage', () => {
  it('filters the synthetic queue through a Signal Form', async () => {
    configure(async () => QUEUE);
    const fixture = TestBed.createComponent(ReceptionPage);
    const loader = TestbedHarnessEnvironment.loader(fixture);
    await fixture.whenStable();
    const search = await loader.getHarness(MatInputHarness.with({ selector: '#queue-search' }));

    await search.setValue('Bruno');
    const submit = await loader.getHarness(MatButtonHarness.with({ text: /Buscar/ }));
    await submit.click();

    const rows = fixture.nativeElement.querySelectorAll('tbody tr');
    expect(rows).toHaveLength(1);
    expect(rows[0]?.textContent).toContain('Bruno R. (demo)');
  });

  it('confirms check-in once and updates the visible queue status', async () => {
    configure(async () => QUEUE);
    const fixture = TestBed.createComponent(ReceptionPage);
    const loader = TestbedHarnessEnvironment.loader(fixture);
    await fixture.whenStable();
    const checkIn = await loader.getHarness(
      MatButtonHarness.with({ selector: '[data-check-in-id="queue-synthetic-test-1"]' }),
    );

    await checkIn.click();

    expect(
      fixture.nativeElement.querySelector('[data-entry-id="queue-synthetic-test-1"]')?.textContent,
    ).toContain('Admisión confirmada');
    expect(fixture.nativeElement.querySelector('.action-message')?.textContent).toContain(
      'Admisión registrada',
    );
  });

  it('renders a safe recovery action when the queue cannot load', async () => {
    configure(async () => Promise.reject(new Error('database detail')));
    const fixture = TestBed.createComponent(ReceptionPage);
    await fixture.whenStable();
    const errorState = fixture.nativeElement.querySelector('[data-state="error"]') as HTMLElement;

    expect(errorState.textContent).toContain('No se pudo actualizar la cola');
    expect(errorState.textContent).not.toContain('database detail');
  });
});
