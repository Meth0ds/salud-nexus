/**
 * Generated companion types for Orval 8.22 Angular operation parameters.
 * Do not edit manually; update the OpenAPI contract and generator configuration.
 */

export interface ListPatientAppointmentsParams {
  readonly scope?: 'upcoming' | 'past' | 'all';
  readonly page?: number;
  readonly per_page?: number;
}

export interface BookPatientAppointmentHeaders {
  readonly 'Idempotency-Key': string;
}

export interface CancelPatientAppointmentHeaders {
  readonly 'Idempotency-Key': string;
  readonly 'If-Match': string;
}

export interface ReschedulePatientAppointmentHeaders {
  readonly 'Idempotency-Key': string;
  readonly 'If-Match': string;
}

export interface DeclarePatientMedicationHeaders {
  readonly 'Idempotency-Key': string;
}

export interface RequestPatientMedicationRenewalHeaders {
  readonly 'Idempotency-Key': string;
}
