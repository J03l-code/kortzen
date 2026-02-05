/**
 * Preloader Logic
 */

document.addEventListener("DOMContentLoaded", () => {
    const preloader = document.querySelector('.preloader');

    // Ensure preloader stays for at least 2 seconds for branding
    const minTime = 2000;
    const startTime = Date.now();

    window.addEventListener('load', () => {
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, minTime - elapsedTime);

        setTimeout(() => {
            if (preloader) {
                preloader.classList.add('fade-out');
                // Remove from DOM after transition to free up resources
                setTimeout(() => {
                    preloader.remove();
                }, 1000);
            }
        }, remainingTime);
    });
});
