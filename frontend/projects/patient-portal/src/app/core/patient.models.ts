import type { MfaChallengeView } from 'auth';

export type AttendanceMode = 'in-person' | 'phone' | 'video';
export type AppointmentStatus = 'cancelled' | 'completed' | 'no-show' | 'scheduled';
export type DemoScenario = 'empty' | 'error' | 'loading' | 'ready' | 'restricted';

export interface PatientSession {
  readonly displayName: string;
  readonly initials: string;
  readonly runtime: 'connected' | 'demo';
}

export interface PatientCredentials {
  readonly email: string;
  readonly password: string;
}

export type AuthenticationResult =
  | {
      readonly kind: 'rejected';
      readonly message: string;
    }
  | {
      readonly kind: 'mfa-required';
      readonly challenge: MfaChallengeView;
    }
  | {
      readonly kind: 'authenticated';
      readonly session: PatientSession;
    };

export interface Appointment {
  readonly id: string;
  readonly appointmentTypeId: string;
  readonly centreId: string;
  readonly title: string;
  readonly professional: string;
  readonly specialty: string;
  readonly centre: string;
  readonly room: string;
  readonly dateIso: string;
  readonly startsAt: string;
  readonly endsAt: string;
  readonly dateLabel: string;
  readonly timeLabel: string;
  readonly timezone: string;
  readonly attendanceMode: AttendanceMode;
  readonly status: AppointmentStatus;
  readonly version: number;
  readonly changeAllowed: boolean;
  readonly changeDeadline: string;
  readonly preparation: readonly string[];
}

export type AppointmentCancellationReason =
  'feeling-better' | 'other' | 'plans-changed' | 'transport-issue';

export interface AppointmentCancellationRequest {
  readonly appointmentId: string;
  readonly clientRequestId: string;
  readonly expectedVersion: number;
  readonly reason: AppointmentCancellationReason;
}

export interface AppointmentRescheduleRequest {
  readonly appointmentId: string;
  readonly clientRequestId: string;
  readonly expectedVersion: number;
  readonly slotId: string;
}

export interface AppointmentBookingRequest {
  readonly appointmentTypeId: string;
  readonly clientRequestId: string;
  readonly slotId: string;
}

export interface BookingCentre {
  readonly id: string;
  readonly name: string;
  readonly timezone: string;
}

export interface BookingSlot {
  readonly centre: BookingCentre;
  readonly dateLabel: string;
  readonly endsAt: string;
  readonly id: string;
  readonly locationLabel: string;
  readonly startsAt: string;
  readonly timeLabel: string;
}

export interface BookingAppointmentType {
  readonly attendanceMode: AttendanceMode;
  readonly durationMinutes: number;
  readonly id: string;
  readonly name: string;
  readonly serviceName: string;
  readonly slots: readonly BookingSlot[];
}

export interface BookingOptions {
  readonly appointmentTypes: readonly BookingAppointmentType[];
  readonly generatedAt: string;
}

export interface MedicationItem {
  readonly id: string;
  readonly name: string;
  readonly presentation: string;
  readonly scheduleLabel: string;
  readonly source: 'patient-declaration' | 'professional-record';
  readonly sourceLabel: string;
  readonly status: 'active' | 'inactive';
  readonly canRequestRenewal: boolean;
  readonly renewalRequestStatus: 'submitted' | null;
  readonly lastUpdatedLabel: string;
}

export interface MedicationDeclarationRequest {
  readonly clientRequestId: string;
  readonly name: string;
  readonly presentation: string;
  readonly scheduleLabel: string;
}

export interface MedicationRenewalResult {
  readonly id: string;
  readonly medicationId: string;
  readonly requestedAt: string;
  readonly status: 'submitted';
}

export interface PatientDocument {
  readonly id: string;
  readonly title: string;
  readonly category:
    'attendance-certificate' | 'care-summary' | 'consent' | 'laboratory' | 'medication-summary';
  readonly categoryLabel: string;
  readonly dateIso: string;
  readonly dateLabel: string;
  readonly publishedAt: string;
  readonly centre: string;
  readonly format: 'PDF';
  readonly mimeType: 'application/pdf';
  readonly sizeBytes: number;
  readonly sizeLabel: string;
  readonly versionNumber: number;
  readonly integrityStatus: 'verified';
  readonly canDownload: boolean;
}

export interface DocumentDownloadAuthorization {
  readonly documentId: string;
  readonly downloadUrl: string;
  readonly expiresAt: string;
}

export interface AccessEvent {
  readonly id: string;
  readonly actorLabel: string;
  readonly roleLabel: string;
  readonly purposeLabel: string;
  readonly dateIso: string;
  readonly dateLabel: string;
  readonly result: 'Legitimate access';
}

export interface DashboardSummary {
  readonly nextAppointment: Appointment | undefined;
  readonly medication: readonly MedicationItem[];
  readonly recentDocuments: readonly PatientDocument[];
  readonly recentAccesses: readonly AccessEvent[];
}

export type ViewState<T> =
  | { readonly kind: 'loading' }
  | { readonly kind: 'empty' }
  | { readonly kind: 'error'; readonly correlationId: string }
  | { readonly kind: 'restricted' }
  | { readonly kind: 'ready'; readonly data: T };
