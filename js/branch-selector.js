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
        <div id="branch-modal" class="branch-modal-overlay" style="position: fixed; inset: 0; z-index: 100000; background: rgba(0, 0, 0, 0.88); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; padding: 1.5rem; animation: fadeIn 0.3s ease; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;">
            <div class="branch-modal-card" style="background: #0D0D0D; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; color: #FFFFFF; padding: 2.25rem; box-shadow: 0 30px 60px rgba(0, 0, 0, 0.95); position: relative;">
                
                <!-- BOTÓN X PARA CERRAR -->
                <button onclick="closeBranchModal()" aria-label="Cerrar" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: #888888; cursor: pointer; padding: 8px; transition: color 0.2s ease;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#888888'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 6px; opacity: 0.9;">
                        KORTZEN BARBERÍA
                    </div>
                    <h2 style="font-size: 1.65rem; font-weight: 800; margin: 0 0 8px 0; color: #FFFFFF; letter-spacing: 1px; text-transform: uppercase;">
                        SELECCIONA TU SUCURSAL
                    </h2>
                    <p style="font-size: 0.88rem; color: #888888; margin: 0; line-height: 1.5;">
                        Selecciona el local donde deseas agendar tu próximo corte o servicio.
                    </p>
                </div>

                <div class="branch-list" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- SUCURSALES ACTIVAS -->
                    ${activeBranches.map(branch => {
                        const isSelected = currentSelectedBranch && currentSelectedBranch.id == branch.id;
                        return `
                        <div class="branch-card ${isSelected ? 'is-selected' : ''}" 
                             onclick="selectBranchFromModal(${branch.id})"
                             style="background: #141414; border: 2px solid ${isSelected ? '#FFFFFF' : 'rgba(255, 255, 255, 0.1)'}; border-radius: 14px; padding: 1.35rem; cursor: pointer; transition: all 0.25s ease; position: relative;"
                             onmouseover="if(!${isSelected}) this.style.borderColor='rgba(255,255,255,0.4)'"
                             onmouseout="if(!${isSelected}) this.style.borderColor='rgba(255,255,255,0.1)'">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: #FFFFFF; letter-spacing: -0.2px;">${branch.name}</h3>
                                        <span style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.62rem; font-weight: 800; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">ABIERTO</span>
                                    </div>
                                    <p style="font-size: 0.84rem; color: #CCCCCC; margin: 0 0 8px 0; line-height: 1.4; display: flex; align-items: center; gap: 6px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" style="flex-shrink:0;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        ${branch.address}
                                    </p>
                                    <div style="display: flex; align-items: center; gap: 12px; font-size: 0.78rem; color: #888888;">
                                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            ${branch.openTime} - ${branch.closeTime}
                                        </span>
                                        <span>|</span>
                                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            ${branch.phone}
                                        </span>
                                    </div>
                                </div>
                                <button style="background: ${isSelected ? '#FFFFFF' : 'transparent'}; color: ${isSelected ? '#000000' : '#FFFFFF'}; border: 1px solid #FFFFFF; border-radius: 10px; padding: 10px 18px; font-size: 0.75rem; font-weight: 800; letter-spacing: 1px; cursor: pointer; white-space: nowrap; transition: all 0.2s ease; margin-top: 4px;">
                                    ${isSelected ? 'SELECCIONADO' : 'INGRESAR'}
                                </button>
                            </div>
                        </div>
                    `;
                    }).join('')}

                    <!-- SUCURSALES PRÓXIMAMENTE (NEGRO Y BLANCO ELEGANTE) -->
                    ${proximamenteBranches.map(branch => `
                        <div class="branch-card is-disabled" 
                             style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.2); border-radius: 14px; padding: 1.35rem; opacity: 0.7; position: relative; cursor: not-allowed;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: #FFFFFF;">${branch.name}</h3>
                                        <span style="background: rgba(255, 255, 255, 0.05); color: #AAAAAA; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.62rem; font-weight: 800; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            PRÓXIMAMENTE
                                        </span>
                                    </div>
                                    <p style="font-size: 0.84rem; color: #888888; margin: 0 0 6px 0; line-height: 1.4; display: flex; align-items: center; gap: 6px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#888888" stroke-width="2" style="flex-shrink:0;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        ${branch.address}
                                    </p>
                                    <p style="font-size: 0.78rem; color: #666666; margin: 0; font-style: italic;">
                                        Próximamente apertura de nueva sucursal. ¡Muy pronto cerca de ti!
                                    </p>
                                </div>
                                <button disabled style="background: rgba(255, 255, 255, 0.03); color: #555555; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 10px 16px; font-size: 0.72rem; font-weight: 700; cursor: not-allowed; white-space: nowrap; margin-top: 4px;">
                                    NO DISPONIBLE
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                ${currentSelectedBranch ? `
                    <div style="margin-top: 1.75rem; text-align: center;">
                        <button onclick="closeBranchModal()" style="background: transparent; color: #777777; border: none; font-size: 0.82rem; cursor: pointer; text-decoration: underline; font-family: inherit; transition: color 0.2s ease;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#777777'">
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
    sessionStorage.setItem('kortzen_branch_dismissed', 'true');
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
        <div class="branch-info-bar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 10001; background: #FFFFFF; color: #111111; border-bottom: 1px solid rgba(0, 0, 0, 0.08); height: 42px; display: flex; align-items: center; backdrop-filter: blur(10px); font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;">
            <div class="branch-info-bar__content" style="max-width: 1200px; width: 100%; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                
                <!-- SUCURSAL SELECTOR (BOTÓN ULTRA LUXURY Y MINIMALISTA) -->
                <div onclick="window.KortzenBranches.showSelector()" 
                     style="display: flex; align-items: center; gap: 0.4rem; color: #111111; cursor: pointer; user-select: none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="font-weight: 700; letter-spacing: -0.2px;">${branch.name}</span>
                    <span style="border: 1px solid rgba(0, 0, 0, 0.22); background: transparent; color: #111111; font-size: 0.66rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; padding: 2px 8px; border-radius: 4px; margin-left: 6px; display: inline-flex; align-items: center; gap: 3px; transition: all 0.25s ease;"
                          onmouseover="this.style.background='#111111'; this.style.color='#FFFFFF'; this.style.borderColor='#111111';"
                          onmouseout="this.style.background='transparent'; this.style.color='#111111'; this.style.borderColor='rgba(0, 0, 0, 0.22)';">
                        <span>CAMBIAR</span>
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </span>
                </div>

                <!-- DETALLES: HORARIO, TELÉFONO Y USUARIO -->
                <div class="branch-info-bar__details" style="display: flex; align-items: center; gap: 1.5rem; color: #555555;">
                    <span style="display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        ${branch.openTime} - ${branch.closeTime}
                    </span>
                    <span style="display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
    
    // Obtener sucursal activa
    getSelectedBranch();

    const isDashboardPage = window.location.pathname.includes('barber-dashboard') || document.body.classList.contains('pwa-app-mode');

    // Desplegar modal automáticamente al cargar la página si no se ha seleccionado en esta sesión
    if (!isDashboardPage && !sessionStorage.getItem('kortzen_branch_dismissed')) {
        setTimeout(() => {
            createBranchSelectorModal();
        }, 300);
    }

    // Actualizar la barra superior informativa
    if (!isDashboardPage) {
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
