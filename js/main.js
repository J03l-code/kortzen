/**
 * KORTZEN - Main JavaScript Entry Point
 * Premium Barbershop Website
 */

import { initNavigation } from './navigation.js';
import { initGallery } from './gallery.js';
import { initForms } from './forms.js';

/**
 * Initialize all modules when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
  // Initialize navigation (header scroll, mobile menu)
  initNavigation();

  // Initialize gallery lightbox if on gallery page
  if (document.querySelector('.gallery-grid')) {
    initGallery();
  }

  // Initialize form validation
  initForms();

  // Initialize testimonials slider if present
  if (document.querySelector('.testimonials-slider')) {
    loadTestimonials().then(() => {
      initTestimonialsSlider();
    });
  }

  // Add scroll reveal animations
  initScrollReveal();
});

/**
 * Load testimonials from API
 */
async function loadTestimonials() {
  const track = document.getElementById('testimonials-track');
  if (!track) return;

  try {
    const response = await fetch('/api/get_reviews.php?t=' + Date.now());
    if (!response.ok) throw new Error('Network response was not ok');
    const data = await response.json();

    if (data.success && data.reviews.length > 0) {
      track.innerHTML = ''; // Clear fallback
      data.reviews.forEach(review => {
        const div = document.createElement('div');
        div.className = 'testimonial';

        // Stars logic not needed in design but can be added if desired. 
        // Design uses quote style.

        div.innerHTML = `
                  <blockquote class="testimonial__quote">
                    "${review.comentario}"
                  </blockquote>
                  <cite class="testimonial__author">— ${review.cliente_nombre}</cite>
                `;
        track.appendChild(div);
      });
    }
  } catch (e) {
    console.error('Error loading reviews:', e);
    // Fallback content in case of error is handled by existing HTML or empty
  }
}

/**
 * Initialize testimonials slider
 */
function initTestimonialsSlider() {
  const slider = document.querySelector('.testimonials-slider');
  const track = slider.querySelector('.testimonials-track');
  const slides = track.querySelectorAll('.testimonial');
  const dotsContainer = slider.querySelector('.testimonials-dots');

  // Clear existing dots and listeners if any (simple reset)
  dotsContainer.innerHTML = '';
  const oldClone = track.cloneNode(true);
  track.parentNode.replaceChild(oldClone, track);
  const newTrack = slider.querySelector('.testimonials-track');

  // Re-select slides from new track
  const newSlides = newTrack.querySelectorAll('.testimonial');

  if (newSlides.length <= 1) return;

  let currentSlide = 0;
  let autoplayInterval;

  // Create dots
  newSlides.forEach((_, index) => {
    const dot = document.createElement('button');
    dot.classList.add('testimonials-dot');
    if (index === 0) dot.classList.add('testimonials-dot--active');
    dot.setAttribute('aria-label', `Ir al testimonio ${index + 1}`);
    dot.addEventListener('click', () => goToSlide(index));
    dotsContainer.appendChild(dot);
  });

  const dots = dotsContainer.querySelectorAll('.testimonials-dot');

  function goToSlide(index) {
    currentSlide = index;
    newTrack.style.transform = `translateX(-${currentSlide * 100}%)`;

    dots.forEach((dot, i) => {
      dot.classList.toggle('testimonials-dot--active', i === currentSlide);
    });
  }

  function nextSlide() {
    const next = (currentSlide + 1) % newSlides.length;
    goToSlide(next);
  }

  // Autoplay
  function startAutoplay() {
    // Clear any existing interval just in case
    clearInterval(autoplayInterval);
    autoplayInterval = setInterval(nextSlide, 5000);
  }

  function stopAutoplay() {
    clearInterval(autoplayInterval);
  }

  slider.addEventListener('mouseenter', stopAutoplay);
  slider.addEventListener('mouseleave', startAutoplay);

  startAutoplay();
}

/**
 * Initialize scroll reveal animations using Intersection Observer
 */
function initScrollReveal() {
  const revealElements = document.querySelectorAll('[data-reveal]');

  if (!revealElements.length) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px',
    }
  );

  revealElements.forEach((el) => {
    el.classList.add('reveal-hidden');
    observer.observe(el);
  });
}

// Add CSS for scroll reveal
const style = document.createElement('style');
style.textContent = `
  .reveal-hidden {
    opacity: 0;
    transform: translateY(30px);
  }
  
  .revealed {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.6s ease-out, transform 0.6s ease-out;
  }
`;
document.head.appendChild(style);
