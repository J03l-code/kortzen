<?php
$barberosCompraList = [];
try {
    $pdoTmp = getConnection();
    $barberosCompraList = getVendedoresVenta($pdoTmp);
} catch (Throwable $e) {
    $barberosCompraList = [];
}
$csrfCompras = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
?>
<div data-compras-barberos-panel data-csrf="<?php echo $csrfCompras; ?>">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
        <div>
            <h3 style="margin: 0; font-size: 1.08rem; font-weight: 900; color: #111827;">Compras de Barberos (Descuento en Rol)</h3>
            <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748B;">Productos retirados por barberos o personal a precio especial para cruzar contra su liquidación.</p>
        </div>
        <button type="button" data-abrir-venta-barbero class="btn-primary" style="background: #1E293B; color: #FFF; border: none; padding: 9px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer;">
            + Venta a Barbero / Personal
        </button>
    </div>

    <div class="kortzen-filtros" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 14px; align-items: end;">
        <label style="font-size: 0.72rem; font-weight: 700; color: #475569; display: flex; flex-direction: column; gap: 4px;">Desde
            <input type="date" data-cb-desde value="<?php echo date('Y-m-01'); ?>" style="height: 38px; border-radius: 8px; border: 1.5px solid #E2E8F0; padding: 0 8px; font-weight: 700; font-size: 0.8rem; background: #FFF;">
        </label>
        <label style="font-size: 0.72rem; font-weight: 700; color: #475569; display: flex; flex-direction: column; gap: 4px;">Hasta
            <input type="date" data-cb-hasta value="<?php echo date('Y-m-d'); ?>" style="height: 38px; border-radius: 8px; border: 1.5px solid #E2E8F0; padding: 0 8px; font-weight: 700; font-size: 0.8rem; background: #FFF;">
        </label>
        <label style="font-size: 0.72rem; font-weight: 700; color: #475569; display: flex; flex-direction: column; gap: 4px;">Barbero / Personal
            <select data-cb-barbero style="height: 38px; border-radius: 8px; border: 1.5px solid #E2E8F0; padding: 0 8px; font-weight: 700; font-size: 0.8rem; background: #FFF;">
                <option value="">Todos</option>
                <?php foreach ($barberosCompraList as $bc): ?>
                    <option value="<?php echo (int) $bc['id']; ?>"><?php echo htmlspecialchars($bc['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="font-size: 0.72rem; font-weight: 700; color: #475569; display: flex; flex-direction: column; gap: 4px;">Estado en Rol
            <select data-cb-estado style="height: 38px; border-radius: 8px; border: 1.5px solid #E2E8F0; padding: 0 8px; font-weight: 700; font-size: 0.8rem; background: #FFF;">
                <option value="">Todos</option>
                <option value="pendiente_descuento">Pendiente de descontar</option>
                <option value="descontado">Descontado en rol</option>
            </select>
        </label>
        <button type="button" data-cb-buscar style="height: 38px; background: #111; color: #FFF; border: none; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer;">Consultar</button>
    </div>

    <div data-cb-resumen style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 14px;"></div>

    <div style="overflow-x: auto; border: 1px solid #E5E7EB; border-radius: 10px; background: #FFF;">
        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
            <thead>
                <tr style="background: #F8FAFC; text-align: left;">
                    <th style="padding: 10px 12px;">Fecha</th>
                    <th style="padding: 10px 12px;">Barbero / Personal</th>
                    <th style="padding: 10px 12px;">Producto</th>
                    <th style="padding: 10px 12px;">Cant.</th>
                    <th style="padding: 10px 12px;">Precio Barbero</th>
                    <th style="padding: 10px 12px;">Total Rol</th>
                    <th style="padding: 10px 12px;">Sucursal</th>
                    <th style="padding: 10px 12px;">Estado Rol</th>
                    <th style="padding: 10px 12px;">Acción</th>
                </tr>
            </thead>
            <tbody data-cb-tbody>
                <tr><td colspan="9" style="padding: 20px; text-align: center; color: #64748B;">Cargando compras de barberos...</td></tr>
            </tbody>
        </table>
    </div>
</div>
