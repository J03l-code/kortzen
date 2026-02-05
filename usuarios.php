<?php
require_once 'config.php';
requireLogin();
requirePermission(canViewUsers());
$currentUser = getCurrentUser();

// Obtener usuarios
try {
    $usuarios = query("SELECT u.*, s.nombre as sucursal_nombre 
                      FROM usuarios u 
                      LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                      ORDER BY u.fecha_creacion DESC");
} catch (PDOException $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    $usuarios = [];
}

$pageTitle = 'Usuarios';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Gestión de Usuarios</h1>
    <?php if (canManageUsers()): ?>
        <button onclick="window.location.href='usuarios_crear.php'" class="btn btn-primary">+ AÑADIR USUARIO</button>
    <?php endif; ?>
</div>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #333333, #555555);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: #FFFFFF;
        flex-shrink: 0;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .user-name {
        font-weight: 500;
        color: var(--text-primary);
        font-size: 14px;
    }

    .user-phone {
        font-size: 12px;
        color: var(--text-muted);
    }

    .role-badge {
        padding: 6px 14px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
        background: rgba(51, 51, 51, 0.1);
        color: #333333;
        border: 1px solid rgba(51, 51, 51, 0.2);
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
</style>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>USUARIO</th>
                <th>EMAIL</th>
                <th>ROL</th>
                <th>SUCURSAL</th>
                <th>ACCIONES</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($usuarios) > 0): ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                    // Generar iniciales
                    $nombres = explode(' ', $usuario['nombre']);
                    $iniciales = '';
                    if (count($nombres) >= 2) {
                        $iniciales = strtoupper(substr($nombres[0], 0, 1) . substr($nombres[1], 0, 1));
                    } else {
                        $iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
                    }

                    // Determinar tipo de rol
                    $rol_texto = getRolDisplayName($usuario['rol']);

                    // Generar teléfono ficticio basado en ID
                    $telefono = '+34 ' . (900 + $usuario['id']) . ' ' . str_pad($usuario['id'] * 123, 6, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar"><?php echo $iniciales; ?></div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                    <div class="user-phone"><?php echo $telefono; ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <span class="role-badge"><?php echo $rol_texto; ?></span>
                        </td>
                        <td><?php echo $usuario['sucursal_nombre'] ? htmlspecialchars($usuario['sucursal_nombre']) : 'Todas'; ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <?php if (canManageUsers()): ?>
                                    <a href="usuarios_editar.php?id=<?php echo $usuario['id']; ?>" class="btn-action">EDITAR</a>
                                    <?php if ($usuario['id'] != $currentUser['id']): ?>
                                        <button
                                            onclick="confirmarEliminar(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')"
                                            class="btn-action btn-delete">ELIMINAR</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Solo lectura</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        No hay usuarios registrados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function confirmarEliminar(id, nombre) {
        if (confirm('¿Estás seguro de eliminar el usuario "' + nombre + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/usuarios_action.php';

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