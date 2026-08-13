<?php
/**
 * KORTZEN - Detalle Completo de Cliente (Panel Admin)
 * Permite ver todos los datos del cliente, su historial completo de cortes y editar sus Puntos KORTZEN
 */
require_once 'config.php';
requireLogin();

$cliente_id = intval($_GET['id'] ?? 0);

if ($cliente_id <= 0) {
    header('Location: clientes.php');
    exit;
}

$pdo = getConnection();

// Asegurar que exista la columna 'puntos'
try {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
} catch (Exception $e) {}

// Obtener datos del cliente
$stmtC = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmtC->execute([$cliente_id]);
$cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: clientes.php?error=Cliente no encontrado');
    exit;
}

// Historial completo de citas
$stmtH = $pdo->prepare("
    SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre, suc.nombre as sucursal_nombre
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN usuarios b ON c.barbero_id = b.id
    LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
    WHERE c.cliente_id = ?
    ORDER BY c.fecha_hora DESC
");
$stmtH->execute([$cliente_id]);
$historialCitas = $stmtH->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del cliente
$totalCitas = count($historialCitas);
$citasCompletadas = 0;
$citasCanceladas = 0;
$totalGastado = 0;

foreach ($historialCitas as $h) {
    if ($h['estado'] === 'completada') {
        $citasCompletadas++;
        $totalGastado += floatval($h['precio_final'] ?? 0);
    } elseif ($h['estado'] === 'cancelada') {
        $citasCanceladas++;
    }
}

// Reseñas dejadas por el cliente
$reseñas = [];
try {
    $stmtR = $pdo->prepare("SELECT * FROM comentarios_resenas WHERE cliente_id = ? ORDER BY fecha_creacion DESC");
    $stmtR->execute([$cliente_id]);
    $reseñas = $stmtR->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$pageTitle = 'Detalle de Cliente: ' . htmlspecialchars($cliente['nombre']);
include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <a href="clientes.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600;">← Volver a Clientes</a>
        <h1 class="page-title" style="margin-top: 6px;"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="clientes_editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-secondary">Editar Datos</a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 12px 16px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        ✅ <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<!-- Grid de Información General -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    
    <!-- Tarjeta de Datos Personales -->
    <div style="background: var(--bg-sidebar, #1E1E1E); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; color: #FFF;">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
            📋 Datos de Contacto y Cuenta
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem;">
            <div>
                <span style="color: #888; font-weight: 600;">Email:</span>
                <strong style="margin-left: 8px;"><?php echo $cliente['email'] ? htmlspecialchars($cliente['email']) : 'Sin registrar'; ?></strong>
            </div>
            <div>
                <span style="color: #888; font-weight: 600;">Teléfono:</span>
                <strong style="margin-left: 8px;"><?php echo $cliente['telefono'] ? htmlspecialchars($cliente['telefono']) : 'Sin registrar'; ?></strong>
                <?php if (!empty($cliente['telefono'])): 
                    $wa_phone = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                ?>
                    <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" style="margin-left: 10px; color: #25D366; text-decoration: none; font-weight: 700;">
                        💬 Abrir WhatsApp
                    </a>
                <?php endif; ?>
            </div>
            <div>
                <span style="color: #888; font-weight: 600;">Fecha de Registro:</span>
                <strong style="margin-left: 8px;"><?php echo date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])); ?></strong>
            </div>
            <?php if (!empty($cliente['notas'])): ?>
                <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px; margin-top: 6px;">
                    <span style="color: #888; font-weight: 600; display: block; margin-bottom: 4px;">Notas del Barbero / Admin:</span>
                    <p style="margin: 0; font-style: italic; color: #DDD;"><?php echo nl2br(htmlspecialchars($cliente['notas'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjeta de Gestión de Puntos KORTZEN -->
    <div style="background: #111111; border: 1px solid var(--color-gold, #C0A062); border-radius: 12px; padding: 20px; color: #FFF; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-gold, #C0A062); margin: 0;">
                    🏆 Puntos KORTZEN (Fidelidad)
                </h3>
                <span style="background: rgba(192, 160, 98, 0.2); color: #C0A062; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 0.8rem;">
                    <?php 
                        $pts = intval($cliente['puntos'] ?? 0);
                        if ($pts >= 1500) echo "Nivel Oro 👑";
                        elseif ($pts >= 500) echo "Nivel Plata 🥈";
                        else echo "Nivel Bronce 🥉";
                    ?>
                </span>
            </div>

            <div style="font-size: 2.2rem; font-weight: 800; color: #FFFFFF; margin-bottom: 12px;">
                <?php echo number_format(intval($cliente['puntos'] ?? 0)); ?> <span style="font-size: 1rem; color: #888; font-weight: 500;">pts</span>
            </div>
            
            <p style="font-size: 0.85rem; color: #AAA; margin-bottom: 16px;">
                Los puntos se acumulan automáticamente por cada cita finalizada o pueden ser gestionados manualmente desde aquí.
            </p>
        </div>

        <!-- Formulario para Modificar Puntos -->
        <form method="POST" action="api/clientes_action.php" style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="update_puntos">
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
            <input type="hidden" name="redirect_to" value="cliente_detalle.php?id=<?php echo $cliente['id']; ?>">
            
            <div style="flex: 1;">
                <label style="font-size: 0.75rem; color: #AAA; display: block; margin-bottom: 2px;">Editar Puntos Totales:</label>
                <input type="number" name="puntos" value="<?php echo intval($cliente['puntos'] ?? 0); ?>" required min="0" 
                       style="width: 100%; padding: 8px; background: #222; border: 1px solid #444; color: #FFF; border-radius: 6px; font-weight: 700;">
            </div>
            
            <button type="submit" style="padding: 10px 16px; background: #C0A062; color: #111; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 0.8rem; margin-top: 14px;">
                Guardar Puntos
            </button>
        </form>
    </div>

</div>

<!-- KPIs del Cliente -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="background: var(--bg-sidebar, #1E1E1E); padding: 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <div style="font-size: 0.8rem; color: #888; font-weight: 600;">TOTAL DE CITAS</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #FFF; margin-top: 4px;"><?php echo $totalCitas; ?></div>
    </div>
    <div style="background: var(--bg-sidebar, #1E1E1E); padding: 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <div style="font-size: 0.8rem; color: #28a745; font-weight: 600;">COMPLETADAS</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #28a745; margin-top: 4px;"><?php echo $citasCompletadas; ?></div>
    </div>
    <div style="background: var(--bg-sidebar, #1E1E1E); padding: 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <div style="font-size: 0.8rem; color: #dc3545; font-weight: 600;">CANCELADAS</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #dc3545; margin-top: 4px;"><?php echo $citasCanceladas; ?></div>
    </div>
    <div style="background: var(--bg-sidebar, #1E1E1E); padding: 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <div style="font-size: 0.8rem; color: #C0A062; font-weight: 600;">TOTAL INVERTIDO</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #C0A062; margin-top: 4px;">$<?php echo number_format($totalGastado, 2); ?></div>
    </div>
</div>

<!-- Tabla de Historial Completo de Citas -->
<div class="table-container" style="background: var(--bg-sidebar, #1E1E1E); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: #FFF;">
        ✂️ Historial Completo de Citas & Servicios
    </h3>

    <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #888; font-size: 0.8rem;">
                <th style="padding: 12px;">FECHA Y HORA</th>
                <th style="padding: 12px;">SERVICIO</th>
                <th style="padding: 12px;">BARBERO</th>
                <th style="padding: 12px;">SUCURSAL</th>
                <th style="padding: 12px;">PRECIO</th>
                <th style="padding: 12px;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($historialCitas) > 0): ?>
                <?php foreach ($historialCitas as $cita): 
                    $ts = strtotime($cita['fecha_hora']);
                    $estadoClass = '#6c757d';
                    if ($cita['estado'] === 'completada') $estadoClass = '#28a745';
                    elseif ($cita['estado'] === 'confirmada') $estadoClass = '#007bff';
                    elseif ($cita['estado'] === 'pendiente') $estadoClass = '#ffc107';
                    elseif ($cita['estado'] === 'cancelada') $estadoClass = '#dc3545';
                ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); color: #DDD; font-size: 0.9rem;">
                        <td style="padding: 12px;">
                            <strong><?php echo date('d/m/Y', $ts); ?></strong>
                            <span style="color: #888; font-size: 0.8rem; display: block;"><?php echo date('H:i', $ts); ?> hs</span>
                        </td>
                        <td style="padding: 12px; font-weight: 600; color: #FFF;">
                            <?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'Servicio Personalizado'); ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo htmlspecialchars($cita['barbero_nombre'] ?? 'Por asignar'); ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo htmlspecialchars($cita['sucursal_nombre'] ?? 'Llano Chico'); ?>
                        </td>
                        <td style="padding: 12px; font-weight: 700; color: #C0A062;">
                            $<?php echo number_format(floatval($cita['precio_final'] ?? 0), 2); ?>
                        </td>
                        <td style="padding: 12px;">
                            <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: <?php echo $estadoClass; ?>22; color: <?php echo $estadoClass; ?>; border: 1px solid <?php echo $estadoClass; ?>;">
                                <?php echo htmlspecialchars($cita['estado']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #888;">
                        El cliente no tiene citas ni servicios registrados aún.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
