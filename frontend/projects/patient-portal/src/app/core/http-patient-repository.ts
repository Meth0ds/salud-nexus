import { HttpParams } from '@angular/common/http';
import {
  ApiClient,
  ApiContractError,
  ApiProblemError,
  AppointmentEnvelope as GeneratedAppointmentEnvelopeSchema,
  AppointmentListEnvelope as GeneratedAppointmentListEnvelopeSchema,
  BookingOptionsEnvelope as GeneratedBookingOptionsEnvelopeSchema,
  DocumentDownloadAuthorizationEnvelope as GeneratedDocumentDownloadAuthorizationEnvelopeSchema,
  DocumentListEnvelope as GeneratedDocumentListEnvelopeSchema,
  MedicationEnvelope as GeneratedMedicationEnvelopeSchema,
  MedicationListEnvelope as GeneratedMedicationListEnvelopeSchema,
  MedicationRenewalEnvelope as GeneratedMedicationRenewalEnvelopeSchema,
  type AppointmentEnvelopeOutput as GeneratedAppointmentEnvelopeOutput,
  type AppointmentListEnvelopeOutput as GeneratedAppointmentListEnvelopeOutput,
  type BookingOptionsEnvelopeOutput as GeneratedBookingOptionsEnvelopeOutput,
  type DocumentDownloadAuthorizationEnvelopeOutput as GeneratedDocumentDownloadAuthorizationEnvelopeOutput,
  type DocumentListEnvelopeOutput as GeneratedDocumentListEnvelopeOutput,
  type MedicationEnvelopeOutput as GeneratedMedicationEnvelopeOutput,
  type MedicationListEnvelopeOutput as GeneratedMedicationListEnvelopeOutput,
  type MedicationRenewalEnvelopeOutput as GeneratedMedicationRenewalEnvelopeOutput,
  type RuntimeSchema,
  type RuntimeSchemaResult,
  type ApiValidatedResponse,
} from 'api-client';
import {
  type MfaChallengeVerification,
  type MfaStatusView,
  type RecoveryCodesView,
  SessionAuth,
  type TotpEnrollmentView,
} from 'auth';
import { firstValueFrom } from 'rxjs';
import { parsePublicId } from 'shared';

import type {
  AccessEvent,
  Appointment,
  AppointmentBookingRequest,
  AppointmentCancellationReason,
  AppointmentCancellationRequest,
  AppointmentRescheduleRequest,
  AppointmentStatus,
  AttendanceMode,
  AuthenticationResult,
  BookingAppointmentType,
  BookingOptions,
  BookingSlot,
  DashboardSummary,
  DocumentDownloadAuthorization,
  MedicationDeclarationRequest,
  MedicationItem,
  MedicationRenewalResult,
  PatientCredentials,
  PatientDocument,
  PatientSession,
} from './patient.models';
import type { PatientRepository } from './patient-repository';

const UUID_V7_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const OFFSET_DATETIME_PATTERN =
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/;
const GENERIC_AUTHENTICATION_ERROR = 'No hemos podido verificar los datos de acceso.';
const DOCUMENT_DOWNLOAD_PATH_PATTERN =
  /^\/api\/v1\/patient\/document-downloads\/[A-Za-z0-9_-]{43}$/;

type DeepReadonly<T> = T extends readonly (infer Item)[]
  ? readonly DeepReadonly<Item>[]
  : T extends object
    ? { readonly [Key in keyof T]: DeepReadonly<T[Key]> }
    : T;

type ApiAppointmentEnvelope = DeepReadonly<GeneratedAppointmentEnvelopeOutput>;
type ApiAppointmentListEnvelope = DeepReadonly<GeneratedAppointmentListEnvelopeOutput>;
type ApiBookingOptionsEnvelope = DeepReadonly<GeneratedBookingOptionsEnvelopeOutput>;
type ApiMedicationEnvelope = DeepReadonly<GeneratedMedicationEnvelopeOutput>;
type ApiMedicationListEnvelope = DeepReadonly<GeneratedMedicationListEnvelopeOutput>;
type ApiMedicationRenewalEnvelope = DeepReadonly<GeneratedMedicationRenewalEnvelopeOutput>;
type ApiPatientDocumentListEnvelope = DeepReadonly<GeneratedDocumentListEnvelopeOutput>;
type ApiDocumentDownloadAuthorizationEnvelope =
  DeepReadonly<GeneratedDocumentDownloadAuthorizationEnvelopeOutput>;

