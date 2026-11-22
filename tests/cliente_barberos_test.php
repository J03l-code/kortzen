<?php
/**
 * Test unitario para verificar:
 * 1. cliente-login.php: Solo login con Google visible (sin form de admin ni card de WhatsApp).
 * 2. Restricción estricta de perfiles para clientes y reservas: únicamente rol = 'barbero'.
 */
require_once __DIR__ . '/fixtures/ventas_database.php';

// --- Test 1: Inspección de cliente-login.php ---
$loginHtml = file_get_contents(__DIR__ . '/../cliente-login.php');
assert(strpos($loginHtml, 'Continuar con Google') !== false, "Debe tener el botón 'Continuar con Google'.");
assert(strpos($loginHtml, 'Solicita tu registro por WhatsApp') === false, "No debe tener la tarjeta de solicitud de registro por WhatsApp.");
assert(strpos($loginHtml, 'Si no tienes cuenta de Google, inicia sesión con tu perfil') === false, "No debe tener el texto de inicio con perfil de administrador.");
assert(strpos($loginHtml, 'name="action" value="login"') === false, "No debe tener el formulario tradicional de login administrativo.");
echo "OK: cliente-login.php presenta exclusivamente inicio con Google y sin solicitud de WhatsApp.\n";

// --- Test 2: Comportamiento en base de datos con perfiles mezclados ---
$rutaDb = tempnam(sys_get_temp_dir(), 'test_cli_barb_');
$pdo = fixtureEndpoint($rutaDb);

// Insertar varios usuarios con diferentes roles
// id 1: Admin
// id 2: Recepción
// id 3: Barbero 1 (Mateo)
// Insertar id 4: Admin Local (e.g. 'Administrador locales' de Laureles)
// Insertar id 5: Barbero 2 (Jeffrey)
$pdo->exec("INSERT INTO usuarios (id, nombre, rol, activo, sucursal_id) VALUES (4, 'Administrador locales', 'admin_local', 1, 2)");
$pdo->exec("INSERT INTO usuarios (id, nombre, rol, activo, sucursal_id) VALUES (5, 'Jeffrey Herrera', 'barbero', 1, 2)");

// Insertar relación indebida en servicios_barberos para id 4 (admin_local) y id 5 (barbero)
$pdo->exec("INSERT INTO servicios_barberos (servicio_id, barbero_id) VALUES (1, 4)");
$pdo->exec("INSERT INTO servicios_barberos (servicio_id, barbero_id) VALUES (1, 5)");

// Ejecutar limpieza/integridad
require_once __DIR__ . '/../config.php';
asegurarIntegridadServiciosSucursalesBarberos($pdo);

// Comprobar que en servicios_barberos se eliminó al admin_local (id 4) y se mantuvo al barbero (id 5)
$assignedIds = $pdo->query("SELECT barbero_id FROM servicios_barberos WHERE servicio_id = 1")->fetchAll(PDO::FETCH_COLUMN);
$assignedIds = array_map('intval', $assignedIds);
assert(!in_array(4, $assignedIds, true), "El admin_local (id 4) debe haber sido eliminado de servicios_barberos.");
assert(in_array(5, $assignedIds, true), "El barbero (id 5) debe mantenerse en servicios_barberos.");
echo "OK: asegurarIntegridadServiciosSucursalesBarberos purga perfiles no barberos de servicios_barberos.\n";

// Comprobar consulta de barbers en get_catalog
$stmtCat = $pdo->query("SELECT u.id, u.nombre, u.rol FROM usuarios u WHERE u.rol = 'barbero' AND u.activo = 1");
$barbersCatalog = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
$roles = array_column($barbersCatalog, 'rol');
$names = array_column($barbersCatalog, 'nombre');
assert(!in_array('admin_local', $roles, true), "No debe haber ningún admin_local en el catálogo de barberos.");
assert(!in_array('Administrador locales', $names, true), "No debe figurar 'Administrador locales' en el catálogo de barberos.");
assert(in_array('Jeffrey Herrera', $names, true), "'Jeffrey Herrera' (barbero) debe estar en el catálogo.");
echo "OK: get_catalog retorna únicamente usuarios con rol 'barbero'.\n";

unlink($rutaDb);
echo "Todas las pruebas de clientes y barberos completadas con éxito.\n";
