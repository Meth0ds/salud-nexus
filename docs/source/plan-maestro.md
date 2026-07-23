# Plan maestro para una plataforma profesional de gestión sanitaria con Laravel y Angular Material

## 1. Visión general del producto

El sistema debe permitir que un centro sanitario gestione de forma centralizada:

* Pacientes.
* Profesionales.
* Especialidades y servicios.
* Centros, consultas, salas y recursos.
* Agendas y disponibilidad.
* Citas presenciales, telefónicas y telemáticas.
* Medicación visible para el paciente.
* Documentación y justificantes.
* Comunicaciones y recordatorios.
* Administración y configuración.
* Auditoría de accesos.
* Seguridad, privacidad y cumplimiento normativo.
* Integraciones con sistemas sanitarios externos.

No debe diseñarse únicamente como una aplicación de citas. Desde el principio debe concebirse como una **plataforma modular de información sanitaria**, aunque la primera versión no tenga que ser una historia clínica electrónica completa.

La aplicación tratará datos de salud, que el RGPD considera categorías especiales de datos personales. El tratamiento requiere una base jurídica válida, medidas técnicas y organizativas proporcionadas al riesgo y controles específicos de confidencialidad, integridad, disponibilidad y resiliencia.

Además, la Ley 41/2002 establece expresamente la confidencialidad de los datos referentes a la salud y obliga a los centros sanitarios a disponer de medidas y procedimientos que garanticen que solamente se produzcan accesos legalmente autorizados.

---

# 2. Principios rectores

## 2.1. Seguridad desde el diseño

La seguridad no puede añadirse al final. Cada funcionalidad deberá responder previamente a estas preguntas:

1. ¿Quién puede ejecutarla?
2. ¿Sobre qué pacientes o centros?
3. ¿Con qué finalidad?
4. ¿Qué datos necesita realmente?
5. ¿Debe quedar auditada?
6. ¿Necesita reautenticación?
7. ¿Puede implicar una exportación o filtración?
8. ¿Qué sucede si se ejecuta dos veces?
9. ¿Qué sucede si dos usuarios la ejecutan simultáneamente?
10. ¿Cómo se recupera el sistema si falla?

Como estándar técnico de verificación debería adoptarse **OWASP ASVS 5.0.0**, actualmente la versión estable publicada por OWASP. Para una plataforma sanitaria recomendaría ASVS nivel 2 como mínimo contractual y requisitos seleccionados de nivel 3 para autenticación, administración, criptografía, auditoría y datos clínicos.

## 2.2. Privacidad por defecto

Por defecto:

* Un administrativo no debe poder consultar medicación.
* Un profesional no debe poder consultar pacientes ajenos a su relación asistencial.
* Un técnico informático no debe poder leer información clínica.
* Un administrador funcional no debe obtener automáticamente privilegios clínicos.
* Los listados no deben mostrar más información de la necesaria.
* Las notificaciones por correo o SMS no deben contener diagnósticos, medicamentos ni datos clínicos sensibles.
* Los registros de aplicación no deben almacenar cuerpos completos de peticiones con datos sanitarios.

## 2.3. Trazabilidad completa

Todo acceso relevante deberá responder a:

* Quién accedió.
* Qué paciente o recurso consultó.
* Qué información visualizó o modificó.
* Qué acción realizó.
* Desde qué sesión y dispositivo.
* En qué momento.
* Desde qué organización o centro.
* Por qué motivo.
* Si se utilizó un acceso excepcional.
* Cuál fue el resultado.

## 2.4. Modularidad

El producto debe poder evolucionar sin convertirse en un bloque inmanejable. La arquitectura inicial recomendada es un **monolito modular**, no microservicios.

Cada dominio tendrá:

* Modelos propios.
* Casos de uso propios.
* Políticas de autorización.
* Eventos de dominio.
* Pruebas.
* API.
* Migraciones controladas.
* Contratos explícitos con otros módulos.

## 2.5. Interoperabilidad

Aunque inicialmente los datos sean internos, el modelo debe permitir posteriormente:

* Exportación interoperable.
* Integraciones con historia clínica.
* Integración con proveedores de identidad.
* Integración con plataformas de mensajería.
* Integración con servicios de firma.
* Integración con sistemas de receta o medicación.
* Integración con sistemas públicos o privados.

FHIR es un estándar de intercambio de información sanitaria publicado por HL7. La versión concreta —R4, R4B o R5— deberá seleccionarse conforme al sistema con el que se vaya a integrar, no únicamente porque sea la más reciente.

---

# 3. Alcance normativo y de cumplimiento

Antes de desarrollar debe realizarse una fase jurídica y organizativa.

## 3.1. Normativa principal

El análisis debe contemplar, como mínimo:

* Reglamento General de Protección de Datos.
* Ley Orgánica 3/2018 de Protección de Datos Personales y garantía de los derechos digitales.
* Ley 41/2002 de autonomía del paciente e información y documentación clínica.
* Normativa sanitaria autonómica aplicable.
* Obligaciones sobre conservación de documentación clínica.
* Normativa sobre firma e identificación electrónica.
* Contratos de encargado del tratamiento.
* Reglas de transferencias internacionales.
* Normativa sobre accesibilidad.
* Normativa específica aplicable al centro público o privado.

La Ley 41/2002 regula los derechos y obligaciones de pacientes, profesionales, centros y servicios sanitarios tanto públicos como privados en materia de información y documentación clínica.

Si la aplicación forma parte de una Administración pública, presta servicios a esta o queda incluida en el ámbito correspondiente, habrá que analizar su adecuación al **Esquema Nacional de Seguridad**, regulado por el Real Decreto 311/2022. El ENS exige medidas proporcionadas al riesgo y contempla protección, control de acceso, continuidad, auditoría, gestión de incidentes y seguridad de los sistemas que tratan información.

## 3.2. Evaluación de impacto

Antes de producción deberá ejecutarse una **Evaluación de Impacto relativa a la Protección de Datos**. Es especialmente relevante porque el sistema puede implicar tratamiento sistemático y potencialmente a gran escala de categorías especiales de datos.

La EIPD debe incluir:

* Descripción detallada de los tratamientos.
* Finalidades.
* Categorías de interesados.
* Categorías de datos.
* Flujos internos y externos.
* Proveedores y subencargados.
* Riesgos para pacientes.
* Probabilidad e impacto.
* Medidas mitigadoras.
* Riesgo residual.
* Aprobación del responsable.
* Revisión periódica.
* Proceso de consulta al delegado de protección de datos.

La AEPD dispone de orientaciones específicas para el sector sanitario y para la gestión de riesgos y evaluaciones de impacto.

## 3.3. Registro de actividades de tratamiento

Debe mantenerse un inventario que identifique:

* Gestión de pacientes.
* Gestión de citas.
* Gestión de medicación.
* Comunicaciones.
* Documentación sanitaria.
* Atención a derechos.
* Auditoría.
* Videoconsulta, si existe.
* Analítica.
* Soporte técnico.
* Copias de seguridad.
* Gestión de incidentes.

## 3.4. Delegado de protección de datos

Debe analizarse la obligatoriedad de designar un delegado de protección de datos. El RGPD contempla su designación, entre otros casos, para autoridades públicas y organizaciones cuya actividad principal implique tratamiento a gran escala de categorías especiales, como datos de salud.

## 3.5. Preparación para el Espacio Europeo de Datos de Salud

El Reglamento europeo sobre el Espacio Europeo de Datos de Salud introduce un marco para mejorar el acceso y control de los ciudadanos sobre sus datos sanitarios electrónicos y favorecer la interoperabilidad. Su aplicación es escalonada, pero el sistema debería prepararse desde ahora.

Conviene incorporar desde el diseño:

* Acceso del paciente a sus datos.
* Descarga de copias.
* Solicitudes de rectificación.
* Acceso delegado o representación.
* Restricciones de acceso.
* Historial visible de quién ha consultado información.
* Portabilidad.
* Interoperabilidad.
* Identificación fiable de pacientes y profesionales.

El EEDS contempla que los ciudadanos puedan obtener información sobre quién accedió a sus datos, cuándo y qué datos fueron consultados, además de funcionalidades de representación, rectificación, transmisión y restricción.

---

# 4. Arquitectura funcional

Se recomienda dividir el producto en cuatro grandes superficies:

