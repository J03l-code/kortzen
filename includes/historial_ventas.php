<?php
$equipoHistorial = query("SELECT id, nombre, activo FROM usuarios UNION ALL SELECT v.usuario_id AS id, COALESCE(MAX(v.vendedor_nombre),'Usuario eliminado') AS nombre, 0 AS activo FROM ventas_productos v LEFT JOIN usuarios u ON u.id=v.usuario_id WHERE u.id IS NULL GROUP BY v.usuario_id ORDER BY nombre");
if (!esAdministradorCaja($currentUser)) $equipoHistorial = array_values(array_filter($equipoHistorial, fn ($p) => (int) $p['id'] === (int) $currentUser['id']));
?>
<div data-historial-ventas class="kortzen-panel">
    <h2>Historial de Ventas de Productos</h2>
    <form data-filtros-ventas class="kortzen-filtros">
        <label>Desde<input type="date" name="desde" value="<?php echo date('Y-m-01'); ?>" required></label>
        <label>Hasta<input type="date" name="hasta" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label>Vendedor<select name="vendedor_id"><option value=""><?php echo esAdministradorCaja($currentUser) ? 'Todos los vendedores' : 'Mis ventas'; ?></option>
            <?php foreach ($equipoHistorial as $personaHistorial): ?>
                <option value="<?php echo (int) $personaHistorial['id']; ?>"><?php echo htmlspecialchars($personaHistorial['nombre'] . ($personaHistorial['activo'] ? '' : ' (inactivo)'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select></label>
        <button type="submit" class="btn-primary">Filtrar</button>
        <a data-exportar-ventas="xlsx" class="btn-secondary">Descargar Excel</a>
        <a data-exportar-ventas="pdf" class="btn-secondary">Descargar PDF</a>
    </form>
    <p data-estado-ventas role="status" aria-live="polite">Cargando historial…</p>
    <div class="kortzen-tabla-scroll"><table class="kortzen-tabla">
        <thead><tr><th>Fecha / Hora</th><th>Producto</th><th>Cantidad</th><th>Total cobrado</th><th>Vendedor</th><th>Sucursal</th><th>Método de pago</th></tr></thead>
        <tbody data-filas-ventas></tbody>
    </table></div>
    <div class="kortzen-filtros" style="margin-top: 14px;">
        <button type="button" data-pagina-ventas="-1" class="btn-secondary">Anterior</button>
        <span data-paginacion-ventas></span>
        <button type="button" data-pagina-ventas="1" class="btn-secondary">Siguiente</button>
    </div>
</div>
