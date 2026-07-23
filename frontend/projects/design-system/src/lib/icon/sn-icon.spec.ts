import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SnIcon } from './sn-icon';

describe('SnIcon', () => {
  let fixture: ComponentFixture<SnIcon>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [SnIcon] });
    fixture = TestBed.createComponent(SnIcon);
    fixture.componentRef.setInput('name', 'health_and_safety');
  });

  it('renders a decorative local Material Symbol by default', async () => {
    fixture.componentRef.setInput('size', 24);

    await fixture.whenStable();

    const glyph = fixture.nativeElement.querySelector('.sn-icon__glyph') as HTMLElement;
    expect(glyph.textContent?.trim()).toBe('health_and_safety');
    expect(glyph.getAttribute('aria-hidden')).toBe('true');
    expect(glyph.getAttribute('role')).toBeNull();
    expect(glyph.style.getPropertyValue('--sn-icon-size')).toBe('24px');
  });

  it('exposes a semantic icon as an image when it has an accessible label', async () => {
    fixture.componentRef.setInput('accessibleLabel', 'Protección de datos activa');

    await fixture.whenStable();

    const glyph = fixture.nativeElement.querySelector('.sn-icon__glyph') as HTMLElement;
    expect(glyph.getAttribute('role')).toBe('img');
    expect(glyph.getAttribute('aria-label')).toBe('Protección de datos activa');
    expect(glyph.getAttribute('aria-hidden')).toBeNull();
  });
});
