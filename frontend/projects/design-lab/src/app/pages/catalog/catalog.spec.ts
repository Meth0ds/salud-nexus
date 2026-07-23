import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { mockupRouteFor, screenCatalog, screenCatalogCount } from '../../screen-catalog';
import { Catalog } from './catalog';

describe('screenCatalog', () => {
  it('mantiene el inventario completo y sin colisiones', () => {
    expect(screenCatalogCount).toBe(273);
    expect(new Set(screenCatalog.map((screen) => screen.id)).size).toBe(screenCatalogCount);
    expect(new Set(screenCatalog.map((screen) => screen.ruta)).size).toBe(screenCatalogCount);
    expect(
      screenCatalog.every(
        (screen) => mockupRouteFor(screen) === `/mockups/${screen.id.toLowerCase()}`,
      ),
    ).toBe(true);
  });

  it('cubre todas las familias previstas', () => {
    const counts = screenCatalog.reduce<Record<string, number>>((result, screen) => {
      const family = screen.id[0];
      result[family] = (result[family] ?? 0) + 1;
      return result;
    }, {});

    expect(counts).toEqual({
      A: 44,
      C: 34,
      G: 20,
      O: 15,
      P: 70,
      R: 34,
      S: 56,
    });
  });
});

describe('Catalog', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Catalog],
      providers: [provideRouter([])],
    });
  });

  it('renderiza un mockup navegable por cada pantalla inventariada', async () => {
    const fixture = TestBed.createComponent(Catalog);

    await fixture.whenStable();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelector('h1')?.textContent).toContain('273 pantallas');
    expect(element.querySelectorAll('[data-testid="screen-card"]')).toHaveLength(273);
  });

  it('filtra por texto sin perder el recuento total', async () => {
    const fixture = TestBed.createComponent(Catalog);
    await fixture.whenStable();
    const input = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(
      '#catalog-query',
    );

    expect(input).not.toBeNull();
    input!.value = 'break-glass';
    input!.dispatchEvent(new Event('input'));
    await fixture.whenStable();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelectorAll('[data-testid="screen-card"]')).not.toHaveLength(0);
    expect(element.querySelector('[data-testid="results-count"]')?.textContent).toContain('de 273');
  });
});
