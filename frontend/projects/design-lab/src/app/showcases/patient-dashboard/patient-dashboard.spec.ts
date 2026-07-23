import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PatientDashboard } from './patient-dashboard';

describe('PatientDashboard', () => {
  let fixture: ComponentFixture<PatientDashboard>;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    fixture = TestBed.createComponent(PatientDashboard);
  });

  it('presents the synthetic-data notice and core patient landmarks', async () => {
    await fixture.whenStable();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.querySelector('main')).not.toBeNull();
    expect(
      host.querySelector('nav[aria-label="Navegación del portal del paciente"]'),
    ).not.toBeNull();
    expect(host.textContent).toContain('Datos de demostración');
    expect(host.textContent).toContain('Hilo de cuidados');
  });

  it('updates the reminder switch through an accessible control', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const reminderSwitch = host.querySelector<HTMLButtonElement>('[role="switch"]');

    expect(reminderSwitch?.getAttribute('aria-checked')).toBe('true');
    reminderSwitch?.click();
    await fixture.whenStable();

    expect(reminderSwitch?.getAttribute('aria-checked')).toBe('false');
    expect(host.textContent).toContain('Recordatorios desactivados');
  });

  it('dismisses the privacy notice without removing the live announcement', async () => {
    await fixture.whenStable();
    const host: HTMLElement = fixture.nativeElement;
    const dismissButton = host.querySelector<HTMLButtonElement>(
      '[aria-label="Ocultar información de privacidad"]',
    );

    dismissButton?.click();
    await fixture.whenStable();

    expect(host.querySelector('[aria-label="Información de privacidad"]')).toBeNull();
    expect(host.querySelector('[aria-live="polite"]')?.textContent).toContain(
      'Aviso de privacidad ocultado',
    );
  });
});
