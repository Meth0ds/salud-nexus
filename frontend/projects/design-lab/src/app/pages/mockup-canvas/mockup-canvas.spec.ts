import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { MockupCanvas } from './mockup-canvas';

describe('MockupCanvas', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([{ path: 'mockups/:id', component: MockupCanvas }])],
    });
  });

  it('materializa una pantalla del catálogo desde su ID', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/p01', MockupCanvas);
    await harness.fixture.whenStable();

    expect(harness.routeNativeElement?.querySelector('h1')?.textContent).toContain(
      'Inicio del paciente',
    );
    expect(
      harness.routeNativeElement?.querySelector('[data-testid="mockup-frame"]'),
    ).not.toBeNull();
    expect(harness.routeNativeElement?.textContent).toContain('P01');
  });

  it('ofrece una salida segura si el ID no existe', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/z99', MockupCanvas);
    await harness.fixture.whenStable();

    expect(harness.routeNativeElement?.textContent).toContain('Mockup no encontrado');
    expect(harness.routeNativeElement?.querySelector('a')?.getAttribute('href')).toBe('/catalogo');
  });

  it('presenta el cambio de cita como una continuidad entre reserva actual y propuesta', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/p23', MockupCanvas);
    await harness.fixture.whenStable();

    const mockup = harness.routeNativeElement?.querySelector(
      '[data-testid="appointment-change-mockup"]',
    );
    expect(mockup?.textContent).toContain('Tu cita sigue reservada');
    expect(mockup?.textContent).toContain('Nuevo hueco propuesto');
  });

  it('presenta las consecuencias y la alternativa de cambio antes de cancelar', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/p24', MockupCanvas);
    await harness.fixture.whenStable();

    const mockup = harness.routeNativeElement?.querySelector(
      '[data-testid="appointment-change-mockup"]',
    );
    expect(mockup?.textContent).toContain('Cancelar libera este hueco');
    expect(mockup?.textContent).toContain('Prefiero cambiar la cita');
  });

  it('presenta el reto MFA como una línea de confianza y ofrece recuperación', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/g03', MockupCanvas);
    await harness.fixture.whenStable();

    const mockup = harness.routeNativeElement?.querySelector('[data-testid="mfa-flow-mockup"]');
    expect(mockup?.getAttribute('data-mode')).toBe('challenge');
    expect(mockup?.textContent).toContain('Contraseña verificada');
    expect(mockup?.textContent).toContain('No tengo acceso a la aplicación');
    expect(mockup?.querySelector('input')?.getAttribute('autocomplete')).toBe('one-time-code');
  });

  it('presenta un alta TOTP sin un código QR escaneable ni códigos de recuperación', async () => {
    const harness = await RouterTestingHarness.create();

    await harness.navigateByUrl('/mockups/p12', MockupCanvas);
    await harness.fixture.whenStable();

    const mockup = harness.routeNativeElement?.querySelector('[data-testid="mfa-flow-mockup"]');
    expect(mockup?.getAttribute('data-mode')).toBe('enrollment');
    expect(mockup?.textContent).toContain('Muestra no escaneable');
    expect(mockup?.textContent).toContain('Una única entrega');
    expect(mockup?.querySelector('img')).toBeNull();
  });
});
