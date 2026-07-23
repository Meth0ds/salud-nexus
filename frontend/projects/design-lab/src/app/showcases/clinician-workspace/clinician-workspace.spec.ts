import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ClinicianWorkspace } from './clinician-workspace';

describe('ClinicianWorkspace', () => {
  let fixture: ComponentFixture<ClinicianWorkspace>;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    fixture = TestBed.createComponent(ClinicianWorkspace);
  });

  it('identifies all content as demonstrative and exposes professional landmarks', async () => {
    await fixture.whenStable();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.querySelector('main')).not.toBeNull();
    expect(host.querySelector('nav[aria-label="Navegación profesional"]')).not.toBeNull();
    expect(host.textContent).toContain('Entorno de demostración');
    expect(host.textContent).toContain('Todos los datos son ficticios');
  });

  it('changes the selected appointment and its context', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const appointmentButtons = host.querySelectorAll<HTMLButtonElement>('.appointment');

    appointmentButtons.item(2).click();
    await fixture.whenStable();

    expect(appointmentButtons.item(2).getAttribute('aria-pressed')).toBe('true');
    expect(host.querySelector('#context-title')?.textContent).toContain('DEMO-4096');
    expect(
      Array.from(host.querySelectorAll('[aria-live="polite"]')).some((region) =>
        region.textContent?.includes('DEMO-4096'),
      ),
    ).toBe(true);
  });

  it('collapses and restores the minimum patient context', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const toggle = host.querySelector<HTMLButtonElement>('[aria-controls="patient-context"]');

    toggle?.click();
    await fixture.whenStable();
    expect(host.querySelector('#patient-context')).toBeNull();

    toggle?.click();
    await fixture.whenStable();
    expect(host.querySelector('#patient-context')).not.toBeNull();
  });
});
