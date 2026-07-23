import { ViewportScroller } from '@angular/common';
import { Component, computed, inject, signal, viewChild } from '@angular/core';
import { form, FormField, required, submit } from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { RouterLink } from '@angular/router';
import { ApiProblemError, ApiTransportError } from 'api-client';
import { SnIcon, SnStatusChip } from 'design-system';
import { ScrollReveal } from 'motion';

import type {
  Appointment,
  AppointmentCancellationReason,
  BookingOptions,
  BookingSlot,
  ViewState,
} from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';
import { AppointmentSelectionStore, DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';
import { AppointmentChangeEligibility } from './appointment-change-eligibility';
import { AppointmentChangeFeedback } from './appointment-change-feedback';
import { AppointmentChangeResult } from './appointment-change-result';
import { AppointmentCurrentCard } from './appointment-current-card';

type AppointmentChangeMode = 'cancel' | 'reschedule';
type MutationFailureKind = 'blocked' | 'conflict' | 'error' | 'offline';

interface AppointmentChangeData {
  readonly appointment: Appointment | undefined;
}

interface RescheduleFormModel {
  slotId: string;
}

interface CancellationFormModel {
  reason: AppointmentCancellationReason | '';
}

interface MutationAttempt {
  readonly clientRequestId: string;
  readonly fingerprint: string;
}

interface MutationFeedback {
  readonly kind: MutationFailureKind;
  readonly message: string;
  readonly mode: AppointmentChangeMode;
  readonly requestId?: string;
  readonly title: string;
}

interface CompletedChange {
  readonly after: Appointment;
  readonly before: Appointment;
  readonly mode: AppointmentChangeMode;
}

interface DateTileParts {
  readonly day: string;
  readonly month: string;
  readonly weekday: string;
}

@Component({
  selector: 'sn-patient-appointment-change',
  imports: [
    AppointmentChangeEligibility,
    AppointmentChangeFeedback,
    AppointmentChangeResult,
    AppointmentCurrentCard,
    DemoStatePanel,
    FormField,
    MatButtonModule,
    MatFormFieldModule,
    MatSelectModule,
    RouterLink,
    ScrollReveal,
    SnIcon,
    SnStatusChip,
  ],
  templateUrl: './appointment-change.html',
  styleUrl: './appointment-change.scss',
})
export class AppointmentChange {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly selection = inject(AppointmentSelectionStore);
  private readonly viewportScroller = inject(ViewportScroller);
  private readonly resultPanel = viewChild(AppointmentChangeResult);
  private readonly feedbackPanel = viewChild(AppointmentChangeFeedback);
  private readonly detail = signal<AppointmentChangeData | undefined>(undefined);
  private readonly bookingOptions = signal<BookingOptions | undefined>(undefined);
  private readonly loadFailure = signal<string | undefined>(undefined);
  private readonly accessRestricted = signal(false);
  private loadSequence = 0;
  private availabilitySequence = 0;
  private cancellationAttempt: MutationAttempt | undefined;
  private rescheduleAttempt: MutationAttempt | undefined;

  protected readonly scenarioStore = inject(DemoScenarioStore);
  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly mode = signal<AppointmentChangeMode>('reschedule');
  protected readonly availabilityLoading = signal(false);
  protected readonly availabilityError = signal('');
  protected readonly submitting = signal(false);
  protected readonly mutationFeedback = signal<MutationFeedback | undefined>(undefined);
  protected readonly completedChange = signal<CompletedChange | undefined>(undefined);
  protected readonly announcement = signal('');
  protected readonly rescheduleModel = signal<RescheduleFormModel>({ slotId: '' });
  protected readonly rescheduleForm = form(this.rescheduleModel, (path) => {
    required(path.slotId, { message: 'Selecciona una nueva fecha y hora.' });
  });
  protected readonly cancellationModel = signal<CancellationFormModel>({ reason: '' });
  protected readonly cancellationForm = form(this.cancellationModel, (path) => {
    required(path.reason, { message: 'Selecciona un motivo para continuar.' });
  });

  protected readonly viewState = computed<ViewState<AppointmentChangeData>>(() => {
    if (this.accessRestricted()) {
      return { kind: 'restricted' };
    }
    const failure = this.loadFailure();
    if (failure !== undefined) {
      return { kind: 'error', correlationId: failure };
    }
    return resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.detail(),
      (detail) => detail.appointment === undefined,
    );
  });

  protected readonly appointment = computed(() => this.detail()?.appointment);
  protected readonly isEligible = computed(() => {
    const appointment = this.appointment();
    return appointment?.status === 'scheduled' && appointment.changeAllowed;
  });
  protected readonly changeDeadlineLabel = computed(() => {
    const deadline = this.appointment()?.changeDeadline;
    return deadline === undefined ? '' : formatDeadline(deadline);
  });
  protected readonly availableSlots = computed<readonly BookingSlot[]>(() => {
    const appointment = this.appointment();
    if (appointment === undefined) {
      return [];
    }
    const appointmentType = this.bookingOptions()?.appointmentTypes.find(
      (candidate) => candidate.id === appointment.appointmentTypeId,
    );
    return (
      appointmentType?.slots.filter(
        (slot) => slot.centre.id === appointment.centreId && slot.startsAt !== appointment.startsAt,
      ) ?? []
    );
  });
  protected readonly selectedSlot = computed(() =>
    this.availableSlots().find((slot) => slot.id === this.rescheduleModel().slotId),
  );
  protected readonly selectedDate = computed<DateTileParts | undefined>(() => {
    const slot = this.selectedSlot();
    return slot === undefined ? undefined : dateTileParts(slot.startsAt);
  });
  protected readonly canReschedule = computed(
    () =>
      this.isEligible() &&
      !this.submitting() &&
      this.selectedSlot() !== undefined &&
      this.rescheduleForm().valid(),
  );
  protected readonly canCancel = computed(
    () =>
      this.isEligible() &&
      !this.submitting() &&
      this.cancellationModel().reason !== '' &&
      this.cancellationForm().valid(),
  );

  constructor() {
    void this.load();
  }

  protected selectMode(mode: AppointmentChangeMode): void {
    if (this.submitting()) {
      return;
    }
    this.mode.set(mode);
    this.mutationFeedback.set(undefined);
    this.announcement.set(
      mode === 'reschedule'
        ? 'Opción cambiar fecha y hora seleccionada.'
        : 'Opción cancelar cita seleccionada.',
    );
  }

  protected confirmReschedule(): void {
    this.mutationFeedback.set(undefined);
    void submit(this.rescheduleForm, async () => {
      const appointment = this.appointment();
      const slot = this.selectedSlot();
      if (appointment === undefined || slot === undefined || !this.isEligible()) {
        return;
      }

      const fingerprint = JSON.stringify({
        appointmentId: appointment.id,
        expectedVersion: appointment.version,
        slotId: slot.id,
      });
      if (this.rescheduleAttempt?.fingerprint !== fingerprint) {
        this.rescheduleAttempt = {
          clientRequestId: `appointment-reschedule-${crypto.randomUUID()}`,
          fingerprint,
        };
      }

      this.submitting.set(true);
      try {
        const changed = await this.repository.rescheduleAppointment({
          appointmentId: appointment.id,
          clientRequestId: this.rescheduleAttempt.clientRequestId,
          expectedVersion: appointment.version,
          slotId: slot.id,
        });
        this.finishMutation('reschedule', appointment, changed);
        this.rescheduleAttempt = undefined;
      } catch (error: unknown) {
        this.handleMutationFailure(error, 'reschedule');
      } finally {
        this.submitting.set(false);
      }
    });
  }

  protected confirmCancellation(): void {
    this.mutationFeedback.set(undefined);
    void submit(this.cancellationForm, async () => {
      const appointment = this.appointment();
      const reason = this.cancellationModel().reason;
      if (appointment === undefined || reason === '' || !this.isEligible()) {
        return;
      }

      const fingerprint = JSON.stringify({
        appointmentId: appointment.id,
        expectedVersion: appointment.version,
        reason,
      });
      if (this.cancellationAttempt?.fingerprint !== fingerprint) {
        this.cancellationAttempt = {
          clientRequestId: `appointment-cancellation-${crypto.randomUUID()}`,
          fingerprint,
        };
      }

      this.submitting.set(true);
      try {
        const changed = await this.repository.cancelAppointment({
          appointmentId: appointment.id,
          clientRequestId: this.cancellationAttempt.clientRequestId,
          expectedVersion: appointment.version,
          reason,
        });
        this.finishMutation('cancel', appointment, changed);
        this.cancellationAttempt = undefined;
      } catch (error: unknown) {
        this.handleMutationFailure(error, 'cancel');
      } finally {
        this.submitting.set(false);
      }
    });
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    this.completedChange.set(undefined);
    this.mutationFeedback.set(undefined);
    void this.load();
  }

  protected retryAvailability(): void {
    const appointment = this.appointment();
    if (appointment !== undefined) {
      void this.loadAvailability(appointment);
    }
  }

  protected recoverFromMutation(): void {
    const feedback = this.mutationFeedback();
    if (feedback === undefined || this.submitting()) {
      return;
    }
    if (feedback.kind === 'blocked' || feedback.kind === 'conflict') {
      this.retry();
      return;
    }
    if (feedback.mode === 'reschedule') {
      this.confirmReschedule();
    } else {
      this.confirmCancellation();
    }
  }

  private async load(): Promise<void> {
    const sequence = ++this.loadSequence;
    this.detail.set(undefined);
    this.bookingOptions.set(undefined);
    this.availabilityError.set('');
    this.loadFailure.set(undefined);
    this.accessRestricted.set(false);
    const selectedId = this.selection.selectedId();
    if (selectedId === undefined) {
      this.detail.set({ appointment: undefined });
      return;
    }

    try {
      const appointment = await this.repository.getAppointment(selectedId);
      if (sequence !== this.loadSequence) {
        return;
      }
      this.detail.set({ appointment });
      if (appointment?.status === 'scheduled' && appointment.changeAllowed) {
        await this.loadAvailability(appointment);
      }
    } catch (error: unknown) {
      if (sequence !== this.loadSequence) {
        return;
      }
      if (error instanceof ApiProblemError && error.status === 403) {
        this.accessRestricted.set(true);
        return;
      }
      this.loadFailure.set(requestIdFor(error));
    }
  }

  private async loadAvailability(appointment: Appointment): Promise<void> {
    const sequence = ++this.availabilitySequence;
    this.availabilityLoading.set(true);
    this.availabilityError.set('');
    try {
      const options = await this.repository.getBookingOptions();
      if (sequence === this.availabilitySequence && this.appointment()?.id === appointment.id) {
        this.bookingOptions.set(options);
      }
    } catch {
      if (sequence === this.availabilitySequence) {
        this.bookingOptions.set(undefined);
        this.availabilityError.set(
          'No se pudo consultar la disponibilidad del centro. Puedes reintentar o cancelar la cita.',
        );
      }
    } finally {
      if (sequence === this.availabilitySequence) {
        this.availabilityLoading.set(false);
      }
    }
  }

  private finishMutation(
    mode: AppointmentChangeMode,
    before: Appointment,
    after: Appointment,
  ): void {
    this.detail.set({ appointment: after });
    this.completedChange.set({ mode, before, after });
    this.mutationFeedback.set(undefined);
    this.announcement.set(mode === 'reschedule' ? 'Cita cambiada.' : 'Cita cancelada.');
    this.viewportScroller.scrollToPosition([0, 0]);
    queueMicrotask(() => this.resultPanel()?.focus());
  }

  private handleMutationFailure(error: unknown, mode: AppointmentChangeMode): void {
    const feedback = mutationFeedbackFor(error, mode, this.isDemo);
    this.mutationFeedback.set(feedback);
    this.announcement.set(`${feedback.title}. ${feedback.message}`);
    queueMicrotask(() => this.feedbackPanel()?.focus());
  }
}

