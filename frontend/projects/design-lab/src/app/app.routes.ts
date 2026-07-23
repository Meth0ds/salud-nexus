import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    title: 'Salud Nexus · Laboratorio de diseño',
    loadComponent: () => import('./pages/overview/overview').then((module) => module.Overview),
  },
  {
    path: 'catalogo',
    title: 'Catálogo de pantallas · Salud Nexus',
    loadComponent: () => import('./pages/catalog/catalog').then((module) => module.Catalog),
  },
  {
    path: 'mockups/:id',
    title: 'Mockup · Salud Nexus',
    loadComponent: () =>
      import('./pages/mockup-canvas/mockup-canvas').then((module) => module.MockupCanvas),
  },
  {
    path: 'showcase/paciente',
    title: 'Portal del paciente · Salud Nexus',
    loadComponent: () =>
      import('./showcases/patient-dashboard/patient-dashboard').then(
        (module) => module.PatientDashboard,
      ),
  },
  {
    path: 'showcase/clinico',
    title: 'Espacio clínico · Salud Nexus',
    loadComponent: () =>
      import('./showcases/clinician-workspace/clinician-workspace').then(
        (module) => module.ClinicianWorkspace,
      ),
  },
  {
    path: 'showcase/seguridad',
    title: 'Seguridad y cumplimiento · Salud Nexus',
    loadComponent: () =>
      import('./showcases/security-center/security-center').then((module) => module.SecurityCenter),
  },
  { path: '**', redirectTo: '' },
];
