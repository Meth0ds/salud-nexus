import { InjectionToken, type Provider } from '@angular/core';
import { ApiClient } from 'api-client';
import {
  type MfaChallengeVerification,
  type MfaStatusView,
  type RecoveryCodesView,
  SessionAuth,
  type TotpEnrollmentView,
} from 'auth';

import { HttpPatientRepository } from './http-patient-repository';
import { InMemoryPatientRepository } from './in-memory-patient-repository';
import type {
  AccessEvent,
  Appointment,
  AppointmentBookingRequest,
  AppointmentCancellationRequest,
  AppointmentRescheduleRequest,
  AuthenticationResult,
  BookingOptions,
  DashboardSummary,
  DocumentDownloadAuthorization,
  MedicationDeclarationRequest,
  MedicationItem,
  MedicationRenewalResult,
  PatientCredentials,
  PatientDocument,
} from './patient.models';
import { PATIENT_RUNTIME_MODE } from './patient-runtime';

export interface PatientRepository {
  authenticate(credentials: PatientCredentials): Promise<AuthenticationResult>;
  verifyMfaChallenge(verification: MfaChallengeVerification): Promise<AuthenticationResult>;
  getMfaStatus(): Promise<MfaStatusView>;
  beginTotpEnrollment(): Promise<TotpEnrollmentView>;
  discloseTotpEnrollmentQr(): Promise<string>;
  confirmTotpEnrollment(code: string): Promise<RecoveryCodesView>;
  authorizeDocumentDownload(documentId: string): Promise<DocumentDownloadAuthorization>;
  bookAppointment(request: AppointmentBookingRequest): Promise<Appointment>;
  cancelAppointment(request: AppointmentCancellationRequest): Promise<Appointment>;
  clearSensitiveRuntimeState(): void;
  declareMedication(request: MedicationDeclarationRequest): Promise<MedicationItem>;
  getAppointment(id: string): Promise<Appointment | undefined>;
  getBookingOptions(): Promise<BookingOptions>;
  getDashboardSummary(): Promise<DashboardSummary>;
  listAccessEvents(): Promise<readonly AccessEvent[]>;
  listAppointments(): Promise<readonly Appointment[]>;
  listDocuments(): Promise<readonly PatientDocument[]>;
  listMedication(): Promise<readonly MedicationItem[]>;
  requestMedicationRenewal(
    medicationId: string,
    clientRequestId: string,
  ): Promise<MedicationRenewalResult>;
  rescheduleAppointment(request: AppointmentRescheduleRequest): Promise<Appointment>;
}

export const PATIENT_REPOSITORY = new InjectionToken<PatientRepository>('PATIENT_REPOSITORY');

export function provideDemoPatientRepository(): Provider {
  return { provide: PATIENT_REPOSITORY, useClass: InMemoryPatientRepository };
}

export function providePatientRepository(): Provider {
  return {
    provide: PATIENT_REPOSITORY,
    deps: [ApiClient, SessionAuth],
    useFactory: (api: ApiClient, sessionAuth: SessionAuth): PatientRepository =>
      PATIENT_RUNTIME_MODE === 'demo'
        ? new InMemoryPatientRepository()
        : new HttpPatientRepository(api, sessionAuth),
  };
}
