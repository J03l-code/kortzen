<?php
$vendedoresVenta = [];
try {
    $vendedoresVenta = getVendedoresVenta(getConnection());
} catch (Throwable $e) {
    error_log('No se pudo cargar el equipo de ventas: ' . $e->getMessage());
}
$vendedorSeleccionado = !empty($vendedorPreseleccionadoId) ? (int)$vendedorPreseleccionadoId : (empty($vendedorEsCobro) && ($_SESSION['rol'] ?? '') === 'barbero' ? (int)($_SESSION['user_id'] ?? 0) : 0);
?>
<div style="margin: 12px 0;">
    <label for="<?php echo htmlspecialchars($vendedorSelectId, ENT_QUOTES, 'UTF-8'); ?>" style="display: block; font-weight: 700; margin-bottom: 6px;">Vendedor</label>
    <select name="vendedor_id" id="<?php echo htmlspecialchars($vendedorSelectId, ENT_QUOTES, 'UTF-8'); ?>"
        <?php echo !empty($vendedorEsCobro) ? 'data-vendedor-cobro' : 'required'; ?>
        style="width: 100%; padding: 12px; border: 1px solid #C0A062; border-radius: 8px; background: #FFF; color: #111;">
        <option value=""><?php echo $vendedoresVenta ? 'Seleccionar vendedor...' : 'No se pudo cargar el equipo. Actualiza la página.'; ?></option>
        <?php foreach ($vendedoresVenta as $vendedor): ?>
            <option value="<?php echo (int) $vendedor['id']; ?>" <?php echo ($vendedorSeleccionado === (int) $vendedor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vendedor['nombre'] . ' (' . $vendedor['rol'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select>
    <small style="display: block; margin-top: 6px; color: #666;">Selecciona a quien realizó la venta de productos.</small>
</div>
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

<?php include __DIR__ . '/metodo_pago.php'; ?>
