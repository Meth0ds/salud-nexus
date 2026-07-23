import { Component, computed, signal } from '@angular/core';
import { form, FormField } from '@angular/forms/signals';
import { RouterLink } from '@angular/router';

import {
  mockupRouteFor,
  screenCatalog,
  screenCatalogCount,
  type ScreenAudience,
  type ScreenDefinition,
  type ScreenFamily,
  type ScreenPriority,
} from '../../screen-catalog';

type FilterValue<T extends string> = T | 'todas';

interface CatalogFilters {
  readonly query: string;
  readonly family: FilterValue<ScreenFamily>;
  readonly audience: FilterValue<ScreenAudience>;
  readonly priority: FilterValue<ScreenPriority>;
}

interface FilterOption<T extends string> {
  readonly value: FilterValue<T>;
  readonly label: string;
}

@Component({
  selector: 'sn-design-catalog',
  imports: [FormField, RouterLink],
  templateUrl: './catalog.html',
  styleUrl: './catalog.scss',
})
export class Catalog {
  protected readonly total = screenCatalogCount;
  private readonly screens: readonly ScreenDefinition[] = screenCatalog;
  protected readonly filtersModel = signal<CatalogFilters>({
    query: '',
    family: 'todas',
    audience: 'todas',
    priority: 'todas',
  });
  protected readonly filters = form(this.filtersModel);

  protected readonly familyOptions: readonly FilterOption<ScreenFamily>[] = [
    { value: 'todas', label: 'Todas las familias' },
    { value: 'G', label: 'Global e identidad' },
    { value: 'P', label: 'Paciente y representante' },
    { value: 'C', label: 'Profesional clínico' },
    { value: 'R', label: 'Recepción' },
    { value: 'A', label: 'Administración' },
    { value: 'S', label: 'Seguridad y cumplimiento' },
    { value: 'O', label: 'Overlays y diálogos' },
  ];

  protected readonly audienceOptions: readonly FilterOption<ScreenAudience>[] = [
    { value: 'todas', label: 'Todas las audiencias' },
    { value: 'paciente', label: 'Paciente' },
    { value: 'representante', label: 'Representante' },
    { value: 'profesional', label: 'Profesional' },
    { value: 'recepcion', label: 'Recepción' },
    { value: 'administracion', label: 'Administración' },
    { value: 'seguridad', label: 'Seguridad' },
    { value: 'auditoria', label: 'Auditoría' },
    { value: 'dpd', label: 'DPD' },
    { value: 'soporte', label: 'Soporte' },
    { value: 'publica', label: 'Pública' },
  ];

  protected readonly priorityOptions: readonly FilterOption<ScreenPriority>[] = [
    { value: 'todas', label: 'Todas las prioridades' },
    { value: 'critica', label: 'Crítica' },
    { value: 'alta', label: 'Alta' },
    { value: 'media', label: 'Media' },
    { value: 'baja', label: 'Baja' },
  ];

  protected readonly filteredScreens = computed<readonly ScreenDefinition[]>(() => {
    const filters = this.filtersModel();
    const query = filters.query.trim().toLocaleLowerCase('es');

    return this.screens.filter((screen) => {
      const matchesQuery =
        query.length === 0 ||
        `${screen.id} ${screen.titulo} ${screen.descripcion} ${screen.ruta}`
          .toLocaleLowerCase('es')
          .includes(query);
      const matchesFamily = filters.family === 'todas' || screen.id.startsWith(filters.family);
      const matchesAudience =
        filters.audience === 'todas' || screen.audiencia.includes(filters.audience);
      const matchesPriority = filters.priority === 'todas' || screen.prioridad === filters.priority;

      return matchesQuery && matchesFamily && matchesAudience && matchesPriority;
    });
  });

  protected readonly familyCounts = computed(() => {
    const counts = new Map<ScreenFamily, number>();
    for (const screen of this.screens) {
      const family = screen.id[0] as ScreenFamily;
      counts.set(family, (counts.get(family) ?? 0) + 1);
    }
    return counts;
  });

  protected mockupRouteFor(screen: ScreenDefinition): string {
    return mockupRouteFor(screen);
  }

  protected familyCount(family: FilterValue<ScreenFamily>): number {
    return family === 'todas' ? this.total : (this.familyCounts().get(family) ?? 0);
  }

  protected clearFilters(): void {
    this.filtersModel.set({ query: '', family: 'todas', audience: 'todas', priority: 'todas' });
  }
}
