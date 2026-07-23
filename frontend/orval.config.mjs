import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from 'orval';

import { finalizeGeneratedApi } from './scripts/finalize-generated-api.mjs';

const frontendRoot = dirname(fileURLToPath(import.meta.url));
const outputRoot = resolve(
  frontendRoot,
  process.env['SALUD_NEXUS_API_OUTPUT'] ?? 'projects/api-client/src/lib/generated',
);

export default defineConfig({
  saludNexus: {
    hooks: {
      afterAllFilesWrite: () => finalizeGeneratedApi(outputRoot),
    },
    input: {
      target: resolve(frontendRoot, '../backend/openapi/openapi.json'),
    },
    output: {
      baseUrl: {
        getBaseUrlFromSpecification: true,
      },
      client: 'angular',
      clean: true,
      formatter: 'prettier',
      headers: true,
      mode: 'single',
      schemas: {
        path: resolve(outputRoot, 'model'),
        type: 'zod',
      },
      target: resolve(outputRoot, 'salud-nexus-api.ts'),
      override: {
        angular: {
          provideIn: 'root',
          retrievalClient: 'httpClient',
          runtimeValidation: true,
        },
        zod: {
          generate: {
            header: true,
          },
          generateReusableSchemas: true,
          strict: {
            body: true,
            header: true,
            param: true,
            query: true,
            response: true,
          },
          version: 4,
        },
      },
    },
  },
});
