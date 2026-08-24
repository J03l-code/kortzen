/**
 * KORTZEN - Selector de Sucursales Dinámico (Activas & Próximamente)
 */

const BRANCH_STORAGE_KEY = 'kortzen_selected_branch';
let cachedBranches = [];
let currentSelectedBranch = null;

/**
 * Cargar sucursales desde la API backend
 */
async function fetchBranches() {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3500);

        const response = await fetch('/api/get_sucursales.php', { signal: controller.signal });
        clearTimeout(timeoutId);
        const json = await response.json();
        if (json.success && json.data && json.data.length > 0) {
            cachedBranches = json.data;
            return cachedBranches;
        }
    } catch (e) {
        console.warn('Error o timeout al cargar sucursales:', e);
    }

    // Fallback por defecto si no hay conexión
    cachedBranches = [
        {
            id: 1,
            name: "KORTZEN Llano Chico",
            address: "Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito",
            phone: "+593 098 842 2770",
            openTime: "09:00",
            closeTime: "20:00",
            isProximamente: false,
            estado: "activo"
        }
    ];
    return cachedBranches;
}

/**
 * Obtener la sucursal seleccionada actualmente
 */
function getSelectedBranch() {
    if (currentSelectedBranch) return currentSelectedBranch;

    const savedId = localStorage.getItem(BRANCH_STORAGE_KEY);
    if (savedId && cachedBranches.length > 0) {
        const found = cachedBranches.find(b => b.id == savedId && !b.isProximamente && b.estado === 'activo');
        if (found) {
            currentSelectedBranch = found;
            return currentSelectedBranch;
        }
    }

    // Seleccionar la primera activa si existe
    const firstActive = cachedBranches.find(b => !b.isProximamente && b.estado === 'activo') || cachedBranches[0];
    if (firstActive) {
        currentSelectedBranch = firstActive;
        localStorage.setItem(BRANCH_STORAGE_KEY, firstActive.id);
    }
    return currentSelectedBranch;
}

/**
 * Guardar selección de sucursal
 */
function setSelectedBranch(branchId) {
    const branch = cachedBranches.find(b => b.id == branchId);
    if (!branch || branch.isProximamente || branch.estado === 'inactivo') return false;

    currentSelectedBranch = branch;
    localStorage.setItem(BRANCH_STORAGE_KEY, branch.id);
    updateBranchInfoBar();

    // Emitir evento personalizado para recalcular barberos y servicios en reservar.php
    window.dispatchEvent(new CustomEvent('kortzen:branchChanged', { detail: branch }));
    return true;
}

/**
 * Crear e inyectar el Modal Selector de Sucursales
 */
