export function renderBootstrapFailure(target: Document): void {
  const alert = target.createElement('main');
  alert.className = 'bootstrap-failure';
  alert.setAttribute('role', 'alert');

  const heading = target.createElement('h1');
  heading.textContent = 'No se pudo iniciar el portal';

  const guidance = target.createElement('p');
  guidance.textContent =
    'Cierra esta pestaña y vuelve a acceder desde el enlace seguro. Si continúa, contacta con soporte.';

  alert.append(heading, guidance);
  target.body.replaceChildren(alert);
}
