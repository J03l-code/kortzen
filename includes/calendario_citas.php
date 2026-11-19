<link rel="stylesheet" href="css/gestion-operativa.css">
<div data-gestion-calendario data-sucursal="<?php echo (int) ($_GET['sucursal_id'] ?? 0); ?>" data-csrf="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="kortzen-calendario-toolbar" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px;">
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
        <div style="display: flex; align-items: center; gap: 6px;">
            <button type="button" data-dia-anterior class="kortzen-cal-nav-btn" title="Día anterior" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid #E2E8F0; border-radius: 8px; background: #FFF; color: #111; cursor: pointer; transition: all 0.15s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #475569; margin: 0;">
                <input type="date" data-fecha-calendario style="height: 38px; min-height: 38px; border: 1.5px solid #E2E8F0; border-radius: 8px; padding: 0 10px; font-weight: 700; font-size: 0.82rem; background: #FFF; color: #111; cursor: pointer;" value="<?php echo htmlspecialchars($fecha ?: date('Y-m-d')); ?>">
            </label>
            <button type="button" data-dia-siguiente class="kortzen-cal-nav-btn" title="Día siguiente" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid #E2E8F0; border-radius: 8px; background: #FFF; color: #111; cursor: pointer; transition: all 0.15s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>
    </div>
    <div data-pendientes-citas class="kortzen-aviso" style="margin-bottom: 14px;" hidden>
        <strong style="color: #92400E; display: flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            Sin barbero asignado · Pendientes de reasignación
        </strong>
        <p style="margin: 4px 0 8px; font-size: 0.85rem; color: #78350F;">Las reservas conservan su fecha y servicio. Selecciona un barbero disponible para cada cita.</p>
        <div data-lista-pendientes></div>
    </div>
    <div class="kortzen-calendario-meta" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;">
        <p data-estado-calendario role="status" aria-live="polite" style="margin: 0; font-size: 0.82rem; font-weight: 700; color: #475569;"></p>
        <div class="kortzen-cal-scroll-hint" style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; color: #854D0E; background: #FEF9C3; padding: 4px 10px; border-radius: 20px; font-weight: 700; border: 1px solid #FEF08A;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>Desliza para ver más barberos</span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
    </div>
    <div data-cuadricula-calendario class="kortzen-calendario" hidden></div>
</div>
<script src="js/calendario-citas.js" defer></script>