type ApiAppointment = ApiAppointmentEnvelope['data'];
type ApiAttendanceMode = ApiAppointment['attendance_mode'];
type ApiAppointmentStatus = ApiAppointment['status'];
type ApiCenter = ApiAppointment['center'];
type ApiBookingType = ApiBookingOptionsEnvelope['data']['appointment_types'][number];
type ApiBookingSlot = ApiBookingType['slots'][number];
type ApiMedication = ApiMedicationEnvelope['data'];
type ApiMedicationSource = ApiMedication['source'];
type ApiMedicationStatus = ApiMedication['status'];
type ApiPatientDocument = ApiPatientDocumentListEnvelope['data'][number];
type ApiDocumentCategory = ApiPatientDocument['category'];

const appointmentEnvelopeSchema = refineGeneratedSchema(
  GeneratedAppointmentEnvelopeSchema,
  parseAppointmentEnvelope,
);
const appointmentListEnvelopeSchema = refineGeneratedSchema(
  GeneratedAppointmentListEnvelopeSchema,
  parseAppointmentListEnvelope,
);
const bookingOptionsEnvelopeSchema = refineGeneratedSchema(
  GeneratedBookingOptionsEnvelopeSchema,
  parseBookingOptionsEnvelope,
);
const medicationEnvelopeSchema = refineGeneratedSchema(
  GeneratedMedicationEnvelopeSchema,
  parseMedicationEnvelope,
);
const medicationListEnvelopeSchema = refineGeneratedSchema(
  GeneratedMedicationListEnvelopeSchema,
  parseMedicationListEnvelope,
);
const medicationRenewalEnvelopeSchema = refineGeneratedSchema(
  GeneratedMedicationRenewalEnvelopeSchema,
  parseMedicationRenewalEnvelope,
);
const patientDocumentListEnvelopeSchema = refineGeneratedSchema(
  GeneratedDocumentListEnvelopeSchema,
  parsePatientDocumentListEnvelope,
);
const documentDownloadAuthorizationEnvelopeSchema = refineGeneratedSchema(
  GeneratedDocumentDownloadAuthorizationEnvelopeSchema,
  parseDocumentDownloadAuthorizationEnvelope,
);

export class HttpPatientRepository implements PatientRepository {
  constructor(
    private readonly api: ApiClient,
    private readonly sessionAuth: SessionAuth,
  ) {}

  async authenticate(credentials: PatientCredentials): Promise<AuthenticationResult> {
    try {
      const outcome = await firstValueFrom(
        this.sessionAuth.login({ email: credentials.email, password: credentials.password }),
      );

      if (outcome.kind === 'mfa-required') {
        return outcome;
      }

      return {
        kind: 'authenticated',
        session: toPatientSession(outcome.session.identity.displayName),
      };
    } catch {
      return { kind: 'rejected', message: GENERIC_AUTHENTICATION_ERROR };
    }
  }

  async verifyMfaChallenge(verification: MfaChallengeVerification): Promise<AuthenticationResult> {
    try {
      const session = await firstValueFrom(this.sessionAuth.verifyMfaChallenge(verification));

      return {
        kind: 'authenticated',
        session: toPatientSession(session.identity.displayName),
      };
    } catch {
      return { kind: 'rejected', message: GENERIC_AUTHENTICATION_ERROR };
    }
  }

  getMfaStatus(): Promise<MfaStatusView> {
    return firstValueFrom(this.sessionAuth.getMfaStatus());
  }

  beginTotpEnrollment(): Promise<TotpEnrollmentView> {
    return firstValueFrom(this.sessionAuth.beginTotpEnrollment());
  }

  discloseTotpEnrollmentQr(): Promise<string> {
    return firstValueFrom(this.sessionAuth.discloseTotpEnrollmentQr());
  }

  confirmTotpEnrollment(code: string): Promise<RecoveryCodesView> {
    return firstValueFrom(this.sessionAuth.confirmTotpEnrollment(code));
  }

  async authorizeDocumentDownload(documentId: string): Promise<DocumentDownloadAuthorization> {
    const publicId = requiredPublicId(documentId);
    const envelope = await firstValueFrom(
      this.api.post(
        `/patient/documents/${publicId}/download-authorizations`,
        {},
        documentDownloadAuthorizationEnvelopeSchema,
      ),
    );
    if (envelope.data.document_id !== publicId) {
      throw new ApiContractError();
    }

    return {
      documentId: envelope.data.document_id,
      downloadUrl: envelope.data.download_url,
      expiresAt: envelope.data.expires_at,
    };
  }

  async bookAppointment(request: AppointmentBookingRequest): Promise<Appointment> {
    const envelope = await firstValueFrom(
      this.api.post(
        '/patient/appointments',
        {
          appointment_type_id: requiredPublicId(request.appointmentTypeId),
          slot_id: requiredPublicId(request.slotId),
        },
        appointmentEnvelopeSchema,
        { idempotencyKey: request.clientRequestId },
      ),
    );

    return toAppointment(envelope.data);
  }

