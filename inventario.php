<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

if ($currentUser['rol'] === 'barbero') {
    header('Location: dashboard.php');
    exit;
}

// Obtener inventario
try {
    $inventario = query("SELECT i.*, s.nombre as sucursal_nombre 
                        FROM inventario i 
                        LEFT JOIN sucursales s ON i.sucursal_id = s.id 
                        ORDER BY i.fecha_creacion DESC");
} catch (PDOException $e) {
    error_log("Error al obtener inventario: " . $e->getMessage());
    $inventario = [];
}

$pageTitle = 'Inventario';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Inventario Global</h1>
    <button onclick="window.location.href='inventario_crear.php'" class="btn btn-primary">+ AÑADIR PRODUCTO</button>
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

    .status-ok {
        background: rgba(46, 204, 113, 0.12);
        color: #2ECC71;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    .status-low {
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

    .category-tag {
        color: var(--text-muted);
        font-size: 13px;
    }
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>PRODUCTO</th>
                <th>CATEGORÍA</th>
                <th>STOCK</th>
                <th>MÍNIMO</th>
                <th>ESTADO</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($inventario) > 0): ?>
                <?php foreach ($inventario as $item): ?>
                    <?php
                    $minimo = $item['stock_minimo']; // Usar valor de BD
                    $estado = $item['cantidad'] >= $minimo ? 'ok' : 'low';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['producto']); ?></td>
                        <td><span class="category-tag">producto</span></td>
                        <td><?php echo $item['cantidad']; ?> unidades</td>
                        <td><?php echo $minimo; ?> unidades</td>
                        <td>
                            <span class="status-badge status-<?php echo $estado; ?>">
                                <?php echo $estado == 'ok' ? 'OK' : 'STOCK BAJO'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="inventario_editar.php?id=<?php echo $item['id']; ?>" class="btn-action">EDITAR</a>
                                <button onclick="confirmarEliminar(<?php echo $item['id']; ?>)"
                                    class="btn-action btn-delete">ELIMINAR</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay productos en el inventario
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function confirmarEliminar(id) {
        if (confirm('¿Estás seguro de eliminar este producto?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/inventario_action.php';

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