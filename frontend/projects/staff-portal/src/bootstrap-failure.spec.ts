import { renderBootstrapFailure } from './bootstrap-failure';

describe('renderBootstrapFailure', () => {
  it('renders an accessible generic message without technical details', () => {
    document.body.replaceChildren(document.createTextNode('previous content'));

    renderBootstrapFailure(document);

    expect(document.body.querySelector('[role="alert"]')?.textContent).toContain(
      'No se pudo iniciar el portal',
    );
    expect(document.body.textContent).not.toContain('previous content');
    expect(document.body.textContent).not.toContain('stack');
  });
});