1. Portal del paciente.
2. Portal de profesionales.
3. Portal de recepción y administración.
4. Consola de seguridad, auditoría y cumplimiento.

Aunque compartan backend, deben tener navegación, permisos y experiencias diferenciadas.

---

# 5. Portal del paciente

## 5.1. Registro e identificación

Funciones previstas:

* Registro mediante invitación.
* Registro mediante código de activación.
* Verificación de correo.
* Verificación de teléfono.
* Asociación segura con ficha de paciente existente.
* Validación manual de identidad.
* Integración futura con Cl@ve u otro proveedor de identidad.
* Inicio de sesión con contraseña.
* Inicio de sesión mediante passkey.
* Segundo factor.
* Recuperación de cuenta.
* Gestión de dispositivos.
* Consulta de sesiones activas.
* Cierre remoto de sesiones.
* Alertas de inicio de sesión sospechoso.
* Cambio de correo o teléfono con verificación reforzada.
* Bloqueo temporal por intentos fallidos.
* Contacto con soporte de identidad.

No debe permitirse que una persona se vincule a una ficha clínica solamente porque conozca nombre, DNI y fecha de nacimiento. La asociación debe utilizar un proceso fuerte de verificación.

## 5.2. Perfil

* Datos identificativos.
* Datos de contacto.
* Dirección.
* Persona de contacto.
* Preferencias de idioma.
* Preferencias de accesibilidad.
* Canal preferido de notificación.
* Consentimientos.
* Autorizaciones a representantes.
* Datos administrativos.
* Tarjeta sanitaria o identificadores internos.
* Solicitud de corrección de datos.
* Historial de cambios relevantes.

Los campos clínicos y administrativos deben separarse. No todos los operadores que puedan modificar un teléfono deben poder acceder a medicación o documentación clínica.

## 5.3. Gestión de citas

El paciente podrá:

* Consultar próximas citas.
* Consultar citas anteriores.
* Buscar disponibilidad.
* Filtrar por centro.
* Filtrar por especialidad.
* Filtrar por profesional.
* Filtrar por modalidad.
* Seleccionar fecha y franja.
* Reservar una cita.
* Confirmar una cita.
* Modificar una cita.
* Cancelar una cita.
* Indicar motivo de cancelación.
* Solicitar cambio.
* Añadirse a lista de espera.
* Aceptar una cita liberada.
* Rechazar una oferta de lista de espera.
* Descargar confirmación.
* Añadir la cita a su calendario.
* Consultar instrucciones previas.
* Consultar ubicación.
* Consultar documentación necesaria.
* Recibir recordatorios.
* Completar formularios previos.
* Realizar preadmisión.
* Indicar necesidades de accesibilidad.
* Consultar el estado de una solicitud.

## 5.4. Medicación

La medicación debe comenzar como un módulo informativo y de conciliación, no como un sistema autónomo de prescripción.

Funciones:

* Ver medicación activa.
* Ver principio activo.
* Ver nombre comercial cuando proceda.
* Ver presentación.
* Ver dosis.
* Ver pauta.
* Ver vía de administración.
* Ver fecha de inicio.
* Ver fecha de finalización.
* Ver profesional prescriptor.
* Ver instrucciones.
* Ver advertencias introducidas por el profesional.
* Ver medicación suspendida.
* Descargar listado de medicación.
* Solicitar renovación.
* Informar de discrepancias.
* Indicar que no está tomando un medicamento.
* Registrar recordatorios personales.
* Confirmar tomas, como funcionalidad de bienestar.
* Gestionar recordatorios.
* Consultar documentos asociados.

El paciente no debe modificar directamente el registro clínico autoritativo. Las modificaciones deberán tratarse como solicitudes o declaraciones del paciente, claramente diferenciadas de la información validada por un profesional.

## 5.5. Documentos

* Justificante de asistencia.
* Justificante de cita programada.
* Confirmación de cita.
* Resumen de medicación.
* Instrucciones previas.
* Formularios.
* Consentimientos.
* Informes publicados para el paciente.
* Facturas, si el centro es privado.
* Descarga individual.
* Descarga agrupada.
* Validación de autenticidad.
* Historial de descargas.
* Solicitud de documentación.
* Solicitud de corrección.
* Aviso de publicación de documento.

## 5.6. Privacidad y accesos

El paciente debería disponer de:

* Registro de accesos a sus datos.
* Fecha y hora de acceso.
* Centro o entidad.
* Profesional o categoría del profesional, según proceda.
* Tipo de información consultada.
* Accesos excepcionales.
* Descarga del historial.
* Alertas de acceso.
* Gestión de representantes.
* Revocación de representantes.
* Solicitud de acceso.
* Solicitud de rectificación.
* Solicitud de limitación.
* Solicitud de portabilidad.
* Canal de contacto con el delegado de protección de datos.

## 5.7. Comunicaciones

* Bandeja de avisos.
* Notificaciones de citas.
* Avisos de cambios.
* Mensajes administrativos.
* Solicitudes de documentación.
* Notificación de disponibilidad.
* Avisos de medicación.
* Mensajes de soporte.
* Preferencias de suscripción.
* Estado de lectura.
* Archivo de mensajes.
* Notificaciones push, si se crea PWA o aplicación móvil.

No debe utilizarse una mensajería libre entre paciente y profesional sin definir previamente tiempos de respuesta, responsabilidad clínica, triaje, emergencias y registro asistencial.

---

# 6. Portal del profesional sanitario

## 6.1. Agenda profesional

* Vista diaria.
* Vista semanal.
* Vista mensual.
* Agenda por profesional.
* Agenda por consulta.
* Agenda por recurso.
* Filtros por estado.
* Filtros por modalidad.
* Bloqueo de franjas.
* Apertura de disponibilidad.
* Ausencias.
* Vacaciones.
* Sustituciones.
* Reasignación de agenda.
* Duraciones configurables.
* Huecos urgentes.
* Sobrecitas autorizadas.
* Citas recurrentes.
* Citas encadenadas.
* Citas con varios recursos.
* Lista de espera.
* Avisos de retraso.
* Estado de sala de espera.
* Marcado de paciente atendido.
* Marcado de ausencia.
* Finalización de cita.

## 6.2. Información del paciente

El profesional verá únicamente la información necesaria:

* Identificación.
* Datos de contacto relevantes.
* Motivo administrativo de la cita.
* Alertas organizativas.
* Medicación.
* Documentos publicados.
* Formularios previos.
* Historial de citas pertinente.
* Representantes autorizados.
* Restricciones de acceso.
* Avisos críticos autorizados.

La información deberá limitarse según especialidad, centro, relación asistencial y finalidad.

## 6.3. Medicación profesional

Según el alcance clínico aprobado:

* Consultar medicación.
* Registrar medicación.
* Actualizar pauta.
* Suspender medicación.
* Marcar medicación como histórica.
* Registrar motivo del cambio.
* Registrar procedencia.
* Conciliar medicación.
* Resolver discrepancias.
* Validar información aportada por el paciente.
* Generar listado.
* Solicitar revisión.
* Registrar alergias o intolerancias, si el proyecto amplía el alcance clínico.
* Integrar sistemas externos de prescripción.

Las modificaciones nunca deben sobrescribir silenciosamente el estado anterior. Debe conservarse la evolución completa.

## 6.4. Documentación profesional

* Generar justificante.
* Publicar documento.
* Retirar documento publicado.
* Crear documento desde plantilla.
* Firmar.
* Solicitar firma.
* Revisar documento.
* Añadir anexos.
* Versionar documento.
* Visualizar estado.
* Descargar copia.
* Validar autenticidad.
* Registrar entrega.
* Compartir con el paciente.
* Revocar enlaces temporales.

---

# 7. Recepción y atención administrativa

## 7.1. Gestión de pacientes

* Alta.
* Búsqueda segura.
* Detección de posibles duplicados.
* Fusión controlada de duplicados.
* Actualización de contacto.
* Verificación de identidad.
* Activación de portal.
* Restablecimiento asistido.
* Gestión de representante.
* Gestión de incapacidades o tutelas, cuando proceda.
* Marcado de datos pendientes.
* Importación.
* Exportación autorizada.
* Gestión de documentación identificativa.

