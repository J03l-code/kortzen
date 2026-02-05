/**
 * KORTZEN - Forms Module
 * Form validation and handling
 */

/**
 * Initialize form validation
 */
export function initForms() {
    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach((form) => {
        form.addEventListener('submit', handleFormSubmit);

        // Real-time validation on blur
        const inputs = form.querySelectorAll('.form-input');
        inputs.forEach((input) => {
            input.addEventListener('blur', () => validateInput(input));
            input.addEventListener('input', () => clearError(input));
        });
    });
}

/**
 * Handle form submission
 * @param {Event} e - Submit event
 */
function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;

    // Validate all inputs
    const inputs = form.querySelectorAll('.form-input[required]');
    let isValid = true;

    inputs.forEach((input) => {
        if (!validateInput(input)) {
            isValid = false;
        }
    });

    if (isValid) {
        // Form is valid, handle submission
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Show success message (simulated)
        showFormSuccess(form);

        // Reset form after delay
        setTimeout(() => {
            form.reset();
        }, 2000);
    }
}

/**
 * Validate a single input
 * @param {HTMLInputElement} input - Input element to validate
 * @returns {boolean} - Whether input is valid
 */
function validateInput(input) {
    const value = input.value.trim();
    const type = input.type;
    const name = input.name;

    // Required check
    if (input.hasAttribute('required') && !value) {
        showError(input, 'Este campo es obligatorio');
        return false;
    }

    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showError(input, 'Por favor, introduce un email válido');
            return false;
        }
    }

    // Phone validation
    if (type === 'tel' && value) {
        const phoneRegex = /^[+]?[\d\s-]{9,}$/;
        if (!phoneRegex.test(value)) {
            showError(input, 'Por favor, introduce un teléfono válido');
            return false;
        }
    }

    // Password validation (for login)
    if (type === 'password' && value) {
        if (value.length < 6) {
            showError(input, 'La contraseña debe tener al menos 6 caracteres');
            return false;
        }
    }

    // If all checks pass
    clearError(input);
    return true;
}

/**
 * Show error message for input
 * @param {HTMLInputElement} input - Input element
 * @param {string} message - Error message
 */
function showError(input, message) {
    input.classList.add('form-input--error');

    // Check if error element already exists
    let errorEl = input.parentElement.querySelector('.form-error');

    if (!errorEl) {
        errorEl = document.createElement('span');
        errorEl.classList.add('form-error');
        input.parentElement.appendChild(errorEl);
    }

    errorEl.textContent = message;
}

/**
 * Clear error from input
 * @param {HTMLInputElement} input - Input element
 */
function clearError(input) {
    input.classList.remove('form-input--error');

    const errorEl = input.parentElement.querySelector('.form-error');
    if (errorEl) {
        errorEl.remove();
    }
}

/**
 * Show form success message
 * @param {HTMLFormElement} form - Form element
 */
function showFormSuccess(form) {
    const successEl = document.createElement('div');
    successEl.classList.add('form-success');
    successEl.innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span>¡Mensaje enviado correctamente!</span>
  `;

    // Add styles
    successEl.style.cssText = `
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    background-color: rgba(74, 124, 89, 0.2);
    border: 1px solid #4A7C59;
    color: #4A7C59;
    margin-top: 16px;
  `;

    form.appendChild(successEl);

    // Remove after delay
    setTimeout(() => {
        successEl.remove();
    }, 3000);
}

/**
 * Validate contact form specifically
 * @param {HTMLFormElement} form - Contact form element
 * @returns {boolean} - Whether form is valid
 */
export function validateContactForm(form) {
    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const message = form.querySelector('[name="message"]');

    let isValid = true;

    if (name && !name.value.trim()) {
        showError(name, 'Por favor, introduce tu nombre');
        isValid = false;
    }

    if (email && !validateEmail(email.value)) {
        showError(email, 'Por favor, introduce un email válido');
        isValid = false;
    }

    if (message && !message.value.trim()) {
        showError(message, 'Por favor, escribe tu mensaje');
        isValid = false;
    }

    return isValid;
}

/**
 * Validate email format
 * @param {string} email - Email string
 * @returns {boolean} - Whether email is valid
 */
function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}
