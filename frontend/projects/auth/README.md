# Sesión y autenticación de Salud Nexus

Estado de sesión únicamente en memoria para los portales Angular. El backend conserva la sesión en
cookie `HttpOnly`; Angular obtiene CSRF, inicia/cierra sesión y consulta una vista mínima de identidad
y capacidades. La librería no crea ni persiste bearer tokens.

Contrato:

```text
GET  /api/v1/auth/csrf    -> 204
POST /api/v1/auth/login   -> 204
GET  /api/v1/auth/session -> sesión mínima
POST /api/v1/auth/logout  -> 204
```

Uso:

```ts
provideHttpClient(withInterceptors([sessionExpiryInterceptor]));
provideApiClient({ baseUrl: '/api/v1' });
```

`SessionAuth.login()` establece CSRF antes de enviar credenciales y vuelve a consultar la sesión tras
la rotación del identificador. `SessionStore` solo guarda nombre de presentación, identificador
público, AAL, método, capacidades y referencia de petición. `authenticatedSessionGuard` es una ayuda
de experiencia de usuario; la autorización final siempre pertenece al backend.

Verificación:

```text
ng test auth --watch=false
ng lint auth
ng build api-client && ng build auth
```
