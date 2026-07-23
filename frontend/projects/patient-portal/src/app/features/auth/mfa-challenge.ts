import { Component, computed, ElementRef, inject, signal, viewChild } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
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
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { Router } from '@angular/router';
import { SessionStore, type MfaChallengeVerification } from 'auth';
import { SnIcon } from 'design-system';
import { interval, map, startWith } from 'rxjs';

import type { AuthenticationResult } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PatientSessionStore } from '../../core/session.store';

const GENERIC_VERIFICATION_ERROR =
  'No hemos podido verificar el código. Revisa los datos e inténtalo de nuevo.';

@Component({
  selector: 'sn-patient-mfa-challenge',
  imports: [
    FormField,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
    SnIcon,
  ],
  templateUrl: './mfa-challenge.html',
  styleUrl: './mfa-challenge.scss',
})
export class MfaChallenge {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly router = inject(Router);
  private readonly sessionStore = inject(PatientSessionStore);
  private readonly authStore = inject(SessionStore);
  private readonly codeInput = viewChild<ElementRef<HTMLInputElement>>('codeInput');
  private readonly now = toSignal(
    interval(1_000).pipe(
      startWith(0),
      map(() => Date.now()),
    ),
    { initialValue: Date.now() },
  );

  protected readonly challenge = this.authStore.pendingMfaChallenge;
  protected readonly method = signal<'recovery' | 'totp'>('totp');
  protected readonly verification = signal({ code: '' });
  protected readonly verificationForm = form(this.verification, (path) => {
    required(path.code, { message: 'Escribe el código para continuar.' });
    minLength(path.code, () => (this.method() === 'totp' ? 6 : 23), {
      message: 'El código está incompleto.',
    });
    maxLength(path.code, () => (this.method() === 'totp' ? 6 : 64), {
      message: 'El código supera la longitud permitida.',
    });
    pattern(path.code, () => (this.method() === 'totp' ? /^\d{6}$/ : /^[A-Za-z0-9\s-]{23,64}$/), {
      message: 'Revisa el formato del código.',
    });
  });
  protected readonly submitting = signal(false);
  protected readonly verificationError = signal('');
  protected readonly hasRecoveryMethod = computed(
    () => this.challenge()?.methods.includes('recovery') ?? false,
  );
  protected readonly remainingSeconds = computed(() => {
    const expiresAt = this.challenge()?.expiresAt;
    const expiry = expiresAt === undefined ? Number.NaN : Date.parse(expiresAt);

    return Number.isFinite(expiry) ? Math.max(0, Math.ceil((expiry - this.now()) / 1_000)) : 0;
  });
  protected readonly expired = computed(() => this.remainingSeconds() === 0);
  protected readonly countdownLabel = computed(() => {
    const seconds = this.remainingSeconds();
    const minutesPart = Math.floor(seconds / 60);
    const secondsPart = seconds % 60;

    return `${minutesPart.toString().padStart(2, '0')}:${secondsPart.toString().padStart(2, '0')}`;
  });
  protected readonly submitLabel = computed(() =>
    this.submitting() ? 'Verificando…' : 'Verificar y entrar',
  );

  constructor() {
    const methods = this.challenge()?.methods;

    if (methods !== undefined && !methods.includes('totp') && methods.includes('recovery')) {
      this.method.set('recovery');
    }
  }

  protected useMethod(method: 'recovery' | 'totp'): void {
    if (method === 'recovery' && !this.hasRecoveryMethod()) {
      return;
    }

    this.method.set(method);
    this.verification.set({ code: '' });
    this.verificationError.set('');
    queueMicrotask(() => this.codeInput()?.nativeElement.focus());
  }

  protected verify(): void {
    this.verificationError.set('');

    void submit(this.verificationForm, async () => {
      if (this.expired() || this.challenge() === undefined) {
        this.verificationError.set('El reto ha caducado. Vuelve a iniciar sesión.');
        return;
      }

      this.submitting.set(true);
      const verification: MfaChallengeVerification = {
        method: this.method(),
        code: normalizeCode(this.method(), this.verification().code),
      };

      try {
        const result = await this.repository.verifyMfaChallenge(verification);
        await this.handleResult(result);
      } finally {
        this.verification.set({ code: '' });
        this.submitting.set(false);
      }
    });
  }

  protected restartLogin(): void {
    this.authStore.markAnonymous();
    this.verification.set({ code: '' });
    void this.router.navigateByUrl('/iniciar-sesion', { replaceUrl: true });
  }

  private async handleResult(result: AuthenticationResult): Promise<void> {
    if (result.kind !== 'authenticated') {
      this.verificationError.set(
        result.kind === 'rejected' ? result.message : GENERIC_VERIFICATION_ERROR,
      );
      return;
    }

    this.sessionStore.open(result.session);
    await this.router.navigateByUrl('/inicio', { replaceUrl: true });
  }
}

function normalizeCode(method: 'recovery' | 'totp', code: string): string {
  const normalized = code.trim();

  return method === 'recovery' ? normalized.toUpperCase() : normalized;
}
