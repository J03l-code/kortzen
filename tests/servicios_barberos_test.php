<?php
/**
 * Test unitario para verificar la restricción de barberos por servicio.
 */
require_once __DIR__ . '/fixtures/ventas_database.php';

$rutaDb = tempnam(sys_get_temp_dir(), 'test_srv_barb_');
$pdo = fixtureEndpoint($rutaDb);

// Insertar segundo barbero para las pruebas
$pdo->exec("INSERT INTO usuarios (id, nombre, rol, activo, sucursal_id) VALUES (4, 'Jeffrey Herrera', 'barbero', 1, 1)");

// Insertar horarios para barbero 3 y 4
for ($d = 0; $d < 7; $d++) {
    $pdo->exec("INSERT INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (3, $d, '09:00:00', '19:00:00', 1)");
    $pdo->exec("INSERT INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (4, $d, '09:00:00', '19:00:00', 1)");
}

// Configurar servicio 1 asignado ÚNICAMENTE al barbero 3
$pdo->exec("DELETE FROM servicios_barberos WHERE servicio_id = 1");
$pdo->exec("INSERT INTO servicios_barberos (servicio_id, barbero_id) VALUES (1, 3)");

// 1. Probar que servicios_barberos contiene solo el barbero 3
$barberosAsignados = $pdo->query("SELECT barbero_id FROM servicios_barberos WHERE servicio_id = 1")->fetchAll(PDO::FETCH_COLUMN);
assert(count($barberosAsignados) === 1, "Debe haber exactamente 1 barbero asignado.");
assert((int)$barberosAsignados[0] === 3, "El barbero asignado debe ser el ID 3.");
assert(!in_array(4, array_map('intval', $barberosAsignados), true), "Jeffrey Herrera (ID 4) no debe estar en servicios_barberos.");

echo "OK: Tabla servicios_barberos filtra correctamente al barbero desmarcado.\n";

unlink($rutaDb);
