/**
 * KORTZEN - Services Loader (100% Dinámico desde el Dashboard)
 * Carga dinámicamente las categorías y servicios configurados en el Dashboard
 */

const ServicesLoader = {
    init: function () {
        document.addEventListener('DOMContentLoaded', () => {
            this.loadServices();
        });
    },

    loadServices: async function () {
        // 1. Contenedor de servicios destacados (página de Inicio)
        const indexContainer = document.querySelector('.services-wrapper') || document.getElementById('highlighted-services');

        // 2. Contenedor dinámico principal en servicios.html
        const dynamicContainer = document.getElementById('dynamic-services-container');

        // 3. Contenedores específicos legacy
        const legacyContainers = {
            'Corte': document.getElementById('services-corte'),
            'Afeitado': document.getElementById('services-afeitado'),
            'Barba': document.getElementById('services-barba'),
            'Spa': document.getElementById('services-spa'),
            'Otros': document.getElementById('services-otros')
        };

        try {
            const response = await fetch('/api/get_servicios_public.php');
            const result = await response.json();

            if (result.success && result.data && result.data.length > 0) {
                // Si estamos en la página de inicio (Index)
                if (indexContainer) {
                    let featuredServices = result.data.filter(s => s.destacado == 1);
                    if (featuredServices.length === 0) {
                        featuredServices = result.data.slice(0, 3);
                    } else if (featuredServices.length > 3) {
                        featuredServices = featuredServices.slice(0, 3);
                    }
                    this.renderServices(featuredServices, indexContainer);
                }

                // Si estamos en la página de servicios (servicios.html) con contenedor dinámico
                if (dynamicContainer) {
                    this.renderDynamicCategories(result.categories || [], result.data, dynamicContainer);
                } else {
                    // Fallback a contenedores legacy
                    this.renderCategorizedServices(result.data, legacyContainers);
                }
            }
        } catch (error) {
            console.error('Error cargando servicios:', error);
        }
    },

    // Renderiza todas las categorías configuradas dinámicamente desde el dashboard
    renderDynamicCategories: function (categoriesList, allServices, mainContainer) {
        mainContainer.innerHTML = '';

        // Agrupar servicios por categoría
        const grouped = {};
        const catMeta = {};

        // 1. Inicializar con las categorías del dashboard en su orden exacto
        categoriesList.forEach(c => {
            grouped[c.nombre] = [];
            catMeta[c.nombre] = c;
        });

        // 2. Clasificar cada servicio en su categoría correspondiente
        allServices.forEach(service => {
            const cat = service.categoria || 'General';
            if (!grouped[cat]) {
                grouped[cat] = [];
                catMeta[cat] = {
                    nombre: cat,
                    descripcion: service.cat_descripcion || '',
                    orden: service.cat_orden || 999
                };
            }
            grouped[cat].push(service);
        });

        // 3. Crear barra de navegación / filtros por categorías si hay más de 1 categoría
        const categoriesWithServices = Object.keys(grouped).filter(k => grouped[k].length > 0);

        if (categoriesWithServices.length > 1) {
            const filterBar = document.createElement('div');
            filterBar.className = 'services-category-nav';
            filterBar.style.cssText = 'display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin: -1.5rem auto 3.5rem auto; padding: 0 1.5rem; max-width: 1000px;';

            filterBar.innerHTML = `
                <button type="button" class="service-nav-pill is-active" onclick="ServicesLoader.filterView('all', this)" style="padding: 10px 22px; border-radius: 30px; font-weight: 700; font-size: 0.85rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; border: 1px solid var(--color-gold, #C5A880); background: var(--color-gold, #C5A880); color: #111111; transition: all 0.25s ease;">
                    Todos
                </button>
            `;

            categoriesWithServices.forEach(catName => {
                const slug = this.slugify(catName);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'service-nav-pill';
                btn.style.cssText = 'padding: 10px 22px; border-radius: 30px; font-weight: 700; font-size: 0.85rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.03); color: var(--color-white, #FFFFFF); transition: all 0.25s ease;';
                btn.textContent = catName;
                btn.onclick = () => ServicesLoader.filterView(slug, btn);
                filterBar.appendChild(btn);
            });

            mainContainer.appendChild(filterBar);
        }

        // 4. Renderizar cada sección de categoría
        let sectionIndex = 0;
        categoriesWithServices.forEach(catName => {
            const services = grouped[catName];
            const meta = catMeta[catName] || {};
            const slug = this.slugify(catName);
            const isCharcoalBg = (sectionIndex % 2 === 1);

            const section = document.createElement('section');
            section.className = 'section service-category';
            section.id = `cat-${slug}`;
            section.setAttribute('data-category-slug', slug);
            if (isCharcoalBg) {
                section.style.backgroundColor = 'var(--color-charcoal, #1A1A1A)';
            }

            const containerDiv = document.createElement('div');
            containerDiv.className = 'container';

            let descHtml = '';
            if (meta.descripcion && meta.descripcion.trim() !== '') {
                descHtml = `<p style="text-align: center; max-width: 650px; margin: -1.5rem auto 3rem auto; color: var(--color-gray, #999999); font-size: 0.95rem; line-height: 1.6;">${meta.descripcion}</p>`;
            }

            containerDiv.innerHTML = `
                <h2 class="service-category__title">
                    <span>${meta.nombre || catName}</span>
                </h2>
                ${descHtml}
                <div class="services-grid" id="services-grid-${slug}"></div>
            `;

            const grid = containerDiv.querySelector(`#services-grid-${slug}`);
            services.forEach(service => {
                this.renderServiceCard(service, grid);
            });

            section.appendChild(containerDiv);
            mainContainer.appendChild(section);
            sectionIndex++;
        });

        // Trigger reveal animation
        if (window.initScrollReveal) {
            setTimeout(() => {
                const elements = mainContainer.querySelectorAll('[data-reveal]');
                elements.forEach(el => el.classList.add('revealed'));
            }, 100);
        }
    },

    // Filtra las categorías en pantalla o hace scroll suave
    filterView: function (slug, clickedBtn) {
        document.querySelectorAll('.service-nav-pill').forEach(btn => {
            btn.style.background = 'rgba(255,255,255,0.03)';
            btn.style.color = 'var(--color-white, #FFFFFF)';
            btn.style.borderColor = 'rgba(255,255,255,0.15)';
        });

        clickedBtn.style.background = 'var(--color-gold, #C5A880)';
        clickedBtn.style.color = '#111111';
        clickedBtn.style.borderColor = 'var(--color-gold, #C5A880)';

        const sections = document.querySelectorAll('.service-category[data-category-slug]');
        if (slug === 'all') {
            sections.forEach(s => s.style.display = 'block');
        } else {
            sections.forEach(s => {
                if (s.getAttribute('data-category-slug') === slug) {
                    s.style.display = 'block';
                    s.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    s.style.display = 'none';
                }
            });
        }
    },

    slugify: function (text) {
        return (text || '').toString().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    },

    // Render logic para contenedores legacy
    renderCategorizedServices: function (services, containers) {
        Object.values(containers).forEach(c => { if (c) c.innerHTML = ''; });

        services.forEach(service => {
            const catRaw = service.categoria || 'Otros';
            const cat = catRaw.toLowerCase();

            let targetContainer = null;
            if (cat.includes('corte')) targetContainer = containers['Corte'];
            else if (cat.includes('afeitado')) targetContainer = containers['Afeitado'];
            else if (cat.includes('barba')) targetContainer = containers['Barba'];
            else if (cat.includes('spa') || cat.includes('facial')) targetContainer = containers['Spa'];
            else targetContainer = containers['Otros'];

            if (targetContainer) {
                this.renderServiceCard(service, targetContainer);
            }
        });
    },

    renderServiceCard: function (service, container) {
        const card = document.createElement('div');
        card.className = 'service-card revealed';
        card.setAttribute('data-reveal', '');
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';

        const price = parseFloat(service.precio).toFixed(2);
        const cat = service.categoria || 'General';
        const tagHtml = service.tag ? `<span class="service-card__tag-badge">${service.tag}</span>` : '';
        const beneficioHtml = service.beneficio ? `<span class="service-card__benefit-text">${service.beneficio}</span>` : '';

        card.innerHTML = `
            <div class="service-card__image-wrapper">
                 <img src="${service.foto_url || '/assets/images/service-placeholder.jpg'}" alt="${service.nombre}" class="service-card__image" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(service.nombre)}&background=333333&color=fff&size=128'">
                 ${tagHtml}
            </div>
            
            <div class="service-card__content">
                <span class="service-card__category">${cat}</span>
                <h3 class="service-card__title">${service.nombre}</h3>
                ${beneficioHtml}
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
        services.forEach(service => this.renderServiceCard(service, container));

        if (window.initScrollReveal) {
            setTimeout(() => {
                const elements = container.querySelectorAll('[data-reveal]');
                elements.forEach(el => el.classList.add('revealed'));
            }, 100);
        }
    }
};

ServicesLoader.init();
