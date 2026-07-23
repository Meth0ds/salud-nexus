import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { map } from 'rxjs';
import { SnIcon, SnMetricCard, SnShellCard, SnStatusChip, type SnStatusTone } from 'design-system';

import {
  motionProfiles,
  responsiveProfiles,
  screenCatalog,
  type MotionProfile,
  type ResponsiveProfile,
  type ScreenDefinition,
  type ScreenFamily,
  type ScreenPriority,
  type VisualStateProfile,
  visualStateProfiles,
} from '../../screen-catalog';
import { AppointmentChangeMockup } from './appointment-change-mockup';
import { MfaFlowMockup } from './mfa-flow-mockup';
import { MockupDesignNotes } from './mockup-design-notes';

interface NavItem {
  readonly icon: string;
  readonly label: string;
}

interface FamilyUi {
  readonly label: string;
  readonly icon: string;
  readonly primaryAction: string;
  readonly nav: readonly NavItem[];
  readonly metrics: readonly [string, string, string];
}

const FAMILY_UI: Readonly<Record<ScreenFamily, FamilyUi>> = {
  G: {
    label: 'Acceso y experiencia global',
    icon: 'health_and_safety',
    primaryAction: 'Continuar de forma segura',
    nav: [
      { icon: 'login', label: 'Acceso' },
      { icon: 'badge', label: 'Identidad' },
      { icon: 'help', label: 'Ayuda' },
    ],
    metrics: ['Pasos completados', 'Métodos disponibles', 'Nivel de confianza'],
  },
  P: {
    label: 'Portal del paciente',
    icon: 'personal_injury',
    primaryAction: 'Gestionar mi salud',
    nav: [
      { icon: 'home', label: 'Inicio' },
      { icon: 'calendar_month', label: 'Citas' },
      { icon: 'pill', label: 'Medicamentos' },
      { icon: 'folder_open', label: 'Documentos' },
      { icon: 'visibility_lock', label: 'Privacidad' },
    ],
    metrics: ['Próximas citas', 'Documentos nuevos', 'Acciones pendientes'],
  },
  C: {
    label: 'Espacio clínico',
    icon: 'clinical_notes',
    primaryAction: 'Registrar actuación',
    nav: [
      { icon: 'calendar_today', label: 'Agenda' },
      { icon: 'groups', label: 'Sala de espera' },
      { icon: 'person_search', label: 'Pacientes' },
      { icon: 'medication', label: 'Conciliación' },
      { icon: 'task_alt', label: 'Tareas' },
    ],
    metrics: ['Citas del día', 'En espera', 'Tareas abiertas'],
  },
  R: {
    label: 'Recepción',
    icon: 'concierge',
    primaryAction: 'Crear gestión',
    nav: [
      { icon: 'space_dashboard', label: 'Operativa' },
      { icon: 'event_available', label: 'Citas' },
      { icon: 'person_search', label: 'Pacientes' },
      { icon: 'meeting_room', label: 'Recursos' },
      { icon: 'notifications', label: 'Avisos' },
    ],
    metrics: ['Llegadas', 'Huecos libres', 'Incidencias'],
  },
  A: {
    label: 'Administración',
    icon: 'admin_panel_settings',
    primaryAction: 'Crear configuración',
    nav: [
      { icon: 'dashboard', label: 'Resumen' },
      { icon: 'domain', label: 'Centro' },
      { icon: 'manage_accounts', label: 'Profesionales' },
      { icon: 'tune', label: 'Configuración' },
      { icon: 'monitoring', label: 'Informes' },
    ],
    metrics: ['Servicios activos', 'Usuarios habilitados', 'Revisiones'],
  },
  S: {
    label: 'Seguridad y cumplimiento',
    icon: 'shield_lock',
    primaryAction: 'Abrir investigación',
    nav: [
      { icon: 'shield', label: 'Seguridad' },
      { icon: 'policy', label: 'Cumplimiento' },
      { icon: 'history', label: 'Auditoría' },
      { icon: 'emergency', label: 'Incidentes' },
      { icon: 'verified_user', label: 'Evidencias' },
    ],
    metrics: ['Eventos revisados', 'Alertas abiertas', 'Controles vigentes'],
  },
  O: {
    label: 'Overlay seguro',
    icon: 'dialogs',
    primaryAction: 'Confirmar y auditar',
    nav: [
      { icon: 'home', label: 'Contexto anterior' },
      { icon: 'history', label: 'Actividad' },
    ],
    metrics: ['Contexto conservado', 'Validaciones', 'Eventos auditados'],
  },
};

const PRIORITY_TONE: Readonly<Record<ScreenPriority, SnStatusTone>> = {
  critica: 'danger',
  alta: 'warning',
  media: 'info',
  baja: 'neutral',
};

@Component({
  selector: 'sn-design-mockup-canvas',
  imports: [
    AppointmentChangeMockup,
    MfaFlowMockup,
    RouterLink,
    SnIcon,
    SnMetricCard,
    SnShellCard,
    SnStatusChip,
    MockupDesignNotes,
  ],
  templateUrl: './mockup-canvas.html',
  styleUrl: './mockup-canvas.scss',
})
export class MockupCanvas {
  private readonly route = inject(ActivatedRoute);
  private readonly screens: readonly ScreenDefinition[] = screenCatalog;
  private readonly states: readonly VisualStateProfile[] = visualStateProfiles;
  private readonly responsiveProfiles: readonly ResponsiveProfile[] = responsiveProfiles;
  private readonly motionProfiles: readonly MotionProfile[] = motionProfiles;

  private readonly requestedId = toSignal(
    this.route.paramMap.pipe(map((parameters) => parameters.get('id')?.toUpperCase() ?? '')),
    { initialValue: '' },
  );

  protected readonly screen = computed(() =>
    this.screens.find((candidate) => candidate.id === this.requestedId()),
  );
  protected readonly family = computed(() => {
    const screen = this.screen();
    return screen ? FAMILY_UI[screen.id[0] as ScreenFamily] : undefined;
  });
  protected readonly stateProfile = computed(() => {
    const screen = this.screen();
    return screen ? this.states.find((profile) => profile.id === screen.estadoVisual) : undefined;
  });
  protected readonly responsiveProfile = computed(() => {
    const screen = this.screen();
    return screen
      ? this.responsiveProfiles.find((profile) => profile.id === screen.responsive)
      : undefined;
  });
  protected readonly motionProfile = computed(() => {
    const screen = this.screen();
    return screen ? this.motionProfiles.find((profile) => profile.id === screen.motion) : undefined;
  });

  protected priorityTone(priority: ScreenPriority): SnStatusTone {
    return PRIORITY_TONE[priority];
  }
}
