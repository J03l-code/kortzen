<?php
/**
 * KORTZEN - Configuración del Sistema (Panel de Administración)
 * Control de Puntos KORTZEN, Descuentos y Sistema de Referidos (Diseño Blanco y Negro Minimalista)
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

<style>
    .config-card {
        background: #FFFFFF;
        border: 1px solid #EAEAEA;
        border-radius: 16px;
        padding: 28px;
        color: #111111;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        margin-bottom: 24px;
        transition: all 0.2s ease;
    }
    .config-card-header {
        font-size: 1.15rem;
        font-weight: 800;
        color: #111111;
        margin-bottom: 22px;
        border-bottom: 1px solid #F0F0F0;
        padding-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.01em;
    }
    .config-icon {
        width: 22px;
        height: 22px;
        stroke: #111111;
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .config-label {
        font-weight: 700;
        color: #222222;
        display: block;
        margin-bottom: 8px;
        font-size: 0.92rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .config-input {
        width: 100%;
        padding: 13px 16px;
        background: #FAFAFA;
        border: 1px solid #D1D1D1;
        color: #111111;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.05rem;
        transition: border-color 0.2s;
    }
    .config-input:focus {
        border-color: #000000;
        outline: none;
        background: #FFFFFF;
    }
    .config-help {
        color: #777777;
        font-size: 0.82rem;
        display: block;
        margin-top: 6px;
        line-height: 1.4;
    }
    .btn-save-main {
        background: #000000;
        color: #FFFFFF;
        border: none;
        padding: 16px 36px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.95rem;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: transform 0.15s, background 0.15s;
    }
    .btn-save-main:hover {
        background: #222222;
        transform: translateY(-2px);
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
    <div>
        <h1 class="page-title" style="margin: 0; color: #111111; font-weight: 900; font-size: 1.8rem; letter-spacing: -0.02em;">Configuración de Puntos & Referidos</h1>
        <p style="color: #666666; margin-top: 6px; font-size: 0.95rem;">Gestiona las reglas del programa de fidelización y parámetros del sistema.</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="padding: 14px 20px; background: #111111; color: #FFFFFF; border-radius: 12px; margin-bottom: 28px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
        <svg class="config-icon" style="stroke: #FFFFFF;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
<?php endif; ?>

<form method="POST" action="api/configuracion_action.php">
    <input type="hidden" name="action" value="save_configs">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        
        <!-- Tarjeta de Puntos KORTZEN -->
        <div class="config-card">
            <h3 class="config-card-header">
                <svg class="config-icon" viewBox="0 0 24 24"><path d="M12 15l-2 5l9-11h-7l2-5l-9 11h7z"></path></svg>
                Sistema de Puntos de Fidelidad
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label class="config-label">Puntos por Cita / Corte Completado</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_corte" value="<?php echo htmlspecialchars($configs['puntos_por_corte'] ?? '100'); ?>" required min="0" class="config-input">
                        <span style="color: #666666; font-weight: 700; white-space: nowrap; font-size: 0.85rem;">PTS / CITA</span>
                    </div>
                    <small class="config-help">Puntos asignados al cliente automáticamente cuando el barbero completa un servicio.</small>
                </div>

                <div>
                    <label class="config-label">Puntos de Bonificación por Referido Exitoso</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="puntos_por_referido" value="<?php echo htmlspecialchars($configs['puntos_por_referido'] ?? '200'); ?>" required min="0" class="config-input">
                        <span style="color: #666666; font-weight: 700; white-space: nowrap; font-size: 0.85rem;">PTS / AMIGO</span>
                    </div>
                    <small class="config-help">Puntos entregados al cliente que invitó cuando su amigo finaliza su primera cita.</small>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Descuentos por Referidos -->
        <div class="config-card">
            <h3 class="config-card-header">
                <svg class="config-icon" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                Descuentos y Promociones
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label class="config-label">Descuento para el Nuevo Cliente (Amigo)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #111111; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referido_amigo" value="<?php echo htmlspecialchars($configs['descuento_referido_amigo'] ?? '2.00'); ?>" step="0.50" required min="0" class="config-input">
                    </div>
                    <small class="config-help">Monto de descuento directo restado en la reserva al aplicar el código de referido.</small>
                </div>

                <div>
                    <label class="config-label">Descuento para el Cliente Referente</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #111111; font-weight: 800; font-size: 1.2rem;">$</span>
                        <input type="number" name="descuento_referente" value="<?php echo htmlspecialchars($configs['descuento_referente'] ?? '2.00'); ?>" step="0.50" required min="0" class="config-input">
                    </div>
                    <small class="config-help">Descuento otorgado al cliente que refirió para usar en su próxima reserva.</small>
                </div>
            </div>
        </div>

    </div>

    <!-- Tarjeta de Niveles y Rangos KORTZEN -->
    <div class="config-card">
        <h3 class="config-card-header">
            <svg class="config-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            Niveles de Fidelización & Metas de Puntos
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <label class="config-label">Nivel Plata (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_plata" value="<?php echo htmlspecialchars($configs['puntos_nivel_plata'] ?? '500'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Puntos necesarios para pasar de Bronce a Plata.</small>
            </div>

            <div>
                <label class="config-label">Nivel Oro (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_oro" value="<?php echo htmlspecialchars($configs['puntos_nivel_oro'] ?? '1500'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Puntos necesarios para pasar de Plata a Oro.</small>
            </div>

            <div>
                <label class="config-label">Nivel VIP / Máximo (Requerido)</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="puntos_nivel_vip" value="<?php echo htmlspecialchars($configs['puntos_nivel_vip'] ?? '3000'); ?>" required min="0" class="config-input">
                    <span style="color: #666666; font-weight: 700; font-size: 0.82rem;">PTS</span>
                </div>
                <small class="config-help">Meta máxima de puntos para el nivel VIP.</small>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Configuración de Servidor de Correos (SMTP Hostinger) -->
    <div class="config-card">
        <h3 class="config-card-header">
            <svg class="config-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            Configuración del Servidor de Correos (SMTP)
        </h3>
        <p style="color: #666666; font-size: 0.88rem; margin-bottom: 18px; line-height: 1.5;">Configura tu correo corporativo para garantizar la entrega de confirmaciones y recordatorios a la bandeja de entrada del cliente.</p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label class="config-label">Servidor SMTP (Host)</label>
                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($configs['smtp_host'] ?? 'smtp.hostinger.com'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Puerto SMTP</label>
                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($configs['smtp_port'] ?? '465'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Correo Remitente / Usuario SMTP</label>
                <input type="email" name="smtp_user" placeholder="info@kortzen.com" value="<?php echo htmlspecialchars($configs['smtp_user'] ?? 'info@kortzen.com'); ?>" class="config-input">
            </div>

            <div>
                <label class="config-label">Contraseña de Correo SMTP</label>
                <input type="password" name="smtp_pass" placeholder="••••••••••••" value="<?php echo htmlspecialchars($configs['smtp_pass'] ?? 'Kortzen2026!'); ?>" class="config-input">
            </div>
        </div>
    </div>

    <!-- Botón Guardar Cambios -->
    <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
        <button type="submit" class="btn-save-main">
            <svg class="config-icon" style="stroke: #FFFFFF; width: 18px; height: 18px;" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>Guardar Configuración</span>
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
