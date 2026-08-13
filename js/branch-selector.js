/**
 * KORTZEN - Selector de Sucursal (Sucursal Única: Llano Chico)
 */

const BRANCH_STORAGE_KEY = 'kortzen_selected_branch';

// Solo una sucursal activa
const DEFAULT_BRANCHES = [
    {
        id: 1,
        name: "KORTZEN Llano Chico",
        address: "Calle 17 de septiembre, frente a la casa de colchon Llano Chico",
        phone: "+593 098 842 2770",
        openTime: "10:00",
        closeTime: "20:00",
        mapUrl: "https://maps.app.goo.gl/KRoz9HfjNnZyMtPk9"
    }
];

function loadBranches() {
    return [...DEFAULT_BRANCHES];
}

function getSelectedBranch() {
    return DEFAULT_BRANCHES[0];
}

function setSelectedBranch(branchId) {
    localStorage.setItem(BRANCH_STORAGE_KEY, "1");
}

function isFirstVisit() {
    return false;
}

function createBranchSelectorModal() {
    // Deshabilitado: solo hay una sucursal
    const existing = document.getElementById('branch-modal');
    if (existing) existing.remove();
    return;
}

function closeBranchModal() {
    const modal = document.getElementById('branch-modal');
    if (modal) modal.remove();
}

function updateBranchInfoBar() {
    const branch = getSelectedBranch();
    if (!branch) return;

    // Remover barra existente si hay
    const existingBar = document.querySelector('.branch-info-bar');
    if (existingBar) existingBar.remove();

    const barHTML = `
        <div class="branch-info-bar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 10001; background: rgba(255, 255, 255, 0.98); border-bottom: 1px solid rgba(0,0,0,0.08); height: 36px; display: flex; align-items: center; backdrop-filter: blur(10px);">
            <div class="branch-info-bar__content" style="max-width: 1200px; width: 100%; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                <div class="branch-info-bar__location" style="display: flex; align-items: center; gap: 0.5rem; color: #111;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span><strong>${branch.name}</strong></span>
                </div>
                <div class="branch-info-bar__details" style="display: flex; align-items: center; gap: 1.5rem; color: #555;">
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
                </div>
            </div>
        </div>
    `;

    const header = document.querySelector('.header');
    if (header) {
        header.insertAdjacentHTML('beforebegin', barHTML);
        document.body.classList.add('has-branch-bar');
    }
}

function showBranchSelector() {
    return;
}

function initBranchSelector() {
    localStorage.setItem(BRANCH_STORAGE_KEY, "1");
    closeBranchModal();
    updateBranchInfoBar();
}

window.KortzenBranches = {
    init: initBranchSelector,
    showSelector: function() {},
    getSelected: getSelectedBranch,
    setSelected: setSelectedBranch,
    loadAll: loadBranches,
    updateInfoBar: updateBranchInfoBar
};

document.addEventListener('DOMContentLoaded', () => {
    localStorage.setItem(BRANCH_STORAGE_KEY, "1");
    closeBranchModal();
    if (!window.location.pathname.includes('barber-dashboard') && !document.body.classList.contains('pwa-app-mode')) {
        updateBranchInfoBar();
    }
});
