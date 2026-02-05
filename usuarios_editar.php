<?php
require_once 'config.php';
requireLogin();
requirePermission(canManageUsers());
$currentUser = getCurrentUser();

$usuario = null;
$isEdit = false;

// Si hay ID, cargar usuario para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM usuarios WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $usuario = $result[0];
        } else {
            header('Location: usuarios.php?error=Usuario no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: usuarios.php?error=Error al cargar usuario');
        exit;
    }
}

// Obtener sucursales
try {
    $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
} catch (PDOException $e) {
    $sucursales = [];
}

$pageTitle = $isEdit ? 'Editar Usuario' : 'Nuevo Usuario';
include 'includes/header.php';
?>

<style>
    .form-modal {
        max-width: 520px;
        margin: 40px auto;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .form-title {
        font-size: 22px;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 32px;
        text-align: left;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-input,
    .form-select {
        width: 100%;
        padding: 14px 16px;
        background: #FFFFFF;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 15px;
        font-family: var(--font-body);
        transition: all 0.3s ease;
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: rgba(51, 51, 51, 0.5);
        background: #FFFFFF;
    }

    .form-select {
        cursor: pointer;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 32px;
        justify-content: flex-end;
    }

    .btn-cancel {
        padding: 12px 28px;
        background: transparent;
        border: 1px solid rgba(0, 0, 0, 0.2);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-cancel:hover {
        background: rgba(0, 0, 0, 0.05);
        border-color: rgba(0, 0, 0, 0.3);
    }

    .btn-confirm {
        padding: 12px 28px;
        background: linear-gradient(135deg, #333333, #555555);
        border: none;
        border-radius: 6px;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-confirm:hover {
        background: linear-gradient(135deg, #444444, #333333);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(51, 51, 51, 0.3);
    }
</style>

<div class="form-modal">
    <h1 class="form-title">
        <?php echo $isEdit ? htmlspecialchars($usuario['nombre']) : 'Nuevo Usuario'; ?>
    </h1>

    <form method="POST" action="api/usuarios_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre Completo</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($usuario['nombre']) : ''; ?>" placeholder="Carlos Méndez"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($usuario['email']) : ''; ?>"
                placeholder="barbero@kortzen.com" required>
        </div>

        <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" name="telefono" class="form-input"
                value="<?php echo $isEdit && isset($usuario['telefono']) ? htmlspecialchars($usuario['telefono']) : ''; ?>"
                placeholder="+34 612 345 678">
        </div>

        <div class="form-group">
            <label class="form-label">Biografía</label>
            <textarea name="biografia" class="form-input" rows="4" placeholder="Breve descripción del barbero..."><?php echo $isEdit && isset($usuario['biografia']) ? htmlspecialchars($usuario['biografia']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Especialidades</label>
            <input type="text" name="especialidades" class="form-input"
                value="<?php echo $isEdit && isset($usuario['especialidades']) ? htmlspecialchars($usuario['especialidades']) : ''; ?>"
                placeholder="Corte Clásico, Barba, Fade...">
            <small style="color: var(--text-muted); font-size: 0.8em;">Separadas por comas</small>
        </div>

        <div class="form-group">
            <label class="form-label">URL Foto Perfil</label>
            <input type="text" name="foto_url" class="form-input"
                value="<?php echo $isEdit && isset($usuario['foto_url']) ? htmlspecialchars($usuario['foto_url']) : ''; ?>"
                placeholder="/assets/images/barbers/barber1.jpg">
        </div>

        <div class="form-group">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-input" 
                placeholder="<?php echo $isEdit ? 'Dejar en blanco para mantener la actual' : '••••••••'; ?>" 
                <?php echo !$isEdit ? 'required' : ''; ?>>
            <?php if ($isEdit): ?>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 5px; display: block;">
                    * Solo escribe aquí si deseas cambiar la contraseña del usuario.
                </small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-select" required>
                <option value="barbero" <?php echo ($isEdit && $usuario['rol'] == 'barbero') ? 'selected' : ''; ?>>Barbero
                </option>
                <option value="admin_local" <?php echo ($isEdit && $usuario['rol'] == 'admin_local') ? 'selected' : ''; ?>>Admin Locales</option>
                <option value="admin" <?php echo ($isEdit && $usuario['rol'] == 'admin') ? 'selected' : ''; ?>>Admin
                    Técnico</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Comisión (%)</label>
            <input type="number" name="comision_porcentaje" class="form-input" 
                   value="<?php echo $isEdit && isset($usuario['comision_porcentaje']) ? $usuario['comision_porcentaje'] : '50.00'; ?>" 
                   min="0" max="100" step="0.01" placeholder="Ej. 50">
            <small style="color: var(--text-muted); font-size: 0.8em;">Porcentaje que gana el barbero por servicio</small>
        </div>

        <div class="form-group">
            <label class="form-label">Sucursal</label>
            <select name="sucursal_id" class="form-select">
                <option value="">Todas las sucursales</option>
                <?php foreach ($sucursales as $suc): ?>
                    <option value="<?php echo $suc['id']; ?>" <?php echo ($isEdit && $usuario['sucursal_id'] == $suc['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($suc['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <a href="usuarios.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>