<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

$cita = null;
$isEdit = false;

// Si hay ID, cargar cita para editar
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = intval($_GET['id']);
    try {
        $result = query("SELECT * FROM citas WHERE id = ?", [$id]);
        if (count($result) > 0) {
            $cita = $result[0];
        } else {
            header('Location: citas.php?error=Cita no encontrada');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header('Location: citas.php?error=Error al cargar cita');
        exit;
    }
}

// Obtener datos para los selects
try {
    $clientes = query("SELECT id, nombre FROM clientes ORDER BY nombre ASC");
    $servicios = query("SELECT id, nombre, duracion_minutos FROM servicios WHERE activo = 1 ORDER BY nombre ASC");
    $barberos = query("SELECT id, nombre FROM usuarios WHERE rol = 'barbero' ORDER BY nombre ASC");
    $sucursales = query("SELECT id, nombre FROM sucursales ORDER BY nombre ASC");
} catch (PDOException $e) {
    $clientes = [];
    $servicios = [];
    $barberos = [];
    $sucursales = [];
}

$pageTitle = $isEdit ? 'Editar Cita' : 'Nueva Cita';
include 'includes/header.php';
?>

<style>
.form-modal {
    max-width: 580px;
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
    min-height: 80px;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
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
    <h1 class="form-title"><?php echo $isEdit ? 'Editar Cita' : 'Nueva Cita'; ?></h1>
    
    <form method="POST" action="api/citas_action.php">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $cita['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" class="form-select" required>
                <option value="">Seleccionar cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['id']; ?>" 
                        <?php echo ($isEdit && $cita['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cliente['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Servicio</label>
            <select name="servicio_id" class="form-select" required>
                <option value="">Seleccionar servicio</option>
                <?php foreach ($servicios as $servicio): ?>
                    <option value="<?php echo $servicio['id']; ?>" 
                        <?php echo ($isEdit && $cita['servicio_id'] == $servicio['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($servicio['nombre']); ?> (<?php echo $servicio['duracion_minutos']; ?> min)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Barbero</label>
                <select name="barbero_id" class="form-select" required>
                    <option value="">Seleccionar barbero</option>
                    <?php foreach ($barberos as $barbero): ?>
                        <option value="<?php echo $barbero['id']; ?>" 
                            <?php echo ($isEdit && $cita['barbero_id'] == $barbero['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barbero['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Sucursal</label>
                <select name="sucursal_id" class="form-select" required>
                    <option value="">Seleccionar sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['id']; ?>" 
                            <?php echo ($isEdit && $cita['sucursal_id'] == $sucursal['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Fecha</label>
                <input 
                    type="date" 
                    name="fecha" 
                    class="form-input"
                    value="<?php echo $isEdit ? date('Y-m-d', strtotime($cita['fecha_hora'])) : ''; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label class="form-label">Hora</label>
                <input 
                    type="time" 
                    name="hora" 
                    class="form-input"
                    value="<?php echo $isEdit ? date('H:i', strtotime($cita['fecha_hora'])) : ''; ?>"
                    required
                >
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" required>
                <option value="pendiente" <?php echo ($isEdit && $cita['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="confirmada" <?php echo ($isEdit && $cita['estado'] == 'confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                <option value="completada" <?php echo ($isEdit && $cita['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                <option value="cancelada" <?php echo ($isEdit && $cita['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea 
                name="notas" 
                class="form-textarea"
                placeholder="Notas adicionales..."
            ><?php echo $isEdit ? htmlspecialchars($cita['notas']) : ''; ?></textarea>
        </div>
        
        <div class="form-actions">
            <a href="citas.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
