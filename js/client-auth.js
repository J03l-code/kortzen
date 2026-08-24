/**
 * KORTZEN - Client Authentication State Manager
 * Maneja el estado de autenticación del cliente en el frontend
 * 
 * El login con Google es SOLO para CLIENTES que quieren reservar citas.
 * Los barberos usan login.php con contraseña.
 */

(function () {
    'use strict';

    const AUTH_CHECK_ENDPOINT = '/api/auth-status.php';
    const CLIENT_LOGIN_URL = '/cliente-login.php';

    /**
     * Verifica el estado de autenticación del cliente
     */
    async function checkAuthState() {
        try {
            const response = await fetch(AUTH_CHECK_ENDPOINT, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Auth check failed');
            }

            const data = await response.json();
            updateUIForAuthState(data);
            return data;

        } catch (error) {
            console.warn('Error checking auth state:', error);
            return { isLoggedIn: false, user: null };
        }
    }

    /**
     * Actualiza la UI según el estado de autenticación
     */
    function updateUIForAuthState(authState) {
        const { isLoggedIn, user } = authState;

        if (isLoggedIn && user) {
            // Usuario logueado - mostrar avatar en la barra de sucursal
            addUserIndicator(user);

            // Agregar link a "Mi Cuenta" en el footer si no existe
            addAccountLinkToFooter();
        }

        // Disparar evento personalizado
        window.dispatchEvent(new CustomEvent('kortzen:authStateChanged', { detail: authState }));
    }

    /**
     * Agrega indicador de usuario en la barra de sucursal
     */
    function addUserIndicator(user) {
        // Manejado de forma unificada en branch-selector.js updateBranchInfoBar
        return;
    }

    /**
     * Agrega link a Mi Cuenta en el footer
     */
    function addAccountLinkToFooter() {
        const footerLinks = document.querySelector('.footer__links');
        if (!footerLinks) return;

        // Verificar si ya existe
        if (footerLinks.querySelector('[href="/cliente-dashboard.php"]')) return;

        const li = document.createElement('li');
        li.innerHTML = '<a href="/cliente-dashboard.php" class="footer__link">Mi Cuenta</a>';
        footerLinks.appendChild(li);
    }

    /**
     * Redirige al login de cliente para reservar
     * Guarda la URL de retorno para después del login
     */
    function requireLoginForBooking(returnUrl = null) {
        const url = returnUrl || window.location.href;
        sessionStorage.setItem('kortzen_booking_return', url);
        window.location.href = CLIENT_LOGIN_URL;
    }

    // Exponer globalmente
    window.KortzenAuth = {
        checkState: checkAuthState,
        requireLogin: requireLoginForBooking,
        loginUrl: CLIENT_LOGIN_URL,
        isLoggedIn: () => {
            return fetch(AUTH_CHECK_ENDPOINT, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => d.isLoggedIn)
                .catch(() => false);
        }
    };

    // Auto-inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', () => {
        // Solo en páginas públicas (no dashboard)
        if (!window.location.pathname.includes('dashboard') &&
            !window.location.pathname.includes('login')) {
            checkAuthState();
        }
    });

})();
