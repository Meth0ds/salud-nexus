import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SecurityCenter } from './security-center';

describe('SecurityCenter', () => {
  let fixture: ComponentFixture<SecurityCenter>;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    fixture = TestBed.createComponent(SecurityCenter);
  });

  it('labels the environment and audit data as synthetic', async () => {
    await fixture.whenStable();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.querySelector('main')).not.toBeNull();
    expect(
      host.querySelector('nav[aria-label="Navegación del centro de seguridad"]'),
    ).not.toBeNull();
    expect(host.textContent).toContain('Solo datos sintéticos');
    expect(host.textContent).toContain('no contienen información sanitaria real');
  });

  it('filters the audit table by severity', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const criticalFilter = Array.from(
      host.querySelectorAll<HTMLButtonElement>('.filters button'),
    ).find((button) => button.textContent?.trim() === 'Crítico');

    criticalFilter?.click();
    await fixture.whenStable();

    expect(host.querySelectorAll('tbody tr')).toHaveLength(1);
    expect(host.querySelector('tbody')?.textContent).toContain('Acceso excepcional rechazado');
    expect(
      Array.from(host.querySelectorAll('[aria-live="polite"]')).some((region) =>
        region.textContent?.includes('1 eventos visibles'),
      ),
    ).toBe(true);
  });

  it('opens another event and closes its detail panel', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const inspectButtons = host.querySelectorAll<HTMLButtonElement>('.inspect-button');

    inspectButtons.item(2).click();
    await fixture.whenStable();
    expect(host.querySelector('#event-detail-title')?.textContent).toContain('EVT-DEMO-832B');

    host.querySelector<HTMLButtonElement>('[aria-label="Cerrar detalle del evento"]')?.click();
    await fixture.whenStable();
    expect(host.querySelector('.event-detail')).toBeNull();
  });
});
