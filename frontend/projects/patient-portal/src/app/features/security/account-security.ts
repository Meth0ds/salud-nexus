import { DOCUMENT } from '@angular/common';
import {
  Component,
  computed,
  DestroyRef,
  ElementRef,
  inject,
  InjectionToken,
  signal,
  viewChild,
} from '@angular/core';
import {
  form,
  FormField,
  maxLength,
  minLength,
  pattern,
  required,
  submit,
} from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { RouterLink } from '@angular/router';
import type { MfaStatusView, TotpEnrollmentView } from 'auth';
import { SnIcon, SnStatusChip } from 'design-system';

import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';

type SecurityStage =
  'active' | 'confirming' | 'disabled' | 'error' | 'loading' | 'recovery' | 'scanning' | 'starting';

export interface MfaObjectUrls {
  create(blob: Blob): string;
  revoke(url: string): void;
}

export const MFA_OBJECT_URLS = new InjectionToken<MfaObjectUrls>('MFA_OBJECT_URLS', {
  factory: () => ({
    create: (blob) => URL.createObjectURL(blob),
    revoke: (url) => URL.revokeObjectURL(url),
  }),
});

@Component({
  selector: 'sn-patient-account-security',
  imports: [
    FormField,
    MatButtonModule,
    MatCheckboxModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
    RouterLink,
    SnIcon,
    SnStatusChip,
  ],
  templateUrl: './account-security.html',
  styleUrl: './account-security.scss',
})
export class AccountSecurity {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly objectUrls = inject(MFA_OBJECT_URLS);
  private readonly document = inject(DOCUMENT);
  private readonly stageHeading = viewChild<ElementRef<HTMLElement>>('stageHeading');
  private readonly confirmationInput = viewChild<ElementRef<HTMLInputElement>>('confirmationInput');
  private readonly destroyRef = inject(DestroyRef);
  private qrObjectUrl: string | undefined;

  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly stage = signal<SecurityStage>('loading');
  protected readonly status = signal<MfaStatusView | undefined>(undefined);
  protected readonly enrollment = signal<TotpEnrollmentView | undefined>(undefined);
  protected readonly qrUrl = signal<string | undefined>(undefined);
  protected readonly recoveryCodes = signal<readonly string[]>([]);
  protected readonly custodyConfirmed = signal(false);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal('');
  protected readonly announcement = signal('');
  protected readonly confirmation = signal({ code: '' });
  protected readonly confirmationForm = form(this.confirmation, (path) => {
    required(path.code, { message: 'Escribe un código temporal.' });
    minLength(path.code, 6, { message: 'El código está incompleto.' });
    maxLength(path.code, 6, { message: 'El código debe tener seis dígitos.' });
    pattern(path.code, /^\d{6}$/, { message: 'Usa exactamente seis dígitos.' });
  });
  protected readonly activeRecoveryCount = computed(
    () => this.status()?.recoveryCodesRemaining ?? 0,
  );

  constructor() {
    this.destroyRef.onDestroy(() => {
      this.clearQr();
      this.recoveryCodes.set([]);
      this.confirmation.set({ code: '' });
    });
    void this.loadStatus();
  }

  protected async startEnrollment(): Promise<void> {
    this.stage.set('starting');
    this.errorMessage.set('');
    this.clearQr();

    try {
      const enrollment = await this.repository.beginTotpEnrollment();
      const svg = await this.repository.discloseTotpEnrollmentQr();
      const objectUrl = this.objectUrls.create(
        new Blob([svg], { type: 'image/svg+xml;charset=UTF-8' }),
      );
      this.enrollment.set(enrollment);
      this.qrObjectUrl = objectUrl;
      this.qrUrl.set(objectUrl);
      this.stage.set('scanning');
      this.focusStage();
    } catch {
      this.clearQr();
      this.fail('No se pudo preparar el alta. Vuelve a intentarlo desde una sesión reciente.');
    }
  }

  protected continueToConfirmation(): void {
    this.clearQr();
    this.confirmation.set({ code: '' });
    this.stage.set('confirming');
    this.focusStage();
  }

  protected confirmEnrollment(): void {
    this.errorMessage.set('');

    void submit(this.confirmationForm, async () => {
      this.submitting.set(true);

      try {
        const result = await this.repository.confirmTotpEnrollment(this.confirmation().code.trim());
        this.clearConfirmationCode();
        this.recoveryCodes.set([...result.codes]);
        this.custodyConfirmed.set(false);
        this.stage.set('recovery');
        this.focusStage();
      } catch {
        this.errorMessage.set(
          'No hemos podido verificar el código. Solicita uno nuevo e inténtalo otra vez.',
        );
      } finally {
        this.clearConfirmationCode();
        this.submitting.set(false);
      }
    });
  }

  protected async copyRecoveryCodes(): Promise<void> {
    const codes = this.recoveryCodes();

    if (codes.length === 0 || !this.document.defaultView?.navigator.clipboard) {
      this.announcement.set('No se pudo copiar. Usa la descarga segura.');
      return;
    }

    await this.document.defaultView.navigator.clipboard.writeText(codes.join('\n'));
    this.announcement.set('Códigos copiados. Guárdalos en un lugar privado.');
  }

  protected downloadRecoveryCodes(): void {
    const codes = this.recoveryCodes();

    if (codes.length === 0) {
      return;
    }

    const url = this.objectUrls.create(
      new Blob([`${codes.join('\n')}\n`], { type: 'text/plain;charset=UTF-8' }),
    );
    const link = this.document.createElement('a');
    link.href = url;
    link.download = 'codigos-recuperacion-salud-nexus.txt';
    link.rel = 'noopener';
    link.click();
    this.objectUrls.revoke(url);
    this.announcement.set('Archivo creado desde memoria. Guárdalo en un lugar privado.');
  }

  protected finishEnrollment(): void {
    if (!this.custodyConfirmed()) {
      return;
    }

    const recoveryCodesRemaining = this.recoveryCodes().length;
    this.recoveryCodes.set([]);
    this.custodyConfirmed.set(false);
    this.status.set({
      enabled: true,
      method: 'totp',
      status: 'active',
      confirmedAt: new Date().toISOString(),
      recoveryCodesRemaining,
      requestId: this.enrollment()?.requestId ?? '',
    });
    this.stage.set('active');
    this.announcement.set('Segundo factor activado y códigos retirados de la pantalla.');
    this.focusStage();
  }

  protected retry(): void {
    void this.loadStatus();
  }

  private async loadStatus(): Promise<void> {
    this.stage.set('loading');
    this.errorMessage.set('');

    try {
      const status = await this.repository.getMfaStatus();
      this.status.set(status);
      this.stage.set(status.enabled ? 'active' : 'disabled');
    } catch {
      this.fail('No se pudo consultar la seguridad de la cuenta.');
    }
  }

  private fail(message: string): void {
    this.errorMessage.set(message);
    this.stage.set('error');
    this.focusStage();
  }

  private clearQr(): void {
    if (this.qrObjectUrl !== undefined) {
      this.objectUrls.revoke(this.qrObjectUrl);
      this.qrObjectUrl = undefined;
    }

    this.qrUrl.set(undefined);
  }

  private focusStage(): void {
    queueMicrotask(() => this.stageHeading()?.nativeElement.focus());
  }

  private clearConfirmationCode(): void {
    const input = this.confirmationInput()?.nativeElement;

    if (input !== undefined) {
      input.value = '';
    }

    this.confirmation.set({ code: '' });
  }
}
