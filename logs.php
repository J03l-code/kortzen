<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Filtros
$tabla_filter = $_GET['tabla'] ?? '';
$usuario_filter = $_GET['usuario_id'] ?? '';

// Obtener logs
try {
    $pdo = getConnection();

    // Asegurar estructura de la tabla logs_actividad
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs_actividad (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            cliente_id INT NULL,
            accion VARCHAR(50) NOT NULL,
            tabla_afectada VARCHAR(50) NOT NULL,
            registro_id INT NULL,
            descripcion TEXT NOT NULL,
            ip_address VARCHAR(45) NULL,
            fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try { $pdo->exec("ALTER TABLE logs_actividad ADD COLUMN cliente_id INT NULL AFTER usuario_id"); } catch (Exception $e1) {}
    try { $pdo->exec("ALTER TABLE logs_actividad ADD COLUMN ip_address VARCHAR(45) NULL AFTER descripcion"); } catch (Exception $e2) {}

    $sql = "SELECT l.*, u.nombre as usuario_nombre, c.nombre as cliente_nombre 
            FROM logs_actividad l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN clientes c ON l.cliente_id = c.id
            WHERE 1=1";

    $params = [];

    if ($tabla_filter) {
        $sql .= " AND l.tabla_afectada = ?";
        $params[] = $tabla_filter;
    }

    if ($usuario_filter) {
        $sql .= " AND (l.usuario_id = ? OR l.cliente_id = ?)";
        $params[] = $usuario_filter;
        $params[] = $usuario_filter;
    }

    $sql .= " ORDER BY l.fecha_hora DESC LIMIT 300";

    $logs = query($sql, $params);

    // Obtener usuarios y clientes para filtro
    $usuarios = query("SELECT id, nombre FROM usuarios ORDER BY nombre ASC");
    $tablasDisponibles = query("SELECT DISTINCT tabla_afectada FROM logs_actividad WHERE tabla_afectada IS NOT NULL AND tabla_afectada != '' ORDER BY tabla_afectada ASC");

} catch (Exception $e) {
    error_log("Error al obtener logs: " . $e->getMessage());
    $logs = [];
    $usuarios = [];
    $tablasDisponibles = [];
}

$pageTitle = 'Logs';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Registro de Actividad del Sistema</h1>
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <select name="tabla" class="filter-select" onchange="this.form.submit()">
            <option value="">Todas las áreas / tablas</option>
            <?php foreach ($tablasDisponibles as $t): ?>
                <option value="<?php echo htmlspecialchars($t['tabla_afectada']); ?>" <?php echo $tabla_filter == $t['tabla_afectada'] ? 'selected' : ''; ?>>
                    <?php echo ucfirst(htmlspecialchars($t['tabla_afectada'])); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="usuario_id" class="filter-select" onchange="this.form.submit()">
            <option value="">Todos los usuarios</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filter == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($tabla_filter || $usuario_filter): ?>
            <a href="logs.php" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .filter-select {
        padding: 10px 16px;
        background: rgba(15, 15, 15, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 14px;
        cursor: pointer;
        min-width: 180px;
    }

    .filter-select:focus {
        outline: none;
        border-color: rgba(201, 169, 110, 0.5);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .action-badge {
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-create {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
    }

    .action-update {
        background: rgba(59, 130, 246, 0.12);
        color: #3B82F6;
    }

    .action-delete {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>FECHA/HORA</th>
                <th>USUARIO</th>
                <th>ACCIÓN</th>
                <th>TABLA</th>
                <th>DESCRIPCIÓN</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong>
                                <?php echo date('d/m/Y', strtotime($log['fecha_hora'])); ?>
                            </strong><br>
                            <span style="color: var(--text-muted); font-size: 12px;">
                                <?php echo date('H:i:s', strtotime($log['fecha_hora'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                if (!empty($log['usuario_nombre'])) {
                                    echo '<strong>' . htmlspecialchars($log['usuario_nombre']) . '</strong> <span style="font-size:0.75rem; color:#888;">(Personal)</span>';
                                } elseif (!empty($log['cliente_nombre'])) {
                                    echo '<strong>' . htmlspecialchars($log['cliente_nombre']) . '</strong> <span style="font-size:0.75rem; color:#10B981;">(Cliente PWA)</span>';
                                } elseif (!empty($log['usuario_id'])) {
                                    echo '<strong>Usuario #' . intval($log['usuario_id']) . '</strong> <span style="font-size:0.75rem; color:#888;">(Personal)</span>';
                                } elseif (!empty($log['cliente_id'])) {
                                    echo '<strong>Cliente #' . intval($log['cliente_id']) . '</strong> <span style="font-size:0.75rem; color:#10B981;">(Cliente PWA)</span>';
                                } else {
                                    echo '<span style="color:#C0A062; font-weight:700;">Sistema / Administrador</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <span class="action-badge action-<?php echo strtolower($log['accion']); ?>">
                                <?php echo htmlspecialchars($log['accion']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['tabla_afectada']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['descripcion']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay registros de actividad
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