  async cancelAppointment(request: AppointmentCancellationRequest): Promise<Appointment> {
    const appointmentId = requiredPublicId(request.appointmentId);
    const response = await firstValueFrom(
      this.api.postResponse(
        `/patient/appointments/${appointmentId}/cancellations`,
        { reason_code: toApiCancellationReason(request.reason) },
        appointmentEnvelopeSchema,
        {
          idempotencyKey: request.clientRequestId,
          ifMatch: appointmentEtag(request.expectedVersion),
        },
      ),
    );

    return appointmentFromVersionedResponse(response, true);
  }

  clearSensitiveRuntimeState(): void {
    void firstValueFrom(this.sessionAuth.logout()).catch(() => undefined);
  }

  async declareMedication(request: MedicationDeclarationRequest): Promise<MedicationItem> {
    const envelope = await firstValueFrom(
      this.api.post(
        '/patient/medications/declarations',
        {
          name: request.name.trim(),
          presentation: request.presentation.trim() || null,
          schedule_label: request.scheduleLabel.trim(),
        },
        medicationEnvelopeSchema,
        { idempotencyKey: request.clientRequestId },
      ),
    );

    return toMedication(envelope.data);
  }

  async getAppointment(id: string): Promise<Appointment | undefined> {
    const publicId = parsePublicId(id);
    if (publicId === undefined) {
      return undefined;
    }

    try {
      const response = await firstValueFrom(
        this.api.getResponse(`/patient/appointments/${publicId}`, appointmentEnvelopeSchema),
      );
      return appointmentFromVersionedResponse(response, false);
    } catch (error: unknown) {
      if (error instanceof ApiProblemError && error.status === 404) {
        return undefined;
      }
      throw error;
    }
  }

  async getBookingOptions(): Promise<BookingOptions> {
    const envelope = await firstValueFrom(
      this.api.get('/patient/booking-options', bookingOptionsEnvelopeSchema),
    );

    return {
      generatedAt: envelope.meta.generated_at,
      appointmentTypes: envelope.data.appointment_types.map(toBookingAppointmentType),
    };
  }

  async getDashboardSummary(): Promise<DashboardSummary> {
    const [appointments, medication, documents] = await Promise.all([
      this.listAppointments(),
      this.listMedication(),
      this.listDocuments(),
    ]);
    return {
      nextAppointment: appointments.find((appointment) => appointment.status === 'scheduled'),
      medication: medication.slice(0, 2),
      recentDocuments: documents.slice(0, 2),
      recentAccesses: [],
    };
  }

  listAccessEvents(): Promise<readonly AccessEvent[]> {
    return Promise.resolve([]);
  }

  async listAppointments(): Promise<readonly Appointment[]> {
    const params = new HttpParams().set('scope', 'all').set('per_page', 50).set('page', 1);
    const envelope = await firstValueFrom(
      this.api.get('/patient/appointments', appointmentListEnvelopeSchema, { params }),
    );
    return envelope.data.map(toAppointment);
  }

  async listDocuments(): Promise<readonly PatientDocument[]> {
    const envelope = await firstValueFrom(
      this.api.get('/patient/documents', patientDocumentListEnvelopeSchema),
    );
    return envelope.data.map(toPatientDocument);
  }

  async listMedication(): Promise<readonly MedicationItem[]> {
    const envelope = await firstValueFrom(
      this.api.get('/patient/medications', medicationListEnvelopeSchema),
    );
    return envelope.data.map(toMedication);
  }

  async requestMedicationRenewal(
    medicationId: string,
    clientRequestId: string,
  ): Promise<MedicationRenewalResult> {
    const envelope = await firstValueFrom(
      this.api.post(
        `/patient/medications/${requiredPublicId(medicationId)}/renewal-requests`,
        {},
        medicationRenewalEnvelopeSchema,
        { idempotencyKey: clientRequestId },
      ),
    );

    return {
      id: envelope.data.id,
      medicationId: envelope.data.medication_id,
      requestedAt: envelope.data.requested_at,
      status: envelope.data.status,
    };
  }

  async rescheduleAppointment(request: AppointmentRescheduleRequest): Promise<Appointment> {
    const appointmentId = requiredPublicId(request.appointmentId);
    const response = await firstValueFrom(
      this.api.postResponse(
        `/patient/appointments/${appointmentId}/reschedules`,
        { slot_id: requiredPublicId(request.slotId) },
        appointmentEnvelopeSchema,
        {
          idempotencyKey: request.clientRequestId,
          ifMatch: appointmentEtag(request.expectedVersion),
        },
      ),
    );

    return appointmentFromVersionedResponse(response, true);
  }
}

