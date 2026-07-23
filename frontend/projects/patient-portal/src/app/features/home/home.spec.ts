import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { provideDemoPatientRepository } from '../../core/patient-repository';
import { Home } from './home';

describe('Home', () => {
  it('presents the next care step and only synthetic fixture data', async () => {
    TestBed.configureTestingModule({
      imports: [Home],
      providers: [provideRouter([]), provideDemoPatientRepository()],
    });
    const fixture = TestBed.createComponent(Home);

    await fixture.whenStable();

    const page = fixture.nativeElement as HTMLElement;
    expect(page.querySelector('h1')?.textContent).toContain('Buenos días, Laura');
    expect(page.textContent).toContain('Hilo de cuidados');
    expect(page.textContent).toContain('Consulta de seguimiento');
    expect(page.textContent).toContain('Datos totalmente sintéticos');
  });
});
