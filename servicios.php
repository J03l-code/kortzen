<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Si es barbero, redirigir al dashboard (no tiene acceso a gestión de servicios)
// Si es barbero, tiene acceso pero solo lectura
$isReadOnly = isBarbero();

// Obtener servicios
try {
    $servicios = query("SELECT * FROM servicios ORDER BY activo DESC, nombre ASC");
} catch (PDOException $e) {
    error_log("Error al obtener servicios: " . $e->getMessage());
    $servicios = [];
}

$pageTitle = 'Servicios';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Catálogo de Servicios</h1>
    <?php if (!$isReadOnly): ?>
        <button onclick="window.location.href='servicios_crear.php'" class="btn btn-primary">+ AÑADIR SERVICIO</button>
    <?php endif; ?>
</div>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-active {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    .status-inactive {
        background: rgba(142, 142, 147, 0.12);
        color: #8E8E93;
        border: 1px solid rgba(142, 142, 147, 0.3);
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
        color: #FFFFFF;
    }

    .btn-delete {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .btn-delete:hover {
        background: #E74C3C;
        color: #FFFFFF;
    }

    .actions-cell {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .table th {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .table td {
        vertical-align: middle;
    }

    .service-description {
        color: var(--text-muted);
        font-size: 13px;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>SERVICIO</th>
                <th>CATEGORÍA</th>
                <th>PRECIO</th>
                <th>DURACIÓN</th>
                <th>ESTADO</th>
                <?php if (!$isReadOnly): ?>
                    <th>ACCIONES</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($servicios) > 0): ?>
                <?php foreach ($servicios as $servicio): ?>
                    <tr>
                        <td>
                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($servicio['nombre']); ?>
                                </strong>
                                <?php if ($servicio['descripcion']): ?>
                                    <div class="service-description">
                                        <?php echo htmlspecialchars($servicio['descripcion']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        </td>
                        <td>
                            <span class="status-badge"
                                style="background: rgba(51, 51, 51, 0.1); color: #333333; border: 1px solid rgba(51, 51, 51, 0.3);">
                                <?php echo htmlspecialchars($servicio['categoria'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td>$
                            <?php echo number_format($servicio['precio'], 2); ?>
                        </td>
                        <td>
                            <?php echo $servicio['duracion_minutos']; ?> min
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $servicio['activo'] ? 'active' : 'inactive'; ?>">
                                <?php echo $servicio['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                            </span>
                        </td>
                        <?php if (!$isReadOnly): ?>
                            <td>
                                <div class="actions-cell">
                                    <a href="servicios_editar.php?id=<?php echo $servicio['id']; ?>" class="btn-action">EDITAR</a>
                                    <button
                                        onclick="confirmarEliminar(<?php echo $servicio['id']; ?>, '<?php echo htmlspecialchars($servicio['nombre']); ?>')"
                                        class="btn-action btn-delete">ELIMINAR</button>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay servicios registrados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function confirmarEliminar(id, nombre) {
        if (confirm('¿Estás seguro de eliminar el servicio "' + nombre + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/servicios_action.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>