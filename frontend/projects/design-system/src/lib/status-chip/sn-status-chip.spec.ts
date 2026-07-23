import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SnStatusChip } from './sn-status-chip';

describe('SnStatusChip', () => {
  let fixture: ComponentFixture<SnStatusChip>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [SnStatusChip] });
    fixture = TestBed.createComponent(SnStatusChip);
    fixture.componentRef.setInput('label', 'Requiere revisión');
  });

  it('communicates status with text, icon and a semantic tone', async () => {
    fixture.componentRef.setInput('tone', 'danger');

    await fixture.whenStable();

    const chip = fixture.nativeElement.querySelector('.sn-status-chip') as HTMLElement;
    const icon = fixture.nativeElement.querySelector('sn-icon span') as HTMLElement;
    expect(chip.textContent).toContain('Requiere revisión');
    expect(chip.dataset['tone']).toBe('danger');
    expect(icon.textContent?.trim()).toBe('error');
    expect(icon.getAttribute('aria-hidden')).toBe('true');
  });
});
