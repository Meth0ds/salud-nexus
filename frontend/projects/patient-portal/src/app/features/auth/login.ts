import { Component, computed, ElementRef, inject, signal, viewChild } from '@angular/core';
import { email, form, FormField, minLength, required, submit } from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { Router } from '@angular/router';
import { SnIcon } from 'design-system';

import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';
import { PatientSessionStore } from '../../core/session.store';

@Component({
  selector: 'sn-patient-login',
  imports: [FormField, MatButtonModule, MatFormFieldModule, MatInputModule, SnIcon],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly router = inject(Router);
  private readonly sessionStore = inject(PatientSessionStore);
  private readonly loginMain = viewChild.required<ElementRef<HTMLElement>>('loginMain');

  protected readonly credentials = signal({
    email: '',
    password: '',
  });
  protected readonly loginForm = form(this.credentials, (path) => {
    required(path.email, { message: 'Escribe tu correo electrónico.' });
    email(path.email, { message: 'Escribe un correo con formato válido.' });
    required(path.password, { message: 'Escribe la contraseña o código de acceso.' });
    minLength(path.password, 8, { message: 'Debe tener al menos 8 caracteres.' });
  });
  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly loginError = signal('');
  protected readonly submitting = signal(false);
  protected readonly submitLabel = computed(() => {
    if (this.submitting()) {
      return 'Verificando acceso…';
    }
    return this.isDemo ? 'Entrar en la demostración' : 'Entrar de forma segura';
  });

  protected skipToAccess(event: Event): void {
    event.preventDefault();
    this.loginMain().nativeElement.focus();
  }

  protected signIn(): void {
    this.loginError.set('');
    void submit(this.loginForm, async () => {
      this.submitting.set(true);
      try {
        const result = await this.repository.authenticate(this.credentials());
        if (!result.authenticated) {
          this.loginError.set(result.message);
          return;
        }

        this.sessionStore.open(result.session);
        await this.router.navigateByUrl('/inicio', { replaceUrl: true });
      } finally {
        this.submitting.set(false);
      }
    });
  }
}
