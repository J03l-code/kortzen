/**
 * KORTZEN - Selector de Sucursal
 * Muestra un modal para que los visitantes seleccionen su sucursal preferida
 */

const BRANCH_STORAGE_KEY = 'kortzen_selected_branch';

// Sucursales por defecto (se sincronizarán con el dashboard si hay datos)
const DEFAULT_BRANCHES = [
    {
        id: 1,
        name: "KORTZEN Llano Chico",
        address: "Calle 17 de septiembre, frente a la casa de colchon Llano Chico",
        phone: "+593 098 842 2770",
        openTime: "10:00",
        closeTime: "20:00",
        mapUrl: "https://maps.app.goo.gl/KRoz9HfjNnZyMtPk9"
    },
    {
        id: 2,
        name: "KORTZEN Quito",
        address: "Quito-Ecuador",
        phone: "+593 098 842 2770",
        openTime: "10:00",
        closeTime: "20:00",
        mapUrl: "https://maps.app.goo.gl/KRoz9HfjNnZyMtPk9"
    }
];

/**
 * Carga las sucursales desde la API PHP o localStorage
 */
async function loadBranchesFromAPI() {
    // Try PHP API first
    if (window.KortzenAPI) {
        try {
            const branches = await KortzenAPI.getBranches();
            if (branches && branches.length > 0) {
                console.log('✅ Branches loaded from API:', branches.length);
                return branches.map(b => ({
                    ...b,
                    mapUrl: `https://maps.google.com/?q=${encodeURIComponent(b.address)}`
                }));
            }
        } catch (e) {
            console.warn('⚠️ Branches API not available:', e);
        }
    }
    return loadBranchesFromLocalStorage();
}

/**
 * Carga las sucursales desde localStorage o usa las por defecto
 */
function loadBranches() {
    return loadBranchesFromLocalStorage();
}

function loadBranchesFromLocalStorage() {
    try {
        const dashboardData = localStorage.getItem('kortzen_db');
        if (dashboardData) {
            const parsed = JSON.parse(dashboardData);
            if (parsed.branches && parsed.branches.length > 0) {
                return parsed.branches.filter(b => b.status === 'active').map(b => ({
                    ...b,
                    mapUrl: b.mapUrl || `https://maps.google.com/?q=${encodeURIComponent(b.address)}`
                }));
            }
        }
    } catch (e) {
        console.warn('Error loading branches:', e);
    }
    return [...DEFAULT_BRANCHES];
}

/**
 * Obtiene la sucursal seleccionada guardada
 */
function getSelectedBranch() {
    try {
        const stored = localStorage.getItem(BRANCH_STORAGE_KEY);
        if (stored) {
            const branchId = parseInt(stored);
            const branches = loadBranches();
            return branches.find(b => b.id === branchId) || branches[0];
        }
    } catch (e) {
        console.warn('Error getting selected branch:', e);
    }
    return null;
}

/**
 * Guarda la sucursal seleccionada
 */
function setSelectedBranch(branchId) {
    localStorage.setItem(BRANCH_STORAGE_KEY, branchId.toString());
    // Disparar evento para que otros componentes se actualicen
    window.dispatchEvent(new CustomEvent('kortzen:branchChanged', {
        detail: { branchId: parseInt(branchId) }
    }));

    console.log('🔄 Sincronizando sucursal y recargando datos...');

    // Forzar recarga desde el servidor para actualizar toda la información
    setTimeout(() => {
        window.location.reload();
    }, 100);
}

/**
 * Verifica si es la primera visita del usuario
 */
function isFirstVisit() {
    return !localStorage.getItem(BRANCH_STORAGE_KEY);
}

/**
 * Inyecta los estilos si no existen
 */
function ensureBranchStyles() {
    if (document.getElementById('branch-modal-styles')) return;

    const styles = document.createElement('style');
    styles.id = 'branch-modal-styles';
    styles.textContent = `
        .branch-modal {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            animation: branchModalFadeIn 0.3s ease;
        }
        @keyframes branchModalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .branch-modal__overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
        }
        .branch-modal__content {
            position: relative;
            background: linear-gradient(145deg, #FFFFFF, #F5F5F5);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 80px -12px rgba(0, 0, 0, 0.25), 0 10px 30px rgba(0, 0, 0, 0.1), 0 0 1px rgba(0, 0, 0, 0.1);
            animation: branchModalSlideUp 0.4s ease;
        }
        @keyframes branchModalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .branch-modal__header {
            text-align: center;
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        .branch-modal__logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #1A1A1A;
            margin-bottom: 1rem;
        }
        .branch-modal__logo span { color: #333333; }
        .branch-modal__title {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: #1A1A1A;
            margin-bottom: 0.5rem;
        }
        .branch-modal__subtitle {
            color: #666666;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .branch-modal__list { padding: 1rem; }
        .branch-modal__option {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
            padding: 1.25rem;
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .branch-modal__option:hover {
            background: #FAFAFA;
            border-color: rgba(0, 0, 0, 0.12);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .branch-modal__option:last-child { margin-bottom: 0; }
        .branch-modal__option-icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(51, 51, 51, 0.1);
            border-radius: 12px;
            color: #333333;
        }
        .branch-modal__option-info { flex: 1; min-width: 0; }
        .branch-modal__option-name {
            font-weight: 600;
            color: #1A1A1A;
            font-size: 1.05rem;
            margin-bottom: 0.25rem;
        }
        .branch-modal__option-address {
            color: #666666;
            font-size: 0.875rem;
            margin-bottom: 0.35rem;
        }
        .branch-modal__option-hours {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #333333;
            font-size: 0.8rem;
        }
        .branch-modal__option-arrow {
            flex-shrink: 0;
            color: #999999;
            transition: all 0.3s ease;
        }
        .branch-modal__option:hover .branch-modal__option-arrow {
            color: #333333;
            transform: translateX(3px);
        }
        /* Branch info bar in header */
        .branch-info-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10001;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 245, 245, 0.98) 100%);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
            backdrop-filter: blur(10px);
        }
        /* Offset body when branch bar is present */
        body.has-branch-bar .header {
            top: 38px !important;
        }
        body.has-branch-bar .hero {
            padding-top: 38px;
        }
        body.has-branch-bar .preloader {
            top: 38px;
        }
        .branch-info-bar__content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.85rem;
        }
        .branch-info-bar__location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333333;
        }
        .branch-info-bar__location svg { flex-shrink: 0; }
        .branch-info-bar__details {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: #666666;
        }
        .branch-info-bar__item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .branch-info-bar__change {
            background: none;
            border: none;
            color: #333333;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .branch-info-bar__change:hover { color: #1A1A1A; }
        @media (max-width: 640px) {
            .branch-info-bar__details { display: none; }
            .branch-modal__content { max-height: 85vh; }
            .branch-modal__header { padding: 1.5rem 1.5rem 1rem; }
            .branch-modal__option { padding: 1rem; }
            
            /* Mobile specific fix for change button */
            .branch-info-bar__change {
                display: block !important;
                background: rgba(51, 51, 51, 0.1) !important;
                padding: 0.25rem 0.75rem !important;
                border-radius: 4px;
                color: #333333 !important;
                text-decoration: none !important;
                font-size: 0.75rem !important;
                border: 1px solid rgba(0, 0, 0, 0.15) !important;
            }
        }
    `;
    document.head.appendChild(styles);
}

