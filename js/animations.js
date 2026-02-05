/**
 * Advanced Scroll Animations
 */

document.addEventListener("DOMContentLoaded", () => {

    // Text Reveal Observer
    const revealObserverOptions = {
        threshold: 0.15, // Trigger when 15% visible
        rootMargin: "0px 0px -50px 0px" // Trigger slightly before element is fully in view
    };

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target); // Animate only once
            }
        });
    }, revealObserverOptions);

    const revealElements = document.querySelectorAll('[data-reveal-text]');
    revealElements.forEach(el => revealObserver.observe(el));

    // Simple Parallax Image Observer (Scale Effect)
    const parallaxObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in-view');
            } else {
                entry.target.classList.remove('is-in-view');
            }
        });
    }, { threshold: 0.2 });

    const parallaxImages = document.querySelectorAll('.parallax-image');
    parallaxImages.forEach(el => parallaxObserver.observe(el));


    // Clone Marquee Content for Infinite Loop
    const marquees = document.querySelectorAll('.marquee-content');
    marquees.forEach(marquee => {
        // Clone children to ensure seamless loop
        const content = marquee.innerHTML;
        marquee.innerHTML = content + content + content + content; // Clone 4 times to be safe
    });

});
