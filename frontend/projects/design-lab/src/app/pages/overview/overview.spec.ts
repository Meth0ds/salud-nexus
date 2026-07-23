import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Overview } from './overview';

describe('Overview', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Overview],
      providers: [provideRouter([])],
    });
  });

  it('explica la dirección visual y enlaza las tres experiencias insignia', async () => {
    const fixture = TestBed.createComponent(Overview);

    await fixture.whenStable();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelector('h1')?.textContent).toContain('Confianza clínica');
    expect(element.querySelectorAll('[data-testid="showcase-card"]')).toHaveLength(3);
    expect(element.textContent).toContain('Privacidad visible');
    expect(element.textContent).toContain('Movimiento con propósito');
  });
});