La fusión de pacientes es una operación especialmente peligrosa y debería requerir doble validación, vista previa, trazabilidad completa y posibilidad controlada de reversión.

## 7.2. Gestión de citas

* Crear cita.
* Reprogramar.
* Cancelar.
* Confirmar.
* Marcar llegada.
* Marcar ausencia.
* Cambiar profesional.
* Cambiar sala.
* Cambiar modalidad.
* Añadir observaciones administrativas.
* Gestionar lista de espera.
* Buscar primer hueco.
* Reservar temporalmente un hueco.
* Crear citas múltiples.
* Crear citas recurrentes.
* Asignar intérprete.
* Gestionar transporte sanitario, si aplica.
* Imprimir justificante.
* Enviar recordatorio manual.
* Consultar historial de cambios.

## 7.3. Sala de espera

* Check-in manual.
* Check-in mediante código.
* Check-in mediante quiosco.
* Orden de llegada.
* Prioridad.
* Estado de espera.
* Llamada a consulta.
* Reasignación.
* Retrasos estimados.
* Aviso al paciente.
* Estado ausente.
* Finalización.

---

# 8. Administración funcional

## 8.1. Centros y estructura

* Centros.
* Sedes.
* Plantas.
* Consultas.
* Salas.
* Equipamiento.
* Recursos.
* Especialidades.
* Servicios.
* Tipos de cita.
* Modalidades.
* Duraciones.
* Horarios.
* Festivos.
* Cierres excepcionales.
* Zonas horarias.
* Direcciones.
* Mapas e instrucciones de acceso.

## 8.2. Profesionales

* Alta y baja.
* Número de colegiado.
* Especialidades.
* Centros asignados.
* Servicios autorizados.
* Horarios.
* Sustituciones.
* Estado laboral.
* Fechas de vigencia.
* Permisos.
* Firma.
* Identidad federada.
* Segundo factor.
* Bloqueo de cuenta.
* Historial de roles.

## 8.3. Plantillas

* Plantillas de correo.
* Plantillas SMS.
* Plantillas push.
* Plantillas PDF.
* Plantillas de formularios.
* Variables permitidas.
* Vista previa.
* Versionado.
* Flujo de aprobación.
* Programación de publicación.
* Restauración de versiones.
* Idiomas.
* Centro de aplicación.
* Firma o sello.

## 8.4. Reglas de negocio

* Antelación mínima.
* Antelación máxima.
* Cancelación permitida.
* Número máximo de citas.
* Citas simultáneas.
* Duración por servicio.
* Tiempo de preparación.
* Tiempo de limpieza.
* Sobreagenda.
* Priorización.
* Lista de espera.
* Confirmación obligatoria.
* Caducidad de reserva.
* Recordatorios.
* Penalizaciones administrativas.
* Requisitos documentales.
* Restricciones por edad.
* Restricciones por centro.
* Derivación obligatoria.
* Autorización de aseguradora, si procede.

---

# 9. Administración de seguridad

Debe existir una consola separada para seguridad y cumplimiento.

## 9.1. Usuarios y permisos

* Usuarios.
* Roles.
* Permisos.
* Ámbitos.
* Centros.
* Especialidades.
* Unidades.
* Vigencia temporal.
* Sustituciones.
* Delegaciones.
* Bloqueo.
* Revocación de sesiones.
* Segundo factor.
* Dispositivos.
* Riesgo de cuenta.
* Historial de privilegios.
* Revisiones de acceso.
* Certificación periódica de permisos.

## 9.2. Roles recomendados

* Paciente.
* Representante.
* Recepción.
* Administrativo.
* Profesional sanitario.
* Facultativo.
* Enfermería.
* Farmacia, si aplica.
* Coordinador.
* Gestor de agendas.
* Responsable de centro.
* Administrador funcional.
* Administrador de identidad.
* Seguridad.
* Auditor.
* Delegado de protección de datos.
* Soporte técnico.
* Integración técnica.
* Superadministrador restringido.

No es suficiente un RBAC simple. Debe combinarse:

* **RBAC:** rol.
* **ABAC:** atributos del usuario, paciente, centro, finalidad y contexto.
* **Relación asistencial:** profesional asignado o involucrado.
* **Ámbito temporal:** guardias, sustituciones o contratos.
* **Contexto:** centro, unidad y dispositivo.
* **Finalidad:** asistencia, administración, soporte, auditoría.
* **Restricciones del paciente**, cuando sean aplicables.

## 9.3. Acceso de emergencia o break-glass

Debe existir únicamente cuando sea necesario clínicamente.

El flujo debería requerir:

1. Seleccionar motivo.
2. Introducir justificación.
3. Reautenticarse.
4. Limitar temporalmente el acceso.
5. Registrar todas las consultas.
6. Notificar a seguridad o supervisión.
7. Revisar posteriormente.
8. Mostrarlo en el historial del paciente cuando legalmente corresponda.

No debe convertirse en un botón habitual para evitar las restricciones.

---

# 10. Arquitectura tecnológica

## 10.1. Backend

Recomendación:

* Laravel como aplicación API.
* Arquitectura de monolito modular.
* PHP con una versión oficialmente soportada por la versión elegida de Laravel.
* PostgreSQL como base de datos principal.
* Redis para caché, rate limiting, colas y bloqueos distribuidos.
* Almacenamiento de objetos compatible con S3 para documentos.
* Workers asíncronos.
* Scheduler.
* Servicio de correo.
* Servicio SMS.
* Proveedor de identidad.
* Servicio de firma, si se requiere.
* Observabilidad mediante OpenTelemetry.
* Reverse proxy o balanceador.
* WAF.
* Gestor de secretos.
* KMS para claves criptográficas.

En julio de 2026 la documentación oficial vigente corresponde a Laravel 13.x. Laravel proporciona mecanismos diferenciados de autenticación mediante guards y providers, y debe complementarse con políticas y autorización de dominio.

Las tareas pesadas, como generación de PDF, comunicaciones, importaciones y sincronizaciones, deben enviarse a colas. Laravel soporta distintos backends de cola, entre ellos Redis, bases de datos y servicios administrados.

## 10.2. Frontend

Recomendación:

* Angular estable.
* Angular Material.
* Angular CDK.
* TypeScript en modo estricto.
* Componentes standalone.
* Formularios reactivos tipados.
* Carga diferida por dominio.
* Interceptores HTTP mínimos.
* Signals para estado local.
* Una librería de estado global solamente donde exista complejidad real.
* Sistema de diseño propio construido sobre Material.
* Pruebas mediante component harnesses.
* Internacionalización.
* Accesibilidad.
* PWA opcional.

La documentación oficial de Angular Material muestra actualmente la rama 22.0.4 y proporciona componentes y CDK para aplicaciones Angular de escritorio y móviles. La versión exacta deberá bloquearse al iniciar el proyecto y actualizarse mediante un proceso controlado.

## 10.3. Dos aplicaciones Angular

Conviene mantener dos shells:

### Aplicación paciente

* Interfaz sencilla.
* Flujos guiados.
* Accesibilidad reforzada.
* Diseño móvil prioritario.
* Lenguaje no técnico.
* Sesiones razonablemente largas pero seguras.
* Recuperación de cuenta asistida.

### Aplicación personal

* Alta densidad de información.
* Navegación por teclado.
* Atajos.
* Tablas.
* Agenda avanzada.
* Sesiones más restrictivas.
* MFA obligatorio.
* Restricción por dispositivo o red, cuando sea necesario.

Ambas pueden compartir:

* Tokens de diseño.
* Componentes básicos.
* Cliente API generado.
* Validaciones comunes.
* Localización.
* Librería de iconos.
* Utilidades de accesibilidad.

---

# 11. Diseño del backend mediante módulos

Una estructura posible sería:

```text
app/
  Modules/
    Identity/
    Patients/
    Professionals/
    Organizations/
    Scheduling/
    Appointments/
    WaitingList/
    Medication/
    Documents/
    Notifications/
    Consent/
    Privacy/
    Audit/
    Reporting/
    Integrations/
    Administration/
```

Cada módulo contendría:

```text
Domain/
Application/
Infrastructure/
Http/
Policies/
Events/
Listeners/
Jobs/
Tests/
```

## 11.1. Capa de dominio

Contendrá:

