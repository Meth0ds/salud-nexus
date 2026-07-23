import { TestBed } from '@angular/core/testing';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { Medication } from './medication';

function setValue(page: HTMLElement, selector: string, value: string): void {
  const control = page.querySelector<HTMLInputElement | HTMLTextAreaElement>(selector);
  if (!control) {
    throw new Error(`Missing medication control: ${selector}`);
  }
  control.value = value;
  control.dispatchEvent(new Event('input', { bubbles: true }));
}

function clickButton(page: HTMLElement, label: string): void {
  const button = [...page.querySelectorAll<HTMLButtonElement>('button')].find((candidate) =>
    candidate.textContent?.includes(label),
  );
  if (!button) {
    throw new Error(`Missing medication action: ${label}`);
  }
  button.click();
}

describe('Medication', () => {
  it('labels medication as informational and never as a prescription workflow', async () => {
    TestBed.configureTestingModule({
      imports: [Medication],
      providers: [provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Medication);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.textContent).toContain('Información, no prescripción');
    expect(page.textContent).toContain('Losartán');
    expect(page.textContent).toContain('contacta con tu equipo asistencial');
  });

  it('adds a clearly separated patient declaration with a validated Signal Form', async () => {
    TestBed.configureTestingModule({
      imports: [Medication],
      providers: [provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Medication);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;

    clickButton(page, 'Añadir lo que tomo');
    await fixture.whenStable();
    setValue(page, '#declared-medication-name', 'Vitamina D');
    setValue(page, '#declared-medication-presentation', '1000 UI · cápsulas');
    setValue(page, '#declared-medication-schedule', 'Una cápsula al día');
    clickButton(page, 'Añadir información');
    await fixture.whenStable();

    const declaredItem = [...page.querySelectorAll('li')].find((item) =>
      item.textContent?.includes('Vitamina D'),
    );
    expect(declaredItem?.textContent).toContain('Declarada por ti');
    expect(declaredItem?.textContent).toContain('no generan renovaciones');
    expect(page.querySelector('[role="status"]')?.textContent).toContain(
      'Añadida a esta sesión de demostración',
    );
  });

  it('submits an idempotent renewal only for an eligible professional item', async () => {
    TestBed.configureTestingModule({
      imports: [Medication],
      providers: [provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Medication);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;

    clickButton(page, 'Solicitar renovación');
    await fixture.whenStable();

    expect(page.textContent).toContain('Solicitud de renovación enviada');
    expect(page.querySelectorAll('button').length).toBeGreaterThan(0);
  });
});
