<?php
/**
 * KORTZEN - Dashboard Exclusivo para Barberos
 * Interfaz nativa blanca y elegante para barberos (mismo sistema de diseño que la PWA del cliente)
 */

require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['rol'] !== 'barbero' && $currentUser['rol'] !== 'admin_local' && $currentUser['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$barbero_id = $currentUser['id'];
$sucursal_id = $currentUser['sucursal_id'] ?? 1;

// Mensajes de estado
$mensaje = '';
$tipoMensaje = '';

// Marcar cita como completada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'completar_cita') {
        $citaId = intval($_POST['cita_id'] ?? 0);
        if ($citaId > 0) {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("UPDATE citas SET estado = 'completada' WHERE id = ? AND barbero_id = ?");
                $stmt->execute([$citaId, $barbero_id]);
                $mensaje = '¡Cita marcada como COMPLETADA! Se han acreditado tus ganancias.';
                $tipoMensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al actualizar cita.';
                $tipoMensaje = 'error';
            }
        }
    }
}

// 1. Ganancias por Servicios + Comisión por Ventas de Productos (Separadas)
$com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
$com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);
$com_productos = floatval($currentUser['comision_productos'] ?? 10.00);

// Ganancia Citas Hoy (Servicios)
$gananciaHoyServicios = query("
    SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id])[0]['total'] ?? 0;

// Ganancia Ventas Productos Hoy (Comisión de productos asignada)
$gananciaHoyVentas = query("
    SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
    FROM ventas_productos 
    WHERE usuario_id = ? AND DATE(fecha) = CURDATE()
", [$barbero_id])[0]['total'] ?? 0;

$miGananciaDia = floatval($gananciaHoyServicios) + floatval($gananciaHoyVentas);

// Ganancia Mes (Servicios + Ventas)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$gananciaMesServicios = query("
    SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd])[0]['total'] ?? 0;

$gananciaMesVentas = query("
    SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
    FROM ventas_productos 
    WHERE usuario_id = ? AND DATE(fecha) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd])[0]['total'] ?? 0;

$miGananciaMes = floatval($gananciaMesServicios) + floatval($gananciaMesVentas);

// Total de citas completadas hoy
$countHoy = query("
    SELECT COUNT(*) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id]);
$totalCitasHoy = intval($countHoy[0]['total'] ?? 0);

