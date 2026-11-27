<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!isLoggedIn()) throw new InvalidArgumentException('Inicia sesión.');
    $pdo = getConnection();
    $usuario = getOperadorVenta($pdo, (int) $_SESSION['user_id']);
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    [$desde, $hasta] = rangoReporte(['desde' => $fecha, 'hasta' => $fecha]);
    $params = [$desde, $hasta];
    $filtro = alcanceReporte($usuario, 'c', $params, $_GET['sucursal_id'] ?? null);
    if (!esAdministradorCaja($usuario)) {
        $filtro .= ' AND c.barbero_id = ? AND c.reasignacion_pendiente = 0';
        $params[] = $usuario['id'];
    }
    $base = "SELECT c.id, c.fecha_hora, c.estado, c.sucursal_id, c.barbero_id, c.reasignacion_pendiente,
        c.motivo_reasignacion, COALESCE(cl.nombre,'Cliente') AS cliente,
        COALESCE(s.nombre,'Servicio') AS servicio, COALESCE(s.duracion_minutos,45) AS duracion,
        u.nombre AS barbero_original, su.nombre AS sucursal
        FROM citas c LEFT JOIN clientes cl ON cl.id=c.cliente_id
        LEFT JOIN servicios s ON s.id=c.servicio_id LEFT JOIN usuarios u ON u.id=c.barbero_id
        LEFT JOIN sucursales su ON su.id=c.sucursal_id";
    $stmt = $pdo->prepare($base . ' WHERE c.fecha_hora >= ? AND c.fecha_hora < ?' . $filtro . ' ORDER BY c.fecha_hora,c.id');
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $paramsBarberos = [];
    $filtroBarberos = '';
    $sucursalFiltro = isset($_GET['sucursal_id']) ? (int) $_GET['sucursal_id'] : 0;
    if (!in_array($usuario['rol'], ['admin','administrador','superadmin'], true)) {
        $ids = getUsuarioSucursalesIds($usuario['id']) ?: [0];
        if ($sucursalFiltro > 0 && in_array($sucursalFiltro, $ids, true)) {
            $ids = [$sucursalFiltro];
        }
        $in = implode(',', array_fill(0,count($ids),'?'));
        $filtroBarberos = " AND (u.sucursal_id IN ($in) OR EXISTS (SELECT 1 FROM usuarios_sucursales us WHERE us.usuario_id=u.id AND us.sucursal_id IN ($in)))";
        $paramsBarberos = array_merge($ids,$ids);
    } elseif ($sucursalFiltro > 0) {
        $filtroBarberos = " AND (u.sucursal_id = ? OR EXISTS (SELECT 1 FROM usuarios_sucursales us WHERE us.usuario_id=u.id AND us.sucursal_id = ?))";
        $paramsBarberos = [$sucursalFiltro, $sucursalFiltro];
    }
    if (!esAdministradorCaja($usuario)) { $filtroBarberos .= ' AND u.id = ?'; $paramsBarberos[] = $usuario['id']; }
    $stmt = $pdo->prepare("SELECT u.id,u.nombre FROM usuarios u WHERE u.activo=1 AND u.rol='barbero'" . $filtroBarberos . ' ORDER BY u.nombre');
    $stmt->execute($paramsBarberos);
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pendientes = [];
    if (esAdministradorCaja($usuario)) {
        $paramsPendientes = [date('Y-m-d H:i:s')];
        $filtroPendientes = alcanceReporte($usuario, 'c', $paramsPendientes, $_GET['sucursal_id'] ?? null);
        $stmt = $pdo->prepare($base . " WHERE c.reasignacion_pendiente=1 AND c.fecha_hora >= ? AND c.estado IN ('pendiente','confirmada')" . $filtroPendientes . ' ORDER BY c.fecha_hora,c.id');
        $stmt->execute($paramsPendientes);
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success'=>true, 'puede_administrar'=>esAdministradorCaja($usuario), 'eventos'=>$eventos, 'barberos'=>$barberos, 'pendientes'=>$pendientes]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Calendario: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo cargar el calendario.']);
}
