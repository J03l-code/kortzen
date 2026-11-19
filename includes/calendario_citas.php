<link rel="stylesheet" href="css/gestion-operativa.css">
<div data-gestion-calendario data-sucursal="<?php echo (int) ($_GET['sucursal_id'] ?? 0); ?>" data-csrf="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="kortzen-calendario-toolbar">
        <div class="kortzen-view-switcher" role="tablist">
            <button type="button" data-vista-citas="lista" class="kortzen-view-btn btn-secondary" aria-pressed="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <circle cx="4" cy="6" r="1.5"></circle>
                    <circle cx="4" cy="12" r="1.5"></circle>
                    <circle cx="4" cy="18" r="1.5"></circle>
                </svg>
                <span>Vista Lista</span>
            </button>
            <button type="button" data-vista-citas="calendario" class="kortzen-view-btn btn-secondary" aria-pressed="false">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Vista Calendario</span>
            </button>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #475569;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span>Día:</span>
                <input type="date" data-fecha-calendario style="height: 38px; min-height: 38px; border: 1.5px solid #E2E8F0; border-radius: 8px; padding: 0 10px; font-weight: 600; font-size: 0.85rem; background: #FFF; color: #111;" value="<?php echo htmlspecialchars($fecha ?: date('Y-m-d')); ?>">
            </label>
        </div>
    </div>
    <div data-pendientes-citas class="kortzen-aviso" hidden>
        <strong>Sin barbero asignado · Pendientes de reasignación</strong>
        <p style="margin: 4px 0 8px; font-size: 0.85rem;">Las reservas conservan su fecha y servicio. Selecciona un barbero disponible para cada cita.</p>
        <div data-lista-pendientes></div>
    </div>
    <div class="kortzen-calendario-meta">
        <p data-estado-calendario role="status" aria-live="polite" style="margin: 0;"></p>
        <div class="kortzen-cal-scroll-hint">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>Desliza para ver más barberos</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
    </div>
    <div data-cuadricula-calendario class="kortzen-calendario" hidden></div>
</div>
<script src="js/calendario-citas.js" defer></script>
