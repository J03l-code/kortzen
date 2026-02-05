/**
 * KORTZEN - Branch Content Loader
 * Carga y actualiza dinámicamente el contenido específico de cada sucursal
 * Se integra con branch-selector.js SIN MODIFICARLO
 */

/**
 * Actualiza la sección de equipo con los barberos de la sucursal seleccionada
 */
function updateTeamSection(teamMembers) {
    const teamGrid = document.querySelector('[data-branch-dynamic="team"]');
    if (!teamGrid) return;

    // SVG placeholder icon para los barberos
    const personIcon = `
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="1">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
    `;

    teamGrid.innerHTML = teamMembers.map(member => `
        <article class="team-member" data-reveal>
            <div class="team-member__image"
                style="background: linear-gradient(135deg, #2A2A2A, #1E1E1E); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                <img src="${member.image}" 
                     alt="${member.name}" 
                     style="width: 100%; height: 100%; object-fit: cover;"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='${personIcon.replace(/\n/g, '')}'">
            </div>
            <h3 class="team-member__name">${member.name}</h3>
            <p class="team-member__role">${member.role}</p>
            <p class="team-member__bio">${member.bio}</p>
        </article>
    `).join('');
}

/**
 * Carga el contenido de una sucursal específica
 */
function loadBranchContent(branchId) {
    if (!window.BRANCH_CONTENT) {
        console.warn('⚠️ BRANCH_CONTENT not loaded');
        return;
    }

    const content = window.BRANCH_CONTENT[branchId];
    if (!content) {
        console.warn(`⚠️ No content found for branch ${branchId}`);
        return;
    }

    console.log(`✅ Loading content for: ${content.name}`);

    // Actualizar equipo de barberos
    if (content.team) {
        updateTeamSection(content.team);
    }
}

/**
 * Inicializa el sistema de contenido dinámico
 */
function initBranchContentLoader() {
    // Cargar contenido de la sucursal actual al iniciar
    if (window.KortzenBranches && window.KortzenBranches.getSelected) {
        const selectedBranch = window.KortzenBranches.getSelected();
        if (selectedBranch) {
            loadBranchContent(selectedBranch.id);
        }
    }

    // Escuchar cambios de sucursal desde branch-selector.js
    // Método 1: Observar cambios en localStorage
    window.addEventListener('storage', (e) => {
        if (e.key === 'kortzen_selected_branch' && e.newValue) {
            const branchId = parseInt(e.newValue);
            loadBranchContent(branchId);
        }
    });

    // Método 2: Interceptar función setSelectedBranch (sin modificar el archivo original)
    if (window.KortzenBranches && window.KortzenBranches.setSelected) {
        const originalSetSelected = window.KortzenBranches.setSelected;
        window.KortzenBranches.setSelected = function (branchId) {
            originalSetSelected.call(this, branchId);
            // Cargar contenido después de cambiar sucursal
            setTimeout(() => loadBranchContent(branchId), 100);
        };
    }
}

// Exponer funciones globalmente
window.KortzenBranchContent = {
    init: initBranchContentLoader,
    load: loadBranchContent,
    updateTeam: updateTeamSection
};

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Solo en páginas que tengan contenido dinámico
    if (document.querySelector('[data-branch-dynamic]')) {
        initBranchContentLoader();
    }
});
