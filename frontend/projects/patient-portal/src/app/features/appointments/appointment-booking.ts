import { ViewportScroller } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { form, FormField, required, submit } from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { RouterLink } from '@angular/router';
import { SnIcon } from 'design-system';

import type {
  Appointment,
  BookingAppointmentType,
  BookingOptions,
  BookingSlot,
} from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';

interface BookingFormModel {
  appointmentTypeId: string;
  slotId: string;
}

@Component({
  selector: 'sn-patient-appointment-booking',
  imports: [FormField, MatButtonModule, MatFormFieldModule, MatInputModule, RouterLink, SnIcon],
  templateUrl: './appointment-booking.html',
  styleUrl: './appointment-booking.scss',
})
export class AppointmentBooking {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly viewportScroller = inject(ViewportScroller);
  private readonly clientRequestId = `booking-${crypto.randomUUID()}`;

  protected readonly bookingModel = signal<BookingFormModel>({
    appointmentTypeId: '',
    slotId: '',
  });
  protected readonly bookingForm = form(this.bookingModel, (path) => {
    required(path.appointmentTypeId, { message: 'Selecciona un servicio.' });
    required(path.slotId, { message: 'Selecciona una fecha y hora.' });
  });
  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly options = signal<BookingOptions | undefined>(undefined);
  protected readonly optionsError = signal('');
  protected readonly currentStep = signal(0);
  protected readonly submitting = signal(false);
  protected readonly reservation = signal<Appointment | undefined>(undefined);
  protected readonly bookingError = signal('');
  protected readonly announcement = signal('Paso 1 de 3: elige un servicio.');
  protected readonly steps = ['Servicio', 'Fecha y modalidad', 'Revisión'] as const;

  protected readonly availableAppointmentTypes = computed<readonly BookingAppointmentType[]>(
    () => this.options()?.appointmentTypes ?? [],
  );

  protected readonly selectedAppointmentType = computed(() =>
    this.availableAppointmentTypes().find(
      (type) => type.id === this.bookingModel().appointmentTypeId,
    ),
  );

  protected readonly availableSlots = computed<readonly BookingSlot[]>(() => {
    return this.selectedAppointmentType()?.slots ?? [];
  });

  protected readonly configuredCentre = computed(
    () =>
      this.availableAppointmentTypes()
        .flatMap((type) => type.slots)
        .at(0)?.centre,
  );

  protected readonly selectedSlot = computed(() =>
    this.availableSlots().find((slot) => slot.id === this.bookingModel().slotId),
  );

  protected readonly canContinue = computed(() => {
    const model = this.bookingModel();
    switch (this.currentStep()) {
      case 0:
        return model.appointmentTypeId.length > 0;
      case 1:
        return model.slotId.length > 0;
      case 2:
        return this.bookingForm().valid();
      default:
        return false;
    }
  });

  protected readonly selectedSlotLabel = computed(() => {
    const slot = this.selectedSlot();
    return slot === undefined ? 'Sin seleccionar' : `${slot.dateLabel} · ${slot.timeLabel}`;
  });

  protected readonly selectedCentreLabel = computed(
    () => this.selectedSlot()?.centre.name ?? this.configuredCentre()?.name ?? 'Centro configurado',
  );

  protected readonly attendanceModeLabel = computed(() => {
    switch (this.selectedAppointmentType()?.attendanceMode) {
      case 'video':
        return 'Videoconsulta';
      case 'phone':
        return 'Consulta telefónica';
      case 'in-person':
        return 'Presencial';
      default:
        return 'Sin seleccionar';
    }
  });

  constructor() {
    void this.loadOptions();
  }

  protected nextStep(): void {
    if (!this.canContinue() || this.currentStep() >= 2) {
      return;
    }
    this.currentStep.update((step) => step + 1);
    this.announcement.set(
      `Paso ${this.currentStep() + 1} de 3: ${this.steps[this.currentStep()]}.`,
    );
  }

  protected previousStep(): void {
    if (this.currentStep() === 0) {
      return;
    }
    this.currentStep.update((step) => step - 1);
    this.announcement.set(
      `Paso ${this.currentStep() + 1} de 3: ${this.steps[this.currentStep()]}.`,
    );
  }

  protected confirmBooking(): void {
    this.bookingError.set('');
    void submit(this.bookingForm, async () => {
      this.submitting.set(true);
      try {
        const model = this.bookingModel();
        const appointment = await this.repository.bookAppointment({
          appointmentTypeId: model.appointmentTypeId,
          slotId: model.slotId,
          clientRequestId: this.clientRequestId,
        });
        this.reservation.set(appointment);
        this.viewportScroller.scrollToPosition([0, 0]);
        this.announcement.set(
          this.isDemo ? 'Cita confirmada en la demostración.' : 'Cita confirmada.',
        );
      } catch {
        this.bookingError.set(
          this.isDemo
            ? 'No se pudo completar la reserva de demostración. Inténtalo de nuevo.'
            : 'No se pudo completar la reserva. La franja puede haber dejado de estar disponible.',
        );
      } finally {
        this.submitting.set(false);
      }
    });
  }

  private async loadOptions(): Promise<void> {
    this.optionsError.set('');
    try {
      this.options.set(await this.repository.getBookingOptions());
    } catch {
      this.optionsError.set('No se pudo consultar la disponibilidad. Inténtalo de nuevo.');
    }
  }
}
