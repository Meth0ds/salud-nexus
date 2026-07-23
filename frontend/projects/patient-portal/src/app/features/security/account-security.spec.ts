import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { MatButtonHarness } from '@angular/material/button/testing';
import { MatCheckboxHarness } from '@angular/material/checkbox/testing';
import { MatInputHarness } from '@angular/material/input/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { AccountSecurity, MFA_OBJECT_URLS } from './account-security';

@Component({ template: '<p>Privacidad</p>' })
class PrivacyStub {}

describe('AccountSecurity', () => {
  const getMfaStatus = vi.fn();
  const beginTotpEnrollment = vi.fn();
  const discloseTotpEnrollmentQr = vi.fn();
  const confirmTotpEnrollment = vi.fn();
  const createObjectUrl = vi.fn();
  const revokeObjectUrl = vi.fn();
  const recoveryCodes = '23456789AB'
    .split('')
    .map((suffix) => `234567-89ABCD-EFGHJK-MNPQR${suffix}`);

  beforeEach(() => {
    getMfaStatus.mockReset();
    beginTotpEnrollment.mockReset();
    discloseTotpEnrollmentQr.mockReset();
    confirmTotpEnrollment.mockReset();
    createObjectUrl.mockReset();
    revokeObjectUrl.mockReset();
    getMfaStatus.mockResolvedValue({
      enabled: false,
      method: null,
      status: null,
      confirmedAt: null,
      recoveryCodesRemaining: 0,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });
    beginTotpEnrollment.mockResolvedValue({
      method: 'totp',
      status: 'pending',
      expiresAt: '2099-07-23T00:10:00+00:00',
      qrDisclosureRequired: true,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });
    discloseTotpEnrollmentQr.mockResolvedValue(
      '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
    );
    confirmTotpEnrollment.mockResolvedValue({
      codes: recoveryCodes,
      requestId: '019b1234-5678-7abc-8def-1234567890ae',
    });
    createObjectUrl.mockReturnValue('blob:synthetic-totp-qr');

    TestBed.configureTestingModule({
      imports: [AccountSecurity],
      providers: [
        {
          provide: PATIENT_REPOSITORY,
          useValue: {
            getMfaStatus,
            beginTotpEnrollment,
            discloseTotpEnrollmentQr,
            confirmTotpEnrollment,
          },
        },
        {
          provide: MFA_OBJECT_URLS,
          useValue: {
            create: createObjectUrl,
            revoke: revokeObjectUrl,
          },
        },
        provideRouter([{ path: 'privacidad', component: PrivacyStub }]),
      ],
    });
  });

  it('loads the server-owned status and offers enrollment without exposing a secret', async () => {
    const fixture = TestBed.createComponent(AccountSecurity);
    await fixture.whenStable();
    const loader = TestbedHarnessEnvironment.loader(fixture);
    const start = await loader.getHarness(
      MatButtonHarness.with({ selector: '[data-testid="start-enrollment"]' }),
    );

    expect(await start.getText()).toContain('Configurar');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Aún no has configurado');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
  });

  it('reveals the QR once, removes it before confirmation, and focuses the next stage', async () => {
    const fixture = TestBed.createComponent(AccountSecurity);
    await fixture.whenStable();
    const loader = TestbedHarnessEnvironment.loader(fixture);

    await (
      await loader.getHarness(
        MatButtonHarness.with({ selector: '[data-testid="start-enrollment"]' }),
      )
    ).click();
    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.querySelector<HTMLImageElement>('img[alt*="QR"]')?.src).toBe(
      'blob:synthetic-totp-qr',
    );
    expect(createObjectUrl).toHaveBeenCalledOnce();

    await (
      await loader.getHarness(MatButtonHarness.with({ selector: '[data-testid="qr-scanned"]' }))
    ).click();
    await fixture.whenStable();

    expect(page.querySelector('img[alt*="QR"]')).toBeNull();
    expect(revokeObjectUrl).toHaveBeenCalledWith('blob:synthetic-totp-qr');
    expect(page.querySelector('#confirmation-title')).toBe(document.activeElement);
  });

  it('keeps recovery codes only in memory until the user confirms custody', async () => {
    const fixture = TestBed.createComponent(AccountSecurity);
    await fixture.whenStable();
    const loader = TestbedHarnessEnvironment.loader(fixture);

    await (
      await loader.getHarness(
        MatButtonHarness.with({ selector: '[data-testid="start-enrollment"]' }),
      )
    ).click();
    await fixture.whenStable();
    await (
      await loader.getHarness(MatButtonHarness.with({ selector: '[data-testid="qr-scanned"]' }))
    ).click();
    await fixture.whenStable();
    const input = await loader.getHarness(
      MatInputHarness.with({ selector: '#totp-confirmation-code' }),
    );
    await input.setValue('123456');
    await (
      await loader.getHarness(
        MatButtonHarness.with({ selector: '[data-testid="confirm-enrollment"]' }),
      )
    ).click();
    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(confirmTotpEnrollment).toHaveBeenCalledWith('123456');
    expect(page.textContent).toContain(recoveryCodes[0]);
    expect(await input.getValue()).toBe('');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);

    const custody = await loader.getHarness(
      MatCheckboxHarness.with({ selector: '[data-testid="confirm-custody"]' }),
    );
    await custody.check();
    await (
      await loader.getHarness(
        MatButtonHarness.with({ selector: '[data-testid="finish-enrollment"]' }),
      )
    ).click();
    await fixture.whenStable();

    expect(page.textContent).not.toContain(recoveryCodes[0]);
    expect(page.textContent).toContain('Segundo factor activo');
  });
});
