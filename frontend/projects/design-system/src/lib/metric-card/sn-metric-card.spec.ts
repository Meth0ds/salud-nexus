import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SnMetricCard } from './sn-metric-card';

describe('SnMetricCard', () => {
  let fixture: ComponentFixture<SnMetricCard>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [SnMetricCard] });
    fixture = TestBed.createComponent(SnMetricCard);
    fixture.componentRef.setInput('label', 'Citas confirmadas');
    fixture.componentRef.setInput('value', 24);
  });

  it('associates the metric region with its visible label', async () => {
    await fixture.whenStable();

    const region = fixture.nativeElement.querySelector('.sn-metric-card') as HTMLElement;
    const label = fixture.nativeElement.querySelector('.sn-metric-card__label') as HTMLElement;
    expect(region.getAttribute('aria-labelledby')).toBe(label.id);
    expect(label.textContent?.trim()).toBe('Citas confirmadas');
    expect(fixture.nativeElement.querySelector('.sn-metric-card__value').textContent.trim()).toBe(
      '24',
    );
  });

  it('renders trend direction with an icon and readable text', async () => {
    fixture.componentRef.setInput('trendDirection', 'up');
    fixture.componentRef.setInput('trendLabel', '8 % más que ayer');

    await fixture.whenStable();

    const trend = fixture.nativeElement.querySelector('.sn-metric-card__trend') as HTMLElement;
    expect(trend.dataset['direction']).toBe('up');
    expect(trend.textContent).toContain('trending_up');
    expect(trend.textContent).toContain('8 % más que ayer');
  });
});
