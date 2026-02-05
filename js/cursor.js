/**
 * Custom Cursor Implementation - Simple
 */

document.addEventListener("DOMContentLoaded", () => {
    // Check if device is desktop
    if (window.matchMedia("(pointer: coarse)").matches) return;

    // Create single cursor element
    const cursorDot = document.createElement("div");
    cursorDot.className = "cursor-dot";
    document.body.appendChild(cursorDot);

    // Mouse movement - Direct tracking, no lag
    window.addEventListener("mousemove", (e) => {
        cursorDot.style.left = `${e.clientX}px`;
        cursorDot.style.top = `${e.clientY}px`;
    });

    // Simple hover state check
    const interactiveElements = document.querySelectorAll("a, button, .btn, input, textarea, select, .testimonial, .quiz-option-btn");

    interactiveElements.forEach(el => {
        el.addEventListener("mouseenter", () => {
            document.body.classList.add("hovering");
        });

        el.addEventListener("mouseleave", () => {
            document.body.classList.remove("hovering");
        });
    });
});
