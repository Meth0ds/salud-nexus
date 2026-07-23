# ADR-011: un único centro sanitario por despliegue

## Estado

Aceptado.

## Fecha

2026-07-23.

## Contexto

El alcance de producto se ha concretado: Salud Nexus se implanta para un solo centro sanitario, no
para una red de sedes. Mantener selectores de centro, comparativas entre centros y administración de
una red añadiría complejidad, riesgo de contexto incorrecto y tareas que no aportan valor al caso real.

## Decisión

Cada despliegue configura una organización operativa y exactamente un centro sanitario. La interfaz
no permite elegir ni cambiar de centro. Pacientes y personal seleccionan servicios, unidades,
profesionales o finalidades dentro de ese centro.

Se conserva `organization_id` como frontera interna de autorización y prueba contra IDOR, y
`center_id` donde aporta integridad referencial, zona horaria, ubicación o trazabilidad. La base de
datos impide crear más de un centro por organización. Esta persistencia defensiva no convierte el
producto en multicentro ni se expone como selector.

## Alternativas consideradas

- Eliminar organización y centro de todas las tablas: rechazada porque debilita el aislamiento y
  obliga a una migración transversal sin beneficio funcional.
- Mantener multicentro oculto en la interfaz: rechazado porque conservaría reglas, pruebas y errores
  de contexto innecesarios.
- Permitir varios centros por configuración: rechazado por contradecir el alcance confirmado.

## Consecuencias

- La reserva del paciente pasa de cuatro a tres pasos y comienza por el servicio.
- El portal del personal muestra el centro fijo y solo cambia de área o finalidad autorizada.
- La administración configura un centro, sus unidades, espacios, recursos, servicios y cierres.
- Los informes segmentan por unidad/servicio, no comparan centros.
- Cualquier futuro soporte multicentro exige un ADR nuevo, migraciones, threat model y pruebas de
  aislamiento específicas; no se activa mediante una simple opción visual.
