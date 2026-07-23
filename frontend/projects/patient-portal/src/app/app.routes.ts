import type { Routes } from '@angular/router';

import { anonymousOnlyGuard, authenticatedGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'iniciar-sesion',
    title: 'Acceso · Salud Nexus',
    canActivate: [anonymousOnlyGuard],
    loadComponent: () => import('./features/auth/login').then((module) => module.Login),
  },
  {
    path: '',
    canActivate: [authenticatedGuard],
    canActivateChild: [authenticatedGuard],
    loadComponent: () => import('./layout/patient-shell').then((module) => module.PatientShell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'inicio' },
      {
        path: 'inicio',
        title: 'Inicio · Portal del paciente',
        loadComponent: () => import('./features/home/home').then((module) => module.Home),
      },
      {
        path: 'citas',
        loadChildren: () =>
          import('./features/appointments/appointment.routes').then(
            (module) => module.APPOINTMENT_ROUTES,
          ),
      },
      {
        path: 'medicacion',
        title: 'Medicación · Portal del paciente',
        loadComponent: () =>
          import('./features/medication/medication').then((module) => module.Medication),
      },
      {
        path: 'documentos',
        title: 'Documentos · Portal del paciente',
        loadComponent: () =>
          import('./features/documents/documents').then((module) => module.Documents),
      },
      {
        path: 'privacidad',
        title: 'Privacidad y accesos · Portal del paciente',
        loadComponent: () => import('./features/privacy/privacy').then((module) => module.Privacy),
      },
      {
        path: '**',
        title: 'Página no encontrada · Salud Nexus',
        loadComponent: () =>
          import('./features/not-found/not-found').then((module) => module.NotFound),
      },
    ],
  },
];
