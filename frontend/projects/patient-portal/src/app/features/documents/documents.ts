import { DOCUMENT } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { ApiProblemError, ApiTransportError } from 'api-client';
import { SnIcon, SnStatusChip } from 'design-system';

import type { PatientDocument, ViewState } from '../../core/patient.models';
import { PATIENT_REPOSITORY } from '../../core/patient-repository';
import { PATIENT_RUNTIME_MODE } from '../../core/patient-runtime';
import { DemoScenarioStore } from '../../core/session.store';
import { resolveDemoViewState } from '../../core/view-state';
import { DemoStatePanel } from '../../shared/demo-state-panel/demo-state-panel';

@Component({
  selector: 'sn-patient-documents',
  imports: [DemoStatePanel, MatButtonModule, SnIcon, SnStatusChip],
  templateUrl: './documents.html',
  styleUrl: './documents.scss',
})
export class Documents {
  private readonly repository = inject(PATIENT_REPOSITORY);
  private readonly browserDocument = inject(DOCUMENT);
  protected readonly scenarioStore = inject(DemoScenarioStore);
  private readonly documents = signal<readonly PatientDocument[] | undefined>(undefined);
  private readonly loadFailure = signal<string | undefined>(undefined);
  protected readonly selectedDocument = signal<PatientDocument | undefined>(undefined);
  protected readonly announcement = signal('');
  protected readonly isDemo = PATIENT_RUNTIME_MODE === 'demo';
  protected readonly authorizingDocumentId = signal<string | undefined>(undefined);
  protected readonly downloadNotice = signal<
    { readonly kind: 'error' | 'success'; readonly message: string } | undefined
  >(undefined);

  protected readonly viewState = computed<ViewState<readonly PatientDocument[]>>(() => {
    const failure = this.loadFailure();
    if (failure !== undefined) {
      return { kind: 'error', correlationId: failure };
    }

    return resolveDemoViewState(
      this.scenarioStore.scenario(),
      this.documents(),
      (documents) => documents.length === 0,
    );
  });

  constructor() {
    void this.load();
  }

  protected inspect(document: PatientDocument): void {
    this.selectedDocument.set(document);
    this.downloadNotice.set(undefined);
    this.announcement.set(
      `Ficha de ${document.title} abierta. No se ha descargado ningún archivo.`,
    );
  }

  protected closeDetails(): void {
    this.selectedDocument.set(undefined);
    this.downloadNotice.set(undefined);
    this.announcement.set('Ficha de documento cerrada.');
  }

  protected retry(): void {
    this.scenarioStore.set('ready');
    void this.load();
  }

  protected async requestDownload(item: PatientDocument): Promise<void> {
    if (!item.canDownload || this.authorizingDocumentId() !== undefined) {
      return;
    }

    this.downloadNotice.set(undefined);
    this.authorizingDocumentId.set(item.id);
    try {
      const authorization = await this.repository.authorizeDocumentDownload(item.id);
      if (
        authorization.documentId !== item.id ||
        !/^\/api\/v1\/patient\/document-downloads\/[A-Za-z0-9_-]{43}$/.test(
          authorization.downloadUrl,
        )
      ) {
        throw new Error('La autorización de descarga no es válida.');
      }

      const anchor = this.browserDocument.createElement('a');
      anchor.href = authorization.downloadUrl;
      anchor.download = '';
      anchor.rel = 'noopener';
      anchor.hidden = true;
      this.browserDocument.body.append(anchor);
      anchor.click();
      anchor.remove();
      this.downloadNotice.set({
        kind: 'success',
        message: 'Descarga autorizada. El enlace es temporal y solo puede utilizarse una vez.',
      });
      this.announcement.set(`Descarga segura preparada para ${item.title}.`);
    } catch {
      const message = this.isDemo
        ? 'La demostración no ha creado ninguna descarga ni archivo local.'
        : 'No se pudo autorizar la descarga. Vuelve a intentarlo desde esta ficha.';
      this.downloadNotice.set({ kind: 'error', message });
      this.announcement.set(message);
    } finally {
      this.authorizingDocumentId.set(undefined);
    }
  }

  private async load(): Promise<void> {
    this.loadFailure.set(undefined);
    this.documents.set(undefined);
    this.selectedDocument.set(undefined);
    try {
      this.documents.set(await this.repository.listDocuments());
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
