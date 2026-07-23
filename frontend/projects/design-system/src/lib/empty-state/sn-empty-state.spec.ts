import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SnEmptyState } from './sn-empty-state';

describe('SnEmptyState', () => {
  let fixture: ComponentFixture<SnEmptyState>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [SnEmptyState] });
    fixture = TestBed.createComponent(SnEmptyState);
    fixture.componentRef.setInput('heading', 'Aún no hay documentos');
    fixture.componentRef.setInput(
      'description',
      'Los documentos compartidos por el centro aparecerán aquí.',
    );
  });

  it('provides labelled and described empty-state semantics', async () => {
    await fixture.whenStable();

    const region = fixture.nativeElement.querySelector('.sn-empty-state') as HTMLElement;
    const heading = fixture.nativeElement.querySelector('h2') as HTMLElement;
    const description = fixture.nativeElement.querySelector('p') as HTMLElement;
    expect(region.getAttribute('aria-labelledby')).toBe(heading.id);
    expect(region.getAttribute('aria-describedby')).toBe(description.id);
    expect(heading.textContent?.trim()).toBe('Aún no hay documentos');
    expect(description.textContent).toContain('aparecerán aquí');
  });
});
