import type { PatientRuntimeMode } from './patient-runtime.types';

// Development and automated browser tests are synthetic by default. The production
// build replaces this file with patient-runtime.production.ts.
export const PATIENT_RUNTIME_MODE: PatientRuntimeMode = 'demo';
