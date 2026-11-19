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
<div class="page-header" style="margin-bottom: 20px;">
    <div>
        <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 800; color: #111;">Cierre de Caja y Contabilidad</h1>
        <p class="page-subtitle" style="margin: 4px 0 0; font-size: 0.85rem; color: #6B7280;">Resumen financiero de citas, servicios, productos y métodos de cobro</p>
    </div>
</div>
<div class="kortzen-panel">
    <form method="get" class="kortzen-filtros">
        <label>Periodo
            <select name="periodo" onchange="this.form.desde.value='';this.form.hasta.value='';this.form.submit()">
                <?php foreach (['diario'=>'Diario','semanal'=>'Semanal','mensual'=>'Mensual'] as $valorPeriodo => $nombrePeriodo): ?>
                    <option value="<?php echo $valorPeriodo; ?>" <?php echo ($_GET['periodo'] ?? 'diario') === $valorPeriodo ? 'selected' : ''; ?>><?php echo $nombrePeriodo; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde
            <input name="desde" type="date" value="<?php echo htmlspecialchars($reporteCaja['desde'] ?? date('Y-m-d')); ?>">
        </label>
        <label>Hasta
            <input name="hasta" type="date" value="<?php echo htmlspecialchars($reporteCaja['hasta'] ?? date('Y-m-d')); ?>">
        </label>
        <label>Sucursal
            <select name="sucursal_id">
                <option value="">Todas las permitidas</option>
                <?php foreach ($sucursalesCaja as $sucursalCaja): ?>
                    <option value="<?php echo (int) $sucursalCaja['id']; ?>" <?php echo (int) ($_GET['sucursal_id'] ?? 0) === (int) $sucursalCaja['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sucursalCaja['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <span>Consultar</span>
        </button>
    </form>
    <?php if ($errorCaja): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;" role="alert">
            <?php echo htmlspecialchars($errorCaja); ?>
        </div>
    <?php else: $totalesCaja = $reporteCaja['totales']; ?>
        <div class="kortzen-metricas">
            <div class="kortzen-metrica kortzen-metrica-hero" data-metrica="total">
                <span class="kortzen-metrica-label">Total Cobrado (Ingreso Neto)</span>
                <strong>$<?php echo number_format($totalesCaja['total'], 2); ?></strong>
            </div>
            <div class="kortzen-metrica" data-metrica="cortes">
                <span class="kortzen-metrica-label">Citas / Cortes completados</span>
                <strong><?php echo (int) $totalesCaja['cortes']; ?></strong>
            </div>
            <div class="kortzen-metrica" data-metrica="servicios">
                <span class="kortzen-metrica-label">Servicios cobrados</span>
                <strong>$<?php echo number_format($totalesCaja['servicios'], 2); ?></strong>
            </div>
            <div class="kortzen-metrica" data-metrica="productos">
                <span class="kortzen-metrica-label">Ventas de productos</span>
                <strong>$<?php echo number_format($totalesCaja['productos'], 2); ?></strong>
            </div>
            <div class="kortzen-metrica" data-metrica="propinas">
                <span class="kortzen-metrica-label">Propinas</span>
                <strong>$<?php echo number_format($totalesCaja['propinas'], 2); ?></strong>
            </div>
        </div>
        <div class="kortzen-seccion-header">
            <h2 style="margin: 0;">Desglose por método de pago</h2>
            <span class="kortzen-badge-count"><?php echo count($reporteCaja['metodos']); ?> métodos</span>
        </div>
        <div class="kortzen-tabla-scroll">
            <table class="kortzen-tabla">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Cortes</th>
                        <th>Servicios</th>
                        <th>Productos</th>
                        <th>Propinas</th>
                        <th>Total recibido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporteCaja['metodos'] as $filaCaja): ?>
                        <tr>
                            <td style="font-weight: 700; color: #111;">
                                <?php echo htmlspecialchars($filaCaja['nombre']); ?>
                            </td>
                            <td><?php echo (int) $filaCaja['cortes']; ?></td>
                            <?php foreach (['servicios','productos','propinas','total'] as $importeCaja): ?>
                                <td>$<?php echo number_format((float) $filaCaja[$importeCaja], 2); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reporteCaja['metodos'])): ?>
                        <tr>
                            <td colspan="6" class="kortzen-tabla-vacia">
                                <div class="kortzen-empty-state">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                                    <span>No hay cobros en el periodo seleccionado.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="kortzen-nota">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <div>
                Los importes incluyen propinas y productos una sola vez. Los cobros anteriores sin método registrado aparecen como «Sin especificar»; si tampoco tienen fecha de cobro, se usa la fecha de la cita. Este resumen corresponde a ingresos cobrados; no descuenta retiros, gastos, devoluciones ni liquidaciones de propinas.
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
