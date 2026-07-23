import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

const credentials = {
  email: 'laura.demo@saludnexus.test',
  password: 'NEXUS-2026',
} as const;

function captureRuntimeErrors(page: Page): string[] {
  const errors: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error' || message.type() === 'warning') {
      errors.push(`${message.type()}: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));

  return errors;
}

async function login(page: Page): Promise<void> {
  await page.goto('/iniciar-sesion');
  await page.getByLabel('Correo electrónico').fill(credentials.email);
  await page.getByLabel('Código de acceso').fill(credentials.password);
  await page.getByRole('button', { name: 'Entrar en la demostración' }).click();
  await expect(page).toHaveURL(/\/inicio$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Buenos días, Laura' })).toBeVisible();
}

async function navigateWithinSession(page: Page, path: string): Promise<void> {
  const link = page.locator(`a[href="${path}"]:visible`).first();
  await expect(link).toBeVisible();
  await link.click();
  await expect(page).toHaveURL(new RegExp(`${path.replace('/', '\\/')}$`));
}

async function openAppointmentChange(page: Page): Promise<void> {
  await navigateWithinSession(page, '/citas');
  await page.getByRole('button', { name: 'Ver detalles' }).first().click();
  await expect(page).toHaveURL(/\/citas\/detalle$/);
  await page.getByRole('link', { name: 'Gestionar cita' }).click();
  await expect(page).toHaveURL(/\/citas\/gestionar$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Gestionar cita' })).toBeVisible();
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const layout = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  expect(layout.document, JSON.stringify(layout)).toBeLessThanOrEqual(layout.viewport + 1);
}

function captureName(testInfo: TestInfo, name: string): string {
  const device = testInfo.project.name.endsWith('mobile') ? 'mobile' : 'desktop';
  return `../output/playwright/captures/patient-${name}-${device}.png`;
}

async function prepareCapture(page: Page): Promise<void> {
  await page.evaluate(async () => {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    (document.activeElement as HTMLElement | null)?.blur();
    await document.fonts.ready;
  });
}

test.describe('portal del paciente', () => {
  test('protege las rutas y mantiene un acceso genérico y accesible', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.goto('/inicio');
    await expect(page).toHaveURL(/\/iniciar-sesion$/);

    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Saltar al acceso' });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#login-main')).toBeFocused();

    await page.getByLabel('Correo electrónico').fill(credentials.email);
    await page.getByLabel('Código de acceso').fill('NEXUS-0000');
    await page.getByRole('button', { name: 'Entrar en la demostración' }).click();
    await expect(page.getByRole('alert')).toContainText(
      'No hemos podido verificar los datos de acceso.',
    );

    await page.getByLabel('Código de acceso').fill(credentials.password);
    await page.getByRole('button', { name: 'Entrar en la demostración' }).click();
    await expect(page.getByRole('heading', { name: 'Buenos días, Laura' })).toBeVisible();

    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'inicio'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('completa la reserva guiada e idempotente de demostración', async ({ page }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await login(page);
    await page.getByRole('link', { name: 'Pedir una cita' }).first().click();

    await page.getByLabel('Servicio').selectOption('appointment_type_demo_internal');
    await page.getByRole('button', { name: /Continuar/ }).click();
    await page.getByLabel('Fecha y hora').selectOption('slot_demo_20260729_1130');
    await page.getByRole('button', { name: /Revisar cita/ }).click();
    await page.getByRole('button', { name: 'Confirmar cita' }).click();

    await expect(page.getByRole('heading', { name: 'Cita confirmada en la demo' })).toBeVisible();
    await expect(page.getByText('No se ha contactado con ningún centro')).toBeVisible();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'reserva-confirmada'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('cambia una cita con continuidad visual y confirmación versionada', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await login(page);
    await openAppointmentChange(page);

    await expect(page.getByText('Tu cita sigue reservada')).toBeVisible();
    await page.getByLabel('Nueva fecha y hora').focus();
    await page.keyboard.press('Enter');
    await page.getByRole('option', { name: /29 de julio de 2026/ }).click();
    await expect(page.getByText('Nuevo horario')).toBeVisible();
    await expectNoHorizontalOverflow(page);
    const formResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(formResults.violations, JSON.stringify(formResults.violations, null, 2)).toEqual([]);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'gestionar-cambio'),
      fullPage: true,
      animations: 'disabled',
    });

    await page.getByRole('button', { name: 'Confirmar cambio' }).click();
    await expect(page.getByRole('heading', { name: 'Cita cambiada' })).toBeVisible();
    await expect(page.getByText('11:30–12:00').last()).toBeVisible();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'gestionar-cambio-confirmado'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('cancela una cita con motivo cerrado y consecuencia explícita', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await login(page);
    await openAppointmentChange(page);

    await page.getByRole('button', { name: 'Cancelar cita' }).click();
    await expect(page.getByRole('heading', { name: 'Cancelar libera este hueco' })).toBeVisible();
    await page.getByLabel(/Cambio de planes/).check();
    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'gestionar-cancelacion'),
      fullPage: true,
      animations: 'disabled',
    });

    await page.getByRole('button', { name: 'Confirmar cancelación' }).click();
    await expect(page.getByRole('heading', { name: 'Cita cancelada' })).toBeVisible();
    await expect(page.getByText('su hueco ha quedado liberado')).toBeVisible();
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'gestionar-cancelacion-confirmada'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('distingue declaraciones personales y tramita una renovación elegible', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await login(page);
    await navigateWithinSession(page, '/medicacion');

    await page.getByRole('button', { name: 'Añadir lo que tomo' }).click();
    await page.getByLabel('Nombre').fill('Vitamina D');
    await page.getByLabel('Presentación (opcional)').fill('1000 UI · cápsulas');
    await page.getByLabel('Cómo lo tomas').fill('Una cápsula al día');
    await page.getByRole('button', { name: 'Añadir información' }).click();

    const declaredItem = page.locator('li').filter({ hasText: 'Vitamina D' });
    await expect(declaredItem).toContainText('Declarada por ti');
    await expect(declaredItem).toContainText('no generan renovaciones');
    await expect(page.getByText('Añadida a esta sesión de demostración')).toBeVisible();

    await page.getByRole('button', { name: 'Solicitar renovación' }).first().click();
    await expect(page.locator('.renewal-confirmation')).toContainText(
      'Solicitud de renovación enviada',
    );
    await expectNoHorizontalOverflow(page);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'medicacion-activa'),
      fullPage: true,
      animations: 'disabled',
    });
    expect(runtimeErrors).toEqual([]);
  });

  test('recorre documentos, privacidad y cierre de sesión sin descargas falsas', async ({
    page,
  }, testInfo) => {
    const runtimeErrors = captureRuntimeErrors(page);
    let downloadEvents = 0;
    page.on('download', () => downloadEvents++);
    await login(page);

    await navigateWithinSession(page, '/documentos');
    await expect(page.getByRole('heading', { level: 1, name: 'Documentos' })).toBeVisible();
    await expect(page.getByText('La demo no genera descargas')).toBeVisible();
    await page
      .getByRole('button', { name: /Ver ficha de/ })
      .first()
      .click();
    await expect(page.getByText('El servidor vuelve a comprobar tu sesión')).toBeVisible();
    await page.getByRole('button', { name: 'Probar descarga segura' }).click();
    await expect(
      page.getByRole('alert').filter({ hasText: 'La demostración no ha creado ninguna descarga' }),
    ).toBeVisible();
    expect(downloadEvents).toBe(0);
    await prepareCapture(page);
    await page.screenshot({
      path: captureName(testInfo, 'documentos-activos'),
      fullPage: true,
      animations: 'disabled',
    });

    await navigateWithinSession(page, '/privacidad');
    await expect(
      page.getByRole('heading', { level: 1, name: 'Privacidad y accesos' }),
    ).toBeVisible();
    const closeOtherSessions = page.getByRole('button', { name: 'Cerrar otras sesiones' });
    if (await closeOtherSessions.isVisible()) {
      await closeOtherSessions.click();
      await expect(page.getByText('Solo esta sesión')).toBeVisible();
    }

    await page.getByRole('button', { name: 'Abrir menú de la cuenta de demostración' }).click();
    await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/iniciar-sesion$/);
    expect(runtimeErrors).toEqual([]);
  });

  test('no presenta infracciones automáticas WCAG A/AA en recorridos críticos', async ({
    page,
  }) => {
    const runtimeErrors = captureRuntimeErrors(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/iniciar-sesion');

    const loginResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(loginResults.violations, JSON.stringify(loginResults.violations, null, 2)).toEqual([]);

    await page.getByLabel('Correo electrónico').fill(credentials.email);
    await page.getByLabel('Código de acceso').fill(credentials.password);
    await page.getByRole('button', { name: 'Entrar en la demostración' }).click();

    for (const path of ['/inicio', '/citas', '/medicacion', '/documentos', '/privacidad']) {
      if (path !== '/inicio') {
        await navigateWithinSession(page, path);
      }
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
      expect(results.violations.length, `${path}\n${details}`).toBe(0);
    }

    expect(runtimeErrors).toEqual([]);
  });
});
