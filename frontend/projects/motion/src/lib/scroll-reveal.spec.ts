import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';

import { ScrollReveal } from './scroll-reveal';

@Component({
  imports: [ScrollReveal],
  template: `<section snScrollReveal>Contenido</section>`,
})
class TestHost {}

describe('ScrollReveal', () => {
  it('marca el contenido como una mejora de movimiento no esencial', async () => {
    const fixture = TestBed.createComponent(TestHost);

    await fixture.whenStable();

    const section = (fixture.nativeElement as HTMLElement).querySelector('section');
    expect(section?.dataset['motion']).toBe('scroll-reveal');
    expect(section?.classList.contains('sn-scroll-reveal')).toBe(true);
  });
});
