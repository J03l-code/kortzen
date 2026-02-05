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
            cl.telefono as cliente_telefono,
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
                        <td>
                            <div><?php echo htmlspecialchars($cita['cliente_nombre']); ?></div>
                            <?php if (($currentUser['rol'] === 'admin' || $currentUser['rol'] === 'admin_local') && !empty($cita['cliente_telefono'])):
                                $raw_phone = preg_replace('/[^0-9]/', '', $cita['cliente_telefono']);
                                $wa_msg = urlencode("Hola " . explode(' ', $cita['cliente_nombre'])[0] . ", te escribo de Kortzen sobre tu cita.");
                                ?>
                                <div style="font-size: 11px; margin-top: 4px; display: flex; gap: 8px; align-items: center;">
                                    <span style="color:#888;"><?php echo htmlspecialchars($cita['cliente_telefono']); ?></span>

                                    <!-- WA Button -->
                                    <a href="https://wa.me/<?php echo $raw_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank"
                                        title="WhatsApp" style="color: #25D366; text-decoration: none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="currentColor">
                                            <path
                                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                        </svg>
                                    </a>

                                    <!-- Call Button -->
                                    <a href="tel:<?php echo $raw_phone; ?>" title="Llamar"
                                        style="color: var(--primary-gold); text-decoration: none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path
                                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                            </path>
                                        </svg>
                                    </a>
                                </div>
                            <?php elseif ($currentUser['rol'] === 'barbero' && !empty($cita['cliente_telefono'])): ?>
                                <div style="font-size: 11px; margin-top: 4px; color:#888;">
                                    <?php echo htmlspecialchars($cita['cliente_telefono']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
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