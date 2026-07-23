import type { Routes } from '@angular/router';

export const APPOINTMENT_ROUTES: Routes = [
  {
    path: '',
    title: 'Mis citas · Portal del paciente',
    loadComponent: () => import('./appointment-list').then((module) => module.AppointmentList),
  },
  {
    path: 'detalle',
    title: 'Detalle de cita · Portal del paciente',
    loadComponent: () => import('./appointment-detail').then((module) => module.AppointmentDetail),
  },
  {
    path: 'gestionar',
    title: 'Gestionar cita · Portal del paciente',
    loadComponent: () => import('./appointment-change').then((module) => module.AppointmentChange),
  },
  {
    path: 'reservar',
    title: 'Pedir una cita · Portal del paciente',
    loadComponent: () =>
      import('./appointment-booking').then((module) => module.AppointmentBooking),
  },
];
