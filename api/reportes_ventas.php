<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exportar_tablas.php';
try {
    if (!isLoggedIn()) throw new InvalidArgumentException('Inicia sesión para consultar el reporte.');
    $usuario = getOperadorVenta(getConnection(), (int) $_SESSION['user_id']);
    if (($_GET['accion'] ?? '') === 'vendedores') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode(['success' => true, 'vendedores' => vendedoresHistorial(getConnection(), $usuario),
            'solo_propias' => !esAdministradorCaja($usuario)]);
        exit;
    }
    $formato = $_GET['formato'] ?? 'json';
    $reporte = historialVentas(getConnection(), $usuario, $_GET, in_array($formato, ['xlsx','pdf'], true));
    if ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'reporte' => $reporte]);
        exit;
    }
    if (!in_array($formato, ['xlsx','pdf'], true)) throw new InvalidArgumentException('Formato no válido.');
    $columnas = ['Fecha / Hora', 'Producto', 'Cantidad', 'Total cobrado', 'Vendedor', 'Sucursal', 'Método de pago'];
    $filas = array_map(fn ($r) => [$r['fecha'], $r['producto'], (int) $r['cantidad'], (float) $r['total'], $r['vendedor'], $r['sucursal'], $r['metodo_pago_label']], $reporte['filas']);
    $filas[] = ['TOTAL', '', '', $reporte['total'], '', '', ''];
    if ($formato === 'pdf') { foreach ($filas as &$filaPdf) $filaPdf[3] = '$' . number_format((float) $filaPdf[3],2); unset($filaPdf); }
    $bytes = $formato === 'xlsx' ? tablaXlsx($columnas, $filas) : tablaPdf('Ventas ' . $reporte['desde'] . ' al ' . $reporte['hasta'], $columnas, $filas);
    header('Content-Type: ' . ($formato === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf'));
    header('Content-Disposition: attachment; filename="kortzen-ventas-' . $reporte['desde'] . '.' . $formato . '"');
    header('Cache-Control: private, no-store');
    echo $bytes;
} catch (InvalidArgumentException $e) {
    http_response_code(400); header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Reporte ventas: ' . $e->getMessage());
    http_response_code(500); header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el reporte. Revisa la configuración del servidor.']);
}
