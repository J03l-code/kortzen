/**
 * Booking Wizard Application Logic - Multi Day
 */

const BookingWizard = {
    currentStep: 1,
    selectedBarber: null,
    selectedDate: null, // New: Store the selected date object
    selectedTime: null,

    init() {
        this.bindEvents();
    },

    bindEvents() {
        // Close Button
        document.querySelector('.booking-close')?.addEventListener('click', () => {
            this.close();
        });

        // Barber Selection
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.barber-card');
            if (card) {
                this.selectBarber(parseInt(card.dataset.id));
            }
        });

        // Date Selection
        document.addEventListener('click', (e) => {
            const dateCard = e.target.closest('.date-card');
            if (dateCard) {
                this.selectDate(dateCard.dataset.date);
            }
        });

        // Time Slot Selection
        document.addEventListener('click', (e) => {
            const slot = e.target.closest('.time-slot');
            if (slot && !slot.classList.contains('disabled')) {
                this.selectTime(slot.dataset.time);
            }
        });
    },

    open() {
        const modal = document.getElementById('booking-modal');
        if (modal) {
            modal.classList.add('is-open');
            this.reset();
            this.renderBarbers();
        }
    },

    close() {
        const modal = document.getElementById('booking-modal');
        if (modal) {
            modal.classList.remove('is-open');
        }
    },

    reset() {
        this.currentStep = 1;
        this.selectedBarber = null;
        this.selectedDate = null;
        this.selectedTime = null;
        this.showStep(1);
    },

    showStep(step) {
        this.currentStep = step;

        // Update Indicators
        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, index) => {
            if (index < step) dot.classList.add('active');
            else dot.classList.remove('active');
        });

        // Show Content
        document.querySelectorAll('.booking-step-content').forEach(el => el.classList.remove('active'));
        document.getElementById(`booking-step-${step}`)?.classList.add('active');
    },

    renderBarbers() {
        const container = document.getElementById('barbers-grid');
        if (!container) return;

        container.innerHTML = BARBERS.map(barber => `
            <div class="barber-card" data-id="${barber.id}">
                <img src="${barber.image}" alt="${barber.name}" class="barber-img">
                <div class="barber-info">
                    <h3 class="barber-name">${barber.name}</h3>
                    <div class="barber-role">${barber.role}</div>
                    <div style="font-size: 0.8rem; color: #888; margin-top: 0.5rem;">${barber.specialty}</div>
                </div>
            </div>
        `).join('');
    },

    selectBarber(id) {
        this.selectedBarber = BARBERS.find(b => b.id === id);

        // Highlight
        document.querySelectorAll('.barber-card').forEach(c => c.classList.remove('selected'));
        document.querySelector(`.barber-card[data-id="${id}"]`)?.classList.add('selected');

        // Select first available date by default
        this.selectedDate = NEXT_14_DAYS[0].dateString;

        setTimeout(() => {
            this.showStep(2);
            this.renderDates();
            this.renderSchedule(); // Render schedule for default date
        }, 300);
    },

    renderDates() {
        const container = document.getElementById('date-selector');
        if (!container) return;

        container.innerHTML = NEXT_14_DAYS.map(day => `
            <div class="date-card ${day.dateString === this.selectedDate ? 'selected' : ''}" data-date="${day.dateString}">
                <span class="date-day-name">${day.dayName}</span>
                <span class="date-day-number">${day.dayNumber}</span>
            </div>
        `).join('');
    },

    selectDate(dateString) {
        this.selectedDate = dateString;
        this.selectedTime = null; // Reset time on date change

        // Update UI Highlights
        document.querySelectorAll('.date-card').forEach(c => c.classList.remove('selected'));
        document.querySelector(`.date-card[data-date="${dateString}"]`)?.classList.add('selected');

        // Refresh Slots
        this.renderSchedule();
    },

    renderSchedule() {
        const container = document.getElementById('time-slots');
        if (!container || !this.selectedBarber || !this.selectedDate) return;

        // Find full date object for display
        const dateObj = NEXT_14_DAYS.find(d => d.dateString === this.selectedDate);
        document.getElementById('step-2-title').innerHTML = `
            Disponibilidad de ${this.selectedBarber.name}<br>
            <span style="font-size: 0.9rem; color: var(--color-gold);">${dateObj.fullDate}</span>
        `;

        const barberSchedule = SCHEDULES[this.selectedBarber.id][this.selectedDate] || [];

        if (barberSchedule.length === 0) {
            container.innerHTML = `
                <div class="no-slots-message">
                    Este día no hay horarios disponibles o el barbero no trabaja.
                </div>
            `;
            return;
        }

        container.innerHTML = barberSchedule.map(slot => `
            <div class="time-slot ${slot.available ? '' : 'disabled'}" data-time="${slot.time}">
                <span class="slot-time">${slot.time}</span>
                <span class="slot-status">${slot.available ? 'Disponible' : 'Ocupado'}</span>
            </div>
        `).join('');
    },

    selectTime(time) {
        this.selectedTime = time;

        // Highlight
        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
        document.querySelector(`.time-slot[data-time="${time}"]`)?.classList.add('selected');

        // Confirm
        setTimeout(() => {
            this.showStep(3);
            this.renderConfirmation();
        }, 500);
    },

    renderConfirmation() {
        const container = document.getElementById('booking-confirmation');
        if (!container) return;

        const dateObj = NEXT_14_DAYS.find(d => d.dateString === this.selectedDate);

        // Save booking to localStorage
        this.saveBooking(dateObj);

        container.innerHTML = `
            <div class="success-message">
                <div class="success-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h3 style="color: white; font-size: 1.5rem; margin-bottom: 1rem;">¡Reserva Confirmada!</h3>
                <p style="color: #ccc; margin-bottom: 2rem;">
                    Has reservado con <strong style="color: var(--color-gold);">${this.selectedBarber.name}</strong><br>
                    el <strong style="color: var(--color-gold);">${dateObj.fullDate}</strong><br>
                    a las <strong style="color: var(--color-gold);">${this.selectedTime}</strong>.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <p style="font-size: 0.9rem; color: #888;">Te hemos enviado un email de confirmación.</p>
                </div>
                <button class="btn btn--primary" onclick="BookingWizard.close()">Entendido</button>
            </div>
        `;
    },

    saveBooking(dateObj) {
        // Get branch ID from localStorage
        const selectedBranch = JSON.parse(localStorage.getItem('kortzen_selected_branch') || '{}');
        const branchId = selectedBranch.id || 1;

        // Try to save via PHP API
        if (window.KortzenAPI && KortzenAPI.isLoggedIn()) {
            KortzenAPI.createBooking(
                this.selectedBarber.id,
                this.selectedDate,
                this.selectedTime,
                branchId
            ).then(result => {
                console.log('✅ Reserva guardada en DB:', result);
            }).catch(err => {
                console.warn('⚠️ API no disponible, guardando en localStorage:', err);
                this.saveBookingLocal(dateObj);
            });
        } else {
            // Fallback to localStorage
            this.saveBookingLocal(dateObj);
        }
    },

    saveBookingLocal(dateObj) {
        const bookings = JSON.parse(localStorage.getItem('kortzen_bookings') || '[]');

        const newBooking = {
            id: Date.now(),
            date: dateObj.fullDate,
            dateString: this.selectedDate,
            time: this.selectedTime,
            barber: this.selectedBarber.name,
            barberId: this.selectedBarber.id,
            createdAt: new Date().toISOString(),
            status: 'confirmed'
        };

        bookings.push(newBooking);
        localStorage.setItem('kortzen_bookings', JSON.stringify(bookings));
        console.log('✅ Reserva guardada localmente:', newBooking);
    }
};

// Initialize and Expose
window.BookingWizard = BookingWizard;
document.addEventListener('DOMContentLoaded', () => {
    BookingWizard.init();
});
