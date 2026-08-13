<?php
/**
 * KORTZEN - Detalle Completo de Cliente (Panel Admin)
 * Diseño de alto contraste con tarjetas oscuras legibles e impecables
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

$pageTitle = 'Detalle de Cliente: ' . htmlspecialchars($cliente['nombre']);
include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <a href="clientes.php" style="color: #666; text-decoration: none; font-size: 0.9rem; font-weight: 700;">← Volver a Clientes</a>
        <h1 class="page-title" style="margin-top: 6px; color: #111111; font-weight: 800;"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="clientes_editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-secondary" style="background: #111; color: #FFF; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; text-decoration: none;">EDITAR DATOS</a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 12px 16px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; font-weight: 700;">
        ✅ <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<!-- Grid de Información General -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    
    <!-- Tarjeta de Datos Personales (Fondo Oscuro Elegante) -->
    <div style="background: #181818; border: 1px solid #333333; border-radius: 14px; padding: 22px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #FFFFFF; margin-bottom: 16px; border-bottom: 1px solid #333333; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span>📋</span> Datos de Contacto y Cuenta
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #222222; padding-bottom: 8px;">
                <span style="color: #AAAAAA; font-weight: 600;">Email:</span>
                <strong style="color: #FFFFFF; font-weight: 700;"><?php echo $cliente['email'] ? htmlspecialchars($cliente['email']) : 'Sin registrar'; ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222222; padding-bottom: 8px;">
                <span style="color: #AAAAAA; font-weight: 600;">Teléfono:</span>
                <div>
                    <strong style="color: #FFFFFF; font-weight: 700;"><?php echo $cliente['telefono'] ? htmlspecialchars($cliente['telefono']) : 'Sin registrar'; ?></strong>
                    <?php if (!empty($cliente['telefono'])): 
                        $wa_phone = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                    ?>
                        <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" style="margin-left: 10px; background: #25D366; color: #FFFFFF; padding: 4px 10px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                            💬 Abrir WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #222222; padding-bottom: 8px;">
                <span style="color: #AAAAAA; font-weight: 600;">Fecha de Registro:</span>
                <strong style="color: #FFFFFF; font-weight: 700;"><?php echo date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])); ?></strong>
            </div>
            <?php if (!empty($cliente['notas'])): ?>
                <div style="background: #222222; padding: 12px; border-radius: 8px; margin-top: 6px; border: 1px solid #333333;">
                    <span style="color: #C0A062; font-weight: 700; display: block; margin-bottom: 4px; font-size: 0.82rem;">Notas del Barbero / Admin:</span>
                    <p style="margin: 0; font-style: italic; color: #EEEEEE; font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($cliente['notas'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjeta de Gestión de Puntos KORTZEN (Fondo Negro Gold) -->
    <div style="background: #111111; border: 1px solid #C0A062; border-radius: 14px; padding: 22px; color: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #C0A062; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>🏆</span> Puntos KORTZEN (Fidelidad)
                </h3>
                <span style="background: rgba(192, 160, 98, 0.2); color: #C0A062; border: 1px solid #C0A062; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 0.8rem;">
                    <?php 
                        $pts = intval($cliente['puntos'] ?? 0);
                        if ($pts >= 1500) echo "Nivel Oro 👑";
                        elseif ($pts >= 500) echo "Nivel Plata 🥈";
                        else echo "Nivel Bronce 🥉";
                    ?>
                </span>
            </div>

            <div style="font-size: 2.6rem; font-weight: 800; color: #FFFFFF; margin-bottom: 10px;">
                <?php echo number_format(intval($cliente['puntos'] ?? 0)); ?> <span style="font-size: 1.1rem; color: #C0A062; font-weight: 600;">pts</span>
            </div>
            
            <p style="font-size: 0.88rem; color: #AAAAAA; margin-bottom: 18px; line-height: 1.4;">
                Los puntos se acumulan automáticamente por cada cita finalizada (+100 pts) o pueden ser asignados manualmente desde aquí.
            </p>
        </div>

        <!-- Formulario para Modificar Puntos -->
        <form method="POST" action="api/clientes_action.php" style="background: #1A1A1A; padding: 14px; border-radius: 10px; border: 1px solid #333333; display: flex; gap: 12px; align-items: center;">
            <input type="hidden" name="action" value="update_puntos">
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
            <input type="hidden" name="redirect_to" value="cliente_detalle.php?id=<?php echo $cliente['id']; ?>">
            
            <div style="flex: 1;">
                <label style="font-size: 0.78rem; color: #AAAAAA; font-weight: 600; display: block; margin-bottom: 4px;">Editar Puntos Totales:</label>
                <input type="number" name="puntos" value="<?php echo intval($cliente['puntos'] ?? 0); ?>" required min="0" 
                       style="width: 100%; padding: 10px; background: #282828; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1rem;">
            </div>
            
            <button type="submit" style="padding: 11px 18px; background: #C0A062; color: #111111; border: none; border-radius: 8px; font-weight: 800; cursor: pointer; text-transform: uppercase; font-size: 0.8rem; margin-top: 18px; transition: all 0.2s ease;">
                Guardar Puntos
            </button>
        </form>
    </div>

</div>

<!-- KPIs del Cliente -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div style="background: #181818; border: 1px solid #333333; padding: 18px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.78rem; color: #AAAAAA; font-weight: 700; letter-spacing: 0.05em;">TOTAL DE CITAS</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #FFFFFF; margin-top: 6px;"><?php echo $totalCitas; ?></div>
    </div>
    <div style="background: #181818; border: 1px solid #333333; padding: 18px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.78rem; color: #28a745; font-weight: 700; letter-spacing: 0.05em;">COMPLETADAS</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #28a745; margin-top: 6px;"><?php echo $citasCompletadas; ?></div>
    </div>
    <div style="background: #181818; border: 1px solid #333333; padding: 18px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.78rem; color: #dc3545; font-weight: 700; letter-spacing: 0.05em;">CANCELADAS</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #dc3545; margin-top: 6px;"><?php echo $citasCanceladas; ?></div>
    </div>
    <div style="background: #181818; border: 1px solid #333333; padding: 18px; border-radius: 12px; text-align: center;">
        <div style="font-size: 0.78rem; color: #C0A062; font-weight: 700; letter-spacing: 0.05em;">TOTAL INVERTIDO</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #C0A062; margin-top: 6px;">$<?php echo number_format($totalGastado, 2); ?></div>
    </div>
</div>

<!-- Tabla de Historial Completo de Citas (Fondo Oscuro de Alto Contraste) -->
<div style="background: #181818; border: 1px solid #333333; padding: 22px; border-radius: 14px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 18px; color: #FFFFFF; display: flex; align-items: center; gap: 8px;">
        <span>✂️</span> Historial Completo de Citas & Servicios
    </h3>

    <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 2px solid #333333; color: #AAAAAA; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                <th style="padding: 14px 12px; background: #222222;">FECHA Y HORA</th>
                <th style="padding: 14px 12px; background: #222222;">SERVICIO</th>
                <th style="padding: 14px 12px; background: #222222;">BARBERO</th>
                <th style="padding: 14px 12px; background: #222222;">SUCURSAL</th>
                <th style="padding: 14px 12px; background: #222222;">PRECIO</th>
                <th style="padding: 14px 12px; background: #222222;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($historialCitas) > 0): ?>
                <?php foreach ($historialCitas as $cita): 
                    $ts = strtotime($cita['fecha_hora']);
                    $estadoColor = '#888888';
                    if ($cita['estado'] === 'completada') $estadoColor = '#28a745';
                    elseif ($cita['estado'] === 'confirmada') $estadoColor = '#007bff';
                    elseif ($cita['estado'] === 'pendiente') $estadoColor = '#ffc107';
                    elseif ($cita['estado'] === 'cancelada') $estadoColor = '#dc3545';
                ?>
                    <tr style="border-bottom: 1px solid #2A2A2A; color: #FFFFFF; font-size: 0.92rem;">
                        <td style="padding: 14px 12px;">
                            <strong style="color: #FFFFFF; display: block; font-weight: 700;"><?php echo date('d/m/Y', $ts); ?></strong>
                            <span style="color: #888888; font-size: 0.8rem;"><?php echo date('H:i', $ts); ?> hs</span>
                        </td>
                        <td style="padding: 14px 12px; font-weight: 700; color: #FFFFFF;">
                            <?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'Servicio Personalizado'); ?>
                        </td>
                        <td style="padding: 14px 12px; color: #DDDDDD;">
                            <?php echo htmlspecialchars($cita['barbero_nombre'] ?? 'Por asignar'); ?>
                        </td>
                        <td style="padding: 14px 12px; color: #DDDDDD;">
                            <?php echo htmlspecialchars($cita['sucursal_nombre'] ?? 'Llano Chico'); ?>
                        </td>
                        <td style="padding: 14px 12px; font-weight: 800; color: #C0A062;">
                            $<?php echo number_format(floatval($cita['precio_final'] ?? 0), 2); ?>
                        </td>
                        <td style="padding: 14px 12px;">
                            <span style="display: inline-block; padding: 5px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; background: <?php echo $estadoColor; ?>22; color: <?php echo $estadoColor; ?>; border: 1px solid <?php echo $estadoColor; ?>;">
                                <?php echo htmlspecialchars($cita['estado']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #AAAAAA; font-weight: 500; font-size: 0.95rem;">
                        El cliente no tiene citas ni servicios registrados aún.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
