/**
 * KORTZEN - Services Loader
 * Carga dinámicamente los servicios desde la API
 */

const ServicesLoader = {
    init: function () {
        document.addEventListener('DOMContentLoaded', () => {
            this.loadServices();
        });
    },

    loadServices: async function () {
        // 1. Check for Index Page container
        const indexContainer = document.querySelector('.services-wrapper') || document.getElementById('highlighted-services');

        // 2. Check for Services Page containers
        const categoryContainers = {
            'Corte': document.getElementById('services-corte'),
            'Afeitado': document.getElementById('services-afeitado'),
            'Barba': document.getElementById('services-barba'),
            'Spa': document.getElementById('services-spa'),
            'Otros': document.getElementById('services-otros') // Optional
        };

        try {
            const response = await fetch('/api/get_servicios_public.php');
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                if (indexContainer) {
                    // NEW LOGIC: Filter by 'destacado' flag from DB
                    let featuredServices = result.data.filter(s => s.destacado == 1);

                    // Fallback: If no featured services found, take the first 3
                    if (featuredServices.length === 0) {
                        featuredServices = result.data.slice(0, 3);
                    } else if (featuredServices.length > 3) {
                        // Optional: Limit to first 3 even if more are marked (to not break design)
                        featuredServices = featuredServices.slice(0, 3);
                    }

                    // Sort them to match the order in featuredNames if possible


                    this.renderServices(featuredServices, indexContainer);
                }

                // Render into specific category sections if they exist
                this.renderCategorizedServices(result.data, categoryContainers);
            }
        } catch (error) {
            console.error('Error loading services:', error);
        }
    },

    // Render logic for specific distinct containers (Services Page)
    renderCategorizedServices: function (services, containers) {
        // Clear existing filtered containers
        Object.values(containers).forEach(c => { if (c) c.innerHTML = ''; });

        services.forEach(service => {
            const catRaw = service.categoria || 'Otros';
            const cat = catRaw.toLowerCase();

            // Find best matching container
            let targetContainer = null;

            // Case insensitive matching
            if (cat.includes('corte')) targetContainer = containers['Corte'];
            else if (cat.includes('afeitado')) targetContainer = containers['Afeitado'];
            else if (cat.includes('barba')) targetContainer = containers['Barba'];
            else if (cat.includes('spa') || cat.includes('facial')) targetContainer = containers['Spa'];

            if (targetContainer) {
                this.renderServiceCard(service, targetContainer);
            }
        });
    },

    // Helper to render a single card (extracted from previous renderServices)
    renderServiceCard: function (service, container) {
        const card = document.createElement('div');
        card.className = 'service-card revealed'; // Add 'revealed' class immediately
        card.setAttribute('data-reveal', '');
        // Force visibility styles inline to prevent any CSS/JS conflict hiding it
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';

        const price = parseFloat(service.precio).toFixed(2);
        const cat = service.categoria || 'General';

        card.innerHTML = `
            <div class="service-card__image-wrapper">
                 <img src="${service.foto_url || '/assets/images/service-placeholder.jpg'}" alt="${service.nombre}" class="service-card__image" onerror="this.src='https://ui-avatars.com/api/?name=${service.nombre}&background=333333&color=fff&size=128'">
            </div>
            
            <div class="service-card__content">
                <span class="service-card__category">${cat}</span>
                <h3 class="service-card__title">${service.nombre}</h3>
                <p class="service-card__desc">${service.descripcion || ''}</p>
                <div class="service-card__footer">
                    <span class="service-card__price">$${price}</span>
                    <span class="service-card__duration">${service.duracion_minutos} min</span>
                </div>
                <a href="/cliente-login.php" class="btn btn--secondary btn--sm service-card__btn">Reservar</a>
            </div>
        `;
        container.appendChild(card);
    },

    renderServices: function (services, container) {
        container.innerHTML = '';

        // Map icon based on category/name logic
        const getIcon = (cat, name) => {
            const lower = (cat + ' ' + name).toLowerCase();
            if (lower.includes('corte')) return 'scissors';
            if (lower.includes('barba')) return 'anchor'; // or 'smile'
            if (lower.includes('afeitado')) return 'feather';
            if (lower.includes('spa') || lower.includes('facial')) return 'sun';
            return 'zap'; // default
        };

        // For SVG icons, we'll embed simple paths or use an icon sprite if available.
        // Using inline SVGs similar to existing ones.
        const icons = {
            scissors: '<path d="M6 9l6 6 6-6"/>', // Placeholder
            // Let's use generic SVGs matching the design
        };

        services.forEach(service => this.renderServiceCard(service, container));

        // Re-init ScrollReveal if needed
        if (window.initScrollReveal) {
            setTimeout(() => {
                const elements = container.querySelectorAll('[data-reveal]');
                elements.forEach(el => {
                    el.classList.add('revealed'); // Simple reveal
                });
            }, 100);
        }
    },

    getSvgPath: function (category) {
        // Return mostly valid SVG paths for visual distinction
        const c = (category || '').toLowerCase();
        if (c.includes('corte')) return '<circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line>'; // Scissors-ish
        if (c.includes('barba') || c.includes('afeitado')) return '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>'; // User/Face
        if (c.includes('spa')) return '<circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>'; // Sun
        return '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>'; // Layers/General
    }
};

ServicesLoader.init();