* Entidades.
* Value objects.
* Reglas.
* Estados.
* Excepciones de negocio.
* Servicios de dominio.
* Eventos de dominio.

## 11.2. Capa de aplicación

Contendrá casos de uso como:

* CreateAppointment.
* RescheduleAppointment.
* CancelAppointment.
* ConfirmAppointment.
* PublishDocument.
* UpdateMedication.
* GrantProxyAccess.
* RevokeSession.
* MergePatients.

## 11.3. Infraestructura

Contendrá:

* Repositorios.
* Eloquent.
* Almacenamiento.
* Proveedores externos.
* Colas.
* Correo.
* SMS.
* Auditoría.
* Criptografía.
* Adaptadores FHIR.

## 11.4. API

La API deberá:

* Estar versionada.
* Publicar OpenAPI 3.1.
* Validar estrictamente entradas.
* Utilizar identificadores opacos.
* Implementar paginación.
* Implementar filtros autorizados.
* Utilizar errores estandarizados.
* Soportar claves de idempotencia.
* Aplicar límites de frecuencia.
* Validar tipo y tamaño de contenido.
* Rechazar campos desconocidos en operaciones sensibles.
* No exponer trazas internas.
* Implementar ETag o control de versión cuando exista edición concurrente.

---

# 12. Autenticación y sesiones

## 12.1. Recomendación para navegador

Para aplicaciones web propias bajo dominios controlados, es preferible utilizar:

* Sesión servidor.
* Cookie `HttpOnly`.
* Cookie `Secure`.
* Política `SameSite`.
* Protección CSRF.
* Rotación del identificador de sesión.
* Expiración absoluta.
* Expiración por inactividad.
* Revocación centralizada.

No recomiendo almacenar JWT de larga duración en `localStorage`.

## 12.2. Proveedor de identidad

Para profesionales y administradores, la opción más sólida es integrar un proveedor OIDC que soporte:

* MFA.
* WebAuthn/passkeys.
* TOTP.
* Recuperación segura.
* Políticas adaptativas.
* Federación corporativa.
* Revocación.
* Registro de eventos.
* Gestión de dispositivos.

Laravel seguiría aplicando la autorización de negocio después de autenticar al usuario.

## 12.3. MFA

* Obligatorio para personal sanitario.
* Obligatorio para administradores.
* Obligatorio para auditores.
* Recomendado o progresivamente obligatorio para pacientes.
* Reautenticación para operaciones críticas.

Operaciones que deberían exigir reautenticación:

* Exportación masiva.
* Cambio de permisos.
* Fusión de pacientes.
* Acceso de emergencia.
* Cambio de correo principal.
* Desactivación de MFA.
* Descarga masiva.
* Firma de documentos.
* Modificación de políticas.
* Generación de credenciales técnicas.

## 12.4. Recuperación de cuenta

Debe evitar preguntas de seguridad.

El proceso incluirá:

* Token de un solo uso.
* Expiración breve.
* Invalidación después del uso.
* Registro de auditoría.
* Revocación de sesiones anteriores.
* Aviso por canal alternativo.
* Revisión manual en casos de alto riesgo.
* Periodo de protección para cambios sensibles.

---

# 13. Modelo de autorización

Toda acción deberá evaluarse en backend.

Ejemplo conceptual:

```text
Puede ver medicación si:
- está autenticado;
- pertenece a una organización autorizada;
- tiene permiso clínico;
- mantiene relación asistencial con el paciente;
- el acceso está dentro de su ámbito;
- no existe una restricción aplicable;
- la finalidad declarada es válida;
- la cuenta no está suspendida;
- la sesión cumple el nivel de autenticación requerido.
```

Nunca debe confiarse en ocultar botones en Angular como mecanismo de seguridad. El frontend solamente mejora la experiencia; la autorización real reside en Laravel.

Laravel diferencia autenticación de roles y permisos y ofrece policies y gates para autorización. Las políticas deberían aplicarse sistemáticamente a recursos como pacientes, citas, medicación y documentos.

---

# 14. Modelo de datos inicial

## 14.1. Identidad y organización

* users
* identities
* user_sessions
* authenticators
* organizations
* centers
* departments
* units
* rooms
* resources
* professionals
* professional_assignments
* roles
* permissions
* role_assignments
* access_scopes
* delegations

## 14.2. Pacientes

* patients
* patient_identifiers
* patient_contacts
* patient_addresses
* patient_representatives
* patient_portal_links
* patient_merge_cases
* patient_restrictions
* patient_preferences
* patient_flags
* patient_data_corrections

## 14.3. Citas

* services
* specialties
* appointment_types
* agenda_templates
* availability_rules
* availability_exceptions
* generated_slots
* appointments
* appointment_participants
* appointment_resources
* appointment_status_history
* appointment_changes
* cancellation_reasons
* waiting_list_entries
* waiting_list_offers
* check_ins
* room_queue_entries

## 14.4. Medicación

* medications
* medication_catalog_entries
* medication_statements
* medication_requests
* medication_dosages
* medication_schedules
* medication_status_history
* medication_discrepancies
* renewal_requests
* adherence_reminders
* medication_documents

## 14.5. Documentos

* documents
* document_versions
* document_templates
* document_signatures
* document_publications
* document_downloads
* document_verification_tokens
* document_retention_rules
* document_access_grants

## 14.6. Privacidad

* consents
* consent_versions
* legal_bases
* processing_purposes
* proxy_authorizations
* data_subject_requests
* access_restrictions
* privacy_notices
* privacy_notice_acceptances

## 14.7. Auditoría

* audit_events
* audit_event_integrity
* security_events
* break_glass_events
* export_events
* administrative_actions
* authentication_events
* permission_change_events
* incident_cases

## 14.8. Notificaciones

* notification_templates
* notifications
* notification_deliveries
* notification_preferences
* notification_failures
* email_suppressions
* sms_suppressions
* outbox_messages

---

# 15. Identificadores y enumeración

No deben exponerse identificadores incrementales como:

```text
/patients/152
/documents/620
```

Conviene emplear identificadores opacos, como UUIDv7 o ULID, sin confiar únicamente en ellos como control de acceso.

Incluso utilizando UUID, cada petición debe verificar la autorización. Un identificador difícil de adivinar no sustituye una política.

---

# 16. Motor de citas

El motor de disponibilidad será uno de los componentes más complejos.

## 16.1. Variables de cálculo

Debe considerar:

* Profesional.
* Centro.
* Servicio.
* Tipo de cita.
* Duración.
* Sala.
* Equipamiento.
* Modalidad.
* Horario laboral.
* Pausas.
* Festivos.
* Ausencias.
* Sustituciones.
* Tiempo previo.
* Tiempo posterior.
* Edad del paciente.
* Requisitos de derivación.
* Primera consulta o revisión.
* Número máximo diario.
* Capacidad simultánea.
* Citas grupales.
* Prioridad.
* Bloqueos.
* Sobrecitas.
* Zona horaria.

## 16.2. Prevención de dobles reservas

No debe resolverse únicamente con una comprobación previa del tipo “el hueco sigue libre”.

Se necesita:

* Restricción única en base de datos.
* Transacción.
* Bloqueo de fila o mecanismo equivalente.
* Reserva temporal con expiración.
* Idempotency key.
* Reintentos controlados.
* Manejo explícito de conflictos.
* Pruebas de concurrencia.

Flujo:

1. El paciente selecciona el hueco.
2. El backend crea una retención temporal.
3. Se verifica disponibilidad dentro de transacción.
4. Se confirma la cita.
5. La restricción de base de datos impide duplicados.
6. Se publica un evento.
7. Se genera la notificación asíncrona.

## 16.3. Estados

Estados recomendados:

* draft
* held
* requested
* booked
* pending_confirmation
* confirmed
* checked_in
* in_waiting_room
* in_progress
* completed
* no_show
* cancelled_by_patient
* cancelled_by_center
* rescheduled
* rejected
* expired

Debe conservarse todo el historial. El estado actual puede almacenarse para eficiencia, pero cada transición debe producir un evento auditable.

## 16.4. Lista de espera

* Prioridad configurable.
* Preferencias horarias.
* Centros aceptados.
* Profesionales aceptados.
* Fecha de caducidad.
* Oferta secuencial o simultánea.
* Tiempo máximo de respuesta.
* Liberación automática.
* Prevención de ofertas duplicadas.
* Registro de aceptación.
* Registro de rechazo.
* Métricas de conversión.

