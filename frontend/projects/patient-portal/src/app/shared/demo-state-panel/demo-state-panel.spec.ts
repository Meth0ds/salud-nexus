import { TestBed } from '@angular/core/testing';

import { DemoStatePanel } from './demo-state-panel';

describe('DemoStatePanel', () => {
  it('renders a safe error with an opaque support reference', async () => {
    const fixture = TestBed.createComponent(DemoStatePanel);
    fixture.componentRef.setInput('kind', 'error');
    fixture.componentRef.setInput('heading', 'No se pudieron cargar tus citas');
    fixture.componentRef.setInput('description', 'Inténtalo de nuevo dentro de unos minutos.');
    fixture.componentRef.setInput('correlationId', 'demo-correlation-7Q2M');

    await fixture.whenStable();

    const panel = fixture.nativeElement as HTMLElement;
    expect(panel.querySelector('[role="alert"]')).toBeTruthy();
    expect(panel.textContent).toContain('demo-correlation-7Q2M');
    expect(panel.textContent).not.toContain('SQL');
  });
});
