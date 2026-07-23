import { ChangeDetectionStrategy, Component, input, signal } from '@angular/core';
import { SnIcon } from 'design-system';

export type MfaMockupMode = 'challenge' | 'enrollment';

@Component({
  selector: 'sn-design-mfa-flow-mockup',
  imports: [SnIcon],
  templateUrl: './mfa-flow-mockup.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MfaFlowMockup {
  readonly mode = input.required<MfaMockupMode>();

  protected readonly challengeMethod = signal<'recovery' | 'totp'>('totp');
  protected readonly enrollmentStage = signal<2 | 3>(2);

  protected useRecoveryCode(): void {
    this.challengeMethod.set('recovery');
  }

  protected useAuthenticatorCode(): void {
    this.challengeMethod.set('totp');
  }

  protected continueEnrollment(): void {
    this.enrollmentStage.set(3);
  }

  protected returnToScan(): void {
    this.enrollmentStage.set(2);
  }
}