---

# 17. Auditoría inmutable

La auditoría sanitaria no debe ser una tabla convencional que un administrador pueda editar.

## 17.1. Contenido de un evento

```text
event_id
occurred_at
actor_id
actor_type
organization_id
center_id
session_id_hash
device_id
ip_address
action
resource_type
resource_id
patient_id
purpose
result
reason
break_glass
correlation_id
request_id
previous_hash
event_hash
```

No deben incluirse en la auditoría:

* Contraseñas.
* Tokens.
* Cookies.
* Secretos.
* Contenido clínico completo.
* Cuerpos completos de formularios.
* Claves de firma.

## 17.2. Protección

* Escritura append-only.
* Cuenta de base de datos separada.
* Imposibilidad de actualización desde la aplicación normal.
* Encadenamiento criptográfico.
* Exportación periódica.
* Almacenamiento WORM cuando sea necesario.
* Sincronización horaria.
* Alertas de huecos o alteraciones.
* Acceso restringido.
* Conservación definida.
* Monitorización de intentos de borrado.

## 17.3. Tipos de eventos

* Inicio y cierre de sesión.
* Fallo de autenticación.
* Cambio de contraseña.
* Alta o baja de MFA.
* Lectura de datos clínicos.
* Lectura de medicación.
* Descarga de documento.
* Exportación.
* Creación y modificación de cita.
* Cambio de medicación.
* Cambio de permisos.
* Acceso administrativo.
* Acceso de emergencia.
* Fusión de pacientes.
* Cambio de identidad.
* Uso de una integración.
* Error de autorización.
* Operación masiva.
* Cambio de configuración.

## 17.4. Correlación

Cada solicitud debe tener:

* `request_id`.
* `correlation_id`.
* Usuario.
* Sesión.
* Servicio.
* Resultado.

Esto permitirá reconstruir un incidente sin almacenar datos sanitarios innecesarios en los logs técnicos.

---

# 18. Documentos y PDF

## 18.1. Generación

Los PDF deben generarse en backend a partir de plantillas controladas.

Proceso:

1. Selección de plantilla versionada.
2. Obtención de datos autorizados.
3. Renderizado.
4. Normalización.
5. Cálculo de hash.
6. Firma o sellado, si procede.
7. Almacenamiento de objeto.
8. Registro de metadatos.
9. Publicación al paciente.
10. Auditoría.

## 18.2. Justificante

Un justificante podría incluir:

* Centro.
* Identificación mínima del paciente.
* Fecha.
* Hora.
* Tipo de asistencia.
* Profesional o unidad, si procede.
* Número de documento.
* Fecha de emisión.
* Código seguro de verificación.
* QR.
* Sello o firma.
* Texto de privacidad.

El QR no debe incluir información clínica ni un DNI en texto claro. Debe contener una URL con un token aleatorio, limitado a la validación de autenticidad.

## 18.3. Verificación

Página pública de verificación:

* Token opaco.
* Número de documento.
* Estado válido, revocado o sustituido.
* Fecha de emisión.
* Emisor.
* Hash.
* Información mínima.
* Protección contra enumeración.
* Rate limiting.
* Auditoría.

## 18.4. Firma electrónica

Cuando el documento requiera efectos jurídicos reforzados, deberá integrarse con un prestador de servicios de confianza y definir el nivel de firma necesario. El marco eIDAS regula la identificación electrónica y los servicios de confianza en la Unión Europea.

No debe afirmarse que insertar una imagen de una firma en un PDF equivale a una firma electrónica avanzada o cualificada.

## 18.5. Versionado

Un documento emitido no debe modificarse en el mismo archivo.

Debe:

* Generarse una nueva versión.
* Marcar la anterior como sustituida.
* Mantener la relación.
* Conservar el hash.
* Registrar quién efectuó el cambio.
* Notificar al paciente si procede.

---

# 19. Seguridad de datos

## 19.1. En tránsito

* TLS moderno.
* Redirección obligatoria a HTTPS.
* HSTS.
* Cookies seguras.
* Comunicación interna cifrada cuando proceda.
* Validación de certificados.
* Desactivación de protocolos y cifrados obsoletos.

## 19.2. En reposo

* Cifrado de discos.
* Cifrado de base de datos administrada.
* Cifrado de objetos.
* KMS.
* Rotación de claves.
* Separación por entorno.
* Copias cifradas.
* Restricción de acceso a claves.

## 19.3. Cifrado de campos

Para determinados valores puede utilizarse cifrado a nivel de aplicación:

* Identificadores especialmente sensibles.
* Credenciales técnicas.
* Tokens externos.
* Documentos de identidad.
* Secretos de integraciones.

Debe evitarse cifrar indiscriminadamente todos los campos, porque dificulta búsquedas, índices, rotación y mantenimiento.

## 19.4. Contraseñas

* Argon2id.
* Parámetros revisados.
* Sin límites artificialmente bajos.
* Comprobación de contraseñas filtradas.
* Sin preguntas de seguridad.
* Sin enviar contraseñas por correo.
* Sin almacenar contraseñas reversibles.
* Rotación únicamente por sospecha, política de riesgo o requisito normativo.

## 19.5. Datos en logs

Debe implantarse un sanitizador central que elimine:

* Authorization.
* Cookies.
* Password.
* Tokens.
* DNI.
* Tarjeta sanitaria.
* Datos de medicación.
* Diagnósticos.
* Contenido de documentos.
* Parámetros sensibles de URL.

---

# 20. Protección de la aplicación web

## 20.1. Controles

* Validación de entrada.
* Codificación de salida.
* Protección CSRF.
* Política CORS restrictiva.
* Content Security Policy.
* Protección contra clickjacking.
* Cabeceras de seguridad.
* Prevención de inyección SQL.
* Prevención de XSS.
* Prevención de SSRF.
* Protección de subida de archivos.
* Control de redirecciones.
* Protección contra mass assignment.
* Límites de tamaño.
* Timeouts.
* Rate limiting.
* Bloqueo progresivo.
* Protección contra automatización.
* Detección de abuso de lógica.

## 20.2. Archivos

Los archivos subidos deberán:

* Tener límite de tamaño.
* Validar MIME real.
* Validar extensión.
* Renombrarse.
* Almacenarse fuera del webroot.
* Analizarse con antimalware.
* Renderizarse de forma segura.
* Servirse mediante autorización.
* Utilizar URLs temporales.
* Evitar ejecución.
* Eliminar metadatos cuando proceda.
* Pasar por cuarentena.

## 20.3. Límites diferenciados

No debe existir un único límite genérico.

Ejemplos:

* Inicio de sesión por cuenta e IP.
* Recuperación por cuenta, IP y dispositivo.
* Alta de citas por paciente.
* Búsqueda de pacientes por trabajador.
* Descarga de documentos.
* Verificación pública de justificantes.
* Exportaciones.
* Uso de API externa.
* Mensajes.
* Generación de códigos.

---

# 21. Seguridad administrativa

Las operaciones de alto impacto deberán aplicar:

* Reautenticación.
* Confirmación explícita.
* Motivo.
* Vista previa.
* Doble control.
* Registro de auditoría.
* Notificación.
* Límite temporal.
* Reversión cuando sea posible.

Operaciones candidatas:

* Exportación masiva.
* Fusión de pacientes.
* Eliminación lógica.
* Cambio de roles.
* Creación de superadministrador.
* Desactivación de auditoría.
* Cambio de proveedor de identidad.
* Rotación de claves.
* Cambio de política de retención.
* Activación de acceso de soporte.

## 21.1. Soporte técnico

El soporte no debe “suplantar” libremente a pacientes o profesionales.

Debe existir un modo de asistencia:

* Consentimiento o ticket.
* Motivo.
* Tiempo limitado.
* Permisos reducidos.
* Banner visible.
* Prohibición de acceder a información clínica salvo autorización específica.
* Registro de todas las acciones.
* Revisión posterior.

---

# 22. Notificaciones

## 22.1. Canales

* Correo.
* SMS.
* Push.
* Bandeja interna.
* Integraciones de mensajería autorizadas.

## 22.2. Eventos

