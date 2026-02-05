/**
 * KORTZEN Auth System - Connected to PHP Backend
 */
const KortzenAuth = {
    user: null,
    storageKey: 'kortzen_user',
    tokenKey: 'kortzen_token',

    init() {
        this.loadUser();
        this.updateUI();
        this.interceptBookings();
    },

    // Cargar usuario desde localStorage (cache)
    loadUser() {
        const stored = localStorage.getItem(this.storageKey);
        if (stored) {
            this.user = JSON.parse(stored);
        }
    },

    // Guardar usuario en localStorage (cache)
    saveUser(user, token = null) {
        this.user = user;
        localStorage.setItem(this.storageKey, JSON.stringify(user));
        if (token) {
            localStorage.setItem(this.tokenKey, token);
        }
        this.updateUI();
    },

    // Cerrar sesión - Usa API
    async logout() {
        try {
            await KortzenAPI.logout();
        } catch (e) {
            console.log('Logout local');
        }
        this.user = null;
        localStorage.removeItem(this.storageKey);
        localStorage.removeItem(this.tokenKey);
        window.location.reload();
    },

    isLoggedIn() {
        return !!this.user;
    },

    // Login con Google (simulación que registra en DB)
    async loginWithGoogle() {
        const btn = document.querySelector('.btn-google');
        const btnText = btn?.querySelector('span');
        const originalText = btnText?.textContent;

        if (btn) {
            btn.classList.add('is-loading');
            if (btnText) btnText.textContent = 'Conectando con Google...';
        }

        try {
            // Simular datos de Google
            const googleUser = {
                name: "Joel",
                email: "usuario@gmail.com"
            };

            // Intentar registrar o login en el backend
            let result;
            try {
                result = await KortzenAPI.register(
                    googleUser.name,
                    googleUser.email,
                    'google_' + Date.now(), // Password temporal para Google
                    null
                );
            } catch (e) {
                // Si ya existe, intentar login
                result = await KortzenAPI.login(googleUser.email, 'google_auth');
            }

            if (result?.success) {
                this.saveUser(result.data.user, result.data.token);
            } else {
                // Fallback a localStorage si la API falla
                const mockUser = {
                    name: googleUser.name,
                    email: googleUser.email,
                    avatar: googleUser.name.charAt(0).toUpperCase()
                };
                this.saveUser(mockUser);
            }

        } catch (e) {
            console.warn('API no disponible, usando localStorage:', e);
            // Fallback a simulación local
            const mockUser = {
                name: "Joel",
                email: "usuario@gmail.com",
                avatar: "J"
            };
            this.saveUser(mockUser);
        }

        if (btn) {
            btn.classList.remove('is-loading');
            if (btnText) btnText.textContent = originalText;
        }

        this.closeModal();

        // Abrir Booking Wizard
        if (window.BookingWizard && typeof window.BookingWizard.open === 'function') {
            window.BookingWizard.open();
        }

        return this.user;
    },

    // Login con Email/Password
    async loginWithEmail(email, password) {
        try {
            const result = await KortzenAPI.login(email, password);
            if (result?.success) {
                this.saveUser(result.data.user, result.data.token);
                this.closeModal();
                return { success: true, user: result.data.user };
            }
            return { success: false, error: result?.error || 'Error de autenticación' };
        } catch (e) {
            return { success: false, error: e.message };
        }
    },

    // Registrar con Email/Password
    async registerWithEmail(name, email, password, phone = null) {
        try {
            const result = await KortzenAPI.register(name, email, password, phone);
            if (result?.success) {
                this.saveUser(result.data.user, result.data.token);
                this.closeModal();
                return { success: true, user: result.data.user };
            }
            return { success: false, error: result?.error || 'Error al registrar' };
        } catch (e) {
            return { success: false, error: e.message };
        }
    },

    openModal() {
        const modal = document.getElementById('auth-modal');
        if (modal) {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    },

    closeModal() {
        const modal = document.getElementById('auth-modal');
        if (modal) {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    },

    updateUI() {
        const userArea = document.querySelector('.header__container');

        if (this.isLoggedIn()) {
            let profileIcon = document.querySelector('.header__user');
            if (!profileIcon && userArea) {
                const cta = document.querySelector('.header__cta');
                const avatar = this.user.avatar || this.user.name?.charAt(0).toUpperCase() || 'U';

                const profileHTML = `
                    <div class="header__user" id="user-dropdown">
                        <div class="header__user-avatar">${avatar}</div>
                        <span>${this.user.name}</span>
                        <svg class="header__user-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                        
                        <div class="header__user-menu">
                            <div class="user-menu__header">
                                <div class="user-menu__name">${this.user.name}</div>
                                <div class="user-menu__email">${this.user.email}</div>
                            </div>
                            <div class="user-menu__items">
                                <button class="user-menu__item" data-action="profile">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    Mi Perfil
                                </button>
                                <button class="user-menu__item" data-action="bookings">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    Mis Reservas
                                </button>
                                <button class="user-menu__item" data-action="settings">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                    </svg>
                                    Configuración
                                </button>
                                <div class="user-menu__divider"></div>
                                <button class="user-menu__item user-menu__item--danger" data-action="logout">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    Cerrar Sesión
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                if (cta) cta.style.display = 'none';
                if (cta) {
                    cta.insertAdjacentHTML('afterend', profileHTML);
                    this.bindUserMenuEvents();
                }
            }
        }
    },

    bindUserMenuEvents() {
        const menuItems = document.querySelectorAll('.user-menu__item');

        menuItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = item.dataset.action;

                switch (action) {
                    case 'profile':
                        this.showProfileModal();
                        break;
                    case 'bookings':
                        this.showBookingsModal();
                        break;
                    case 'settings':
                        this.showSettingsModal();
                        break;
                    case 'logout':
                        this.logout();
                        break;
                }
            });
        });
    },

    showProfileModal() {
        const avatar = this.user.avatar || this.user.name?.charAt(0).toUpperCase() || 'U';
        this.showUserModal('profile', `
            <div class="user-modal__header">
                <div class="user-modal__avatar">${avatar}</div>
                <h2 class="user-modal__title">${this.user.name}</h2>
                <p class="user-modal__subtitle">${this.user.email}</p>
            </div>
            <div class="user-modal__content">
                <div class="user-modal__field">
                    <label>Nombre Completo</label>
                    <input type="text" value="${this.user.name}" readonly>
                </div>
                <div class="user-modal__field">
                    <label>Correo Electrónico</label>
                    <input type="email" value="${this.user.email}" readonly>
                </div>
                <div class="user-modal__info">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <span>Tu cuenta está vinculada con Google</span>
                </div>
            </div>
        `);
    },

    async showBookingsModal() {
        // Intentar obtener reservas de la API
        let bookings = [];
        try {
            bookings = await KortzenAPI.getMyBookings();
        } catch (e) {
            // Fallback a localStorage
            bookings = JSON.parse(localStorage.getItem('kortzen_bookings') || '[]');
        }

        const bookingsHTML = bookings.length > 0
            ? bookings.map(b => `
                <div class="booking-item">
                    <div class="booking-item__date">${b.date} - ${b.time}</div>
                    <div class="booking-item__barber">con ${b.barber}</div>
                </div>
            `).join('')
            : `<div class="user-modal__empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.5">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <p>No tienes reservas aún</p>
                <button class="btn btn--primary" onclick="KortzenAuth.closeUserModal(); if(window.BookingWizard) BookingWizard.open();">
                    Agendar Cita
                </button>
            </div>`;

        this.showUserModal('bookings', `
            <h2 class="user-modal__title" style="margin-bottom: 1.5rem;">Mis Reservas</h2>
            <div class="user-modal__bookings">
                ${bookingsHTML}
            </div>
        `);
    },

    showSettingsModal() {
        this.showUserModal('settings', `
            <h2 class="user-modal__title" style="margin-bottom: 1.5rem;">Configuración</h2>
            <div class="user-modal__content">
                <div class="user-modal__setting">
                    <span>Notificaciones por email</span>
                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="user-modal__setting">
                    <span>Recordatorios de citas</span>
                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="user-modal__setting">
                    <span>Ofertas y promociones</span>
                    <label class="switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        `);
    },

    showUserModal(type, content) {
        this.closeUserModal();

        const modalHTML = `
            <div class="user-modal" id="user-modal">
                <div class="user-modal__overlay" onclick="KortzenAuth.closeUserModal()"></div>
                <div class="user-modal__container">
                    <button class="user-modal__close" onclick="KortzenAuth.closeUserModal()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    ${content}
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        requestAnimationFrame(() => {
            document.getElementById('user-modal').classList.add('is-open');
        });
    },

    closeUserModal() {
        const modal = document.getElementById('user-modal');
        if (modal) {
            modal.classList.remove('is-open');
            setTimeout(() => modal.remove(), 300);
        }
    },

    interceptBookings() {
        const bookingLinks = document.querySelectorAll('a[href*="contacto.html"], a[href*="agendar"]');

        bookingLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (!this.isLoggedIn()) {
                    this.openModal();
                } else {
                    if (window.BookingWizard) {
                        window.BookingWizard.open();
                    }
                }
            });
        });
    }
};

// Exponer globalmente
window.KortzenAuth = KortzenAuth;

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    KortzenAuth.init();

    // Bind Google Login Button
    const googleBtn = document.querySelector('.btn-google');
    if (googleBtn) {
        googleBtn.addEventListener('click', () => {
            KortzenAuth.loginWithGoogle();
        });
    }

    // Bind Close Button
    const authClose = document.querySelector('.auth-close');
    if (authClose) {
        authClose.addEventListener('click', () => KortzenAuth.closeModal());
    }
});

// ==========================================
// ES6 Module Exports (para Dashboard)
// ==========================================
export const requireAuth = () => {
    // Verificar si hay usuario en localStorage (admin/staff)
    const user = JSON.parse(localStorage.getItem('kortzen_user'));

    // Si estamos en el dashboard y no hay usuario, redirigir al login
    if (window.location.pathname.includes('barber-dashboard') && !user) {
        // Redirigir a login (podría ser un modal o página dedicada)
        // Por ahora simulamos que no está autenticado
        // window.location.href = '/login.html'; // Si existiera
        return false;
    }
    return !!user;
};

export const logout = () => {
    KortzenAuth.logout();
};

export const getCurrentUser = () => {
    return KortzenAuth.user || JSON.parse(localStorage.getItem('kortzen_user'));
};

export const getUserRole = () => {
    const user = getCurrentUser();
    return user ? (user.role || 'client') : null;
};