function mutationFeedbackFor(
  error: unknown,
  mode: AppointmentChangeMode,
  isDemo: boolean,
): MutationFeedback {
  if (error instanceof ApiProblemError) {
    if (error.status === 409 || error.status === 412) {
      return {
        kind: 'conflict',
        mode,
        requestId: error.requestId,
        title: 'La cita ha cambiado',
        message: 'Recarga la información antes de volver a confirmar para evitar sobrescribirla.',
      };
    }
    if (error.status === 403 || error.status === 422) {
      return {
        kind: 'blocked',
        mode,
        requestId: error.requestId,
        title: 'El cambio ya no está disponible',
        message: 'Recarga la cita para consultar el estado y el límite confirmados por el centro.',
      };
    }
  }

  if (error instanceof ApiTransportError && (error.status === 0 || error.status >= 500)) {
    return {
      kind: 'offline',
      mode,
      requestId: error.requestId,
      title: 'No se pudo confirmar con el centro',
      message:
        'Tu cita puede seguir igual. Recupera la conexión y reintenta; conservaremos la misma clave segura.',
    };
  }

  if (
    isDemo &&
    error instanceof Error &&
    /actualizad|disponible|admite cambios/iu.test(error.message)
  ) {
    return {
      kind: 'conflict',
      mode,
      title: 'La cita ha cambiado',
      message: 'Recarga los datos sintéticos antes de volver a confirmar.',
    };
  }

  return {
    kind: 'error',
    mode,
    requestId:
      error instanceof ApiProblemError || error instanceof ApiTransportError
        ? error.requestId
        : undefined,
    title: 'No se pudo completar la operación',
    message: 'La cita actual no se ha modificado. Inténtalo de nuevo dentro de unos instantes.',
  };
}

function requestIdFor(error: unknown): string {
  if (
    (error instanceof ApiProblemError || error instanceof ApiTransportError) &&
    error.requestId !== undefined
  ) {
    return error.requestId;
  }
  return `portal-${crypto.randomUUID().slice(0, 8)}`;
}

function dateTileParts(value: string): DateTileParts {
  const date = new Date(value);
  return {
    day: new Intl.DateTimeFormat('es-ES', {
      day: '2-digit',
      timeZone: 'Europe/Madrid',
    }).format(date),
    month: new Intl.DateTimeFormat('es-ES', {
      month: 'short',
      timeZone: 'Europe/Madrid',
    })
      .format(date)
      .replace('.', '')
      .toLocaleUpperCase('es-ES'),
    weekday: new Intl.DateTimeFormat('es-ES', {
      weekday: 'short',
      timeZone: 'Europe/Madrid',
    })
      .format(date)
      .replace('.', '')
      .toLocaleUpperCase('es-ES'),
  };
}

function formatDeadline(value: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Europe/Madrid',
  }).format(new Date(value));
}
