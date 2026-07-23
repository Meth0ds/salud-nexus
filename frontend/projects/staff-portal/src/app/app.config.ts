import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideApiClient } from 'api-client';
import { sessionExpiryInterceptor } from 'auth';

import { routes } from './app.routes';
import { InMemoryStaffWorkspaceRepository } from './core/in-memory-staff-workspace.repository';
import { STAFF_WORKSPACE_REPOSITORY } from './core/staff-workspace.repository';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(withInterceptors([sessionExpiryInterceptor])),
    provideApiClient({ baseUrl: '/api/v1' }),
    provideRouter(routes),
    InMemoryStaffWorkspaceRepository,
    {
      provide: STAFF_WORKSPACE_REPOSITORY,
      useExisting: InMemoryStaffWorkspaceRepository,
    },
  ],
};
