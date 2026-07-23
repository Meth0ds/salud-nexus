import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

const criticalRoutes = ['/agenda', '/recepcion', '/administracion'] as const;

function captureRuntimeErrors(page: Page): string[] {
  const errors: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(`console: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));

  return errors;
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const layout = await page.evaluate(() => {
    const viewport = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll<HTMLElement>('body *')]
      .filter((element) => {
        const bounds = element.getBoundingClientRect();
        return bounds.right > viewport + 1 || bounds.left < -1;
      })
      .slice(0, 10)
      .map((element) => {
        const bounds = element.getBoundingClientRect();
        return {
          element: `${element.tagName.toLowerCase()}${
            element.classList.length ? `.${[...element.classList].join('.')}` : ''
          }`,
          left: Math.round(bounds.left),
          right: Math.round(bounds.right),
          clientWidth: element.clientWidth,
          scrollWidth: element.scrollWidth,
        };
      });

    return {
      viewport,
      document: document.documentElement.scrollWidth,
      offenders,
    };
  });

  expect(layout.document, JSON.stringify(layout)).toBeLessThanOrEqual(layout.viewport + 1);
}

function captureName(testInfo: TestInfo, name: string): string {
  const device = testInfo.project.name.endsWith('mobile') ? 'mobile' : 'desktop';
  return `../output/playwright/captures/staff-${name}-${device}.png`;
}

async function prepareCapture(page: Page): Promise<void> {
  await page.evaluate(async () => {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    document.querySelectorAll<HTMLElement>('.table-scroll').forEach((element) => {
      element.scrollLeft = 0;
    });
    (document.activeElement as HTMLElement | null)?.blur();
    await document.fonts.ready;
  });
}

test.describe('portal operativo del personal', () => {
  test('presenta una agenda navegable y accesible por teclado', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/agenda');

    await expect(page).toHaveTitle(/Agenda clínica · Salud Nexus/);
    await expect(page.getByRole('heading', { level: 1, name: 'Agenda clínica' })).toBeVisible();
    await expect(page.locator('[data-appointment-id]')).toHaveCount(4);

    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#staff-main')).toBeFocused();

    await page.locator('[data-appointment-id="apt-synthetic-02"]').click();
    await expect(page.getByRole('heading', { name: 'Elena M. (demo)' })).toBeVisible();
    await page.getByRole('button', { name: 'Cerrar detalle' }).click();

    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'agenda'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('valida la búsqueda y registra una llegada sin duplicarla', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.goto('/recepcion');

    const search = page.getByLabel('Buscar en esta cola');
    await search.fill('x');
    await search.blur();
    await expect(page.getByText('Escribe al menos 2 caracteres.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Buscar' })).toBeDisabled();

    await search.fill('sin coincidencias');
    await page.getByRole('button', { name: 'Buscar' }).click();
    await expect(page.getByRole('heading', { name: 'No hay coincidencias' })).toBeVisible();
    await page.getByRole('button', { name: 'Mostrar toda la cola' }).click();

    await page.locator('[data-check-in-id="queue-synthetic-01"]').click();
    await expect(page.getByText('Admisión registrada para Bruno R. (demo).')).toBeVisible();
    await expect(page.locator('[data-entry-id="queue-synthetic-01"]')).toContainText(
      'Admisión confirmada',
    );

    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'recepcion'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('permite revisar el tablero de administración sin exponer datos reales', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.goto('/administracion');

    await expect(
      page.getByRole('heading', { level: 1, name: 'Administración y seguridad' }),
    ).toBeVisible();
    await expect(page.getByText('Datos sintéticos de demostración')).toHaveCount(0);
    await page.getByRole('button', { name: 'Solo prioridad alta' }).click();
    await expect(page.locator('.alert-item')).toHaveCount(1);
    await page.getByRole('button', { name: 'Revisar' }).click();
    await expect(page.getByText(/Datos sintéticos de demostración/)).toBeVisible();
    await page.getByRole('button', { name: 'Cerrar detalle de alerta' }).click();

    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'administracion'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('no tiene infracciones automáticas WCAG A/AA en sus rutas críticas', async ({ page }) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });

    for (const route of criticalRoutes) {
      await page.goto(route);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

      const details = results.violations
        .flatMap((violation) =>
          violation.nodes.map(
            (node) =>
              `${violation.id} ${node.target.join(' ')}: ${node.failureSummary
                ?.replaceAll('\n', ' ')
                .slice(0, 220)}`,
          ),
        )
        .join('\n');
      expect(results.violations.length, `${route}\n${details}`).toBe(0);
    }

    expect(runtimeErrors).toEqual([]);
  });
});
