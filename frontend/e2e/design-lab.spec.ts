import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

const criticalRoutes = [
  '/',
  '/catalogo',
  '/mockups/P01',
  '/mockups/G03',
  '/mockups/P12',
  '/showcase/paciente',
  '/showcase/clinico',
  '/showcase/seguridad',
] as const;

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
    const viewportWidth = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll<HTMLElement>('body *')]
      .filter((element) => {
        const bounds = element.getBoundingClientRect();
        return bounds.right > viewportWidth + 1 || bounds.left < -1;
      })
      .slice(0, 8)
      .map((element) => ({
        element: `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${
          element.classList.length ? `.${[...element.classList].join('.')}` : ''
        }`,
        left: Math.round(element.getBoundingClientRect().left),
        right: Math.round(element.getBoundingClientRect().right),
      }));

    return {
      fits: document.documentElement.scrollWidth <= viewportWidth + 1,
      viewportWidth,
      documentWidth: document.documentElement.scrollWidth,
      offenders,
      tableAncestors: (() => {
        const rows: Array<Record<string, string | number>> = [];
        let element: HTMLElement | null = document.querySelector('table');
        while (element && rows.length < 8) {
          const bounds = element.getBoundingClientRect();
          const styles = getComputedStyle(element);
          rows.push({
            element: `${element.tagName.toLowerCase()}${
              element.classList.length ? `.${[...element.classList].join('.')}` : ''
            }`,
            left: Math.round(bounds.left),
            right: Math.round(bounds.right),
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
            minWidth: styles.minWidth,
            overflowX: styles.overflowX,
            display: styles.display,
            columns: styles.gridTemplateColumns,
          });
          element = element.parentElement;
        }
        return rows;
      })(),
    };
  });

  expect(layout.fits, JSON.stringify(layout, null, 2)).toBe(true);
}

function captureName(testInfo: TestInfo, name: string): string {
  const device = testInfo.project.name.replace('chromium-', '');
  return `../output/playwright/captures/${name}-${device}.png`;
}

async function waitForVisualAssets(page: Page): Promise<void> {
  await page.evaluate(async () => {
    await document.fonts.ready;
  });
}

test.describe('laboratorio visual Salud Nexus', () => {
  test('ofrece navegación, tema y salto de teclado accesibles', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    await expect(page).toHaveTitle(/Salud Nexus/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Confianza clínica');
    await expect(page.getByTestId('brand')).toContainText('Salud Nexus');

    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Saltar al contenido principal' });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    const themeToggle = page.locator('.theme-toggle');
    await expect(themeToggle).toHaveAccessibleName('Usar tema oscuro');
    await themeToggle.click();
    await expect(themeToggle).toHaveAttribute('aria-pressed', 'true');
    await expect(themeToggle).toHaveAccessibleName('Usar tema claro');
    await themeToggle.click();

    await expectNoHorizontalOverflow(page);
    await waitForVisualAssets(page);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.screenshot({
      path: captureName(testInfo, 'overview'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('filtra el catálogo canónico y abre un mockup ejecutable', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.goto('/catalogo');

    await expect(page.getByRole('heading', { level: 1 })).toContainText('273 pantallas');
    await expect(page.getByTestId('screen-card')).toHaveCount(273);
    await expect(page.getByTestId('results-count')).toHaveText('273 de 273 pantallas');

    await page.getByLabel('Buscar pantalla, ruta o capacidad').fill('P01');
    await expect(page.getByTestId('screen-card')).toHaveCount(1);
    await expect(page.getByTestId('results-count')).toHaveText('1 de 273 pantallas');
    await page.getByRole('link', { name: /Abrir mockup P01:/ }).click();

    await expect(page).toHaveURL(/\/mockups\/p01$/i);
    await expect(page.getByTestId('mockup-frame')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Volver al catálogo' })).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await waitForVisualAssets(page);
    await page.screenshot({
      path: captureName(testInfo, 'mockup-p01'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('resuelve de forma segura un identificador de mockup desconocido', async ({ page }) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.goto('/mockups/NO-EXISTE');

    await expect(
      page.getByRole('heading', { level: 1, name: 'Mockup no encontrado' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Volver al catálogo' })).toBeVisible();
    expect(runtimeErrors).toEqual([]);
  });

  test('materializa el reto y el alta MFA con continuidad, alternativas y reflow', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });

    await page.goto('/mockups/G03');
    await expect(page.getByRole('heading', { name: 'Confirma que eres tú' })).toBeVisible();
    await expect(page.getByText('Contraseña verificada')).toBeVisible();
    await page.getByRole('button', { name: 'No tengo acceso a la aplicación' }).click();
    await expect(
      page.getByRole('heading', { name: 'Usa un código de recuperación' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await waitForVisualAssets(page);
    await page.screenshot({
      path: captureName(testInfo, 'mockup-mfa-challenge'),
      fullPage: true,
      animations: 'disabled',
    });

    await page.goto('/mockups/P12');
    await expect(
      page.getByRole('img', { name: 'Muestra de código QR no escaneable' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Ya lo he escaneado' }).click();
    await expect(page.getByRole('heading', { name: 'Comprueba la vinculación' })).toBeVisible();
    await expect(page.getByText('No se mostrarán de nuevo')).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await waitForVisualAssets(page);
    await page.screenshot({
      path: captureName(testInfo, 'mockup-mfa-enrollment'),
      fullPage: true,
      animations: 'disabled',
    });

    expect(runtimeErrors).toEqual([]);
  });

  test('renderiza las tres experiencias insignia', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    const experiences = [
      { route: '/showcase/paciente', heading: 'Buenos días, Laura', capture: 'paciente' },
      { route: '/showcase/clinico', heading: 'Agenda clínica', capture: 'clinico' },
      { route: '/showcase/seguridad', heading: 'Seguridad y auditoría', capture: 'seguridad' },
    ] as const;

    for (const experience of experiences) {
      await page.goto(experience.route);
      await expect(page.getByRole('heading', { level: 1, name: experience.heading })).toBeVisible();
      await expectNoHorizontalOverflow(page);
      await waitForVisualAssets(page);
      await page.screenshot({
        path: captureName(testInfo, experience.capture),
        fullPage: true,
        animations: 'disabled',
      });
    }
    expect(runtimeErrors).toEqual([]);
  });

  test('no presenta infracciones automáticas WCAG 2.2 A/AA en rutas críticas', async ({ page }) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });

    for (const route of criticalRoutes) {
      await page.goto(route);
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

  test('mantiene el contenido disponible con movimiento reducido', async ({ page }) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    await expect
      .poll(() => page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches))
      .toBe(true);
    await page
      .getByRole('heading', { name: 'Serena en la forma. Rigurosa en el fondo.' })
      .scrollIntoViewIfNeeded();
    await expect(
      page.getByRole('heading', { name: 'Serena en la forma. Rigurosa en el fondo.' }),
    ).toBeVisible();
    expect(runtimeErrors).toEqual([]);
  });
});
