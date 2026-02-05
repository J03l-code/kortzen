/**
 * KORTZEN - Navigation Module
 * Handles header scroll effects and mobile menu
 */

/**
 * Initialize all navigation functionality
 */
export function initNavigation() {
    initHeaderScroll();
    initMobileMenu();
}

/**
 * Handle header background change on scroll
 */
function initHeaderScroll() {
    const header = document.querySelector('.header');
    if (!header) return;

    const scrollThreshold = 50;

    function updateHeader() {
        if (window.scrollY > scrollThreshold) {
            header.classList.add('header--scrolled');
        } else {
            header.classList.remove('header--scrolled');
        }
    }

    // Initial check
    updateHeader();

    // Throttled scroll handler
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                updateHeader();
                ticking = false;
            });
            ticking = true;
        }
    });
}

/**
 * Initialize mobile menu toggle and functionality
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileNav = document.querySelector('.mobile-nav');
    const mobileLinks = document.querySelectorAll('.mobile-nav__link');

    if (!menuToggle || !mobileNav) return;

    let isOpen = false;

    function openMenu() {
        isOpen = true;
        menuToggle.classList.add('menu-toggle--active');
        mobileNav.classList.add('mobile-nav--active');
        document.body.style.overflow = 'hidden';
        menuToggle.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        isOpen = false;
        menuToggle.classList.remove('menu-toggle--active');
        mobileNav.classList.remove('mobile-nav--active');
        document.body.style.overflow = '';
        menuToggle.setAttribute('aria-expanded', 'false');
    }

    function toggleMenu() {
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    // Toggle button click
    menuToggle.addEventListener('click', toggleMenu);

    // Close on link click
    mobileLinks.forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) {
            closeMenu();
        }
    });

    // Close on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024 && isOpen) {
            closeMenu();
        }
    });
}

/**
 * Smooth scroll to anchor
 * @param {string} targetId - The ID of the target element
 */
export function smoothScrollTo(targetId) {
    const target = document.querySelector(targetId);
    if (!target) return;

    const headerHeight = document.querySelector('.header')?.offsetHeight || 0;
    const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight;

    window.scrollTo({
        top: targetPosition,
        behavior: 'smooth',
    });
}
