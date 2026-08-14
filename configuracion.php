<?php
/**
 * KORTZEN - Configuración del Sistema (Panel de Administración)
 * Control de Puntos KORTZEN, Descuentos y Sistema de Referidos
 */
require_once 'config.php';
requireLogin();

// Solo Administradores
if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getConnection();

// Asegurar tablas de configuración
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(50) NOT NULL UNIQUE,
            valor TEXT NOT NULL,
            descripcion VARCHAR(255) NULL,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $defaultConfigs = [
        ['puntos_por_corte', '100', 'Puntos otorgados al cliente por cada cita/corte completado'],
        ['puntos_por_referido', '200', 'Puntos otorgados al cliente referente por cada referido exitoso'],
        ['descuento_referido_amigo', '2.00', 'Descuento ($) en la primera reserva del amigo que aplica el código'],
        ['descuento_referente', '2.00', 'Descuento ($) otorgado al cliente referente para su próxima cita'],
        ['puntos_nivel_plata', '500', 'Puntos requeridos para alcanzar Nivel Plata'],
        ['puntos_nivel_oro', '1500', 'Puntos requeridos para alcanzar Nivel Oro'],
        ['puntos_nivel_vip', '3000', 'Puntos requeridos para alcanzar Nivel VIP / Máximo']
    ];

    $stmtCfg = $pdo->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    foreach ($defaultConfigs as $cfg) {
        $stmtCfg->execute($cfg);
    }
} catch (Exception $e) {}

// Obtener todas las configuraciones actuales
$stmt = $pdo->query("SELECT * FROM configuracion");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$configs = [];
foreach ($rows as $r) {
    $configs[$r['clave']] = $r['valor'];
}

