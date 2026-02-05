<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

$servicio = null;
$isEdit = false;

// Si hay ID, cargar servicio para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM servicios WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $servicio = $result[0];
        } else {
            header('Location: servicios.php?error=Servicio no encontrado');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: servicios.php?error=Error al cargar servicio');
        exit;
    }
}

$pageTitle = $isEdit ? 'Editar Servicio' : 'Nuevo Servicio';
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
    .form-select,
    .form-textarea {
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

    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
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
        <?php echo $isEdit ? htmlspecialchars($servicio['nombre']) : 'Nuevo Servicio'; ?>
    </h1>

    <form method="POST" action="api/servicios_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $servicio['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Nombre del Servicio</label>
            <input type="text" name="nombre" class="form-input"
                value="<?php echo $isEdit ? htmlspecialchars($servicio['nombre']) : ''; ?>" placeholder="Corte Clásico"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-textarea"
                placeholder="Corte tradicional con máquina y tijera, incluye lavado"><?php echo $isEdit ? htmlspecialchars($servicio['descripcion']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Precio ($)</label>
            <input type="number" name="precio" class="form-input"
                value="<?php echo $isEdit ? $servicio['precio'] : ''; ?>" placeholder="15.00" min="0" step="0.01"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Duración (minutos)</label>
            <input type="number" name="duracion_minutos" class="form-input"
                value="<?php echo $isEdit ? $servicio['duracion_minutos'] : '30'; ?>" placeholder="30" min="5" step="5"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-select" required>
                <?php
                $cats = ['Corte', 'Barba', 'Afeitado', 'Spa', 'Otros'];
                $currentCat = $isEdit ? ($servicio['categoria'] ?? 'General') : 'Corte';
                foreach ($cats as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo ($currentCat == $cat) ? 'selected' : ''; ?>>
                        <?php echo $cat; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="activo" class="form-select" required>
                <option value="1" <?php echo ($isEdit && $servicio['activo']) ? 'selected' : ''; ?>>Activo</option>
                <option value="0" <?php echo ($isEdit && !$servicio['activo']) ? 'selected' : ''; ?>>Inactivo</option>
            </select>
        </div>

        <div class="form-actions">
            <a href="servicios.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>