// 2. Próximo Cliente
$nextClient = query("
    SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.barbero_id = ? 
    AND c.fecha_hora >= NOW()
    AND c.estado IN ('pendiente', 'confirmada')
    ORDER BY c.fecha_hora ASC 
    LIMIT 1
", [$barbero_id]);

$proximo = $nextClient ? $nextClient[0] : null;

// 3. Turnos de Hoy
$turnosHoy = query("
    SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.nombre as cliente, cli.telefono as cliente_telefono
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.barbero_id = ? AND DATE(c.fecha_hora) = CURDATE()
    ORDER BY c.fecha_hora ASC
", [$barbero_id]);

// 4. Inventario de la Sucursal
$inventarioItems = query("SELECT id, producto, cantidad, precio FROM inventario WHERE sucursal_id = ? ORDER BY producto ASC", [$sucursal_id]);

$nombreBarbero = explode(' ', trim($currentUser['nombre']))[0];
$inicial_barbero = strtoupper(substr($nombreBarbero, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Panel de Barbero - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=3">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">

    <style>
        .pwa-barber-card {
            background: var(--pwa-card-bg);
            border: 1px solid var(--pwa-border);
            border-radius: var(--pwa-radius-card);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--pwa-shadow);
        }

        .pwa-form-control {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid var(--pwa-border);
            border-radius: 12px;
            background: #FAFAFA;
            color: var(--pwa-text-main);
            font-family: inherit;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
            box-sizing: border-box;
        }

        .pwa-form-control:focus {
            outline: none;
            border-color: #111111;
            background: #FFFFFF;
        }

        .badge-turno {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }

        .badge-pendiente { background: #FFF8E7; color: #B78103; border: 1px solid #F3E0B5; }
        .badge-completada { background: #E8F8F0; color: #1E824C; border: 1px solid #A3E4D7; }
        .badge-cancelada { background: #FADBD8; color: #78281F; border: 1px solid #F5B7B1; }
    </style>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <!-- Header Blanco Nativo -->
        <header class="pwa-header">
            <div class="pwa-header__logo">KORTZEN</div>
            <div class="pwa-header__title" style="font-size: 0.95rem; font-weight: 700;">PANEL BARBERO</div>
            <a href="logout.php" class="pwa-header__btn" title="Cerrar sesión" style="color: #dc3545;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </header>

        <!-- Saludo e Información del Barbero -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; margin-top: 0.5rem;">
            <div>
                <h1 class="pwa-greeting">Hola, <?php echo htmlspecialchars($nombreBarbero); ?> 👋</h1>
                <p class="pwa-subtitle" style="margin-bottom: 0;">Barbero Profesional • KORTZEN</p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; flex-shrink: 0;">
                <?php echo $inicial_barbero; ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div style="background: #E8F8F0; border: 1px solid #A3E4D7; color: #1E824C; padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.85rem; margin-bottom: 1.25rem; font-weight: 600;">
                ✓ <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de Métricas de Ganancias -->
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.85rem; margin-bottom: 1.25rem;">
            <div class="pwa-card" style="margin-bottom: 0; padding: 1.1rem;">
                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--pwa-text-muted); margin-bottom: 0.3rem;">Ganancias Hoy</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--pwa-text-main);">$<?php echo number_format($miGananciaDia, 2); ?></div>
                <div style="font-size: 0.75rem; color: var(--pwa-text-muted); margin-top: 0.2rem;"><?php echo $totalCitasHoy; ?> cortes + ventas</div>
            </div>
            <div class="pwa-card" style="margin-bottom: 0; padding: 1.1rem;">
                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--pwa-text-muted); margin-bottom: 0.3rem;">Ganancias Mes</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--pwa-text-main);">$<?php echo number_format($miGananciaMes, 2); ?></div>
                <div style="font-size: 0.75rem; color: var(--pwa-text-muted); margin-top: 0.2rem;">Comisiones acumuladas</div>
            </div>
        </div>

        <!-- Próximo Cliente Widget -->
        <div class="pwa-section-title">Próximo Cliente</div>
        <?php if ($proximo): 
            $horaProxima = date('H:i', strtotime($proximo['fecha_hora']));
        ?>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box" style="background: #111111; color: #FFFFFF;">
                    <div class="pwa-date-box__day" style="color: #CCCCCC;">⏰</div>
                    <div class="pwa-date-box__num" style="font-size: 1rem; color: #FFFFFF;"><?php echo $horaProxima; ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proximo['servicio'] ?? 'Corte de Autor'); ?></div>
                    <div class="pwa-upcoming-detail">Cliente: <strong><?php echo htmlspecialchars($proximo['cliente'] ?? 'Cliente'); ?></strong></div>
                    <?php if (!empty($proximo['cliente_telefono'])): ?>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $proximo['cliente_telefono']); ?>" target="_blank" class="pwa-upcoming-detail" style="color: #25D366; text-decoration: none; font-weight: 600;">
                            📞 Contactar por WhatsApp (<?php echo htmlspecialchars($proximo['cliente_telefono']); ?>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST" style="margin-top: 0.85rem;">
                <input type="hidden" name="action" value="completar_cita">
                <input type="hidden" name="cita_id" value="<?php echo $proximo['id']; ?>">
                <button type="submit" class="pwa-btn-black" style="background: #10B981; border: none; padding: 0.85rem;">
                    ✓ MARCAR SERVICIO COMO COMPLETADO
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="pwa-card" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--pwa-text-main);">No tienes más turnos pendientes por hoy 🎉</div>
            <div style="font-size: 0.8rem; color: var(--pwa-text-muted); margin-top: 0.3rem;">¡Excelente trabajo! Tus ganancias se encuentran actualizadas en las tarjetas de arriba.</div>
        </div>
        <?php endif; ?>

        <!-- Registrar Venta de Producto (Comisión) -->
        <div class="pwa-section-title" style="margin-top: 1.5rem;">🛍️ Registrar Venta de Producto</div>
        <div class="pwa-barber-card">
            <p style="font-size: 0.8rem; color: var(--pwa-text-muted); margin-bottom: 0.85rem;">
                Suma comisiones instantáneas a tus ganancias al vender productos de barbería a tus clientes.
            </p>
            <form id="formVentaProducto" onsubmit="registrarVentaProductoBarbero(event)">
                <label style="font-size: 0.78rem; font-weight: 700; color: var(--pwa-text-main); display: block; margin-bottom: 0.35rem;">Producto Vendido:</label>
                <select name="producto_id" class="pwa-form-control" required>
                    <option value="">-- Seleccionar producto del inventario --</option>
                    <?php foreach ($inventarioItems as $item): ?>
                        <option value="<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['producto']); ?> (Stock: <?php echo $item['cantidad']; ?>) - $<?php echo number_format($item['precio'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div style="display: flex; gap: 0.75rem;">
                    <div style="flex: 1;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--pwa-text-main); display: block; margin-bottom: 0.35rem;">Cantidad:</label>
                        <input type="number" name="cantidad" value="1" min="1" class="pwa-form-control" required>
                    </div>
                    <div style="flex: 2; display: flex; align-items: flex-end;">
                        <button type="submit" class="pwa-btn-black" style="padding: 0.85rem; font-size: 0.8rem;">
                            REGISTRAR VENTA
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Debitar Insumos de Inventario -->
        <div class="pwa-section-title" style="margin-top: 1.5rem;">📦 Consumo de Insumos del Turno</div>
        <div class="pwa-barber-card">
            <p style="font-size: 0.8rem; color: var(--pwa-text-muted); margin-bottom: 0.85rem;">
                Registra los materiales gastados al final del turno para mantener actualizado el inventario.
            </p>
            <form id="formConsumoInsumo" onsubmit="descontarInsumoBarbero(event)">
                <label style="font-size: 0.78rem; font-weight: 700; color: var(--pwa-text-main); display: block; margin-bottom: 0.35rem;">Insumo / Material Gastado:</label>
                <select name="producto_id" class="pwa-form-control" required>
                    <option value="">-- Seleccionar insumo a debitar --</option>
                    <?php foreach ($inventarioItems as $item): ?>
                        <option value="<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['producto']); ?> (Quedan: <?php echo $item['cantidad']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <div style="display: flex; gap: 0.75rem;">
                    <div style="flex: 1;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--pwa-text-main); display: block; margin-bottom: 0.35rem;">Cantidad Gastada:</label>
                        <input type="number" name="cantidad" value="1" min="1" class="pwa-form-control" required>
                    </div>
                    <div style="flex: 2; display: flex; align-items: flex-end;">
                        <button type="submit" class="pwa-btn-secondary" style="padding: 0.85rem; font-size: 0.8rem; border-color: #111111; color: #111111;">
                            DEBITAR INSUMO
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Turnos de Hoy List -->
        <div class="pwa-section-title" style="margin-top: 1.5rem;">Mis Turnos de Hoy (<?php echo date('d/m/Y'); ?>)</div>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php if (!empty($turnosHoy)): ?>
                <?php foreach ($turnosHoy as $t): 
                    $horaT = date('H:i', strtotime($t['fecha_hora']));
                    $est = strtolower($t['estado']);
                    $badgeClass = 'badge-pendiente';
                    if ($est === 'completada') $badgeClass = 'badge-completada';
                    if ($est === 'cancelada') $badgeClass = 'badge-cancelada';
                ?>
                <div class="pwa-card" style="margin-bottom: 0; padding: 1rem 1.15rem; display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--pwa-text-main); width: 60px;">
                        <?php echo $horaT; ?>
                    </div>
                    <div style="flex: 1; padding: 0 0.75rem;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--pwa-text-main); margin-bottom: 0.15rem;">
                            <?php echo htmlspecialchars($t['servicio'] ?? 'Corte'); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--pwa-text-muted);">
                            Cliente: <?php echo htmlspecialchars($t['cliente'] ?? 'Cliente'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge-turno <?php echo $badgeClass; ?>"><?php echo strtoupper($est); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="pwa-card" style="text-align: center; padding: 2rem; color: var(--pwa-text-muted); font-size: 0.85rem;">
                    No tienes agendamientos para el día de hoy.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        async function registrarVentaProductoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'registrar_venta');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al registrar la venta');
            }
        }

        async function descontarInsumoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'descontar_insumo');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al debitar insumo');
            }
        }
    </script>

</body>
</html>