function createBranchSelectorModal() {
    closeBranchModal();

    const activeBranches = cachedBranches.filter(b => !b.isProximamente && b.estado === 'activo');
    const proximamenteBranches = cachedBranches.filter(b => b.isProximamente || b.estado === 'proximamente');

    const modalHTML = `
        <div id="branch-modal" class="branch-modal-overlay" style="position: fixed; inset: 0; z-index: 100000; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 1.5rem; animation: fadeIn 0.3s ease;">
            <div class="branch-modal-card" style="background: #111111; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 16px; max-width: 580px; width: 100%; max-height: 90vh; overflow-y: auto; color: #FFFFFF; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);">
                
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="display: inline-block; padding: 6px 16px; background: rgba(255, 255, 255, 0.08); border-radius: 20px; font-size: 0.75rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #D4AF37; margin-bottom: 12px;">
                        KORTZEN BARBERÍA
                    </div>
                    <h2 style="font-size: 1.6rem; font-weight: 700; margin: 0 0 8px 0; color: #FFFFFF; letter-spacing: -0.5px;">
                        SELECCIONA TU SUCURSAL
                    </h2>
                    <p style="font-size: 0.9rem; color: #888888; margin: 0;">
                        Selecciona el local donde deseas agendar tu próximo corte o servicio.
                    </p>
                </div>

                <div class="branch-list" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- SUCURSALES ACTIVAS -->
                    ${activeBranches.map(branch => `
                        <div class="branch-card ${currentSelectedBranch && currentSelectedBranch.id == branch.id ? 'is-selected' : ''}" 
                             onclick="selectBranchFromModal(${branch.id})"
                             style="background: #1A1A1A; border: 2px solid ${currentSelectedBranch && currentSelectedBranch.id == branch.id ? '#FFFFFF' : 'rgba(255, 255, 255, 0.1)'}; border-radius: 12px; padding: 1.25rem; cursor: pointer; transition: all 0.25s ease; position: relative; overflow: hidden;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: #FFFFFF;">${branch.name}</h3>
                                        <span style="background: rgba(46, 204, 113, 0.2); color: #2ECC71; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">ABIERTO</span>
                                    </div>
                                    <p style="font-size: 0.82rem; color: #AAAAAA; margin: 6px 0 0 0; line-height: 1.4;">
                                        📍 ${branch.address}
                                    </p>
                                    <p style="font-size: 0.8rem; color: #777777; margin: 4px 0 0 0;">
                                        🕒 Horario: ${branch.openTime} - ${branch.closeTime} | 📞 ${branch.phone}
                                    </p>
                                </div>
                                <button style="background: ${currentSelectedBranch && currentSelectedBranch.id == branch.id ? '#FFFFFF' : 'transparent'}; color: ${currentSelectedBranch && currentSelectedBranch.id == branch.id ? '#000000' : '#FFFFFF'}; border: 1px solid #FFFFFF; border-radius: 8px; padding: 8px 16px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; cursor: pointer; white-space: nowrap; transition: all 0.2s ease;">
                                    ${currentSelectedBranch && currentSelectedBranch.id == branch.id ? 'SELECCIONADO ✓' : 'INGRESAR'}
                                </button>
                            </div>
                        </div>
                    `).join('')}

                    <!-- SUCURSALES PRÓXIMAMENTE (DESHABILITADAS / PRÓXIMA APERTURA) -->
                    ${proximamenteBranches.map(branch => `
                        <div class="branch-card is-disabled" 
                             style="background: rgba(255, 255, 255, 0.03); border: 1px dashed rgba(212, 175, 55, 0.4); border-radius: 12px; padding: 1.25rem; opacity: 0.85; position: relative; cursor: not-allowed;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0; color: #E5C158;">${branch.name}</h3>
                                        <span style="background: rgba(212, 175, 55, 0.2); color: #E5C158; border: 1px solid rgba(212, 175, 55, 0.5); font-size: 0.65rem; font-weight: 800; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            ⏳ PRÓXIMAMENTE
                                        </span>
                                    </div>
                                    <p style="font-size: 0.82rem; color: #888888; margin: 6px 0 0 0; line-height: 1.4;">
                                        📍 ${branch.address}
                                    </p>
                                    <p style="font-size: 0.78rem; color: #D4AF37; margin: 6px 0 0 0; font-style: italic;">
                                        ✨ Próximamente apertura de nueva sucursal. ¡Muy pronto cerca de ti!
                                    </p>
                                </div>
                                <button disabled style="background: rgba(255, 255, 255, 0.05); color: #666666; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 8px 14px; font-size: 0.72rem; font-weight: 600; cursor: not-allowed; white-space: nowrap;">
                                    NO DISPONIBLE
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                ${currentSelectedBranch ? `
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <button onclick="closeBranchModal()" style="background: transparent; color: #888888; border: none; font-size: 0.85rem; cursor: pointer; text-decoration: underline;">
                            Continuar navegación con ${currentSelectedBranch.name}
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Seleccionar sucursal desde el modal
 */
function selectBranchFromModal(branchId) {
    if (setSelectedBranch(branchId)) {
        closeBranchModal();
    }
}

/**
 * Cerrar modal de sucursal
 */
function closeBranchModal() {
    const modal = document.getElementById('branch-modal');
    if (modal) modal.remove();
}

/**
 * Actualizar la barra superior informativa de sucursal
 */
function updateBranchInfoBar() {
    const branch = getSelectedBranch();
    if (!branch) return;

    const existingBar = document.querySelector('.branch-info-bar');
    if (existingBar) existingBar.remove();

    const barHTML = `
        <div class="branch-info-bar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 10001; background: rgba(255, 255, 255, 0.98); color: #111111; border-bottom: 1px solid rgba(0, 0, 0, 0.08); height: 42px; display: flex; align-items: center; backdrop-filter: blur(10px);">
            <div class="branch-info-bar__content" style="max-width: 1200px; width: 100%; margin: 0 auto; padding: 0 1.25rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                <div class="branch-info-bar__location" style="display: flex; align-items: center; gap: 0.5rem; color: #111111;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <button onclick="window.KortzenBranches.showSelector()" 
                            style="background: transparent; border: 1px solid rgba(0,0,0,0.12); padding: 3px 10px; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 0.82rem; font-weight: 700; color: #111111; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;"
                            onmouseover="this.style.background='rgba(0,0,0,0.05)'" 
                            onmouseout="this.style.background='transparent'">
                        <span>${branch.name}</span>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left: 2px;">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>
                <div class="branch-info-bar__details" style="display: flex; align-items: center; gap: 1.25rem; color: #555555;">
                    <span class="branch-info-bar__item" style="display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        ${branch.openTime} - ${branch.closeTime}
                    </span>
                    <span class="branch-info-bar__item" style="display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${branch.phone}
                    </span>
                    <div id="header-user-profile-badge" style="display: inline-flex; align-items: center; gap: 6px;"></div>
                </div>
            </div>
        </div>
    `;

    const header = document.querySelector('.header');
    if (header) {
        header.insertAdjacentHTML('beforebegin', barHTML);
        document.body.classList.add('has-branch-bar');
        header.style.top = '42px';
    }

    // Auto-cargar perfil del usuario si está iniciada la sesión
    fetch('/api/get_client_profile.php')
        .then(r => r.json())
        .then(res => {
            if (res.success && res.cliente) {
                const badge = document.getElementById('header-user-profile-badge');
                if (badge) {
                    const firstName = res.cliente.nombre.split(' ')[0];
                    const initial = firstName.charAt(0).toUpperCase();
                    const fotoHtml = res.cliente.foto ? 
                        `<img src="${res.cliente.foto}" style="width:22px; height:22px; border-radius:50%; object-fit:cover;" alt="Avatar">` :
                        `<div style="width:22px; height:22px; border-radius:50%; background:#111; color:#fff; font-size:0.68rem; font-weight:800; display:flex; align-items:center; justify-content:center;">${initial}</div>`;

                    badge.innerHTML = `
                        <span style="border-left: 1px solid rgba(0,0,0,0.12); height: 16px; margin: 0 4px;"></span>
                        <div style="display:flex; align-items:center; gap:6px; color:#111; font-weight:700;">
                            ${fotoHtml}
                            <span>${firstName}</span>
                        </div>
                    `;
                }
            }
        }).catch(() => {});
}

/**
 * Inicializar selector de sucursales
 */
async function initBranchSelector() {
    await fetchBranches();
    const savedId = localStorage.getItem(BRANCH_STORAGE_KEY);

    // Si es la primera visita o no hay sucursal guardada válida, abrir el modal selector automáticamente
    if (!savedId || !cachedBranches.some(b => b.id == savedId && !b.isProximamente)) {
        getSelectedBranch(); // Seleccionar por defecto pero mostrar modal
        createBranchSelectorModal();
    } else {
        getSelectedBranch();
    }

    // Si no estamos en el panel de barbero, actualizar barra superior
    if (!window.location.pathname.includes('barber-dashboard') && !document.body.classList.contains('pwa-app-mode')) {
        updateBranchInfoBar();
    }
}

// Exponer en objeto global
window.KortzenBranches = {
    init: initBranchSelector,
    showSelector: function() {
        createBranchSelectorModal();
    },
    getSelected: getSelectedBranch,
    setSelected: setSelectedBranch,
    loadAll: function() { return cachedBranches; },
    updateInfoBar: updateBranchInfoBar
};

// Iniciar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    initBranchSelector();
});
