<link rel="stylesheet" href="css/gestion-operativa.css">
<div data-gestion-calendario data-sucursal="<?php echo (int) ($_GET['sucursal_id'] ?? 0); ?>" data-csrf="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="kortzen-filtros">
        <button type="button" data-vista-citas="lista" class="btn-secondary" aria-pressed="true">Vista Lista</button>
        <button type="button" data-vista-citas="calendario" class="btn-secondary" aria-pressed="false">Vista Calendario</button>
        <label>Día del calendario<input type="date" data-fecha-calendario value="<?php echo htmlspecialchars($fecha ?: date('Y-m-d')); ?>"></label>
    </div>
    <div data-pendientes-citas class="kortzen-aviso" hidden>
        <strong>Sin barbero asignado · Pendientes de reasignación</strong>
        <p>Las reservas conservan su fecha y servicio. Selecciona un barbero disponible para cada cita.</p>
        <div data-lista-pendientes></div>
    </div>
    <p data-estado-calendario role="status" aria-live="polite"></p>
    <div data-cuadricula-calendario class="kortzen-calendario" hidden></div>
</div>
<script src="js/calendario-citas.js" defer></script>
