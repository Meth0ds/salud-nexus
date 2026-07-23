import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'agenda' },
  {
    path: 'agenda',
    title: 'Agenda clínica · Salud Nexus',
    loadComponent: () =>
      import('./features/agenda/agenda-page').then((module) => module.AgendaPage),
  },
  {
    path: 'recepcion',
    title: 'Recepción y llegada · Salud Nexus',
    loadComponent: () =>
      import('./features/reception/reception-page').then((module) => module.ReceptionPage),
  },
  {
    path: 'administracion',
    title: 'Administración y seguridad · Salud Nexus',
    loadComponent: () =>
      import('./features/administration/administration-page').then(
        (module) => module.AdministrationPage,
      ),
  },
  { path: '**', redirectTo: 'agenda' },
];
