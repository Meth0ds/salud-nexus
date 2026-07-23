import { computed, Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

type SecuritySeverity = 'Crítico' | 'Aviso' | 'Informativo';
type SecurityFilter = 'Todos' | SecuritySeverity;

interface SyntheticAuditEvent {
  readonly action: string;
  readonly actor: string;
  readonly channel: string;
  readonly hash: string;
  readonly id: string;
  readonly resource: string;
  readonly severity: SecuritySeverity;
  readonly time: string;
}

@Component({
  selector: 'sn-design-security-audit-stream',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './security-audit-stream.html',
  styleUrl: './security-audit-stream.scss',
})
export class SecurityAuditStream {
  protected readonly events: readonly SyntheticAuditEvent[] = [
    {
      id: 'EVT-DEMO-A91F',
      time: '10:42:18',
      severity: 'Crítico',
      action: 'Acceso excepcional rechazado',
      actor: 'Cuenta DEMO-OPS-07',
      resource: 'Expediente •••• 8841',
      channel: 'Portal profesional',
      hash: '7f3a…b19c',
    },
    {
      id: 'EVT-DEMO-21C4',
      time: '10:39:03',
      severity: 'Aviso',
      action: 'Reautenticación requerida',
      actor: 'Cuenta DEMO-ADM-12',
      resource: 'Exportación DEMO-031',
      channel: 'Administración',
      hash: '21c4…90de',
    },
    {
      id: 'EVT-DEMO-832B',
      time: '10:34:56',
      severity: 'Informativo',
      action: 'Permiso de sesión renovado',
      actor: 'Cuenta DEMO-PRO-22',
      resource: 'Sesión •••• 1920',
      channel: 'Portal profesional',
      hash: '832b…f4a2',
    },
    {
      id: 'EVT-DEMO-5D8E',
      time: '10:31:41',
      severity: 'Informativo',
      action: 'Documento consultado',
      actor: 'Cuenta DEMO-PAC-18',
      resource: 'Documento •••• 4402',
      channel: 'Portal paciente',
      hash: '5d8e…13aa',
    },
    {
      id: 'EVT-DEMO-C070',
      time: '10:27:09',
      severity: 'Aviso',
      action: 'Límite de solicitudes alcanzado',
      actor: 'Cliente DEMO-API-03',
      resource: 'Ruta /v1/demo/**',
      channel: 'API gateway',
      hash: 'c070…7e31',
    },
  ];
  protected readonly filters: readonly SecurityFilter[] = [
    'Todos',
    'Crítico',
    'Aviso',
    'Informativo',
  ];
  protected readonly activeFilter = signal<SecurityFilter>('Todos');
  protected readonly selectedEventId = signal(this.events[0].id);
  protected readonly detailVisible = signal(true);
  protected readonly announcement = signal('');
  protected readonly filteredEvents = computed(() => {
    const filter = this.activeFilter();
    return filter === 'Todos'
      ? this.events
      : this.events.filter((event) => event.severity === filter);
  });
  protected readonly selectedEvent = computed(
    () => this.events.find((event) => event.id === this.selectedEventId()) ?? this.events[0],
  );

  protected applyFilter(filter: SecurityFilter): void {
    this.activeFilter.set(filter);
    this.announcement.set(
      `Filtro ${filter.toLocaleLowerCase('es')} aplicado. ${this.filteredEvents().length} eventos visibles.`,
    );
  }

  protected inspectEvent(event: SyntheticAuditEvent): void {
    this.selectedEventId.set(event.id);
    this.detailVisible.set(true);
    this.announcement.set(`Detalle de ${event.id} abierto.`);
  }

  protected closeDetail(): void {
    this.detailVisible.set(false);
    this.announcement.set('Detalle del evento cerrado.');
  }
}
