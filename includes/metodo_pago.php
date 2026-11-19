<fieldset class="kortzen-pagos" style="border: 1px solid #C0A062; border-radius: 10px; padding: 12px; margin: 12px 0;">
    <legend style="font-weight: 800; padding: 0 6px;">Método de pago</legend>
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
        <?php foreach (metodosPagoKortzen() as $clavePago => $nombrePago): ?>
            <label style="display: flex; align-items: center; gap: 6px; padding: 10px; border: 1px solid #DDD; border-radius: 8px; cursor: pointer; background: #FFF; color: #111;">
                <input type="radio" name="metodo_pago" style="width:auto;min-height:auto" value="<?php echo $clavePago; ?>" required>
                <?php echo htmlspecialchars($nombrePago, ENT_QUOTES, 'UTF-8'); ?>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
