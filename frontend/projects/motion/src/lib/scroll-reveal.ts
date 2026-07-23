import { afterNextRender, Directive, DestroyRef, ElementRef, inject, input } from '@angular/core';

@Directive({
  selector: '[snScrollReveal]',
  host: {
    class: 'sn-scroll-reveal',
    'data-motion': 'scroll-reveal',
  },
})
export class ScrollReveal {
  private readonly destroyRef = inject(DestroyRef);
  private readonly element = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly distance = input(24, { alias: 'snScrollRevealDistance' });
  readonly delay = input(0, { alias: 'snScrollRevealDelay' });

  constructor() {
    afterNextRender(() => void this.attach());
  }

  private async attach(): Promise<void> {
    if (
      this.destroyRef.destroyed ||
      typeof window === 'undefined' ||
      typeof window.matchMedia !== 'function' ||
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      return;
    }

    const [{ gsap }, { ScrollTrigger }] = await Promise.all([
      import('gsap'),
      import('gsap/ScrollTrigger'),
    ]);

    if (this.destroyRef.destroyed) {
      return;
    }

    gsap.registerPlugin(ScrollTrigger);
    const host = this.element.nativeElement;
    const context = gsap.context(() => {
      gsap.fromTo(
        host,
        { autoAlpha: 0, y: this.distance(), willChange: 'transform, opacity' },
        {
          autoAlpha: 1,
          y: 0,
          delay: this.delay(),
          duration: 0.55,
          ease: 'power2.out',
          clearProps: 'willChange,transform,opacity,visibility',
          scrollTrigger: {
            trigger: host,
            start: 'top 88%',
            once: true,
          },
        },
      );
    }, host);

    this.destroyRef.onDestroy(() => context.revert());
  }
}
