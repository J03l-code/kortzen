/**
 * Interactive Features: Sticky Button & Quiz
 */

document.addEventListener("DOMContentLoaded", () => {

    // --- Sticky Button Logic ---
    const stickyBtn = document.querySelector('.sticky-cta');
    const heroSection = document.querySelector('.hero');

    if (stickyBtn && heroSection) {
        window.addEventListener('scroll', () => {
            const heroBottom = heroSection.getBoundingClientRect().bottom;

            // Show button after passing hero section
            if (heroBottom < 0) {
                stickyBtn.classList.add('is-visible');
            } else {
                stickyBtn.classList.remove('is-visible');
            }
        });
    }

    // --- Quiz Logic ---
    const quizModal = document.getElementById('quiz-modal');
    const closeBtn = document.querySelector('.quiz-close');
    const quizTrigger = document.getElementById('quiz-trigger'); // Element to open modal
    const steps = document.querySelectorAll('.quiz-step');
    const progressBar = document.querySelector('.quiz-progress-bar');

    let currentStep = 0;
    const totalSteps = steps.length - 1; // Exclude result step

    // Open Modal
    if (quizTrigger) {
        quizTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            quizModal.classList.add('is-open');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
    }

    // Close Modal
    function closeModal() {
        quizModal.classList.remove('is-open');
        document.body.style.overflow = '';
        setTimeout(resetQuiz, 300);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // Initial Progress
    updateProgress();

    // Option Selection
    const options = document.querySelectorAll('.quiz-option-btn');
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            const nextStepId = opt.dataset.next;

            // Simulate processing time
            opt.style.borderColor = 'var(--color-white-pure)';
            opt.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';

            setTimeout(() => {
                if (nextStepId === 'result') {
                    showResult(opt.dataset.style);
                } else {
                    goToStep(parseInt(nextStepId));
                }
            }, 400);
        });
    });

    function goToStep(stepIndex) {
        steps[currentStep].classList.remove('is-active');
        currentStep = stepIndex;
        steps[currentStep].classList.add('is-active');
        updateProgress();
    }

    function updateProgress() {
        const progress = Math.min(((currentStep + 1) / totalSteps) * 100, 100);
        if (progressBar) progressBar.style.width = `${progress}%`;
    }

    function showResult(styleType) {
        steps[currentStep].classList.remove('is-active');
        const resultStep = document.getElementById('step-result');
        resultStep.classList.add('is-active');

        // Customizing result based on selection (Simplified logic)
        const resultTitle = resultStep.querySelector('h3');
        const resultDesc = resultStep.querySelector('p');

        if (styleType === 'classic') {
            resultTitle.textContent = "El Caballero Clásico";
            resultDesc.textContent = "Tu estilo ideal es un corte ejecutivo a tijera, acompañado de un afeitado tradicional con toalla caliente.";
        } else if (styleType === 'modern') {
            resultTitle.textContent = "El Vanguardista";
            resultDesc.textContent = "Te recomendamos un Fade texturizado con diseño sutil y perfilado de barba geométrico.";
        } else {
            // Default/Relaxed
            resultTitle.textContent = "El Natural Sofisticado";
            resultDesc.textContent = "Un corte de mantenimiento medio con styling natural y tratamiento facial hidratante es perfecto para ti.";
        }
    }

    function resetQuiz() {
        steps.forEach(step => step.classList.remove('is-active'));
        currentStep = 0;
        steps[0].classList.add('is-active');

        options.forEach(opt => {
            opt.style.borderColor = '';
            opt.style.backgroundColor = '';
        });
        updateProgress();
    }
});
