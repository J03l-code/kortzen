<?php
/**
 * Ejecutar: php tests/ventas_productos_test.php
 * SQLite comprueba persistencia y rollback. Los bloqueos FOR UPDATE requieren MySQL.
 */
require_once __DIR__ . '/../includes/ventas_productos.php';

class VentasTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}

function fixtureVentas(): PDO
{
    $pdo = new VentasTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nombre TEXT, rol TEXT, activo INTEGER, sucursal_id INTEGER, comision_productos DECIMAL)');
    $pdo->exec("INSERT INTO usuarios VALUES
        (1, 'Administrador de caja', 'admin', 1, NULL, 0),
        (2, 'Sammy Pérez', 'recepcion', 1, 1, 15),
        (3, 'Mishell Ruiz', 'admin_local', 1, 1, 5),
        (4, 'Barbero de turno', 'barbero', 1, 1, 10),
        (5, 'Usuario inactivo', 'barbero', 0, 1, 10)");
    $pdo->exec('CREATE TABLE usuarios_sucursales (usuario_id INTEGER, sucursal_id INTEGER)');
    $pdo->exec('CREATE TABLE inventario (id INTEGER PRIMARY KEY, producto TEXT, cantidad INTEGER, precio DECIMAL, sucursal_id INTEGER)');
    $pdo->exec("INSERT INTO inventario VALUES (1, 'Cera', 10, 12.50, 1), (2, 'Champú', 1, 8, 1), (3, 'Otra sucursal', 5, 5, 2)");
    $pdo->exec('CREATE TABLE ventas_productos (id INTEGER PRIMARY KEY, cita_id INTEGER, producto_id INTEGER, cantidad INTEGER,
        precio_unitario DECIMAL, sucursal_id INTEGER, usuario_id INTEGER, registrado_por INTEGER,
        vendedor_nombre TEXT, comision_porcentaje DECIMAL, metodo_pago TEXT, cobro_id INTEGER, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE cobros_caja (id INTEGER PRIMARY KEY, cita_id INTEGER UNIQUE, sucursal_id INTEGER, registrado_por INTEGER, metodo_pago TEXT, servicios DECIMAL, productos DECIMAL, propina DECIMAL, total DECIMAL, fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP)');
    return $pdo;
}

function comprobarVenta(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function rechazarVenta(callable $accion): void
{
    try {
        $accion();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('La venta inválida no fue rechazada.');
}

// Ejecutar también la consulta real del resumen, sin cargar configuración de producción.
function isAdminTecnico(): bool { return true; }
function query($sql, $params = []): array
{
    $stmt = $GLOBALS['ventasReportTestPDO']->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function venderFixture(PDO $pdo, array $items, $vendedorId, int $operadorId, ?int $citaId = null, ?int $sucursalId = null): array
{
    return registrarVentaProductos($pdo, $items, $vendedorId, $operadorId, $citaId, $sucursalId, 'efectivo');
}

$pruebas = [
    'Equipo incluye recepción, administración y barberos activos' => function () {
        $equipo = getVendedoresVenta(fixtureVentas());
        comprobarVenta(count($equipo) === 4, 'Debe excluir únicamente al personal inactivo.');
        comprobarVenta(count(array_unique(array_column($equipo, 'rol'))) === 4, 'No debe filtrar por rol.');
    },
    'Venta de recepción se acredita al vendedor y conserva al cajero' => function () {
        $pdo = fixtureVentas();
        $resultado = venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 2]], 2, 1);
        $fila = $pdo->query('SELECT * FROM ventas_productos')->fetch(PDO::FETCH_ASSOC);
        comprobarVenta((int) $fila['usuario_id'] === 2 && (int) $fila['registrado_por'] === 1, 'Vendedor y operador deben ser distintos.');
        comprobarVenta($fila['vendedor_nombre'] === 'Sammy Pérez' && (float) $fila['comision_porcentaje'] === 15.0, 'Debe conservar nombre y comisión.');
        comprobarVenta($resultado['total'] === 25.0, 'El total debe usar precio del inventario.');
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 8, 'Debe descontar el stock.');
        $pdo->exec("UPDATE usuarios SET nombre = 'Otro nombre', comision_productos = 30 WHERE id = 2");
        $fila = $pdo->query('SELECT * FROM ventas_productos')->fetch(PDO::FETCH_ASSOC);
        comprobarVenta($fila['vendedor_nombre'] === 'Sammy Pérez' && (float) $fila['comision_porcentaje'] === 15.0, 'Los cambios del perfil no deben alterar la venta.');
    },
    'Administración y barberos también pueden ser vendedores' => function () {
        foreach ([1, 3, 4] as $id) {
            $pdo = fixtureVentas();
            venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], $id, 1);
            comprobarVenta((int) $pdo->query('SELECT usuario_id FROM ventas_productos')->fetchColumn() === $id, 'La venta debe acreditarse a la selección.');
        }
    },
    'Vendedor obligatorio, válido y activo' => function () {
        foreach ([null, '', 0, -1, 999, 5, '2.5', []] as $id) {
            $pdo = fixtureVentas();
            rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], $id, 1));
            comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 10, 'La validación no debe descontar stock.');
            comprobarVenta((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 0, 'La validación no debe guardar ventas.');
        }
    },
    'Operador inactivo o inexistente no puede registrar ventas' => function () {
        foreach ([5, 999] as $operador) {
            $pdo = fixtureVentas();
            rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], 2, $operador));
            comprobarVenta(!$pdo->inTransaction(), 'Debe cerrar su propia transacción.');
        }
    },
    'Cantidades inválidas y productos inexistentes no modifican inventario' => function () {
        foreach ([0, -1, '1.5', 'texto', null, []] as $cantidad) {
            $pdo = fixtureVentas();
            rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 1, 'cantidad' => $cantidad]], 2, 1));
        }
        $pdo = fixtureVentas();
        rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 999, 'cantidad' => 1]], 2, 1));
        rechazarVenta(fn () => venderFixture($pdo, [], 2, 1));
        rechazarVenta(fn () => venderFixture($pdo, ['fila inválida'], 2, 1));
        comprobarVenta((int) $pdo->query('SELECT SUM(cantidad) FROM inventario')->fetchColumn() === 16, 'El inventario debe permanecer intacto.');
    },
    'Stock insuficiente revierte también los productos procesados previamente' => function () {
        $pdo = fixtureVentas();
        rechazarVenta(fn () => venderFixture($pdo, [
            ['producto_id' => 1, 'cantidad' => 2], ['producto_id' => 2, 'cantidad' => 3]
        ], 2, 1));
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 10, 'Debe revertir el primer descuento.');
        comprobarVenta((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 0, 'No debe quedar una venta parcial.');
    },
    'Productos repetidos se consolidan y verifican contra el stock total' => function () {
        $pdo = fixtureVentas();
        venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 2], ['producto_id' => 1, 'cantidad' => 3]], 2, 1);
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM ventas_productos')->fetchColumn() === 5, 'Debe consolidar cantidades.');
        rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 3], ['producto_id' => 1, 'cantidad' => 3]], 2, 1));
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 5, 'La segunda venta debe rechazarse sin modificar stock.');
    },
    'Vender la última unidad no deja stock negativo' => function () {
        $pdo = fixtureVentas();
        venderFixture($pdo, [['producto_id' => 2, 'cantidad' => 1]], 3, 1);
        rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 2, 'cantidad' => 1]], 2, 1));
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 2')->fetchColumn() === 0, 'El stock mínimo es cero.');
    },
    'Permisos de sucursal dependen del operador y no del vendedor elegido' => function () {
        $pdo = fixtureVentas();
        rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 3, 'cantidad' => 1]], 1, 4));
        $pdo->exec('INSERT INTO usuarios_sucursales VALUES (3, 2)');
        venderFixture($pdo, [['producto_id' => 3, 'cantidad' => 1]], 2, 3);
        comprobarVenta((int) $pdo->query('SELECT sucursal_id FROM ventas_productos')->fetchColumn() === 2, 'Debe respetar la sucursal real del producto y los permisos multisucursal.');
    },
    'Cobro guarda cita y sólo permite productos de su sucursal' => function () {
        $pdo = fixtureVentas();
        venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], 3, 1, 42, 1);
        comprobarVenta((int) $pdo->query('SELECT cita_id FROM ventas_productos')->fetchColumn() === 42, 'Debe vincular la venta a la cita.');
        rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 3, 'cantidad' => 1]], 3, 1, 42, 1));
    },
    'La venta participa en el rollback de un cobro externo' => function () {
        $pdo = fixtureVentas();
        $pdo->beginTransaction();
        venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], 2, 1, 42, 1);
        comprobarVenta($pdo->inTransaction(), 'No debe confirmar la transacción del cobro.');
        $pdo->rollBack();
        comprobarVenta((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 0, 'El rollback del cobro debe eliminar la venta.');
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 10, 'Debe restituir el stock.');
    },
    'Resumen incluye personal administrativo y conserva el cálculo histórico' => function () {
        $pdo = fixtureVentas();
        $GLOBALS['ventasReportTestPDO'] = $pdo;
        venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 2]], 2, 1);
        venderFixture($pdo, [['producto_id' => 2, 'cantidad' => 1]], 3, 1);
        $pdo->exec('INSERT INTO ventas_productos (producto_id, cantidad, precio_unitario, sucursal_id, usuario_id) VALUES (1, 1, 10, 1, 1)');
        $currentUser = ['id' => 1, 'rol' => 'admin'];
        ob_start();
        include __DIR__ . '/../includes/ventas_resumen.php';
        $html = ob_get_clean();
        comprobarVenta(!$errorResumenVentas && count($resumenVentas) === 3, 'Debe incluir a todos los vendedores.');
        $porUsuario = array_column($resumenVentas, null, 'usuario_id');
        comprobarVenta((float) $porUsuario[2]['comision'] === 3.75, 'Recepción debe recibir su comisión del 15%.');
        comprobarVenta((float) $porUsuario[3]['comision'] === 0.4, 'Administración debe recibir su comisión del 5%.');
        comprobarVenta((float) $porUsuario[1]['comision'] === 0.0, 'La atribución histórica del administrador no debe reinterpretarse como comisión nueva.');
        comprobarVenta(str_contains($html, 'Sammy Pérez') && str_contains($html, 'Mishell Ruiz'), 'El resumen debe mostrar nombres completos.');
    },
    'Descuentos y líneas del mismo producto con precios distintos se conservan' => function () {
        $pdo = fixtureVentas();
        $r = venderFixture($pdo, [
            ['producto_id' => 1, 'cantidad' => 2, 'precio_unitario' => '11.00'],
            ['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => '9.50']
        ], 2, 1);
        comprobarVenta($r['total'] === 31.5 && count($r['items']) === 2, 'Debe conservar ambos precios y su total real.');
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 7, 'Debe descontar el total de unidades.');
        comprobarVenta((float) $pdo->query('SELECT SUM(cantidad * precio_unitario) FROM ventas_productos')->fetchColumn() === 31.5, 'El comprobante y la BD deben coincidir.');
    },
    'Precio cero es válido y precios negativos o malformados no consumen stock' => function () {
        $pdo = fixtureVentas();
        $r = venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => '0.00']], 2, 1);
        comprobarVenta($r['total'] === 0.0, 'Un descuento total debe conservar el precio cero.');
        foreach ([-1, 'incorrecto', [], '1e309', '100000000.00'] as $precio) {
            rechazarVenta(fn () => venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => $precio]], 2, 1));
        }
        comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 9, 'Las solicitudes inválidas no deben consumir stock.');
    },
    'Un error de persistencia revierte el descuento de stock' => function () {
        $pdo = fixtureVentas();
        $pdo->exec("CREATE TRIGGER fallo_venta BEFORE INSERT ON ventas_productos BEGIN SELECT RAISE(ABORT, 'Fallo de escritura'); END");
        try {
            venderFixture($pdo, [['producto_id' => 1, 'cantidad' => 1]], 2, 1);
            throw new RuntimeException('La inserción debía fallar.');
        } catch (PDOException $e) {
            comprobarVenta((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 10, 'El error no debe consumir stock.');
        }
    }
];

foreach ($pruebas as $nombre => $prueba) {
    $prueba();
    echo "OK: $nombre\n";
}
echo count($pruebas) . " pruebas completadas.\n";
