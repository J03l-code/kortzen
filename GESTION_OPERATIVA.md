# Ventas, caja y reasignación

## Flujo de uso

- Inventario → Historial de Ventas de Productos: rango de fechas, vendedor, paginación y descarga del filtro completo en XLSX/PDF. Disponible también en Inventario de la PWA.
- Finalizar Cita y ventas directas: seleccionar el método recibido (efectivo, transferencia, DeUna, tarjeta de débito o crédito). Al vender productos se exige además un vendedor activo.
- Administración → Cierre de Caja y Contabilidad: resumen diario, semanal, mensual o por fechas/sucursal. Muestra cortes, servicios, productos, propinas y total por método.
- Citas → Vista Calendario: columnas por barbero y bloques por estado. Las reservas pendientes de reasignación aparecen en una columna independiente y en un panel de administración. El panel muestra todas las pendientes futuras de las sucursales permitidas, aunque se esté viendo otro día.
- Horarios: apagar días, reducir la jornada o agregar bloqueos marca las reservas futuras afectadas como «Sin barbero asignado». Reasignar comprueba jornada, bloqueos, descanso, sucursal, servicio y solapamientos. Restaurar el horario no reasigna automáticamente.

## Datos y compatibilidad

Las migraciones en `config.php` son aditivas y verifican columnas antes de crearlas. No eliminan ni reclasifican datos anteriores.

`cobros_caja` guarda una instantánea del ingreso recibido. Una cita produce un solo cobro que incluye servicios, productos y propina; sus líneas de productos se vinculan a ese cobro. Las ventas sin cita generan su propio cobro. Los reportes no vuelven a sumar las líneas ya incluidas en una instantánea.

Los registros antiguos sin método aparecen como «Sin especificar». Si no tienen fecha de finalización, su fecha de referencia es la de la cita. El resumen es de ingresos cobrados, no del saldo bancario ni del efectivo después de retiros, gastos, devoluciones o pagos de propinas. No implementa contabilidad fiscal.

Una cita sin asignación conserva `barbero_id` como referencia histórica por compatibilidad con las relaciones existentes. `reasignacion_pendiente` determina que no tiene asignación efectiva; no ocupa al barbero anterior. `citas_reasignaciones` registra cada transición. Las citas pasadas, completadas, canceladas o en atención no se desasignan por cambios de horario.

Los reportes aplican el alcance de sucursales en el servidor. Caja y reasignación son exclusivos de administración; los barberos sólo pueden consultar sus ventas. Los avisos de stock se envían después del commit.

## Validación

```sh
php tests/ventas_productos_test.php
php tests/ventas_endpoints_test.php
php tests/gestion_operativa_test.php
node tests/calendario_test.js
```

Las pruebas usan SQLite desechable y ejecutan las APIs en procesos separados con configuración aislada. No leen las credenciales ni la base de producción. La traducción de `FOR UPDATE` y `ON DUPLICATE KEY` permite comprobar transacciones y flujos, pero no prueba los bloqueos concurrentes del motor MySQL.

Antes del despliegue, validar en una copia MySQL/MariaDB con respaldo: ejecutar las migraciones dos veces, completar una cita con adicionales/productos/propina, comprobar el desglose de caja, bloquear y reasignar reservas, y probar dos cobros concurrentes sobre la última unidad de un producto.

Requisitos de exportación: extensiones PHP ZIP, mbstring e iconv. Excel se genera como XLSX real, con importes numéricos y nombres tratados como texto; PDF se genera con paginación y encabezados repetidos.