/**
 * Crea el modal de selección de sucursal
 */
function createBranchSelectorModal() {
    const branches = loadBranches();

    ensureBranchStyles();

    const modalHTML = `
        <div class="branch-modal" id="branch-modal">
            <div class="branch-modal__overlay"></div>
            <div class="branch-modal__content">
                <div class="branch-modal__header">
                    <div class="branch-modal__logo">KORT<span>ZEN</span></div>
                    <h2 class="branch-modal__title">¡Bienvenido!</h2>
                    <p class="branch-modal__subtitle">Selecciona tu sucursal más cercana para ver horarios y servicios personalizados</p>
                </div>
                
                <div class="branch-modal__list">
                    ${branches.map(branch => `
                        <button class="branch-modal__option" data-branch-id="${branch.id}">
                            <div class="branch-modal__option-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <div class="branch-modal__option-info">
                                <h3 class="branch-modal__option-name">${branch.name}</h3>
                                <p class="branch-modal__option-address">${branch.address}</p>
                                <p class="branch-modal__option-hours">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    ${branch.openTime} - ${branch.closeTime}
                                </p>
                            </div>
                            <div class="branch-modal__option-arrow">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </div>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Agregar eventos a las opciones
    const modal = document.getElementById('branch-modal');
    const options = modal.querySelectorAll('.branch-modal__option');

    options.forEach(option => {
        option.addEventListener('click', () => {
            const branchId = parseInt(option.dataset.branchId);
            setSelectedBranch(branchId);
            closeBranchModal();
            updateBranchInfoBar();
        });
    });
}

/**
 * Cierra el modal de selección
 */
function closeBranchModal() {
    const modal = document.getElementById('branch-modal');
    if (modal) {
        modal.style.animation = 'branchModalFadeIn 0.2s ease reverse';
        setTimeout(() => modal.remove(), 200);
    }
}

/**
 * Muestra la barra de información de sucursal en el header
 */
function updateBranchInfoBar() {
    const branch = getSelectedBranch();
    if (!branch) return;

    ensureBranchStyles();

    // Remover barra existente si hay
    const existingBar = document.querySelector('.branch-info-bar');
    if (existingBar) existingBar.remove();

    const barHTML = `
        <div class="branch-info-bar">
            <div class="branch-info-bar__content">
                <div class="branch-info-bar__location" onclick="KortzenBranches.showSelector()" style="cursor: pointer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span><strong>${branch.name}</strong></span>
                </div>
                <div class="branch-info-bar__details">
                    <span class="branch-info-bar__item">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        ${branch.openTime} - ${branch.closeTime}
                    </span>
                    <span class="branch-info-bar__item">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${branch.phone}
                    </span>
                </div>
                <button class="branch-info-bar__change" onclick="KortzenBranches.showSelector()">Cambiar sucursal</button>
            </div>
        </div>
    `;

    // Insertar antes del header
    const header = document.querySelector('.header');
    if (header) {
        header.insertAdjacentHTML('beforebegin', barHTML);
        document.body.classList.add('has-branch-bar');
    }
}

/**
 * Muestra el selector de sucursales (para cambiar)
 */
function showBranchSelector() {
    createBranchSelectorModal();
}

/**
 * Inicializa el sistema de sucursales
 */
function initBranchSelector() {
    if (isFirstVisit()) {
        // Primera visita: mostrar modal
        createBranchSelectorModal();
    } else {
        // Ya tiene sucursal seleccionada: mostrar barra
        updateBranchInfoBar();
    }
}

// Exponer funciones globalmente
window.KortzenBranches = {
    init: initBranchSelector,
    showSelector: showBranchSelector,
    getSelected: getSelectedBranch,
    setSelected: setSelectedBranch,
    loadAll: loadBranches,
    updateInfoBar: updateBranchInfoBar
};

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Solo inicializar en páginas públicas (no en el dashboard)
    if (!window.location.pathname.includes('barber-dashboard')) {
        initBranchSelector();
    }
});
