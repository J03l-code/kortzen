/**
 * KORTZEN - Team Loader
 * Carga dinámicamente los barberos según la sucursal seleccionada
 */

const TeamLoader = {
    init: function () {
        // Load Modal CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/css/barber-modal.css';
        document.head.appendChild(link);

        // Escuchar cambios de sucursal
        window.addEventListener('kortzen:branchChanged', (e) => {
            if (e.detail && e.detail.branchId) {
                this.loadTeam(e.detail.branchId);
            }
        });

        // Cargar inicial si ya hay sucursal seleccionada o usar defecto
        document.addEventListener('DOMContentLoaded', () => {
            // Esperar un poco a que KortzenBranches se inicialice si es necesario
            setTimeout(() => {
                const currentBranch = window.KortzenBranches ? window.KortzenBranches.getSelected() : null;
                const branchId = currentBranch ? currentBranch.id : 1; // Default to ID 1
                this.loadTeam(branchId);
            }, 100);
        });
    },

    loadTeam: async function (branchId) {
        const grid = document.getElementById('team-grid');
        if (!grid) return;

        // Render skeleton loader
        grid.innerHTML = `
            <div class="team-skeleton-grid" style="grid-column: 1/-1; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-8); width: 100%;">
                <div class="team-skeleton-card" style="background: var(--color-charcoal); border: 1px solid var(--color-charcoal-light); height: 380px; border-radius: var(--border-radius-lg); padding: var(--space-6); display: flex; flex-direction: column; gap: var(--space-4); animation: pulse 1.5s infinite;">
                    <div style="width: 100%; height: 240px; background: rgba(255,255,255,0.03); border-radius: var(--border-radius-md);"></div>
                    <div style="width: 60%; height: 20px; background: rgba(255,255,255,0.03); border-radius: 4px; margin-top: auto;"></div>
                    <div style="width: 40%; height: 14px; background: rgba(255,255,255,0.02); border-radius: 4px;"></div>
                </div>
                <div class="team-skeleton-card" style="background: var(--color-charcoal); border: 1px solid var(--color-charcoal-light); height: 380px; border-radius: var(--border-radius-lg); padding: var(--space-6); display: flex; flex-direction: column; gap: var(--space-4); animation: pulse 1.5s infinite; animation-delay: 0.2s;">
                    <div style="width: 100%; height: 240px; background: rgba(255,255,255,0.03); border-radius: var(--border-radius-md);"></div>
                    <div style="width: 60%; height: 20px; background: rgba(255,255,255,0.03); border-radius: 4px; margin-top: auto;"></div>
                    <div style="width: 40%; height: 14px; background: rgba(255,255,255,0.02); border-radius: 4px;"></div>
                </div>
                <div class="team-skeleton-card" style="background: var(--color-charcoal); border: 1px solid var(--color-charcoal-light); height: 380px; border-radius: var(--border-radius-lg); padding: var(--space-6); display: flex; flex-direction: column; gap: var(--space-4); animation: pulse 1.5s infinite; animation-delay: 0.4s;">
                    <div style="width: 100%; height: 240px; background: rgba(255,255,255,0.03); border-radius: var(--border-radius-md);"></div>
                    <div style="width: 60%; height: 20px; background: rgba(255,255,255,0.03); border-radius: 4px; margin-top: auto;"></div>
                    <div style="width: 40%; height: 14px; background: rgba(255,255,255,0.02); border-radius: 4px;"></div>
                </div>
            </div>
            <style>
                @keyframes pulse {
                    0% { opacity: 0.4; }
                    50% { opacity: 0.8; }
                    100% { opacity: 0.4; }
                }
            </style>
        `;

        try {
            const response = await fetch(`/api/get_barberos.php?sucursal_id=${branchId}`);
            const data = await response.json();

            if (data.success && data.data.length > 0) {
                this.renderTeam(data.data);
            } else {
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: var(--color-gray); padding: 3rem; background: var(--color-charcoal); border: 1px solid var(--color-charcoal-light); border-radius: var(--border-radius-lg);">
                        <p>No hay barberos registrados en esta sucursal actualmente.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading team:', error);
            grid.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; color: var(--color-white-off); padding: 3rem; background: var(--color-charcoal); border: 1px solid var(--color-charcoal-light); border-radius: var(--border-radius-lg);">
                    <p style="margin-bottom: var(--space-4);">No pudimos cargar los barberos. Intenta nuevamente.</p>
                    <button class="btn btn--secondary btn--sm" onclick="TeamLoader.loadTeam(${branchId})" style="display: inline-flex; margin: 0 auto;">Reintentar</button>
                </div>
            `;
        }
    },

    renderTeam: function (barbers) {
        const grid = document.getElementById('team-grid');
        grid.innerHTML = '';

        let delay = 0.1;

        barbers.forEach(barber => {
            // Use photo from API or fallback
            // Note: The API currently returns a placeholder path in 'foto' key, 
            // but if we implement real uploads later, this needs to work.
            const imageUrl = barber.foto || '/assets/images/barber-working.jpg?v=2';
            const role = barber.cargo || 'Barbero Profesional';

            const card = document.createElement('div');
            card.className = 'team-member reveal-hidden'; // Start hidden for animation
            card.setAttribute('data-reveal', '');
            card.style.transitionDelay = `${delay}s`;

            // Make cursor pointer to indicate clickability
            card.style.cursor = 'pointer';

            // Truncate bio for card display - ONLY ON NOSOTROS.HTML or root if configured
            // User requested NOT on index (home), but YES on nosotros.
            const isNosotrosPage = window.location.pathname.includes('nosotros.html');

            let shortBio = '';
            // Show bio only on specific pages
            if (isNosotrosPage && barber.biografia) {
                shortBio = barber.biografia;
                if (shortBio.length > 80) {
                    shortBio = shortBio.substring(0, 80) + '...';
                }
            }

            card.innerHTML = `
                <img src="${imageUrl}" alt="${barber.nombre}" class="team-member__image">
                <h3 class="team-member__name">${barber.nombre}</h3>
                <p class="team-member__role">${role}</p>
                ${shortBio ? `<p class="team-member__bio-short" style="font-size: 0.85rem; color: var(--color-gray); margin-top: 0.5rem;">${shortBio}</p>` : ''}
            `;

            // Click event to open modal
            card.addEventListener('click', () => {
                this.openModal(barber);
            });

            grid.appendChild(card);
            delay += 0.1;

            // Trigger reveal animation manually if needed, or rely on ScrollReveal
            // Since elements are added dynamically, we might need to re-observe them
            if (window.initScrollReveal) {
                // Re-init simple reveal or just add class after a tick
                setTimeout(() => card.classList.add('revealed'), 100);
            } else {
                setTimeout(() => card.classList.add('revealed'), 100);
            }
        });
    },

    openModal: function (barber) {
        // Remove existing modal if any
        const existingModal = document.getElementById('barber-modal');
        if (existingModal) existingModal.remove();

        const imageUrl = barber.foto || '/assets/images/barber-working.jpg?v=2';
        const role = barber.cargo || 'Barbero Profesional';
        const bio = barber.biografia || 'Experto en cortes clásicos y modernos, dedicado a ofrecer la mejor experiencia de cuidado masculino.';
        const specialties = barber.especialidades ? barber.especialidades.split(',') : ['Corte clásico', 'Afeitado tradicional', 'Estilismo'];

        const modal = document.createElement('div');
        modal.id = 'barber-modal';
        modal.className = 'modal';

        const tagsHtml = specialties.map(tag => `<span class="modal__tag">${tag.trim()}</span>`).join('');

        modal.innerHTML = `
            <div class="modal__content">
                <button class="modal__close" aria-label="Cerrar">&times;</button>
                
                <div class="modal__header">
                    <img src="${imageUrl}" alt="${barber.nombre}" class="modal__image">
                    <h2 class="modal__title">${barber.nombre}</h2>
                    <span class="modal__subtitle">${role}</span>
                </div>

                <div class="modal__body">
                    <div class="modal__section">
                        <h4 class="modal__section-title">Biografía</h4>
                        <p>${bio}</p>
                    </div>

                    <div class="modal__section">
                        <h4 class="modal__section-title">Especialidades</h4>
                        <div class="modal__tags">
                            ${tagsHtml}
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                     <a href="/cliente-login.php" class="btn btn--primary">Reservar Cita</a>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Open animation
        setTimeout(() => modal.classList.add('modal--open'), 10);

        // Close logic
        const closeBtn = modal.querySelector('.modal__close');
        const close = () => {
            modal.classList.remove('modal--open');
            setTimeout(() => modal.remove(), 300);
        };

        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        // Escape key to close
        document.addEventListener('keydown', function h(e) {
            if (e.key === 'Escape') {
                close();
                document.removeEventListener('keydown', h);
            }
        });
    }
};

TeamLoader.init();
