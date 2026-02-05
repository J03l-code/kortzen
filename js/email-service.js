/**
 * KORTZEN - Email Service (EmailJS Integration)
 * Handles sending real emails via EmailJS.
 */

const EmailService = {
    // PLACEHOLDERS - User needs to replace these with their own from emailjs.com
    SERVICE_ID: 'YOUR_SERVICE_ID',
    TEMPLATE_ID: 'YOUR_TEMPLATE_ID',
    PUBLIC_KEY: 'YOUR_PUBLIC_KEY',

    init() {
        if (typeof emailjs !== 'undefined') {
            emailjs.init(this.PUBLIC_KEY);
            console.log('✅ EmailJS Initialized');
        } else {
            console.warn('⚠️ EmailJS SDK not found');
        }
    },

    /**
     * Sends a booking confirmation email
     * @param {Object} bookingDetails 
     * @returns {Promise}
     */
    sendBookingConfirmation(bookingDetails) {
        if (this.PUBLIC_KEY === 'YOUR_PUBLIC_KEY') {
            console.warn('⚠️ EmailJS not configured. Simulating email send.');
            return new Promise(resolve => setTimeout(resolve, 1000));
        }

        const templateParams = {
            to_name: bookingDetails.userName,
            to_email: bookingDetails.userEmail,
            barber_name: bookingDetails.barberName,
            date: bookingDetails.date,
            time: bookingDetails.time,
            service_name: "Experiencia Premium (Corte)" // Default or dynamic
        };

        return emailjs.send(this.SERVICE_ID, this.TEMPLATE_ID, templateParams)
            .then(function (response) {
                console.log('SUCCESS!', response.status, response.text);
                return response;
            }, function (error) {
                console.log('FAILED...', error);
                throw error;
            });
    }
};

// Expose
window.EmailService = EmailService;

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    // Wait for SDK load
    if (window.emailjs) {
        EmailService.init();
    } else {
        setTimeout(() => EmailService.init(), 1000); // Retry
    }
});
