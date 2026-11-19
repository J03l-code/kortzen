<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();
if (!$currentUser || empty($currentUser['activo']) || !esAdministradorCaja($currentUser)) { http_response_code(403); exit('Acceso reservado a administración.'); }
$errorCaja = '';
try { $reporteCaja = resumenCaja(getConnection(), $currentUser, $_GET); }
catch (Throwable $e) { $errorCaja = $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo cargar el cierre de caja.'; error_log($e->getMessage()); }
$sucursalesCaja = getUsuarioSucursales($currentUser['id']);
$pageTitle = 'Cierre de Caja y Contabilidad';
include 'includes/header.php';
?>
<link rel="stylesheet" href="css/gestion-operativa.css">
<h1 class="page-title">Cierre de Caja y Contabilidad</h1>
<div class="kortzen-panel">
    <form method="get" class="kortzen-filtros">
        <label>Periodo<select name="periodo" onchange="this.form.desde.value='';this.form.hasta.value='';this.form.submit()">
            <?php foreach (['diario'=>'Diario','semanal'=>'Semanal','mensual'=>'Mensual'] as $valorPeriodo => $nombrePeriodo): ?>
                <option value="<?php echo $valorPeriodo; ?>" <?php echo ($_GET['periodo'] ?? 'diario') === $valorPeriodo ? 'selected' : ''; ?>><?php echo $nombrePeriodo; ?></option>
            <?php endforeach; ?>
        </select></label>
        <label>Desde<input name="desde" type="date" value="<?php echo htmlspecialchars($reporteCaja['desde'] ?? date('Y-m-d')); ?>"></label>
        <label>Hasta<input name="hasta" type="date" value="<?php echo htmlspecialchars($reporteCaja['hasta'] ?? date('Y-m-d')); ?>"></label>
        <label>Sucursal<select name="sucursal_id"><option value="">Todas las permitidas</option>
            <?php foreach ($sucursalesCaja as $sucursalCaja): ?>
                <option value="<?php echo (int) $sucursalCaja['id']; ?>" <?php echo (int) ($_GET['sucursal_id'] ?? 0) === (int) $sucursalCaja['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sucursalCaja['nombre']); ?></option>
            <?php endforeach; ?>
        </select></label>
        <button type="submit" class="btn-primary">Consultar</button>
    </form>
    <?php if ($errorCaja): ?><p role="alert"><?php echo htmlspecialchars($errorCaja); ?></p>
    <?php else: $totalesCaja = $reporteCaja['totales']; ?>
        <div class="kortzen-metricas">
            <?php foreach (['cortes'=>'Citas / cortes completados','servicios'=>'Servicios cobrados','productos'=>'Ventas de productos','propinas'=>'Propinas','total'=>'Total cobrado'] as $claveCaja => $nombreCaja): ?>
                <div class="kortzen-metrica"><?php echo $nombreCaja; ?><strong><?php echo $claveCaja === 'cortes' ? (int) $totalesCaja[$claveCaja] : '$' . number_format($totalesCaja[$claveCaja],2); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <h2 style="margin-top:24px;">Desglose por método de pago</h2>
        <div class="kortzen-tabla-scroll"><table class="kortzen-tabla"><thead><tr><th>Método</th><th>Cortes</th><th>Servicios</th><th>Productos</th><th>Propinas</th><th>Total recibido</th></tr></thead><tbody>
            <?php foreach ($reporteCaja['metodos'] as $filaCaja): ?>
                <tr><td><?php echo htmlspecialchars($filaCaja['nombre']); ?></td><td><?php echo (int) $filaCaja['cortes']; ?></td>
                    <?php foreach (['servicios','productos','propinas','total'] as $importeCaja): ?><td>$<?php echo number_format((float) $filaCaja[$importeCaja],2); ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reporteCaja['metodos']): ?><tr><td colspan="6">No hay cobros en el periodo seleccionado.</td></tr><?php endif; ?>
        </tbody></table></div>
        <p style="margin-top:14px;color:#666;font-size:.85rem;">Los importes incluyen propinas y productos una sola vez. Los cobros anteriores sin método registrado aparecen como «Sin especificar»; si tampoco tienen fecha de cobro, se usa la fecha de la cita. Este resumen corresponde a ingresos cobrados; no descuenta retiros, gastos, devoluciones ni liquidaciones de propinas.</p>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