* Alta de cita.
* Reprogramación.
* Cancelación.
* Recordatorio.
* Confirmación pendiente.
* Hueco disponible.
* Documento publicado.
* Solicitud resuelta.
* Cambio de seguridad.
* Nuevo dispositivo.
* Cambio de contraseña.
* Acceso delegado.
* Acceso excepcional, cuando proceda.

## 22.3. Seguridad

Un SMS debería decir:

> Tiene una cita en el centro el día indicado. Acceda de forma segura al portal para consultar los detalles.

No debería decir:

> Su cita de oncología por carcinoma es mañana.

## 22.4. Fiabilidad

Debe utilizarse patrón outbox:

1. La operación de negocio se confirma.
2. Se registra un mensaje en outbox dentro de la misma transacción.
3. Un worker procesa el mensaje.
4. Se envía.
5. Se registra el resultado.
6. Se reintenta con backoff.
7. Los fallos definitivos van a una cola de errores.

---

# 23. Integraciones

## 23.1. Tipos

* Proveedor de identidad.
* Correo.
* SMS.
* Firma.
* Sistemas clínicos.
* Medicación.
* Laboratorio.
* Aseguradoras.
* Facturación.
* Calendarios.
* Videoconsulta.
* Sistemas públicos.
* Directorios profesionales.
* Analítica.
* SIEM.

## 23.2. Capa de integración

Nunca debe dispersarse lógica externa por controladores y modelos.

Cada integración tendrá:

* Adaptador.
* Configuración.
* Credenciales.
* Versionado.
* Timeout.
* Reintentos.
* Circuit breaker.
* Idempotencia.
* Mapeo.
* Auditoría.
* Métricas.
* Pruebas de contrato.
* Simulador para desarrollo.
* Gestión de errores.
* Cola de reprocesamiento.

## 23.3. FHIR

Conviene crear un módulo de interoperabilidad que traduzca el modelo interno a recursos como:

* Patient.
* Practitioner.
* PractitionerRole.
* Organization.
* Location.
* HealthcareService.
* Schedule.
* Slot.
* Appointment.
* Medication.
* MedicationRequest.
* MedicationStatement.
* DocumentReference.
* Consent.
* AuditEvent.

No conviene utilizar directamente objetos FHIR como modelo relacional interno para toda la aplicación. Es preferible disponer de un modelo de dominio propio y adaptadores de entrada y salida.

---

# 24. Accesibilidad y experiencia de usuario

La aplicación debería buscar conformidad con WCAG 2.2 nivel AA. WCAG 2.2 define criterios de accesibilidad para personas con discapacidades visuales, auditivas, físicas, cognitivas, lingüísticas y neurológicas.

## 24.1. Requisitos

* Navegación por teclado.
* Foco visible.
* Orden lógico.
* Lectores de pantalla.
* Etiquetas.
* Descripciones de errores.
* Contraste.
* Zoom.
* Reflow.
* Objetivos táctiles suficientes.
* No depender únicamente del color.
* Mensajes claros.
* Tiempo ampliable.
* Reducción de movimiento.
* Alternativas a drag-and-drop.
* Tablas accesibles.
* Calendarios accesibles.
* Formularios con instrucciones.
* Idioma correctamente definido.

## 24.2. Diseño del paciente

* Lenguaje sencillo.
* Una decisión principal por pantalla.
* Confirmaciones claras.
* Estados visibles.
* Resumen antes de confirmar.
* Errores accionables.
* Diseño móvil.
* Botones grandes.
* Compatibilidad con personas mayores.

## 24.3. Diseño profesional

* Densidad ajustable.
* Atajos de teclado.
* Búsqueda rápida.
* Acciones contextuales.
* Pestañas limitadas.
* Prevención de errores.
* Confirmación de operaciones clínicas.
* Diferenciación visual entre datos aportados por paciente y datos validados.

---

# 25. Informes y analítica

## 25.1. Informes operativos

* Número de citas.
* Ocupación.
* Tiempo hasta próxima cita.
* Cancelaciones.
* Ausencias.
* Reprogramaciones.
* Tiempo de espera.
* Rendimiento por servicio.
* Utilización de salas.
* Lista de espera.
* Notificaciones fallidas.
* Activaciones del portal.

## 25.2. Seguridad

* Fallos de inicio.
* Cuentas bloqueadas.
* MFA.
* Accesos excepcionales.
* Exportaciones.
* Cambios de privilegios.
* Actividad anómala.
* Descargas masivas.
* Sesiones sospechosas.
* Integraciones fallidas.

## 25.3. Privacidad

* Solicitudes de derechos.
* Tiempo de resolución.
* Accesos a datos.
* Incidentes.
* Consentimientos.
* Retenciones.
* Exportaciones.
* Proveedores.

## 25.4. Arquitectura analítica

No deberían ejecutarse informes pesados directamente sobre la base transaccional.

Evolución recomendada:

1. Consultas optimizadas y réplicas.
2. Vistas materializadas.
3. Réplica de lectura.
4. Almacén analítico.
5. Datos seudonimizados.
6. Cuadros de mando.

---

# 26. Observabilidad

## 26.1. Tres pilares

* Métricas.
* Logs.
* Trazas.

## 26.2. Métricas técnicas

* Latencia.
* Errores.
* Saturación.
* CPU.
* Memoria.
* Conexiones.
* Bloqueos.
* Colas.
* Reintentos.
* Almacenamiento.
* Caché.
* Disponibilidad de dependencias.
* Tiempo de generación de PDF.
* Tiempo de respuesta de integraciones.

## 26.3. Métricas de negocio

* Citas creadas.
* Citas fallidas.
* Conflictos.
* Citas pendientes.
* Notificaciones.
* Activaciones.
* Documentos.
* Listas de espera.
* Tiempos de atención.

## 26.4. Alertas

* Error rate elevado.
* Latencia elevada.
* Cola acumulada.
* Fallos de base de datos.
* Almacenamiento bajo.
* Copia fallida.
* Firma fallida.
* Mensajería caída.
* Incremento de accesos denegados.
* Descarga masiva.
* Uso anómalo de break-glass.
* Alteración de auditoría.

---

# 27. Continuidad y recuperación

## 27.1. Objetivos iniciales sugeridos

Para una primera versión seria:

* Disponibilidad objetivo: 99,9 %.
* RPO: 15 minutos.
* RTO: 2 horas.
* Restauración de documentos y base de datos probada.
* Capacidad de operar temporalmente con procedimientos de contingencia.

Estos valores deben aprobarse mediante un análisis de impacto de negocio.

## 27.2. Copias

* Copias automáticas.
* Copias cifradas.
* Separación de cuenta o proyecto.
* Copia inmutable.
* Retención por niveles.
* Restauraciones periódicas.
* Documentación.
* Alertas.
* Simulacro.

No basta con que el proveedor indique que realiza backups. Hay que probar que el centro puede restaurar.

## 27.3. Plan de contingencia

* Caída de frontend.
* Caída de API.
* Caída de base de datos.
* Caída de Redis.
* Caída de proveedor de identidad.
* Caída de SMS.
* Caída de firma.
* Pérdida de región.
* Ransomware.
* Credenciales comprometidas.
* Corrupción de datos.
* Error de despliegue.
* Fuga de información.

---

# 28. DevSecOps

## 28.1. Repositorios

* Protección de ramas.
* Pull requests obligatorias.
* Revisión por pares.
* Commits firmados, cuando sea viable.
* CODEOWNERS.
* Separación de responsabilidades.
* Prohibición de secretos.
* Historial inmutable.

## 28.2. Pipeline

Cada cambio debería ejecutar:

1. Formato.
2. Lint.
3. Análisis estático.
4. Pruebas unitarias.
5. Pruebas de integración.
6. Pruebas de arquitectura.
7. SCA de dependencias.
8. Detección de secretos.
9. Análisis de contenedores.
10. Análisis de infraestructura.
11. Creación de SBOM.
12. Firma de artefacto.
13. Despliegue en staging.
14. Pruebas E2E.
15. DAST.
16. Aprobación.
17. Despliegue progresivo.
18. Verificación.
19. Posibilidad de rollback.

## 28.3. Entornos

* Local.
* Desarrollo.
* Integración.
* Staging.
* Preproducción, si procede.
* Producción.

Nunca deben copiarse datos reales de producción a desarrollo sin un proceso autorizado de anonimización o generación sintética.

