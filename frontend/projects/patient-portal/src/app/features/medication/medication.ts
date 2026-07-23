import { Component, computed, inject, signal } from '@angular/core';
import { form, FormField, maxLength, minLength, required, submit } from '@angular/forms/signals';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { ApiProblemError, ApiTransportError } from 'api-client';
import { SnIcon, SnStatusChip } from 'design-system';

import type { MedicationItem, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';
import { DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

interface MedicationDeclarationModel {
  name: string;
  presentation: string;
  scheduleLabel: string;
}

@Component({
  selector: 'sn-patient-medication',
  imports: [
    DemoStatePanel,
    FormField,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    SnIcon,
    SnStatusChip,
  ],
  templateUrl: './medication.html',
  styleUrl: './medication.scss',
})
export class Medication {
  private readonly repository = inject(PATIENT_REPOSITORY);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly medication = signal<readonly MedicationItem[] | undefined>(undefined);
  private readonly loadFailure = signal<string | undefined>(undefined);
  private readonly renewalRequestKeys = new Map<string, string>();
  private declarationAttempt:
    { readonly fingerprint: string; readonly clientRequestId: string } | undefined;

  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly announcement = signal('');
  protected readonly declarationOpen = signal(false);
  protected readonly declaring = signal(false);
  protected readonly declarationError = signal('');
  protected readonly declarationSuccess = signal('');
  protected readonly renewingMedicationId = signal<string | undefined>(undefined);
  protected readonly renewalError = signal<
    { readonly id: string; readonly message: string } | undefined
  >(undefined);
  protected readonly renewalRequestedIds = signal<ReadonlySet<string>>(new Set());
  protected readonly declarationModel = signal<MedicationDeclarationModel>({
    name: '',
    presentation: '',
    scheduleLabel: '',
  });
  protected readonly declarationForm = form(this.declarationModel, (path) => {
    required(path.name, { message: 'Escribe el nombre que reconoces.' });
    minLength(path.name, 2, { message: 'Escribe al menos 2 caracteres.' });
    maxLength(path.name, 160, { message: 'No puede superar 160 caracteres.' });
    maxLength(path.presentation, 120, { message: 'No puede superar 120 caracteres.' });
    required(path.scheduleLabel, { message: 'Indica cómo lo tomas.' });
    minLength(path.scheduleLabel, 2, { message: 'Escribe al menos 2 caracteres.' });
    maxLength(path.scheduleLabel, 160, { message: 'No puede superar 160 caracteres.' });
  });

  protected readonly viewState = computed<ViewState<readonly MedicationItem[]>>(() => {
    const failure = this.loadFailure();
    if (failure !== undefined) {
      return { kind: 'error', correlationId: failure };
    }

    return resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.medication(),
      (items) => items.length === 0,
    );
  });

  constructor() {
    void this.load();
  }

  protected toggleDeclaration(): void {
    this.declarationOpen.update((open) => !open);
    this.declarationError.set('');
    this.declarationSuccess.set('');
    this.announcement.set(
      this.declarationOpen()
        ? 'Formulario para añadir medicación abierto.'
        : 'Formulario para añadir medicación cerrado.',
    );
  }

  protected declareMedication(): void {
    this.declarationError.set('');
    this.declarationSuccess.set('');
    void submit(this.declarationForm, async () => {
      this.declaring.set(true);
      const model = this.declarationModel();
      const fingerprint = JSON.stringify({
        name: model.name.trim(),
        presentation: model.presentation.trim(),
        scheduleLabel: model.scheduleLabel.trim(),
      });
      let attempt = this.declarationAttempt;
      if (attempt?.fingerprint !== fingerprint) {
        attempt = {
          fingerprint,
          clientRequestId: `medication-declaration-${crypto.randomUUID()}`,
        };
        this.declarationAttempt = attempt;
      }

      try {
        const declared = await this.repository.declareMedication({
          ...model,
          clientRequestId: attempt.clientRequestId,
        });
        this.medication.update((items) => [
          declared,
          ...(items ?? []).filter((item) => item.id !== declared.id),
        ]);
        this.declarationForm().reset({ name: '', presentation: '', scheduleLabel: '' });
        this.declarationAttempt = undefined;
        this.declarationSuccess.set(
          this.isDemo
            ? 'Añadida a esta sesión de demostración. No se ha enviado a ningún centro.'
            : 'Añadida como información declarada por ti. No modifica el registro profesional.',
        );
        this.announcement.set('Medicación declarada añadida correctamente.');
      } catch {
        this.declarationError.set(
          'No se pudo añadir la información. Comprueba los datos y vuelve a intentarlo.',
        );
      } finally {
        this.declaring.set(false);
      }
    });
  }

  protected async requestRenewal(item: MedicationItem): Promise<void> {
    if (
      !item.canRequestRenewal ||
      this.renewingMedicationId() !== undefined ||
      this.renewalRequestedIds().has(item.id)
    ) {
      return;
    }

    this.renewalError.set(undefined);
    this.renewingMedicationId.set(item.id);
    const clientRequestId =
      this.renewalRequestKeys.get(item.id) ?? `medication-renewal-${crypto.randomUUID()}`;
    this.renewalRequestKeys.set(item.id, clientRequestId);
    try {
      await this.repository.requestMedicationRenewal(item.id, clientRequestId);
      this.medication.update((items) =>
        items?.map((candidate) =>
          candidate.id === item.id
            ? {
                ...candidate,
                canRequestRenewal: false,
                renewalRequestStatus: 'submitted' as const,
              }
            : candidate,
        ),
      );
      this.renewalRequestedIds.update((ids) => new Set([...ids, item.id]));
      this.renewalRequestKeys.delete(item.id);
      this.announcement.set(`Solicitud de renovación enviada para ${item.name}.`);
    } catch {
      this.renewalError.set({
        id: item.id,
        message:
          'No se pudo enviar la solicitud. Puede existir otra pendiente; consulta con tu centro.',
      });
    } finally {
      this.renewingMedicationId.set(undefined);
    }
  }

  protected requestHelp(): void {
    this.announcement.set(
      this.isDemo
        ? 'Ayuda simulada. No se ha contactado con ningún centro.'
        : 'La apertura del canal seguro con el centro estará disponible en el módulo de mensajería.',
    );
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    void this.load();
  }

  private async load(): Promise<void> {
    this.loadFailure.set(undefined);
    this.medication.set(undefined);
    try {
      this.medication.set(await this.repository.listMedication());
    } catch (error: unknown) {
      this.loadFailure.set(requestIdOf(error));
    }
  }
}

function requestIdOf(error: unknown): string {
  if (error instanceof ApiProblemError) {
    return error.requestId;
  }
  if (error instanceof ApiTransportError) {
    return error.requestId ?? '';
  }
  return '';
}
