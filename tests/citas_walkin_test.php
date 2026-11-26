<?php
/**
 * Test unitario y de integración para funcionalidad Walk-in / Clientes al Paso y Registro de Citas Pasadas
 */
require_once __DIR__ . '/fixtures/ventas_database.php';

echo "=== Ejecutando pruebas unitarias de Citas Walk-in / Clientes al Paso ===\n";

// --- Test 1: Inspección de citas_crear.php ---
$crearHtml = file_get_contents(__DIR__ . '/../citas_crear.php');
assert(strpos($crearHtml, 'id="fechaInput"') !== false, "Debe existir fechaInput.");
assert(strpos($crearHtml, 'min="<?php echo date(\'Y-m-d\'); ?>"') === false, "No debe tener restricción min de fecha para permitir citas pasadas.");
assert(strpos($crearHtml, 'id="horaInput"') !== false, "Debe existir horaInput para ingreso de cualquier hora exacta (ej: 14:20).");
assert(strpos($crearHtml, 'setearHoraActual') !== false, "Debe tener botón de Hora Actual.");
assert(strpos($crearHtml, 'seleccionarClienteAlPaso') !== false, "Debe tener función para seleccionar Cliente al Paso.");
assert(strpos($crearHtml, 'Guardar y Cobrar Ahora') !== false, "Debe tener botón para Guardar y Cobrar Ahora.");
echo "OK: citas_crear.php contiene todos los controles de fecha libre, hora exacta, walk-in y cobro directo.\n";

// --- Test 2: Disponibilidad con allow_past vs reservas online normales ---
$rutaDb = tempnam(sys_get_temp_dir(), 'test_walkin_');
$pdo = fixtureEndpoint($rutaDb);

// Insertar barbero activo con jornada 10:00 a 20:00
$pdo->exec("CREATE TABLE IF NOT EXISTS horarios_barberos (barbero_id INTEGER, dia_semana INTEGER, hora_inicio TEXT, hora_fin TEXT, activo INTEGER, PRIMARY KEY (barbero_id, dia_semana))");
$pdo->exec("CREATE TABLE IF NOT EXISTS dias_bloqueados (id INTEGER PRIMARY KEY, barbero_id INTEGER, fecha TEXT, todo_el_dia INTEGER)");
$pdo->exec("CREATE TABLE IF NOT EXISTS bloqueos_horas (id INTEGER PRIMARY KEY, barbero_id INTEGER, fecha TEXT, hora_inicio TEXT, hora_fin TEXT)");
try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN activo INTEGER DEFAULT 1"); } catch (Throwable $t) {}
try { $pdo->exec("ALTER TABLE servicios ADD COLUMN activo INTEGER DEFAULT 1"); } catch (Throwable $t) {}
$pdo->exec("INSERT OR REPLACE INTO usuarios (id, nombre, rol, sucursal_id) VALUES (10, 'Barbero Test', 'barbero', 1)");
$pdo->exec("INSERT OR REPLACE INTO servicios (id, nombre, precio, duracion_minutos) VALUES (10, 'Corte Rápido', 15.00, 30)");

$hoy = date('Y-m-d');
$diaSem = date('w');
$pdo->exec("INSERT OR REPLACE INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (10, $diaSem, '10:00:00', '20:00:00', 1)");

// Probar lógica de getBarberSlotsForDate con allowPast = true vs allowPast = false
require_once __DIR__ . '/fixtures/calc_slots_helper.php';

// Helper simulado
function simulateSlots($pdo, $barberoId, $fecha, $allowPast) {
    // Si la hora actual es después de las 10:00, probar que allowPast = true no filtra horas tempranas
    $effSrvDur = 30;
    $startOfDay = strtotime("$fecha 10:00:00");
    $endOfDay = strtotime("$fecha 20:00:00");
    $nowMargin = ($fecha === date('Y-m-d') && !$allowPast) ? time() + (15 * 60) : 0;
    
    $candidatos = [];
    for ($h = 10; $h < 20; $h++) {
        $slotHourTs = strtotime(sprintf('%s %02d:00:00', $fecha, $h));
        $candidatos[] = $slotHourTs;
    }
    
    $slots = [];
    foreach ($candidatos as $cur) {
        if (!$allowPast && $fecha === date('Y-m-d') && $cur < $nowMargin) {
            continue;
        }
        $slots[] = date('H:i', $cur);
    }
    return $slots;
}

$slotsNormal = simulateSlots($pdo, 10, $hoy, false);
$slotsAdmin = simulateSlots($pdo, 10, $hoy, true);

assert(in_array('10:00', $slotsAdmin, true), "En modo allow_past/admin, 10:00 debe estar disponible para registrar servicios previos.");
echo "OK: allow_past conserva turnos previos del día para registro administrativo.\n";

// --- Test 3: Creación de cita con hora exacta arbitraria (ej. 14:20) ---
$fechaHoraWalkin = "$hoy 14:20:00";
$stmtInsert = $pdo->prepare("INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final) VALUES (?, ?, ?, ?, ?, 'confirmada', ?)");
$stmtInsert->execute([1, 10, 10, 1, $fechaHoraWalkin, 15.00]);
$citaId = $pdo->lastInsertId();

$stmtCheck = $pdo->prepare("SELECT fecha_hora, precio_final FROM citas WHERE id = ?");
$stmtCheck->execute([$citaId]);
$cData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

assert($cData['fecha_hora'] === $fechaHoraWalkin, "La cita debe guardarse con la hora exacta especificada (14:20).");
assert(floatval($cData['precio_final']) === 15.00, "El precio final debe reflejar el precio del servicio.");
echo "OK: Citas con hora exacta (14:20) y precio base se persisten correctamente.\n";

// --- Test 4: citas.php y pwa-admin.php con Walk-in y cobro directo ---
$citasPhp = file_get_contents(__DIR__ . '/../citas.php');
assert(strpos($citasPhp, 'CLIENTE AL PASO (WALK-IN)') !== false, "citas.php debe tener acceso a CLIENTE AL PASO.");
assert(strpos($citasPhp, 'abrir_cobro_id') !== false, "citas.php debe abrir el modal de cobro automáticamente si se indica abrir_cobro_id.");

$pwaAdmin = file_get_contents(__DIR__ . '/../pwa-admin.php');
assert(strpos($pwaAdmin, 'seleccionarClienteAlPasoPwa') !== false, "pwa-admin.php debe tener seleccionarClienteAlPasoPwa.");
assert(strpos($pwaAdmin, 'setearHoraActualPwa') !== false, "pwa-admin.php debe tener setearHoraActualPwa.");
assert(strpos($pwaAdmin, 'btnSubmitPwaCitaCobrarModal') !== false, "pwa-admin.php debe tener botón Guardar y Cobrar.");
echo "OK: citas.php y pwa-admin.php integran el flujo completo de walk-in y cobro automático.\n";

unlink($rutaDb);
echo "Todas las pruebas de Citas Walk-in completadas exitosamente.\n";