## 28.4. Secretos

* Gestor central.
* No incluir secretos en `.env` distribuido.
* Rotación.
* Acceso por identidad de workload.
* Caducidad.
* Auditoría.
* Credenciales diferentes por entorno.
* Permisos mínimos.

---

# 29. Estrategia de pruebas

## 29.1. Unitarias

* Reglas de cita.
* Transiciones de estado.
* Permisos.
* Medicación.
* Restricciones.
* Plantillas.
* Retención.
* Cálculo de disponibilidad.

## 29.2. Integración

* Base de datos.
* Colas.
* Almacenamiento.
* Proveedor de identidad.
* Correo.
* SMS.
* Firma.
* FHIR.

## 29.3. API

* Autenticación.
* Autorización.
* Validación.
* Idempotencia.
* Paginación.
* Errores.
* Concurrencia.
* Rate limiting.
* Versionado.

## 29.4. End-to-end

Escenarios críticos:

* Registro de paciente.
* Vinculación.
* Reserva.
* Reprogramación.
* Cancelación.
* Check-in.
* Generación de justificante.
* Publicación.
* Consulta de medicación.
* Solicitud de renovación.
* Acceso de representante.
* Acceso de emergencia.
* Cambio de permisos.
* Exportación.
* Recuperación de cuenta.

## 29.5. Seguridad

* SAST.
* DAST.
* SCA.
* Pentest.
* Revisión manual.
* Pruebas de autorización horizontal.
* Pruebas de autorización vertical.
* Abuso de lógica.
* Fuzzing.
* Subida de archivos.
* Sesiones.
* CSRF.
* XSS.
* SQL injection.
* SSRF.
* Manipulación de identificadores.
* Enumeración.
* Exfiltración.
* Concurrencia.

## 29.6. Rendimiento

* Búsqueda de huecos.
* Reserva simultánea.
* Inicio de jornada.
* Recordatorios masivos.
* Generación de documentos.
* Exportación.
* Lista de pacientes.
* Auditoría.
* Recuperación después de caída.

## 29.7. Accesibilidad

* Pruebas automáticas.
* Navegación por teclado.
* NVDA.
* VoiceOver.
* Zoom.
* Contraste.
* Formularios.
* Calendario.
* Modales.
* Tablas.
* Mensajes de error.

---

# 30. Objetivos no funcionales

## 30.1. Rendimiento inicial

Objetivos orientativos:

* Lecturas comunes p95 inferior a 300 ms en backend.
* Escrituras comunes p95 inferior a 500 ms.
* Reserva de cita inferior a 1 segundo, excluyendo proveedores.
* Generación de PDF asíncrona.
* Búsqueda de disponibilidad inferior a 2 segundos.
* Interfaz interactiva en dispositivos móviles medios.

## 30.2. Escalabilidad

Debe soportar crecimiento horizontal de:

* API.
* Workers.
* Web frontend.
* Procesadores de notificaciones.

La base de datos seguirá siendo inicialmente el punto central, por lo que deberá optimizarse antes de introducir microservicios.

## 30.3. Mantenibilidad

* Cobertura significativa de casos críticos.
* Reglas de arquitectura.
* Documentación de decisiones.
* Contratos API.
* Catálogo de eventos.
* Guía de contribución.
* Guía de despliegue.
* Runbooks.
* Inventario de dependencias.

---

# 31. Arquitectura de infraestructura

## 31.1. Componentes

```text
Internet
  |
CDN / WAF / DDoS protection
  |
Load balancer
  |
Angular applications
  |
Laravel API
  |-- PostgreSQL
  |-- Redis
  |-- Object storage
  |-- Queue workers
  |-- Identity provider
  |-- Email/SMS
  |-- Signature provider
  |-- Monitoring/SIEM
```

## 31.2. Red

* Base de datos no pública.
* Redis no público.
* Almacenamiento privado.
* Acceso administrativo mediante VPN o acceso de confianza.
* Bastion restringido si es necesario.
* Grupos de seguridad mínimos.
* Separación de entornos.
* Egress controlado.
* DNS protegido.
* Certificados gestionados.

## 31.3. Contenedores

Los contenedores deben:

* Ejecutarse sin root.
* Ser mínimos.
* Ser inmutables.
* Tener filesystem de solo lectura cuando sea posible.
* Escanearse.
* Firmarse.
* No contener secretos.
* Disponer de límites.
* Exponer health checks.

Kubernetes no es obligatorio. Una plataforma administrada de contenedores puede ser más segura y operativamente razonable para un equipo pequeño. Kubernetes solamente resulta recomendable si existe capacidad real para operarlo.

---

# 32. Gobierno del dato

## 32.1. Catálogo

Cada dato debe tener:

* Nombre.
* Definición.
* Propietario.
* Clasificación.
* Fuente.
* Finalidad.
* Base jurídica.
* Retención.
* Usuarios autorizados.
* Sistema de origen.
* Calidad.
* Reglas de modificación.

## 32.2. Clasificación

Ejemplo:

* Público.
* Interno.
* Confidencial.
* Especialmente protegido.
* Crítico.

La medicación, los documentos clínicos y los accesos a información sanitaria serán especialmente protegidos.

## 32.3. Retención

No debe aplicarse una regla genérica de eliminación.

Se necesita una matriz por:

* Historia clínica.
* Citas.
* Justificantes.
* Facturas.
* Auditoría.
* Seguridad.
* Comunicaciones.
* Consentimientos.
* Solicitudes de derechos.
* Copias.
* Datos de cuentas.

La conservación de información clínica debe ajustarse a la legislación estatal y autonómica aplicable y a las necesidades asistenciales y legales. La Ley 41/2002 y la normativa relacionada regulan el contenido, uso, acceso y conservación de la documentación clínica.

---

# 33. Funcionalidades que deben excluirse inicialmente

Para controlar riesgo y coste, la primera versión no debería incluir:

* Diagnóstico automatizado.
* Recomendación terapéutica autónoma.
* Prescripción electrónica completa.
* Historia clínica integral.
* Procesamiento de imágenes diagnósticas.
* Integración con todos los sistemas públicos.
* Microservicios.
* Inteligencia artificial sobre datos clínicos.
* Chat clínico 24 horas.
* Videoconsulta compleja.
* Aplicación nativa iOS y Android independiente.
* Analítica predictiva.
* Facturación avanzada multicompañía.
* Múltiples países y legislaciones.
* Investigación y uso secundario de datos.

Estas capacidades pueden desarrollarse posteriormente, tras analizar impacto clínico, jurídico y regulatorio.

---

# 34. MVP recomendado

El MVP profesional debe incluir aproximadamente entre 60 y 90 historias de usuario de tamaño controlado.

## 34.1. Paciente

* Activación.
* Inicio de sesión.
* MFA opcional.
* Perfil.
* Próximas citas.
* Citas anteriores.
* Reserva.
* Reprogramación.
* Cancelación.
* Recordatorios.
* Medicación en modo lectura.
* Documentos.
* Justificantes.
* Representación básica.
* Historial de accesos básico.

## 34.2. Profesionales

* Agenda.
* Información mínima de paciente.
* Estados de cita.
* Check-in.
* Finalización.
* Medicación básica.
* Publicación de documentos.
* Justificantes.

## 34.3. Administración

* Pacientes.
* Profesionales.
* Centros.
* Servicios.
* Agenda.
* Citas.
* Horarios.
* Festivos.
* Plantillas.
* Roles.
* Permisos.

## 34.4. Seguridad

* MFA para personal.
* Sesiones.
* RBAC y ABAC básico.
* Auditoría.
* Rate limiting.
* Cifrado.
* Gestión de secretos.
* Copias.
* Monitorización.
* Alertas.
* EIPD.
* Pentest.

---

# 35. Evolución funcional

## Versión 1.1

* Lista de espera.
* Representantes avanzados.
* Solicitudes de derechos.
* Verificación pública de PDF.
* Firma.
* Restricciones.
* Acceso de emergencia.
* Analítica operativa.
* Importaciones.
* Integraciones básicas.

## Versión 1.2

* Citas recurrentes.
* Recursos múltiples.
* Quiosco.
* PWA.
* Notificaciones push.
* Conciliación de medicación.
* Exportación FHIR.
* Acceso transparente para el paciente.
* Gestión documental avanzada.

