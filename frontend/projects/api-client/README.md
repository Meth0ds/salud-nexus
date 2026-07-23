# Cliente API de Salud Nexus

Cliente HTTP tipado para los portales Angular. Usa cookies de sesión del servidor, valida cada
respuesta con Zod y normaliza errores RFC 9457 sin reflejar cuerpos no confiables.

```ts
import { provideHttpClient } from '@angular/common/http';
import { provideApiClient } from 'api-client';

export const appConfig = {
  providers: [provideHttpClient(), provideApiClient({ baseUrl: '/api/v1' })],
};
```

```ts
const patientSchema = z.object({ id: z.string(), displayName: z.string() });

api.get('/patients/current', patientSchema);
api.post('/appointments', command, appointmentSchema, {
  idempotencyKey: crypto.randomUUID(),
});
```

Los endpoints deben ser rutas relativas sin `..`, query ni fragmento. Los parámetros se envían con
`HttpParams`; no se aceptan URLs arbitrarias ni cabeceras `Authorization` desde consumidores. Las
mutaciones JSON admiten una clave de idempotencia explícita y todas las solicitudes usan
`withCredentials` para la sesión `HttpOnly`.

Verificación:

```text
ng test api-client --watch=false
ng lint api-client
ng build api-client --configuration production
```
