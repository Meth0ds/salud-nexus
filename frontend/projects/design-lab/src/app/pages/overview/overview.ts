import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ScrollReveal } from 'motion';

interface ShowcaseSummary {
  readonly id: string;
  readonly audience: string;
  readonly title: string;
  readonly description: string;
  readonly icon: string;
  readonly route: string;
}

@Component({
  selector: 'sn-design-overview',
  imports: [RouterLink, ScrollReveal],
  templateUrl: './overview.html',
  styleUrl: './overview.scss',
})
export class Overview {
  protected readonly showcases: readonly ShowcaseSummary[] = [
    {
      id: '01',
      audience: 'Portal del paciente',
      title: 'Mi salud, sin fricción',
      description:
        'Próximas citas, medicación informativa, documentos y accesos con lenguaje claro.',
      icon: 'personal_injury',
      route: '/showcase/paciente',
    },
    {
      id: '02',
      audience: 'Espacio clínico',
      title: 'Contexto justo a tiempo',
      description: 'Agenda de alta densidad y contexto asistencial mínimo, siempre trazable.',
      icon: 'clinical_notes',
      route: '/showcase/clinico',
    },
    {
      id: '03',
      audience: 'Seguridad y cumplimiento',
      title: 'Gobernanza accionable',
      description:
        'Accesos, riesgo, break-glass y certificación de permisos en una consola unificada.',
      icon: 'shield_lock',
      route: '/showcase/seguridad',
    },
  ];

  protected readonly principles = [
    {
      icon: 'visibility_lock',
      title: 'Privacidad visible',
      text: 'La persona entiende quién consulta sus datos, por qué y durante cuánto tiempo.',
    },
    {
      icon: 'touch_app',
      title: 'Densidad adaptativa',
      text: 'Calma en móvil para pacientes; precisión y velocidad en el escritorio profesional.',
    },
    {
      icon: 'motion_mode',
      title: 'Movimiento con propósito',
      text: 'ScrollFX solo orienta en contenido explicativo y respeta reducción de movimiento.',
    },
    {
      icon: 'accessible_forward',
      title: 'Acceso equivalente',
      text: 'Teclado, lector de pantalla, zoom, reflow y colores forzados son estados de diseño.',
    },
  ] as const;
}
