/**
 * KORTZEN - API Service
 * Wrapper para comunicarse con el backend PHP
 */

const API_BASE_URL = '/api';

const KortzenAPI = {
    // Token de sesión
    token: localStorage.getItem('kortzen_token'),

    /**
     * Configurar token de autenticación
     */
    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('kortzen_token', token);
        } else {
            localStorage.removeItem('kortzen_token');
        }
    },

    /**
     * Obtener headers con autenticación
     */
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json'
        };
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        return headers;
    },

    /**
     * Realizar petición fetch
     */
    async request(endpoint, options = {}) {
        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...options,
                headers: this.getHeaders()
            });

            // Obtener el texto de la respuesta primero
            const textContent = await response.text();
            
            // Intentar parsear como JSON
            let data;
            try {
                data = JSON.parse(textContent);
            } catch (jsonError) {
                // Si no es JSON, la respuesta probablemente es HTML (error de PHP)
                console.error('❌ Respuesta NO es JSON válido');
                console.error('Endpoint:', endpoint);
                console.error('Status:', response.status);
                console.error('Respuesta recibida:', textContent.substring(0, 500)); // Primeros 500 caracteres
                
                throw new Error(`Error: El servidor devolvió HTML en lugar de JSON. Endpoint: ${endpoint}. Revisa la consola para más detalles.`);
            }

            if (!response.ok) {
                throw new Error(data.error || 'Error en la petición');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // ==================== AUTH ====================

    /**
     * Registrar nuevo usuario
     */
    async register(name, email, password, phone = null) {
        const result = await this.request('/auth/register.php', {
            method: 'POST',
            body: JSON.stringify({ name, email, password, phone })
        });

        if (result.success && result.data.token) {
            this.setToken(result.data.token);
            localStorage.setItem('kortzen_user', JSON.stringify(result.data.user));
        }

        return result;
    },

    /**
     * Iniciar sesión
     */
    async login(email, password) {
        const result = await this.request('/auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });

        if (result.success && result.data.token) {
            this.setToken(result.data.token);
            localStorage.setItem('kortzen_user', JSON.stringify(result.data.user));
        }

        return result;
    },

    /**
     * Cerrar sesión
     */
    async logout() {
        try {
            await this.request('/auth/logout.php', { method: 'POST' });
        } catch (e) {
            // Ignorar errores de logout
        }
        this.setToken(null);
        localStorage.removeItem('kortzen_user');
    },

    /**
     * Obtener usuario actual
     */
    async getCurrentUser() {
        if (!this.token) return null;

        try {
            const result = await this.request('/auth/me.php');
            return result.data?.user || null;
        } catch (e) {
            this.setToken(null);
            return null;
        }
    },

    /**
     * Verificar si hay sesión activa
     */
    isLoggedIn() {
        return !!this.token;
    },

    /**
     * Obtener usuario del localStorage (para UI rápida)
     */
    getCachedUser() {
        const cached = localStorage.getItem('kortzen_user');
        return cached ? JSON.parse(cached) : null;
    },

    // ==================== BOOKINGS ====================

    /**
     * Crear reserva
     */
    async createBooking(barberId, bookingDate, bookingTime, branchId = 1, serviceId = null, notes = null) {
        return this.request('/bookings/create.php', {
            method: 'POST',
            body: JSON.stringify({
                barber_id: barberId,
                booking_date: bookingDate,
                booking_time: bookingTime,
                branch_id: branchId,
                service_id: serviceId,
                notes
            })
        });
    },

    /**
     * Obtener reservas del usuario
     */
    async getMyBookings() {
        const result = await this.request('/bookings/read.php');
        return result.data?.bookings || [];
    },

    /**
     * Cancelar reserva
     */
    async cancelBooking(bookingId) {
        return this.request(`/bookings/delete.php?id=${bookingId}`, {
            method: 'DELETE'
        });
    },

    // ==================== BRANCHES ====================

    /**
     * Obtener sucursales
     */
    async getBranches() {
        const result = await this.request('/branches/read.php');
        return result.data?.branches || [];
    },

    // ==================== BARBERS ====================

    /**
     * Obtener barberos
     */
    async getBarbers(branchId = null) {
        const url = branchId
            ? `/barbers/read.php?branch_id=${branchId}`
            : '/barbers/read.php';
        const result = await this.request(url);
        return result.data?.barbers || [];
    },

    /**
     * Obtener disponibilidad de un barbero
     */
    async getBarberSchedule(barberId, date) {
        const result = await this.request(`/barbers/schedule.php?barber_id=${barberId}&date=${date}`);
        return result.data || { slots: [] };
    }
};

// Exponer globalmente
window.KortzenAPI = KortzenAPI;
