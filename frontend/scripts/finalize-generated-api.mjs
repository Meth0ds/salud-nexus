import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const OPERATION_EXPORT = "export * from './operationTypes';";

const OPERATION_TYPES = `/**
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
`;

export function finalizeGeneratedApi(outputRoot) {
  const modelRoot = resolve(outputRoot, 'model');
  const indexPath = resolve(modelRoot, 'index.ts');

  if (!existsSync(indexPath)) {
    throw new Error('Orval no generó el índice de modelos esperado.');
  }

  writeFileSync(resolve(modelRoot, 'operationTypes.ts'), OPERATION_TYPES, 'utf8');

  const index = readFileSync(indexPath, 'utf8');
  if (!index.includes(OPERATION_EXPORT)) {
    writeFileSync(indexPath, `${index.trimEnd()}\n${OPERATION_EXPORT}\n`, 'utf8');
  }
}