## Versión 2

* Multicentro.
* Multiempresa.
* Federación de identidad.
* Integraciones clínicas.
* Teleconsulta.
* Facturación.
* Aseguradoras.
* Data warehouse.
* Aplicaciones móviles.
* Interoperabilidad regional o nacional.

Un producto de este alcance puede superar fácilmente las 200 o 300 historias de usuario. No deben desarrollarse todas simultáneamente.

---

# 36. Equipo necesario

Para una ejecución profesional:

* 1 product owner.
* 1 responsable clínico.
* 1 arquitecto o tech lead.
* 2 o 3 desarrolladores Laravel.
* 2 desarrolladores Angular.
* 1 diseñador UX/UI con accesibilidad.
* 1 QA automation.
* 1 DevSecOps.
* 1 especialista de seguridad parcial.
* 1 delegado o asesor de protección de datos.
* 1 responsable de integraciones, cuando comiencen.
* Pentest externo independiente.

Con menos personas es posible desarrollar una versión reducida, pero no es realista esperar cientos de funcionalidades, seguridad alta, auditoría avanzada, interoperabilidad y cumplimiento completo en pocos meses.

---

# 37. Hoja de ruta

## Fase 0: descubrimiento — 4 a 6 semanas

* Procesos actuales.
* Usuarios.
* Sistemas existentes.
* Inventario de datos.
* Normativa.
* EIPD inicial.
* Mapa de riesgos.
* Alcance.
* Arquitectura.
* Backlog.
* Prototipos.
* Modelo de permisos.
* Selección de proveedores.
* Definición de SLO.

## Fase 1: fundamentos — 6 a 10 semanas

* Repositorios.
* CI/CD.
* Infraestructura.
* Identidad.
* Organizaciones.
* Usuarios.
* Roles.
* Auditoría.
* Diseño Angular.
* API.
* Base de datos.
* Observabilidad.
* Seguridad.

## Fase 2: citas MVP — 10 a 14 semanas

* Centros.
* Profesionales.
* Servicios.
* Agendas.
* Huecos.
* Reserva.
* Cancelación.
* Reprogramación.
* Recepción.
* Recordatorios.
* Portal paciente.
* Portal profesional.

## Fase 3: medicación y documentos — 8 a 12 semanas

* Medicación.
* Plantillas.
* PDF.
* Justificantes.
* Publicación.
* Verificación.
* Firma.
* Descargas.
* Auditoría reforzada.

## Fase 4: administración avanzada — 8 a 12 semanas

* Lista de espera.
* Representantes.
* Privacidad.
* Restricciones.
* Acceso de emergencia.
* Informes.
* Configuración avanzada.
* Operaciones masivas.

## Fase 5: integraciones y piloto — 8 a 14 semanas

* FHIR.
* Sistemas externos.
* Migración.
* Rendimiento.
* Accesibilidad.
* Pentest.
* Simulacro de recuperación.
* Formación.
* Piloto.
* Correcciones.

## Fase 6: producción y evolución

* Despliegue progresivo.
* Monitorización reforzada.
* Soporte.
* Revisión de permisos.
* Revisión de riesgos.
* Métricas.
* Roadmap.

Una versión sólida puede requerir entre 9 y 15 meses. Una plataforma con varios centros, integraciones sanitarias y cientos de funciones puede superar los 18 meses.

---

# 38. Criterios de salida a producción

La aplicación no debería publicarse hasta que se cumpla:

* EIPD aprobada.
* Registro de tratamientos.
* Contratos con proveedores.
* Modelo de autorización revisado.
* MFA operativo.
* Auditoría operativa.
* Copias restauradas en prueba.
* Plan de incidentes.
* Plan de continuidad.
* Pentest sin hallazgos críticos o altos pendientes.
* Pruebas de concurrencia de citas.
* Pruebas de accesibilidad.
* Pruebas de carga.
* Migración validada.
* Formación.
* Soporte.
* Monitorización.
* Alertas.
* Runbooks.
* Política de retención.
* Proceso de revocación de usuarios.
* Revisión de privilegios.
* Procedimiento de brechas.
* Procedimiento de derechos.
* Inventario de dependencias.
* SBOM.
* Plan de actualización.

---

# 39. Riesgos principales

## 39.1. Exceso de alcance

**Riesgo:** intentar construir una historia clínica completa desde el principio.

**Mitigación:** MVP estricto, módulos y fases.

## 39.2. Permisos demasiado simples

**Riesgo:** cualquier profesional puede ver cualquier paciente.

**Mitigación:** RBAC, ABAC, relación asistencial, finalidad y auditoría.

## 39.3. Doble reserva

**Riesgo:** concurrencia entre pacientes y recepción.

**Mitigación:** transacciones, restricciones únicas, bloqueos e idempotencia.

## 39.4. Auditoría insuficiente

**Riesgo:** no poder demostrar quién accedió.

**Mitigación:** eventos append-only, correlación y protección criptográfica.

## 39.5. Datos sensibles en logs

**Riesgo:** filtración mediante herramientas de observabilidad.

**Mitigación:** sanitización y prohibición de payloads completos.

## 39.6. Dependencia de proveedores

**Riesgo:** caída de identidad, SMS, firma o historia clínica.

**Mitigación:** colas, circuit breaker, reintentos, procedimientos alternativos.

## 39.7. Mala recuperación de cuenta

**Riesgo:** secuestro de cuentas de pacientes.

**Mitigación:** MFA, verificación reforzada y alertas.

## 39.8. Documentos falsificables

**Riesgo:** justificantes manipulados.

**Mitigación:** hash, QR opaco, verificación, firma y revocación.

## 39.9. Importación defectuosa

**Riesgo:** asociación incorrecta de pacientes o medicación.

**Mitigación:** validaciones, staging, reconciliación, muestras y rollback.

## 39.10. Acceso interno abusivo

**Riesgo:** empleados consultando expedientes sin necesidad.

**Mitigación:** mínimo privilegio, detección, revisión y sanción organizativa.

---

# 40. Prácticas que deben evitarse

* Crear un rol `admin` con acceso absoluto.
* Guardar JWT en localStorage.
* Implementar criptografía propia.
* Usar IDs incrementales como seguridad.
* Permitir acceso a datos clínicos al soporte técnico.
* Registrar payloads completos.
* Enviar medicación por SMS.
* Editar documentos emitidos.
* Borrar auditorías.
* Usar soft delete indiscriminadamente.
* Realizar informes pesados en producción.
* Ejecutar tareas lentas dentro de peticiones.
* Desplegar sin rollback.
* Probar con datos reales.
* Dar acceso permanente por sustituciones.
* Confiar en permisos del frontend.
* Utilizar microservicios sin necesidad.
* Incorporar inteligencia artificial antes de tener gobierno del dato.
* Crear una historia clínica sin liderazgo clínico.
* Tratar el consentimiento como única base jurídica para todo.

---

# 41. Recomendación arquitectónica final

La solución inicial más equilibrada sería:

```text
Frontend:
- Angular estable
- Angular Material
- Portal de paciente
- Portal de personal
- Diseño WCAG 2.2 AA

Backend:
- Laravel estable
- Monolito modular
- API REST versionada
- OpenAPI
- Policies y autorización contextual

Datos:
- PostgreSQL
- Redis
- Almacenamiento de objetos
- Auditoría append-only

Identidad:
- OIDC
- MFA
- Passkeys
- Sesiones servidor
- Reautenticación

Operaciones:
- Contenedores administrados
- CI/CD
- WAF
- KMS
- Gestor de secretos
- OpenTelemetry
- SIEM
- Backups inmutables

Cumplimiento:
- RGPD
- LOPDGDD
- Ley 41/2002
- ENS cuando sea aplicable
- EIPD
- OWASP ASVS 5.0.0
- Preparación para EEDS
- FHIR para interoperabilidad
```

La prioridad absoluta debe ser construir correctamente cinco núcleos:

1. Identidad.
2. Autorización.
3. Motor de citas.
4. Auditoría.
5. Modelo de pacientes.

Si esos cinco elementos están mal diseñados, añadir cientos de funciones solamente aumentará el riesgo y la deuda técnica. Si están bien diseñados, la aplicación podrá evolucionar progresivamente hacia una plataforma sanitaria completa.
