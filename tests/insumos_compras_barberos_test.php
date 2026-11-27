<?php
/**
 * Ejecutar: php tests/insumos_compras_barberos_test.php
 */
require_once __DIR__ . '/../includes/insumos_barberos.php';

class InsumosTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}

function fixtureInsumos(): PDO
{
    $pdo = new InsumosTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nombre TEXT, rol TEXT, activo INTEGER, sucursal_id INTEGER, comision_productos DECIMAL)');
    $pdo->exec("INSERT INTO usuarios VALUES
        (1, 'Admin Principal', 'admin', 1, NULL, 0),
        (2, 'Mateo Barbero', 'barbero', 1, 1, 10),
        (3, 'Jeffrey Barbero', 'barbero', 1, 2, 10),
        (4, 'Barbero Inactivo', 'barbero', 0, 1, 10)");
    $pdo->exec('CREATE TABLE usuarios_sucursales (usuario_id INTEGER, sucursal_id INTEGER)');
    $pdo->exec('CREATE TABLE sucursales (id INTEGER PRIMARY KEY, nombre TEXT)');
    $pdo->exec("INSERT INTO sucursales VALUES (1, 'KORTZEN Llano Chico'), (2, 'KORTZEN Laureles')");
    $pdo->exec('CREATE TABLE inventario (id INTEGER PRIMARY KEY, producto TEXT, cantidad INTEGER, precio DECIMAL, stock_minimo INTEGER, sucursal_id INTEGER)');
    $pdo->exec("INSERT INTO inventario VALUES
        (1, 'Cera Nishman', 10, 13.00, 3, 1),
        (2, 'Cuchilla Dorco', 4, 0.50, 3, 1),
        (3, 'Polvo Texturizador', 5, 15.00, 2, 2)");
    $pdo->exec('CREATE TABLE ventas_productos (id INTEGER PRIMARY KEY, cita_id INTEGER, producto_id INTEGER, cantidad INTEGER,
        precio_unitario DECIMAL, sucursal_id INTEGER, usuario_id INTEGER, registrado_por INTEGER,
        vendedor_nombre TEXT, comision_porcentaje DECIMAL, metodo_pago TEXT, cobro_id INTEGER, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE cobros_caja (id INTEGER PRIMARY KEY, cita_id INTEGER UNIQUE, sucursal_id INTEGER, registrado_por INTEGER, metodo_pago TEXT, servicios DECIMAL, productos DECIMAL, propina DECIMAL, total DECIMAL, fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP)');
    asegurarTablasInsumosYComprasBarberos($pdo);
    return $pdo;
}

echo "=== Ejecutando pruebas unitarias de Insumos Internos y Compras de Barberos ===\n";

// Test 1: Insumo interno descuenta stock sin cobrar al cliente ni afectar ventas_productos
$db = fixtureInsumos();
$resIns = registrarInsumosInternos($db, [['producto_id' => 2, 'cantidad' => 2]], 1, 2, 1, 55);
if (count($resIns['items']) !== 1) throw new RuntimeException('Debió registrar 1 insumo');
$stockRestante = (int) $db->query('SELECT cantidad FROM inventario WHERE id = 2')->fetchColumn();
if ($stockRestante !== 2) throw new RuntimeException("Stock esperado 2, obtenido $stockRestante");
if (count($resIns['alertas_stock']) !== 1) throw new RuntimeException('Debió disparar alerta de stock bajo (2 <= 3)');
$ventasCount = (int) $db->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn();
if ($ventasCount !== 0) throw new RuntimeException('Insumo interno no debe generar registro en ventas_productos');
echo "OK: Insumo interno rebaja stock, alerta stock bajo y no cobra al cliente.\n";

// Test 2: Stock insuficiente en insumo interno lanza excepción y conserva stock intacto
try {
    registrarInsumosInternos($db, [['producto_id' => 2, 'cantidad' => 5]], 1, 2, 1, 56);
    throw new RuntimeException('Debió fallar por stock insuficiente');
} catch (InvalidArgumentException $e) {
    $stockAfter = (int) $db->query('SELECT cantidad FROM inventario WHERE id = 2')->fetchColumn();
    if ($stockAfter !== 2) throw new RuntimeException('Rollback falló en stock');
}
echo "OK: Stock insuficiente en insumo interno revierte sin alterar inventario.\n";

// Test 3: Venta a Barbero con precio especial de barbero (menor al PVP) y registro en rol
$compra = registrarCompraBarbero($db, 1, 2, 2, 8.50, 1, null, null, 'Uso personal');
if (abs($compra['total'] - 17.00) > 0.01) throw new RuntimeException('Total de compra barbero incorrecto');
$stockCera = (int) $db->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn();
if ($stockCera !== 8) throw new RuntimeException("Stock Cera esperado 8, obtenido $stockCera");
$ventasCount2 = (int) $db->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn();
if ($ventasCount2 !== 0) throw new RuntimeException('Compra de barbero no debe mezclarse con ventas_productos de clientes');
echo "OK: Compra de barbero rebaja stock con precio especial y registra deuda en rol sin mezclar caja cliente.\n";

// Test 4: Reporte Compras de Barberos agrupa totales pendientes vs descontados
$reporte = obtenerComprasBarberos($db, ['id' => 1, 'rol' => 'admin'], ['desde' => date('Y-m-d'), 'hasta' => date('Y-m-d')]);
if (abs($reporte['total_pendiente'] - 17.00) > 0.01) throw new RuntimeException('Total pendiente en reporte incorrecto');
if (count($reporte['resumen_barberos']) !== 1 || $reporte['resumen_barberos'][0]['barbero'] !== 'Mateo Barbero') {
    throw new RuntimeException('Resumen por barbero incorrecto');
}
$db->exec("UPDATE compras_barberos SET estado_rol = 'descontado' WHERE id = " . (int) $compra['compra_id']);
$reporte2 = obtenerComprasBarberos($db, ['id' => 1, 'rol' => 'admin'], ['desde' => date('Y-m-d'), 'hasta' => date('Y-m-d')]);
if (abs($reporte2['total_pendiente']) > 0.01 || abs($reporte2['total_descontado'] - 17.00) > 0.01) {
    throw new RuntimeException('Actualización de estado en rol no se reflejó en reporte');
}
echo "OK: Reporte Compras de Barberos diferencia pendiente vs descontado en rol.\n";

// Test 5: Verificación de controles UI en citas.php, inventario.php, pwa-admin.php y caja.php
$citasSrc = file_get_contents(__DIR__ . '/../citas.php');
$invSrc = file_get_contents(__DIR__ . '/../inventario.php');
$pwaSrc = file_get_contents(__DIR__ . '/../pwa-admin.php');
$cajaSrc = file_get_contents(__DIR__ . '/../caja.php');
if (strpos($citasSrc, 'Producto Vendido al Cliente') === false || strpos($citasSrc, 'Insumo / Gasto Interno') === false || strpos($citasSrc, 'compra_barbero_producto_id') === false) {
    throw new RuntimeException('citas.php no contiene las casillas separadas de producto, insumo y descuento a barbero');
}
if (strpos($pwaSrc, 'Producto Vendido al Cliente') === false || strpos($pwaSrc, 'Insumo / Gasto Interno') === false || strpos($pwaSrc, 'compra_barbero_producto_id') === false) {
    throw new RuntimeException('pwa-admin.php no contiene las casillas separadas de producto, insumo y descuento a barbero');
}
if (strpos($invSrc, 'compras_barberos') === false || strpos($invSrc, 'Venta a Barbero') === false) {
    throw new RuntimeException('inventario.php no contiene la pestaña Compras de Barberos ni el botón Venta a Barbero');
}
if (strpos($cajaSrc, 'Compras de Barberos') === false) {
    throw new RuntimeException('caja.php no integra el resumen de Compras de Barberos');
}
echo "OK: UI Web, PWA, Inventario y Caja integran correctamente el flujo de Punto 5 y Punto 8.\n";
echo "Todas las pruebas de Insumos Internos y Compras de Barberos completadas.\n";
