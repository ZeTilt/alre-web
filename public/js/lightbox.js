/**
 * Simple Lightbox for image galleries
 * No dependencies required
 */

class SimpleLightbox {
    constructor() {
        this.currentIndex = 0;
        this.images = [];
        this.lightboxElement = null;
        this.init();
    }

    init() {
        // Create lightbox HTML
        this.createLightbox();

        // Find all lightbox images
        const galleryImages = document.querySelectorAll('.lightbox-image');

        galleryImages.forEach((img, index) => {
            this.images.push({
                src: img.src,
                alt: img.alt,
                caption: img.dataset.caption || img.alt
            });

            // Make image clickable
            img.style.cursor = 'pointer';
            img.addEventListener('click', () => this.open(index));
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!this.lightboxElement.classList.contains('active')) return;

            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowLeft') this.prev();
            if (e.key === 'ArrowRight') this.next();
        });
    }

    createLightbox() {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-overlay"></div>
            <div class="lightbox-content">
                <button class="lightbox-close" aria-label="Fermer">&times;</button>
                <button class="lightbox-prev" aria-label="Image précédente">&lsaquo;</button>
                <button class="lightbox-next" aria-label="Image suivante">&rsaquo;</button>
                <img class="lightbox-image-display" src="" alt="">
                <div class="lightbox-caption"></div>
            </div>
        `;

        document.body.appendChild(lightbox);
        this.lightboxElement = lightbox;

        // Event listeners
        lightbox.querySelector('.lightbox-close').addEventListener('click', () => this.close());
        lightbox.querySelector('.lightbox-overlay').addEventListener('click', () => this.close());
        lightbox.querySelector('.lightbox-prev').addEventListener('click', () => this.prev());
        lightbox.querySelector('.lightbox-next').addEventListener('click', () => this.next());
    }

    open(index) {
        this.currentIndex = index;
        this.show();
        this.lightboxElement.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.lightboxElement.classList.remove('active');
        document.body.style.overflow = '';
    }

    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        this.show();
    }

    next() {
        this.currentIndex = (this.currentIndex + 1) % this.images.length;
        this.show();
    }

    show() {
        const image = this.images[this.currentIndex];
        const imgElement = this.lightboxElement.querySelector('.lightbox-image-display');
        const captionElement = this.lightboxElement.querySelector('.lightbox-caption');

        imgElement.src = image.src;
        imgElement.alt = image.alt;
        captionElement.textContent = image.caption;

        // Show/hide navigation buttons
        const prevBtn = this.lightboxElement.querySelector('.lightbox-prev');
        const nextBtn = this.lightboxElement.querySelector('.lightbox-next');

        if (this.images.length <= 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        } else {
            prevBtn.style.display = 'block';
            nextBtn.style.display = 'block';
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new SimpleLightbox());
} else {
    new SimpleLightbox();
}
