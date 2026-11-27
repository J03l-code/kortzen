<?php
$productosModalCb = [];
$barberosModalCb = [];
try {
    $pdoCb = getConnection();
    $productosModalCb = $pdoCb->query("SELECT i.id, i.producto, i.cantidad, COALESCE(i.precio, 0) AS precio, s.nombre AS sucursal FROM inventario i LEFT JOIN sucursales s ON s.id = i.sucursal_id WHERE i.cantidad > 0 ORDER BY i.producto ASC")->fetchAll(PDO::FETCH_ASSOC);
    $barberosModalCb = getVendedoresVenta($pdoCb);
} catch (Throwable $e) {}
$csrfModalCb = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
?>
<div id="modalCompraBarberoGlobal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 10020; align-items: center; justify-content: center; padding: 14px;" onclick="if(event.target===this) this.style.display='none'">
    <div style="background: #FFF; border-radius: 14px; max-width: 460px; width: 100%; padding: 22px; box-shadow: 0 24px 48px rgba(0,0,0,0.25); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 900; color: #111827;">Venta a Barbero / Personal</h3>
                <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748B;">Descuenta del stock y se registra para descontar de su rol.</p>
            </div>
            <button type="button" onclick="document.getElementById('modalCompraBarberoGlobal').style.display='none'" style="background: #F1F5F9; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-weight: 800;">✕</button>
        </div>
        <form id="formCompraBarberoGlobal" onsubmit="enviarCompraBarberoGlobal(event)" style="display: flex; flex-direction: column; gap: 12px;">
            <input type="hidden" name="action" value="registrar">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfModalCb; ?>">

            <label style="font-size: 0.75rem; font-weight: 800; color: #334155; display: flex; flex-direction: column; gap: 4px;">
                BARBERO / PERSONAL COMPRADOR *
                <select name="barbero_id" required style="padding: 10px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 700; font-size: 0.85rem;">
                    <option value="">Seleccionar barbero / personal...</option>
                    <?php foreach ($barberosModalCb as $bm): ?>
                        <option value="<?php echo (int) $bm['id']; ?>"><?php echo htmlspecialchars($bm['nombre'] . ' (' . $bm['rol'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label style="font-size: 0.75rem; font-weight: 800; color: #334155; display: flex; flex-direction: column; gap: 4px;">
                PRODUCTO *
                <select name="producto_id" id="cbGlobalProducto" required onchange="actualizarPrecioSugeridoCbGlobal(this)" style="padding: 10px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 700; font-size: 0.85rem;">
                    <option value="" data-precio="0">Seleccionar producto...</option>
                    <?php foreach ($productosModalCb as $pm): ?>
                        <option value="<?php echo (int) $pm['id']; ?>" data-precio="<?php echo number_format((float) $pm['precio'], 2, '.', ''); ?>">
                            <?php echo htmlspecialchars($pm['producto'] . ' (' . ($pm['sucursal'] ?: 'General') . ' · Stock: ' . $pm['cantidad'] . ') - PVP $' . number_format((float) $pm['precio'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #334155; display: flex; flex-direction: column; gap: 4px;">
                    CANTIDAD *
                    <input type="number" name="cantidad" id="cbGlobalCantidad" min="1" value="1" required oninput="actualizarTotalCbGlobal()" style="padding: 10px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-weight: 800; font-size: 0.9rem;">
                </label>
                <label style="font-size: 0.75rem; font-weight: 800; color: #92400E; display: flex; flex-direction: column; gap: 4px;">
                    PRECIO BARBERO ($) *
                    <input type="number" step="0.01" min="0" name="precio_barbero" id="cbGlobalPrecio" required oninput="actualizarTotalCbGlobal()" placeholder="0.00" style="padding: 10px; border-radius: 8px; border: 1.5px solid #F59E0B; font-weight: 900; font-size: 0.9rem; color: #92400E; background: #FFFBEB;">
                </label>
            </div>

            <label style="font-size: 0.75rem; font-weight: 800; color: #334155; display: flex; flex-direction: column; gap: 4px;">
                NOTAS (OPCIONAL)
                <input type="text" name="notas" placeholder="Ej: Uso personal / descuento en rol semanal" style="padding: 9px 10px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 0.85rem;">
            </label>

            <div style="background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 10px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8rem; font-weight: 800; color: #92400E;">TOTAL A DESCONTAR EN ROL:</span>
                <strong id="cbGlobalTotal" style="font-size: 1.2rem; font-weight: 900; color: #B45309;">$0.00</strong>
            </div>

            <div style="display: flex; gap: 8px; margin-top: 4px;">
                <button type="button" onclick="document.getElementById('modalCompraBarberoGlobal').style.display='none'" style="flex: 1; padding: 11px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #FFF; font-weight: 700; cursor: pointer;">Cancelar</button>
                <button type="submit" id="btnSubmitCbGlobal" style="flex: 2; padding: 11px; border-radius: 8px; border: none; background: #1E293B; color: #FFF; font-weight: 900; cursor: pointer;">Registrar en Rol</button>
            </div>
        </form>
    </div>
</div>
<script>
function actualizarPrecioSugeridoCbGlobal(sel) {
    const opt = sel.options[sel.selectedIndex];
    const pvp = parseFloat(opt?.dataset?.precio || '0') || 0;
    const inp = document.getElementById('cbGlobalPrecio');
    if (inp) inp.value = pvp.toFixed(2);
    actualizarTotalCbGlobal();
}
function actualizarTotalCbGlobal() {
    const cant = parseInt(document.getElementById('cbGlobalCantidad')?.value || '1', 10) || 0;
    const precio = parseFloat(document.getElementById('cbGlobalPrecio')?.value || '0') || 0;
    const totalEl = document.getElementById('cbGlobalTotal');
    if (totalEl) totalEl.textContent = '$' + (cant * precio).toFixed(2);
}
async function enviarCompraBarberoGlobal(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCbGlobal');
    btn.disabled = true;
    try {
        const resp = await fetch('api/compras_barberos.php', { method: 'POST', body: new FormData(e.target) });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Error al registrar');
        document.getElementById('modalCompraBarberoGlobal').style.display = 'none';
        e.target.reset();
        actualizarTotalCbGlobal();
        if (typeof window.recargarComprasBarberos === 'function') window.recargarComprasBarberos();
        alert(data.message);
        window.location.reload();
    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
    }
}
</script>