function toAppointment(value: ApiAppointment): Appointment {
  const attendanceMode = toAttendanceMode(value.attendance_mode);
  return {
    id: value.id,
    appointmentTypeId: value.appointment_type.id,
    centreId: value.center.id,
    title: value.appointment_type.name,
    professional: value.professional_display_name ?? 'Profesional por confirmar',
    specialty: value.service.name,
    centre: value.center.name,
    room: value.location_label ?? locationFallback(attendanceMode),
    dateIso: value.local_starts_at.slice(0, 10),
    startsAt: value.local_starts_at,
    endsAt: value.local_ends_at,
    dateLabel: formatDate(value.local_starts_at, value.center.timezone),
    timeLabel: formatTimeRange(value.local_starts_at, value.local_ends_at, value.center.timezone),
    timezone: timezoneLabel(value.center.timezone),
    attendanceMode,
    status: toAppointmentStatus(value.status),
    version: value.version,
    changeAllowed: value.change_allowed,
    changeDeadline: value.change_deadline,
    preparation: ['Consulta las indicaciones que publique tu centro antes de acudir.'],
  };
}

function appointmentFromVersionedResponse(
  response: ApiValidatedResponse<ApiAppointmentEnvelope>,
  requireIdempotencyMetadata: boolean,
): Appointment {
  const appointment = response.data.data;

  if (
    response.etag !== appointmentEtag(appointment.version) ||
    (requireIdempotencyMetadata && response.idempotencyReplayed === undefined)
  ) {
    throw new ApiContractError();
  }

  return toAppointment(appointment);
}

function appointmentEtag(version: number): string {
  if (!Number.isInteger(version) || version < 1 || version > 4_294_967_295) {
    throw new Error('Expected a positive appointment version.');
  }

  return `"v${version}"`;
}

function toApiCancellationReason(
  reason: AppointmentCancellationReason,
): 'feeling_better' | 'other' | 'plans_changed' | 'transport_issue' {
  switch (reason) {
    case 'feeling-better':
      return 'feeling_better';
    case 'other':
      return 'other';
    case 'plans-changed':
      return 'plans_changed';
    case 'transport-issue':
      return 'transport_issue';
  }
}

function toBookingAppointmentType(value: ApiBookingType): BookingAppointmentType {
  return {
    id: value.id,
    name: value.name,
    serviceName: value.service.name,
    durationMinutes: value.duration_minutes,
    attendanceMode: toAttendanceMode(value.attendance_mode),
    slots: value.slots.map(toBookingSlot),
  };
}

function toBookingSlot(value: ApiBookingSlot): BookingSlot {
  return {
    id: value.id,
    startsAt: value.local_starts_at,
    endsAt: value.local_ends_at,
    dateLabel: formatDate(value.local_starts_at, value.center.timezone),
    timeLabel: formatTimeRange(value.local_starts_at, value.local_ends_at, value.center.timezone),
    locationLabel: value.location_label ?? 'Ubicación por confirmar',
    centre: value.center,
  };
}

function toMedication(value: ApiMedication): MedicationItem {
  const source =
    value.source === 'professional_record' ? 'professional-record' : 'patient-declaration';
  return {
    id: value.id,
    name: value.name,
    presentation: value.presentation ?? 'Presentación no indicada',
    scheduleLabel: value.schedule_label,
    source,
    sourceLabel:
      source === 'professional-record' ? 'Registro profesional del centro' : 'Declarada por ti',
    status: value.status,
    canRequestRenewal: value.can_request_renewal,
    renewalRequestStatus: value.renewal_request_status,
    lastUpdatedLabel: `Actualizado el ${formatMedicationDate(value.updated_at)}`,
  };
}

function toPatientDocument(value: ApiPatientDocument): PatientDocument {
  const category = value.category.replaceAll('_', '-') as PatientDocument['category'];
  return {
    id: value.id,
    title: value.title,
    category,
    categoryLabel: documentCategoryLabel(value.category),
    dateIso: value.published_at.slice(0, 10),
    dateLabel: formatDocumentDate(value.published_at),
    publishedAt: value.published_at,
    centre: value.center.name,
    format: 'PDF',
    mimeType: value.file.mime_type,
    sizeBytes: value.file.size_bytes,
    sizeLabel: formatDocumentSize(value.file.size_bytes),
    versionNumber: value.file.version,
    integrityStatus: value.integrity_status,
    canDownload: value.can_download,
  };
}

