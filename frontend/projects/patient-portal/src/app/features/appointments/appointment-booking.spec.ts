import { ViewportScroller } from '@angular/common';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { AppointmentBooking } from './appointment-booking';

function selectValue(page: HTMLElement, selector: string, value: string): void {
  const select = page.querySelector<HTMLSelectElement>(selector);
  if (!select) {
    throw new Error(`Missing synthetic booking control: ${selector}`);
  }
  select.value = value;
  select.dispatchEvent(new Event('input', { bubbles: true }));
  select.dispatchEvent(new Event('change', { bubbles: true }));
}

function clickButton(page: HTMLElement, label: string): void {
  const button = [...page.querySelectorAll<HTMLButtonElement>('button')].find((candidate) =>
    candidate.textContent?.includes(label),
  );
  if (!button) {
    throw new Error(`Missing booking action: ${label}`);
  }
  button.click();
}

describe('AppointmentBooking', () => {
  it('completes the guided Signal Form and confirms an in-memory booking', async () => {
    const viewportScroller = { scrollToPosition: vi.fn() };
    TestBed.configureTestingModule({
      imports: [AppointmentBooking],
      providers: [
        provideRouter([]),
        provideDemoPatientRepository(),
        { provide: ViewportScroller, useValue: viewportScroller },
      ],
    });
    const fixture = TestBed.createComponent(AppointmentBooking);
    await fixture.whenStable();
    const page = fixture.nativeElement as HTMLElement;

    selectValue(page, '#booking-service', 'appointment_type_demo_internal');
    await fixture.whenStable();
    clickButton(page, 'Continuar');
    await fixture.whenStable();
    selectValue(page, '#booking-slot', 'slot_demo_20260729_1130');
    await fixture.whenStable();
    clickButton(page, 'Revisar cita');
    await fixture.whenStable();
    clickButton(page, 'Confirmar cita');
    await fixture.whenStable();

    expect(page.querySelector('[role="status"]')?.textContent).toContain(
      'Cita confirmada en la demo',
    );
    expect(page.textContent).toContain('Miércoles, 29 de julio de 2026');
    expect(viewportScroller.scrollToPosition).toHaveBeenCalledWith([0, 0]);
  });
});