$pageTitle = 'Configuración del Sistema y Referidos';
include 'includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="margin: 0; color: #111111; font-weight: 800;">⚙️ Configuración de Puntos & Referidos</h1>
        <p style="color: #666666; margin-top: 4px; font-size: 0.95rem;">Gestiona las reglas del programa de fidelización y los descuentos de tu barbería.</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 14px 18px; background: #d4edda; color: #155724; border-radius: 10px; margin-bottom: 24px; font-weight: 700;">
        ✅ <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<form method="POST" action="api/configuracion_action.php">
    <input type="hidden" name="action" value="save_configs">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        
        <!-- Tarjeta de Puntos KORTZEN -->
        <div style="background: #181818; border: 1px solid #333333; border-radius: 14px; padding: 24px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #C0A062; margin-bottom: 18px; border-bottom: 1px solid #333333; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <span>🏆</span> Sistema de Puntos de Fidelidad
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 18px;">
                <div>
                    <label style="font-weight: 700; color: #FFFFFF; display: block; margin-bottom: 6px; font-size: 0.95rem;">
                        Puntos por Cita / Corte Completado:
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_corte" value="<?php echo htmlspecialchars($configs['puntos_por_corte'] ?? '100'); ?>" required min="0" 
                               style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                        <span style="color: #C0A062; font-weight: 800; white-space: nowrap;">pts / cita</span>
                    </div>
                    <small style="color: #AAAAAA; font-size: 0.82rem; display: block; margin-top: 4px;">Puntos asignados al cliente en automático cada vez que el barbero completa un servicio.</small>
                </div>

                <div>
                    <label style="font-weight: 700; color: #FFFFFF; display: block; margin-bottom: 6px; font-size: 0.95rem;">
                        Puntos de Bonificación por Referido Exitoso:
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_referido" value="<?php echo htmlspecialchars($configs['puntos_por_referido'] ?? '200'); ?>" required min="0" 
                               style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                        <span style="color: #C0A062; font-weight: 800; white-space: nowrap;">pts / amigo</span>
                    </div>
                    <small style="color: #AAAAAA; font-size: 0.82rem; display: block; margin-top: 4px;">Puntos de recompensa entregados al cliente que invitó cuando su amigo finaliza su primera cita.</small>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Descuentos por Referidos -->
        <div style="background: #181818; border: 1px solid #333333; border-radius: 14px; padding: 24px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #C0A062; margin-bottom: 18px; border-bottom: 1px solid #333333; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <span>🎁</span> Descuentos y Promociones
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 18px;">
                <div>
                    <label style="font-weight: 700; color: #FFFFFF; display: block; margin-bottom: 6px; font-size: 0.95rem;">
                        Descuento para el Nuevo Cliente (Amigo que usa el código):
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #28a745; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referido_amigo" value="<?php echo htmlspecialchars($configs['descuento_referido_amigo'] ?? '2.00'); ?>" step="0.50" required min="0" 
                               style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                    </div>
                    <small style="color: #AAAAAA; font-size: 0.82rem; display: block; margin-top: 4px;">Monto de descuento directo restado en la reserva al aplicar el código de referido.</small>
                </div>

                <div>
                    <label style="font-weight: 700; color: #FFFFFF; display: block; margin-bottom: 6px; font-size: 0.95rem;">
                        Descuento para el Cliente Referente (Propietario del código):
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #28a745; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referente" value="<?php echo htmlspecialchars($configs['descuento_referente'] ?? '2.00'); ?>" step="0.50" required min="0" 
                               style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                    </div>
                    <small style="color: #AAAAAA; font-size: 0.82rem; display: block; margin-top: 4px;">Descuento otorgado al cliente que refirió para usar en su próxima reserva.</small>
                </div>
            </div>
        </div>

    </div>

    <!-- Tarjeta de Niveles y Rangos KORTZEN -->
    <div style="background: #181818; border: 1px solid #333333; border-radius: 14px; padding: 24px; color: #FFFFFF; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 24px;">
        <h3 style="font-size: 1.2rem; font-weight: 800; color: #C0A062; margin-bottom: 18px; border-bottom: 1px solid #333333; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span>👑</span> Niveles de Fidelización & Metas de Puntos
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <label style="font-weight: 700; color: #E0E0E0; display: block; margin-bottom: 6px; font-size: 0.92rem;">
                    🥈 Nivel Plata (Puntos requeridos):
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_plata" value="<?php echo htmlspecialchars($configs['puntos_nivel_plata'] ?? '500'); ?>" required min="0" 
                           style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                    <span style="color: #CCCCCC; font-weight: 700; font-size: 0.85rem;">pts</span>
                </div>
                <small style="color: #888888; font-size: 0.8rem; display: block; margin-top: 4px;">Puntos necesarios para pasar de Bronce 🥉 a Plata 🥈.</small>
            </div>

            <div>
                <label style="font-weight: 700; color: #FFD700; display: block; margin-bottom: 6px; font-size: 0.92rem;">
                    👑 Nivel Oro (Puntos requeridos):
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_oro" value="<?php echo htmlspecialchars($configs['puntos_nivel_oro'] ?? '1500'); ?>" required min="0" 
                           style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                    <span style="color: #FFD700; font-weight: 700; font-size: 0.85rem;">pts</span>
                </div>
                <small style="color: #888888; font-size: 0.8rem; display: block; margin-top: 4px;">Puntos necesarios para pasar de Plata 🥈 a Oro 👑.</small>
            </div>

            <div>
                <label style="font-weight: 700; color: #C0A062; display: block; margin-bottom: 6px; font-size: 0.92rem;">
                    💎 Nivel VIP / Máximo (Puntos requeridos):
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_vip" value="<?php echo htmlspecialchars($configs['puntos_nivel_vip'] ?? '3000'); ?>" required min="0" 
                           style="width: 100%; padding: 12px; background: #222222; border: 1px solid #444444; color: #FFFFFF; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">
                    <span style="color: #C0A062; font-weight: 700; font-size: 0.85rem;">pts</span>
                </div>
                <small style="color: #888888; font-size: 0.8rem; display: block; margin-top: 4px;">Meta máxima de puntos para el nivel VIP 💎.</small>
            </div>
        </div>
    </div>

    <!-- Botón Guardar Cambios -->
    <div style="display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary" style="background: #C0A062; color: #111111; border: none; padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 1rem; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 4px 15px rgba(192, 160, 98, 0.3);">
            💾 Guardar Toda la Configuración
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