function refineGeneratedSchema<T>(
  generatedSchema: RuntimeSchema<unknown>,
  semanticParser: (value: unknown) => RuntimeSchemaResult<T>,
): RuntimeSchema<T> {
  return {
    safeParse(value: unknown): RuntimeSchemaResult<T> {
      const contractResult = generatedSchema.safeParse(value);

      return contractResult.success ? semanticParser(contractResult.data) : { success: false };
    },
  };
}

function parseAppointmentEnvelope(value: unknown): RuntimeSchemaResult<ApiAppointmentEnvelope> {
  if (!isRecord(value) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const appointment = parseAppointment(value['data']);
  const requestId = parseUuid(value['meta']['request_id']);
  return appointment === undefined || requestId === undefined
    ? { success: false }
    : { success: true, data: { data: appointment, meta: { request_id: requestId } } };
}

function parseAppointmentListEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiAppointmentListEnvelope> {
  if (!isRecord(value) || !Array.isArray(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const appointments = parseArray(value['data'], parseAppointment, 100);
  const meta = value['meta'];
  const requestId = parseUuid(meta['request_id']);
  const page = parsePositiveInteger(meta['page']);
  const perPage = parsePositiveInteger(meta['per_page']);
  const total = parseNonNegativeInteger(meta['total']);
  const lastPage = parsePositiveInteger(meta['last_page']);
  if (
    appointments === undefined ||
    requestId === undefined ||
    page === undefined ||
    perPage === undefined ||
    total === undefined ||
    lastPage === undefined
  ) {
    return { success: false };
  }
  return {
    success: true,
    data: {
      data: appointments,
      meta: { page, per_page: perPage, total, last_page: lastPage, request_id: requestId },
    },
  };
}

function parseBookingOptionsEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiBookingOptionsEnvelope> {
  if (!isRecord(value) || !isRecord(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const rawTypes = value['data']['appointment_types'];
  if (!Array.isArray(rawTypes)) {
    return { success: false };
  }
  const appointmentTypes = parseArray(rawTypes, parseBookingType, 100);
  const generatedAt = parseDateTime(value['meta']['generated_at']);
  const requestId = parseUuid(value['meta']['request_id']);
  return appointmentTypes === undefined ||
    !hasSingleCenter(appointmentTypes) ||
    generatedAt === undefined ||
    requestId === undefined
    ? { success: false }
    : {
        success: true,
        data: {
          data: { appointment_types: appointmentTypes },
          meta: { generated_at: generatedAt, request_id: requestId },
        },
      };
}

function hasSingleCenter(appointmentTypes: readonly ApiBookingType[]): boolean {
  const centerIds = new Set(
    appointmentTypes.flatMap((appointmentType) =>
      appointmentType.slots.map((slot) => slot.center.id),
    ),
  );
  return centerIds.size <= 1;
}

function parseMedicationEnvelope(value: unknown): RuntimeSchemaResult<ApiMedicationEnvelope> {
  if (!isRecord(value) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const medication = parseMedication(value['data']);
  const requestId = parseUuid(value['meta']['request_id']);
  return medication === undefined || requestId === undefined
    ? { success: false }
    : { success: true, data: { data: medication, meta: { request_id: requestId } } };
}

function parseMedicationListEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiMedicationListEnvelope> {
  if (!isRecord(value) || !Array.isArray(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const medication = parseArray(value['data'], parseMedication, 200);
  const requestId = parseUuid(value['meta']['request_id']);
  return medication === undefined || requestId === undefined
    ? { success: false }
    : { success: true, data: { data: medication, meta: { request_id: requestId } } };
}

function parseMedicationRenewalEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiMedicationRenewalEnvelope> {
  if (!isRecord(value) || !isRecord(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const data = value['data'];
  const id = parseUuidV7(data['id']);
  const medicationId = parseUuidV7(data['medication_id']);
  const requestedAt = parseDateTime(data['requested_at']);
  const requestId = parseUuid(value['meta']['request_id']);
  if (
    id === undefined ||
    medicationId === undefined ||
    requestedAt === undefined ||
    data['status'] !== 'submitted' ||
    requestId === undefined
  ) {
    return { success: false };
  }
  return {
    success: true,
    data: {
      data: {
        id,
        medication_id: medicationId,
        requested_at: requestedAt,
        status: 'submitted',
      },
      meta: { request_id: requestId },
    },
  };
}

function parsePatientDocumentListEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiPatientDocumentListEnvelope> {
  if (!isRecord(value) || !Array.isArray(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const documents = parseArray(value['data'], parsePatientDocument, 100);
  const requestId = parseUuid(value['meta']['request_id']);
  return documents === undefined || requestId === undefined
    ? { success: false }
    : { success: true, data: { data: documents, meta: { request_id: requestId } } };
}

function parseDocumentDownloadAuthorizationEnvelope(
  value: unknown,
): RuntimeSchemaResult<ApiDocumentDownloadAuthorizationEnvelope> {
  if (!isRecord(value) || !isRecord(value['data']) || !isRecord(value['meta'])) {
    return { success: false };
  }
  const documentId = parseUuidV7(value['data']['document_id']);
  const downloadUrl = value['data']['download_url'];
  const expiresAt = parseDateTime(value['data']['expires_at']);
  const requestId = parseUuid(value['meta']['request_id']);
  if (
    documentId === undefined ||
    typeof downloadUrl !== 'string' ||
    !DOCUMENT_DOWNLOAD_PATH_PATTERN.test(downloadUrl) ||
    expiresAt === undefined ||
    requestId === undefined
  ) {
    return { success: false };
  }

  return {
    success: true,
    data: {
      data: {
        document_id: documentId,
        download_url: downloadUrl,
        expires_at: expiresAt,
      },
      meta: { request_id: requestId },
    },
  };
}

function parseAppointment(value: unknown): ApiAppointment | undefined {
  if (!isRecord(value) || !isRecord(value['service']) || !isRecord(value['appointment_type'])) {
    return undefined;
  }
  const center = parseCenter(value['center']);
  const id = parseUuidV7(value['id']);
  const status = parseAppointmentStatus(value['status']);
  const version = parsePositiveInteger(value['version']);
  const changeAllowed =
    typeof value['change_allowed'] === 'boolean' ? value['change_allowed'] : undefined;
  const changeDeadline = parseDateTime(value['change_deadline']);
  const mode = parseAttendanceMode(value['attendance_mode']);
  const startsAt = parseDateTime(value['starts_at']);
  const localStartsAt = parseDateTime(value['local_starts_at']);
  const endsAt = parseDateTime(value['ends_at']);
  const localEndsAt = parseDateTime(value['local_ends_at']);
  const serviceId = parseUuidV7(value['service']['id']);
  const serviceName = parseText(value['service']['name'], 160);
  const typeId = parseUuidV7(value['appointment_type']['id']);
  const typeName = parseText(value['appointment_type']['name'], 160);
  const duration = parsePositiveInteger(value['appointment_type']['duration_minutes']);
  const location = parseNullableText(value['location_label'], 160);
  const professional = parseNullableText(value['professional_display_name'], 160);
  if (
    center === undefined ||
    id === undefined ||
    status === undefined ||
    version === undefined ||
    changeAllowed === undefined ||
    changeDeadline === undefined ||
    mode === undefined ||
    startsAt === undefined ||
    localStartsAt === undefined ||
    endsAt === undefined ||
    localEndsAt === undefined ||
    serviceId === undefined ||
    serviceName === undefined ||
    typeId === undefined ||
    typeName === undefined ||
    duration === undefined ||
    location === undefined ||
    professional === undefined
  ) {
    return undefined;
  }
  if (status !== 'scheduled' && changeAllowed) {
    return undefined;
  }
  return {
    id,
    status,
    version,
    change_allowed: changeAllowed,
    change_deadline: changeDeadline,
    attendance_mode: mode,
    location_label: location,
    professional_display_name: professional,
    service: { id: serviceId, name: serviceName },
    appointment_type: { id: typeId, name: typeName, duration_minutes: duration },
    center,
    starts_at: startsAt,
    local_starts_at: localStartsAt,
    ends_at: endsAt,
    local_ends_at: localEndsAt,
  };
}

function parseBookingType(value: unknown): ApiBookingType | undefined {
  if (!isRecord(value) || !isRecord(value['service']) || !Array.isArray(value['slots'])) {
    return undefined;
  }
  const id = parseUuidV7(value['id']);
  const name = parseText(value['name'], 160);
  const duration = parsePositiveInteger(value['duration_minutes']);
  const mode = parseAttendanceMode(value['attendance_mode']);
  const serviceId = parseUuidV7(value['service']['id']);
  const serviceName = parseText(value['service']['name'], 160);
  const slots = parseArray(value['slots'], parseBookingSlot, 500);
  if (
    id === undefined ||
    name === undefined ||
    duration === undefined ||
    mode === undefined ||
    serviceId === undefined ||
    serviceName === undefined ||
    slots === undefined
  ) {
    return undefined;
  }
  return {
    id,
    name,
    duration_minutes: duration,
    attendance_mode: mode,
    service: { id: serviceId, name: serviceName },
    slots,
  };
}

function parseBookingSlot(value: unknown): ApiBookingSlot | undefined {
  if (!isRecord(value)) {
    return undefined;
  }
  const id = parseUuidV7(value['id']);
  const startsAt = parseDateTime(value['starts_at']);
  const endsAt = parseDateTime(value['ends_at']);
  const localStartsAt = parseDateTime(value['local_starts_at']);
  const localEndsAt = parseDateTime(value['local_ends_at']);
  const center = parseCenter(value['center']);
  const location = parseNullableText(value['location_label'], 160);
  if (
    id === undefined ||
    startsAt === undefined ||
    endsAt === undefined ||
    localStartsAt === undefined ||
    localEndsAt === undefined ||
    center === undefined ||
    location === undefined
  ) {
    return undefined;
  }
  return {
    id,
    starts_at: startsAt,
    ends_at: endsAt,
    local_starts_at: localStartsAt,
    local_ends_at: localEndsAt,
    center,
    location_label: location,
  };
}

function parseMedication(value: unknown): ApiMedication | undefined {
  if (!isRecord(value)) {
    return undefined;
  }
  const id = parseUuidV7(value['id']);
  const source = parseMedicationSource(value['source']);
  const name = parseText(value['name'], 160);
  const presentation = parseNullableText(value['presentation'], 120);
  const renewalRequestStatus = parseNullableSubmittedStatus(value['renewal_request_status']);
  const scheduleLabel = parseText(value['schedule_label'], 160);
  const status = parseMedicationStatus(value['status']);
  const canRequestRenewal = parseBoolean(value['can_request_renewal']);
  const updatedAt = parseDateTime(value['updated_at']);
  if (
    id === undefined ||
    source === undefined ||
    name === undefined ||
    presentation === undefined ||
    renewalRequestStatus === undefined ||
    scheduleLabel === undefined ||
    status === undefined ||
    canRequestRenewal === undefined ||
    updatedAt === undefined ||
    canRequestRenewal !==
      (source === 'professional_record' && status === 'active' && renewalRequestStatus === null)
  ) {
    return undefined;
  }
  return {
    id,
    source,
    name,
    presentation,
    renewal_request_status: renewalRequestStatus,
    schedule_label: scheduleLabel,
    status,
    can_request_renewal: canRequestRenewal,
    updated_at: updatedAt,
  };
}

function parsePatientDocument(value: unknown): ApiPatientDocument | undefined {
  if (!isRecord(value) || !isRecord(value['center']) || !isRecord(value['file'])) {
    return undefined;
  }
  const id = parseUuidV7(value['id']);
  const title = parseText(value['title'], 160);
  const category = parseDocumentCategory(value['category']);
  const publishedAt = parseDateTime(value['published_at']);
  const centerId = parseUuidV7(value['center']['id']);
  const centerName = parseText(value['center']['name'], 160);
  const sizeBytes = parsePositiveInteger(value['file']['size_bytes']);
  const version = parsePositiveInteger(value['file']['version']);
  const canDownload = parseBoolean(value['can_download']);
  if (
    id === undefined ||
    title === undefined ||
    category === undefined ||
    publishedAt === undefined ||
    centerId === undefined ||
    centerName === undefined ||
    value['file']['mime_type'] !== 'application/pdf' ||
    sizeBytes === undefined ||
    sizeBytes > 10_485_760 ||
    version === undefined ||
    version > 65_535 ||
    value['integrity_status'] !== 'verified' ||
    canDownload === undefined
  ) {
    return undefined;
  }

  return {
    id,
    title,
    category,
    published_at: publishedAt,
    center: { id: centerId, name: centerName },
    file: { mime_type: 'application/pdf', size_bytes: sizeBytes, version },
    integrity_status: 'verified',
    can_download: canDownload,
  };
}

function parseCenter(value: unknown): ApiCenter | undefined {
  if (!isRecord(value)) {
    return undefined;
  }
  const id = parseUuidV7(value['id']);
  const name = parseText(value['name'], 160);
  const timezone = parseText(value['timezone'], 64);
  return id === undefined || name === undefined || timezone === undefined
    ? undefined
    : { id, name, timezone };
}

function parseArray<T>(
  values: readonly unknown[],
  parser: (value: unknown) => T | undefined,
  maximum: number,
): readonly T[] | undefined {
  if (values.length > maximum) {
    return undefined;
  }
  const parsed: T[] = [];
  for (const value of values) {
    const item = parser(value);
    if (item === undefined) {
      return undefined;
    }
    parsed.push(item);
  }
  return parsed;
}

function parseUuid(value: unknown): string | undefined {
  return typeof value === 'string' && UUID_V7_PATTERN.test(value.toLowerCase())
    ? value.toLowerCase()
    : undefined;
}

function parseUuidV7(value: unknown): string | undefined {
  return parseUuid(value);
}

function parseDateTime(value: unknown): string | undefined {
  return typeof value === 'string' &&
    OFFSET_DATETIME_PATTERN.test(value) &&
    Number.isFinite(Date.parse(value))
    ? value
    : undefined;
}

function parseText(value: unknown, maximum: number): string | undefined {
  return typeof value === 'string' && value.length > 0 && value.length <= maximum
    ? value
    : undefined;
}

function parseNullableText(value: unknown, maximum: number): string | null | undefined {
  return value === null ? null : parseText(value, maximum);
}

function parseBoolean(value: unknown): boolean | undefined {
  return typeof value === 'boolean' ? value : undefined;
}

function parseNullableSubmittedStatus(value: unknown): 'submitted' | null | undefined {
  return value === null || value === 'submitted' ? value : undefined;
}

function parsePositiveInteger(value: unknown): number | undefined {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? value : undefined;
}

function parseNonNegativeInteger(value: unknown): number | undefined {
  return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0 ? value : undefined;
}

function parseAttendanceMode(value: unknown): ApiAttendanceMode | undefined {
  return value === 'in_person' || value === 'phone' || value === 'video' ? value : undefined;
}

function parseAppointmentStatus(value: unknown): ApiAppointmentStatus | undefined {
  return value === 'scheduled' ||
    value === 'completed' ||
    value === 'cancelled' ||
    value === 'no_show'
    ? value
    : undefined;
}

function parseMedicationSource(value: unknown): ApiMedicationSource | undefined {
  return value === 'professional_record' || value === 'patient_declaration' ? value : undefined;
}

function parseMedicationStatus(value: unknown): ApiMedicationStatus | undefined {
  return value === 'active' || value === 'inactive' ? value : undefined;
}

function parseDocumentCategory(value: unknown): ApiDocumentCategory | undefined {
  return value === 'attendance_certificate' ||
    value === 'care_summary' ||
    value === 'consent' ||
    value === 'laboratory' ||
    value === 'medication_summary'
    ? value
    : undefined;
}

function toAttendanceMode(value: ApiAttendanceMode): AttendanceMode {
  return value === 'in_person' ? 'in-person' : value;
}

function toAppointmentStatus(value: ApiAppointmentStatus): AppointmentStatus {
  return value === 'no_show' ? 'no-show' : value;
}

function requiredPublicId(value: string): string {
  const id = parsePublicId(value);
  if (id === undefined) {
    throw new Error('Expected a public UUIDv7 identifier.');
  }
  return id;
}

function initialsFor(displayName: string): string {
  return displayName
    .trim()
    .split(/\s+/u)
    .slice(0, 2)
    .map((part) => part.charAt(0).toLocaleUpperCase('es-ES'))
    .join('');
}

function toPatientSession(displayName: string): PatientSession {
  return {
    displayName,
    initials: initialsFor(displayName),
    runtime: 'connected',
  };
}

function formatDate(value: string, timeZone: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone,
  }).format(new Date(value));
}

function formatTimeRange(startsAt: string, endsAt: string, timeZone: string): string {
  const formatter = new Intl.DateTimeFormat('es-ES', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone,
  });
  return `${formatter.format(new Date(startsAt))}–${formatter.format(new Date(endsAt))}`;
}

function formatMedicationDate(value: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Europe/Madrid',
  }).format(new Date(value));
}

function formatDocumentDate(value: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Europe/Madrid',
  }).format(new Date(value));
}

function formatDocumentSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1_048_576) {
    return `${Math.ceil(bytes / 1024)} KB`;
  }
  return `${new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 }).format(bytes / 1_048_576)} MB`;
}

function documentCategoryLabel(category: ApiDocumentCategory): string {
  switch (category) {
    case 'attendance_certificate':
      return 'Justificante de asistencia';
    case 'care_summary':
      return 'Resumen asistencial';
    case 'consent':
      return 'Consentimiento';
    case 'laboratory':
      return 'Laboratorio';
    case 'medication_summary':
      return 'Resumen de medicación';
  }
}

function timezoneLabel(timezone: string): string {
  return timezone === 'Europe/Madrid' ? 'Hora peninsular' : timezone;
}

function locationFallback(mode: AttendanceMode): string {
  switch (mode) {
    case 'video':
      return 'Videoconsulta segura';
    case 'phone':
      return 'Consulta telefónica';
    case 'in-person':
      return 'Ubicación por confirmar';
  }
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
