<?php
/**
 * KORTZEN - API Gestión de Stock por Barbero con Débito Automático del Inventario Central
 */
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['rol'] !== 'admin' && $currentUser['rol'] !== 'admin_local') {
    header('Location: ../dashboard.php?error=' . urlencode('Solo los administradores pueden gestionar el stock del barbero.'));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$barbero_id = intval($_POST['barbero_id'] ?? $_GET['barbero_id'] ?? 0);

if ($barbero_id <= 0) {
    header('Location: ../usuarios.php?error=' . urlencode('ID de barbero no válido.'));
    exit;
}

try {
    $pdo = getConnection();

    // Obtener sucursal_id del barbero
    $stmtU = $pdo->prepare("SELECT sucursal_id FROM usuarios WHERE id = ?");
    $stmtU->execute([$barbero_id]);
    $userRow = $stmtU->fetch(PDO::FETCH_ASSOC);
    $sucursal_id = $userRow['sucursal_id'] ?? null;

    switch ($action) {
        case 'guardar_stock':
        case 'agregar_stock':
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = floatval($_POST['cantidad'] ?? 0);
            $unidad = trim($_POST['unidad'] ?? 'unidades');
            $precio = floatval($_POST['precio'] ?? 0.00);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $stock_id = intval($_POST['stock_id'] ?? 0);
            $inventario_central_id = intval($_POST['inventario_central_id'] ?? 0);

            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }
            if ($cantidad < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            // Buscar item coincidente en el inventario central general de la sucursal
            $centralItem = null;
            if ($inventario_central_id > 0) {
                $stmtC = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
                $stmtC->execute([$inventario_central_id]);
                $centralItem = $stmtC->fetch(PDO::FETCH_ASSOC);
            }
            if (!$centralItem && !empty($producto)) {
                $stmtC = $pdo->prepare("SELECT * FROM inventario WHERE (sucursal_id = ? OR sucursal_id IS NULL) AND LOWER(TRIM(producto)) = LOWER(TRIM(?)) ORDER BY sucursal_id DESC LIMIT 1");
                $stmtC->execute([$sucursal_id, $producto]);
                $centralItem = $stmtC->fetch(PDO::FETCH_ASSOC);
            }

            if ($stock_id > 0) {
                // Actualizar item existente del barbero
                $stmtOld = $pdo->prepare("SELECT * FROM inventario_barbero WHERE id = ? AND barbero_id = ?");
                $stmtOld->execute([$stock_id, $barbero_id]);
                $oldItem = $stmtOld->fetch(PDO::FETCH_ASSOC);

                if (!$oldItem) {
                    throw new Exception('Registro de stock no encontrado.');
                }

                $cantAnterior = floatval($oldItem['cantidad']);
                $diferencia = $cantidad - $cantAnterior; // Positivo = se le asigna más, Negativo = se le resta

                // Si hay item central y se requiere más stock, validar disponible
                if ($centralItem && $diferencia > 0) {
                    $cantCentralActual = floatval($centralItem['cantidad']);
                    if ($cantCentralActual < $diferencia) {
                        throw new Exception("Stock insuficiente en el inventario central. Disponible: {$cantCentralActual} {$centralItem['unidad']}, Intentando debitar: {$diferencia}");
                    }
                }

                // Actualizar inventario_barbero
                $stmtUpdB = $pdo->prepare("
                    UPDATE inventario_barbero 
                    SET producto = ?, cantidad = ?, unidad = ?, precio = ?, descripcion = ? 
                    WHERE id = ? AND barbero_id = ?
                ");
                $stmtUpdB->execute([$producto, $cantidad, $unidad, $precio, $descripcion, $stock_id, $barbero_id]);

                // Debitar o reintegrar al inventario central
                if ($centralItem && $diferencia != 0) {
                    $stmtUpdC = $pdo->prepare("UPDATE inventario SET cantidad = GREATEST(0, cantidad - ?) WHERE id = ?");
                    $stmtUpdC->execute([$diferencia, $centralItem['id']]);
                }

                $msg = 'Stock del barbero actualizado' . ($centralItem ? ' y debitado del inventario central.' : '.');

            } else {
                // Nuevo producto para el barbero
                if ($centralItem) {
                    $cantCentralActual = floatval($centralItem['cantidad']);
                    if ($cantCentralActual < $cantidad) {
                        throw new Exception("Stock insuficiente en el inventario central. Disponible: {$cantCentralActual} {$centralItem['unidad']}, solicitado: {$cantidad}");
                    }
                }

                // Verificar si ya existe en el stock personal de este barbero
                $stmtCheck = $pdo->prepare("SELECT id, cantidad FROM inventario_barbero WHERE barbero_id = ? AND LOWER(TRIM(producto)) = LOWER(TRIM(?))");
                $stmtCheck->execute([$barbero_id, $producto]);
                $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $nuevaCant = floatval($existing['cantidad']) + $cantidad;
                    $stmtUpd = $pdo->prepare("UPDATE inventario_barbero SET cantidad = ?, unidad = ?, precio = ?, descripcion = ? WHERE id = ?");
                    $stmtUpd->execute([$nuevaCant, $unidad, $precio, $descripcion, $existing['id']]);
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO inventario_barbero (barbero_id, sucursal_id, producto, cantidad, unidad, precio, descripcion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$barbero_id, $sucursal_id, $producto, $cantidad, $unidad, $precio, $descripcion]);
                }

                // Debitar de inventario central
                if ($centralItem) {
                    $stmtUpdC = $pdo->prepare("UPDATE inventario SET cantidad = GREATEST(0, cantidad - ?) WHERE id = ?");
                    $stmtUpdC->execute([$cantidad, $centralItem['id']]);
                    $msg = "Se asignaron {$cantidad} {$unidad} al barbero y se debitaron del inventario central.";
                } else {
                    $msg = "Producto asignado al stock del barbero exitosamente.";
                }
            }

            header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&success=' . urlencode($msg));
            exit;

        case 'eliminar_stock':
            $stock_id = intval($_POST['stock_id'] ?? $_GET['stock_id'] ?? 0);
            if ($stock_id <= 0) {
                throw new Exception('Item de stock no válido.');
            }

            // Obtener datos del item antes de eliminar
            $stmtItem = $pdo->prepare("SELECT * FROM inventario_barbero WHERE id = ? AND barbero_id = ?");
            $stmtItem->execute([$stock_id, $barbero_id]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $cantDevolver = floatval($item['cantidad']);
                $prodNombre = $item['producto'];

                // Eliminar del barbero
                $stmtDel = $pdo->prepare("DELETE FROM inventario_barbero WHERE id = ? AND barbero_id = ?");
                $stmtDel->execute([$stock_id, $barbero_id]);

                // Reintegrar al inventario central si existe el producto
                $stmtC = $pdo->prepare("SELECT id FROM inventario WHERE (sucursal_id = ? OR sucursal_id IS NULL) AND LOWER(TRIM(producto)) = LOWER(TRIM(?)) ORDER BY sucursal_id DESC LIMIT 1");
                $stmtC->execute([$item['sucursal_id'], $prodNombre]);
                $centralId = $stmtC->fetchColumn();

                if ($centralId) {
                    $stmtReintegrar = $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?");
                    $stmtReintegrar->execute([$cantDevolver, $centralId]);
                    $msg = "Producto retirado del barbero y {$cantDevolver} unidades fueron reintegradas al inventario central.";
                } else {
                    $msg = "Producto retirado del stock del barbero.";
                }
            } else {
                $msg = "Producto eliminado.";
            }

            header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&success=' . urlencode($msg));
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Exception $e) {
    header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
