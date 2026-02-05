/**
 * KORTZEN - Gallery Module
 * Handles gallery lightbox functionality
 */

let currentImageIndex = 0;
let galleryImages = [];

/**
 * Initialize gallery lightbox
 */
export function initGallery() {
    // Collect all gallery items
    const galleryItems = document.querySelectorAll('.gallery-item');
    const filterBtns = document.querySelectorAll('.filter-btn');

    if (!galleryItems.length) return;

    // Create lightbox element
    createLightbox();

    // Initial load: all images
    updateGalleryImages();

    // Add click handlers to gallery items
    galleryItems.forEach((item) => {
        item.addEventListener('click', (e) => {
            // Prevent opening if the item is hidden
            if (item.style.display === 'none') return;

            // Find the index of this item among currently VISIBLE items
            const visibleItems = Array.from(document.querySelectorAll('.gallery-item')).filter(i => i.style.display !== 'none');
            const index = visibleItems.indexOf(item);

            if (index !== -1) {
                openLightbox(index);
            }
        });
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'button');
    });

    // --- Filter Logic ---
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Update active state
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filterValue = btn.getAttribute('data-filter');

            galleryItems.forEach(item => {
                const category = item.getAttribute('data-category');

                if (filterValue === 'all' || category === filterValue) {
                    item.style.display = ''; // Show
                    // Re-trigger animation if possible
                    item.classList.remove('revealed');
                    setTimeout(() => item.classList.add('revealed'), 10);
                } else {
                    item.style.display = 'none'; // Hide
                }
            });

            // Update lightbox images list based on visible items
            updateGalleryImages();
        });
    });
}

/**
 * Update the list of images available for the lightbox based on visibility
 */
function updateGalleryImages() {
    const visibleItems = Array.from(document.querySelectorAll('.gallery-item')).filter(item => item.style.display !== 'none');

    galleryImages = visibleItems.map((item) => {
        const img = item.querySelector('img');
        return {
            src: img.src,
            alt: img.alt || '',
        };
    });
}

/**
 * Create lightbox DOM elements
 */
function createLightbox() {
    const lightbox = document.createElement('div');
    lightbox.classList.add('lightbox');
    lightbox.id = 'lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Visor de imagen');

    lightbox.innerHTML = `
    <button class="lightbox__close" aria-label="Cerrar">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
    <button class="lightbox__nav lightbox__nav--prev" aria-label="Imagen anterior">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>
    <img class="lightbox__image" src="" alt="">
    <button class="lightbox__nav lightbox__nav--next" aria-label="Siguiente imagen">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 18 15 12 9 6"></polyline>
      </svg>
    </button>
  `;

    document.body.appendChild(lightbox);

    // Event listeners
    const closeBtn = lightbox.querySelector('.lightbox__close');
    const prevBtn = lightbox.querySelector('.lightbox__nav--prev');
    const nextBtn = lightbox.querySelector('.lightbox__nav--next');

    closeBtn.addEventListener('click', closeLightbox);
    prevBtn.addEventListener('click', showPrevImage);
    nextBtn.addEventListener('click', showNextImage);

    // Close on background click
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (!lightbox.classList.contains('lightbox--active')) return;

        switch (e.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                showPrevImage();
                break;
            case 'ArrowRight':
                showNextImage();
                break;
        }
    });
}

/**
 * Open lightbox at specified index
 * @param {number} index - Image index to display
 */
function openLightbox(index) {
    currentImageIndex = index;
    const lightbox = document.getElementById('lightbox');
    const image = lightbox.querySelector('.lightbox__image');

    image.src = galleryImages[index].src;
    image.alt = galleryImages[index].alt;

    lightbox.classList.add('lightbox--active');
    document.body.style.overflow = 'hidden';

    // Focus trap
    lightbox.querySelector('.lightbox__close').focus();
}

/**
 * Close lightbox
 */
function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('lightbox--active');
    document.body.style.overflow = '';
}

/**
 * Show previous image
 */
function showPrevImage() {
    currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
    updateLightboxImage();
}

/**
 * Show next image
 */
function showNextImage() {
    currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
    updateLightboxImage();
}

/**
 * Update lightbox image
 */
function updateLightboxImage() {
    const lightbox = document.getElementById('lightbox');
    const image = lightbox.querySelector('.lightbox__image');

    image.src = galleryImages[currentImageIndex].src;
    image.alt = galleryImages[currentImageIndex].alt;
}
