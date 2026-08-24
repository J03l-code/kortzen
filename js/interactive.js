/**
 * Interactive Features: Sticky Button & Gentleman Style Preferences Questionnaire
 */

document.addEventListener("DOMContentLoaded", () => {

    // --- Sticky Button Logic ---
    const stickyBtn = document.querySelector('.sticky-cta');
    const heroSection = document.querySelector('.hero');

    if (stickyBtn && heroSection) {
        window.addEventListener('scroll', () => {
            const heroBottom = heroSection.getBoundingClientRect().bottom;
            if (heroBottom < 0) {
                stickyBtn.classList.add('is-visible');
            } else {
                stickyBtn.classList.remove('is-visible');
            }
        });
    }

    // --- Quiz & Preferences Logic ---
    const quizModal = document.getElementById('quiz-modal');
    const closeBtn = document.querySelector('.quiz-close');
    const quizTrigger = document.getElementById('quiz-trigger'); // Botón ¿Cuál es tu estilo?
    const steps = document.querySelectorAll('.quiz-step');
    const progressBar = document.querySelector('.quiz-progress-bar');

    let currentStep = 0;
    const totalSteps = steps.length - 1;

    // Almacenar elecciones del cliente
    const clientPreferences = {
        estilo: '',
        ambiente: '',
        bebida: ''
    };

    // Open Modal
    if (quizTrigger) {
        quizTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            quizModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        });
    }

    // Close Modal
    function closeModal() {
        if (quizModal) {
            quizModal.classList.remove('is-open');
            document.body.style.overflow = '';
            setTimeout(resetQuiz, 300);
        }
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    updateProgress();

    // Selection Handling
    const options = document.querySelectorAll('.quiz-option-btn');
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            const nextStep = opt.dataset.next;
            const prefKey = opt.dataset.prefKey;
            const prefVal = opt.dataset.prefVal;

            if (prefKey && prefVal) {
                clientPreferences[prefKey] = prefVal;
            }

            opt.style.borderColor = 'var(--color-white-pure)';
            opt.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';

            setTimeout(() => {
                if (nextStep === 'result') {
                    saveAndShowResults();
                } else {
                    const stepNum = parseInt(nextStep);
                    goToStep(stepNum - 1);
                }
            }, 300);
        });
    });

    function goToStep(stepIndex) {
        if (steps[currentStep]) steps[currentStep].classList.remove('is-active');
        currentStep = stepIndex;
        if (steps[currentStep]) steps[currentStep].classList.add('is-active');
        updateProgress();
    }

    function updateProgress() {
        const progress = Math.min(((currentStep + 1) / totalSteps) * 100, 100);
        if (progressBar) progressBar.style.width = `${progress}%`;
    }

    async function saveAndShowResults() {
        if (steps[currentStep]) steps[currentStep].classList.remove('is-active');
        const resultStep = document.getElementById('step-result');
        if (resultStep) resultStep.classList.add('is-active');

        // Guardar en localStorage
        localStorage.setItem('kortzen_client_preferences', JSON.stringify(clientPreferences));

        // Enviar a la API backend
        try {
            const formData = new FormData();
            formData.append('estilo_buscado', clientPreferences.estilo);
            formData.append('ambiente_preferido', clientPreferences.ambiente);
            formData.append('bebida_preferida', clientPreferences.bebida);

            fetch('api/guardar_preferencias_cliente.php', {
                method: 'POST',
                body: formData
            }).catch(e => console.warn('Preferencias guardadas localmente'));
        } catch (e) {
            console.warn(e);
        }

        // Renderizar resumen personalizado con alta legibilidad y contraste
        const summaryText = document.getElementById('quiz-summary-text');
        if (summaryText) {
            summaryText.innerHTML = `
                <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 18px; margin-bottom: 20px; text-align: left;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: #FFFFFF; margin-bottom: 12px; text-align: center; text-transform: uppercase; letter-spacing: 1.5px;">
                        Respuestas vinculadas a tu perfil:
                    </div>
                    <div style="font-size: 0.95rem; color: #FFFFFF; margin-bottom: 8px; line-height: 1.5;">
                        <strong style="color: #FFFFFF; font-weight: 800;">• Estilo:</strong> <span style="color: #FFFFFF; font-weight: 600;">${clientPreferences.estilo || 'Personalizado'}</span>
                    </div>
                    <div style="font-size: 0.95rem; color: #FFFFFF; margin-bottom: 8px; line-height: 1.5;">
                        <strong style="color: #FFFFFF; font-weight: 800;">• Ambiente:</strong> <span style="color: #FFFFFF; font-weight: 600;">${clientPreferences.ambiente || 'Relajado'}</span>
                    </div>
                    <div style="font-size: 0.95rem; color: #FFFFFF; line-height: 1.5;">
                        <strong style="color: #FFFFFF; font-weight: 800;">• Bebida:</strong> <span style="color: #FFFFFF; font-weight: 600;">${clientPreferences.bebida || 'A elección'}</span>
                    </div>
                </div>
                <div style="font-size: 0.85rem; color: #CCCCCC; font-style: italic; text-align: center; line-height: 1.5;">
                    Tu barbero asignado revisará estas preferencias antes de iniciar tu atención.
                </div>
            `;
        }
    }

    function resetQuiz() {
        steps.forEach(step => step.classList.remove('is-active'));
        currentStep = 0;
        if (steps[0]) steps[0].classList.add('is-active');

        options.forEach(opt => {
            opt.style.borderColor = '';
            opt.style.backgroundColor = '';
        });
        updateProgress();
    }
});
