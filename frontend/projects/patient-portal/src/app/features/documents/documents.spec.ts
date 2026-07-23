import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';

import { PATIENT_REPOSITORY, provideDemoPatientRepository } from '../../core/patient-repository';
import { Documents } from './documents';

describe('Documents', () => {
  it('shows document metadata without creating a browser-side sensitive export', async () => {
    TestBed.configureTestingModule({
      imports: [Documents],
      providers: [provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Documents);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.textContent).toContain('Resumen de atención');
    expect(page.textContent).toContain('284 KB');
    expect(page.textContent).toContain('La demo no genera descargas');
  });

  it('does not create a browser download when the demo rejects the authorization', async () => {
    TestBed.configureTestingModule({
      imports: [Documents],
      providers: [provideDemoPatientRepository()],
    });
    const anchorClick = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => undefined);
    const fixture = TestBed.createComponent(Documents);
    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    page.querySelector<HTMLButtonElement>('[aria-label^="Ver ficha de"]')?.click();
    fixture.detectChanges();
    page.querySelector<HTMLButtonElement>('[data-testid="document-download"]')?.click();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(anchorClick).not.toHaveBeenCalled();
    expect(page.textContent).toContain('La demostración no ha creado ninguna descarga');
    anchorClick.mockRestore();
  });

  it('opens only the validated relative URL granted by the connected repository', async () => {
    TestBed.configureTestingModule({
      imports: [Documents],
      providers: [provideDemoPatientRepository()],
    });
    const repository = TestBed.inject(PATIENT_REPOSITORY);
    vi.spyOn(repository, 'authorizeDocumentDownload').mockResolvedValue({
      documentId: 'document_demo_x3M7vA',
      downloadUrl: '/api/v1/patient/document-downloads/abcdefghijklmnopqrstuvwxyzABCDEF_1234567890',
      expiresAt: '2026-07-23T08:01:30+00:00',
    });
    const anchorClick = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => undefined);
    const fixture = TestBed.createComponent(Documents);
    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    page.querySelector<HTMLButtonElement>('[aria-label^="Ver ficha de"]')?.click();
    fixture.detectChanges();
    page.querySelector<HTMLButtonElement>('[data-testid="document-download"]')?.click();
    await fixture.whenStable();

    expect(repository.authorizeDocumentDownload).toHaveBeenCalledWith('document_demo_x3M7vA');
    expect(anchorClick).toHaveBeenCalledOnce();
    anchorClick.mockRestore();
  });
});
