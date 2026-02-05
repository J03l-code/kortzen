<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Filtros
$estado = $_GET['estado'] ?? '';
$fecha = $_GET['fecha'] ?? '';

// Obtener inventario para el modal de terminar cita (de todas las sucursales)
$inventario = [];
try {
    $inventario = query("SELECT i.id, i.producto, i.cantidad, i.unidad, s.nombre as sucursal 
                         FROM inventario i 
                         JOIN sucursales s ON i.sucursal_id = s.id 
                         WHERE i.cantidad > 0 
                         ORDER BY i.producto ASC, s.nombre ASC");
} catch (Exception $e) {
    // Silencioso
}

// Obtener citas
try {
    $sql = "SELECT c.*, 
            cl.nombre as cliente_nombre,
            s.nombre as servicio_nombre,
            u.nombre as barbero_nombre,
            su.nombre as sucursal_nombre
            FROM citas c
            INNER JOIN clientes cl ON c.cliente_id = cl.id
            INNER JOIN servicios s ON c.servicio_id = s.id
            INNER JOIN usuarios u ON c.barbero_id = u.id
            INNER JOIN sucursales su ON c.sucursal_id = su.id
            WHERE 1=1";

    $params = [];

    // SI ES BARBERO: Solo ver sus citas
    if ($currentUser['rol'] === 'barbero') {
        $sql .= " AND c.barbero_id = ?";
        $params[] = $currentUser['id'];
    }

    if ($estado) {
        $sql .= " AND c.estado = ?";
        $params[] = $estado;
    }

    if ($fecha) {
        $sql .= " AND DATE(c.fecha_hora) = ?";
        $params[] = $fecha;
    }

    $sql .= " ORDER BY c.fecha_hora DESC";

    $citas = query($sql, $params);
} catch (PDOException $e) {
    error_log("Error al obtener citas: " . $e->getMessage());
    $citas = [];
}

$pageTitle = 'Citas';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <?php echo $currentUser['rol'] === 'barbero' ? 'Mis Citas' : 'Gestión de Citas'; ?>
    </h1>
    <div style="display: flex; gap: 12px;">
        <form method="GET" style="display: flex; gap: 8px;">
            <select name="estado" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="confirmada" <?php echo $estado == 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                <option value="completada" <?php echo $estado == 'completada' ? 'selected' : ''; ?>>Completada</option>
                <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
            </select>
            <input type="date" name="fecha" class="filter-date" value="<?php echo htmlspecialchars($fecha); ?>"
                onchange="this.form.submit()">
            <?php if ($estado || $fecha): ?>
                <a href="citas.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if ($currentUser['rol'] === 'admin'): ?>
            <button onclick="window.location.href='citas_crear.php'" class="btn btn-primary">+ AÑADIR CITA</button>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Estilos existentes + Modal styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .filter-select,
    .filter-date {
        padding: 10px 16px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 14px;
        cursor: pointer;
    }

    .filter-select:focus,
    .filter-date:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: transparent;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 6px;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-secondary:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .status-pendiente {
        background: rgba(255, 184, 0, 0.12);
        color: #FFB800;
        border: 1px solid rgba(255, 184, 0, 0.3);
    }

    .status-confirmada {
        background: rgba(59, 130, 246, 0.12);
        color: #3B82F6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .status-completada {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    .status-cancelada {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
        border: 1px solid rgba(231, 76, 60, 0.3);
    }

    .btn-action {
        padding: 8px 18px;
        background: transparent;
        border: 1px solid #333333;
        border-radius: 4px;
        color: #333333;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-action:hover {
        background: #333333;
        color: #1A1A1A;
    }

    .btn-delete {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .btn-delete:hover {
        background: #E74C3C;
        color: #FFFFFF;
    }

    .btn-complete {
        border-color: #2ECC71;
        color: #2ECC71;
    }

    .btn-complete:hover {
        background: #2ECC71;
        color: #000;
    }

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: #FFFFFF;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        width: 500px;
        max-width: 90%;
    }

    .material-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>FECHA/HORA</th>
                <th>CLIENTE</th>
                <th>SERVICIO</th>
                <?php if ($currentUser['rol'] === 'admin'): ?>
                    <th>BARBERO</th><?php endif; ?>
                <th>ESTADO</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($citas) > 0): ?>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('d/m/Y', strtotime($cita['fecha_hora'])); ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 13px;">
                                <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($cita['cliente_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($cita['servicio_nombre']); ?></td>

                        <?php if ($currentUser['rol'] === 'admin'): ?>
                            <td><?php echo htmlspecialchars($cita['barbero_nombre']); ?></td>
                        <?php endif; ?>

                        <td>
                            <span class="status-badge status-<?php echo $cita['estado']; ?>">
                                <?php echo strtoupper($cita['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php if ($currentUser['rol'] === 'admin'): ?>
                                    <!-- ACCIONES ADMIN -->
                                    <a href="citas_editar.php?id=<?php echo $cita['id']; ?>" class="btn-action">EDITAR</a>
                                    <button onclick="confirmarEliminar(<?php echo $cita['id']; ?>)"
                                        class="btn-action btn-delete">ELIMINAR</button>
                                <?php else: ?>
                                    <!-- ACCIONES BARBERO -->
                                    <?php if ($cita['estado'] !== 'completada' && $cita['estado'] !== 'cancelada'): ?>
                                        <button onclick="abrirModalTerminar(<?php echo $cita['id']; ?>)"
                                            class="btn-action btn-complete">TERMINAR</button>
                                        <button onclick="confirmarCancelar(<?php echo $cita['id']; ?>)"
                                            class="btn-action btn-delete">CANCELAR</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay citas registradas
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Terminar Cita -->
<div id="modalTerminar" class="modal-overlay">
    <div class="modal-content">
        <h2 style="margin-bottom: 20px; color: #333333;">Terminar Cita</h2>
        <p style="margin-bottom: 20px; color: #666;">Selecciona los materiales consumidos:</p>

        <form id="formTerminar" method="POST" action="api/citas_action.php">
            <input type="hidden" name="action" value="completar">
            <input type="hidden" name="id" id="citaIdTerminar">

            <div id="materialesList">
                <div class="material-row">
                    <select name="materiales[]" class="filter-select" style="flex: 1;">
                        <option value="">Seleccionar material...</option>
                        <?php foreach ($inventario as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']) . ' - ' . htmlspecialchars($item['sucursal']) . ' (' . $item['unidad'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cantidades[]" placeholder="Cant." class="filter-select"
                        style="width: 80px;" min="1" step="0.1">
                </div>
            </div>

            <button type="button" onclick="agregarFilaMaterial()" class="btn-secondary"
                style="margin-top: 10px; width: 100%;">+ Agregar otro material</button>

            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="button" onclick="cerrarModal()" class="btn-secondary" style="flex: 1;">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex: 1;">Confirmar y Terminar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmarEliminar(id) {
        if (confirm('¿Estás seguro de eliminar esta cita de la base de datos?')) {
            enviarAccion(id, 'delete');
        }
    }

    function confirmarCancelar(id) {
        if (confirm('¿Deseas cancelar esta cita?')) {
            enviarAccion(id, 'cancelar_barbero');
        }
    }

    function enviarAccion(id, action) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/citas_action.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }

    // Modal Logic
    function abrirModalTerminar(id) {
        document.getElementById('citaIdTerminar').value = id;
        document.getElementById('modalTerminar').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('modalTerminar').style.display = 'none';
    }

    function agregarFilaMaterial() {
        const div = document.createElement('div');
        div.className = 'material-row';
        div.innerHTML = `
            <select name="materiales[]" class="filter-select" style="flex: 1;">
                <option value="">Seleccionar material...</option>
                <?php foreach ($inventario as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo htmlspecialchars($item['producto']) . ' - ' . htmlspecialchars($item['sucursal']) . ' (' . $item['unidad'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="cantidades[]" placeholder="Cant." class="filter-select" style="width: 80px;" min="1" step="0.1">
            <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #E74C3C; cursor: pointer;">✕</button>
        `;
        document.getElementById('materialesList').appendChild(div);
    }
</script>

<?php include 'includes/footer.php'; ?>