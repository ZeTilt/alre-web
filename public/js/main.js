/**
 * ZETiLT - Site Vitrine Freelance Symfony
 * JavaScript Principal
 */

document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // 1. NAVBAR - Scroll effect et menu mobile
    // ============================================
    const navbar = document.querySelector('.navbar');
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarMenu = document.querySelector('.navbar-menu');
    const navbarLinks = document.querySelectorAll('.navbar-menu a');

    // Effet de scroll sur la navbar
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar?.classList.add('scrolled');
        } else {
            navbar?.classList.remove('scrolled');
        }
    });

    // Toggle menu mobile
    navbarToggle?.addEventListener('click', function() {
        navbarMenu?.classList.toggle('active');
        this.innerHTML = navbarMenu?.classList.contains('active')
            ? '<i class="fas fa-times"></i>'
            : '<i class="fas fa-bars"></i>';
    });

    // Dropdown mobile toggle
    const navDropdown = document.querySelector('.navbar-dropdown');
    if (navDropdown) {
        const dropdownLink = navDropdown.querySelector(':scope > a');
        dropdownLink?.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                navDropdown.classList.toggle('open');
            }
        });
    }

    // Fermer le menu mobile au clic sur un lien
    navbarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                navbarMenu?.classList.remove('active');
                if (navbarToggle) {
                    navbarToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }
        });
    });

    // Marquer le lien actif dans la navigation
    const currentPath = window.location.pathname;
    navbarLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ============================================
    // 2. SMOOTH SCROLL pour les ancres
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const offsetTop = target.offsetTop - 80; // 80px pour la navbar
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // ============================================
    // 3. ANIMATIONS AU SCROLL (Fade-in)
    // ============================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observer les cartes et sections
    document.querySelectorAll('.card, .service-card, .project-card, .pricing-card, .testimonial-card').forEach(el => {
        observer.observe(el);
    });

    // ============================================
    // 4. FORMULAIRE DE CONTACT - Validation
    // ============================================
    const contactForm = document.querySelector('#contact-form');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            // Validation côté client basique
            const requiredFields = contactForm.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                const errorElement = field.parentElement.querySelector('.form-error');

                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    if (errorElement) {
                        errorElement.style.display = 'block';
                    }
                } else {
                    field.classList.remove('error');
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                }
            });

            // Validation email
            const emailField = contactForm.querySelector('input[type="email"]');
            if (emailField && emailField.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value)) {
                    isValid = false;
                    emailField.classList.add('error');
                    const errorElement = emailField.parentElement.querySelector('.form-error');
                    if (errorElement) {
                        errorElement.textContent = 'Veuillez entrer une adresse email valide';
                        errorElement.style.display = 'block';
                    }
                }
            }

            if (!isValid) {
                e.preventDefault();
                // Scroll vers le premier champ en erreur
                const firstError = contactForm.querySelector('.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        });

        // Retirer l'erreur lors de la saisie
        const formFields = contactForm.querySelectorAll('.form-control');
        formFields.forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('error');
                const errorElement = this.parentElement.querySelector('.form-error');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        });
    }

    // ============================================
    // 5. BOUTON "RETOUR EN HAUT"
    // ============================================
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollTopBtn.className = 'scroll-top-btn';
    scrollTopBtn.setAttribute('aria-label', 'Retour en haut');
    document.body.appendChild(scrollTopBtn);

    // Ajouter les styles pour le bouton
    const style = document.createElement('style');
    style.textContent = `
        .scroll-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--accent-color, #FF6B35);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }
        .scroll-top-btn.visible {
            opacity: 1;
            visibility: visible;
        }
        .scroll-top-btn:hover {
            background-color: var(--accent-dark, #e65520);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
    `;
    document.head.appendChild(style);

    // Afficher/masquer le bouton selon le scroll
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    });

    // Action du bouton
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // ============================================
    // 6. GESTION DES CARTES INTERACTIVES
    // ============================================
    // Ne pas appliquer l'effet de tilt sur les pages légales et le portfolio
    const isLegalPage = window.location.pathname.includes('/mentions-legales') ||
                        window.location.pathname.includes('/politique-de-confidentialite') ||
                        window.location.pathname.includes('/cgv');
    const isPortfolioPage = window.location.pathname.includes('/portfolio');

    if (!isLegalPage && !isPortfolioPage) {
        const cards = document.querySelectorAll('.card, .service-card');

        cards.forEach(card => {
            // Effet de parallax très subtil au survol
            card.addEventListener('mousemove', function(e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                // Division par 150 au lieu de 20 pour un effet beaucoup plus léger
                const rotateX = (y - centerY) / 150;
                const rotateY = (centerX - x) / 150;

                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
            });
        });
    }

    // ============================================
    // 7. COMPTEUR ANIMÉ (si présent sur la page)
    // ============================================
    const counters = document.querySelectorAll('.counter');

    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 secondes
        const increment = target / (duration / 16); // 60 FPS
        let current = 0;

        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = String(Math.floor(current));
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = String(target);
            }
        };

        // Démarrer l'animation quand l'élément est visible
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counterObserver.observe(counter);
    });

    // ============================================
    // 8. FILTRES PORTFOLIO (si présent)
    // ============================================
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');

            projectCards.forEach(card => {
                if (filter === 'all' || card.getAttribute('data-category') === filter) {
                    card.style.display = 'block';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, 10);
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        });
    });

    // ============================================
    // 9. FLASH MESSAGES AUTO-DISMISS
    // ============================================
    const flashMessages = document.querySelectorAll('.flash-message');

    flashMessages.forEach(message => {
        // Auto-dismiss après 5 secondes
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);

        // Bouton de fermeture
        const closeBtn = message.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 300);
            });
        }
    });

    // ============================================
    // 10. LAZY LOADING IMAGES (amélioration performance)
    // ============================================
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // ============================================
    // 11. ACCORDÉON (FAQ si présent)
    // ============================================
    const accordionHeaders = document.querySelectorAll('.accordion-header');

    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const accordionItem = this.parentElement;
            const accordionContent = accordionItem.querySelector('.accordion-content');
            const isActive = accordionItem.classList.contains('active');

            // Fermer tous les accordéons
            document.querySelectorAll('.accordion-item').forEach(item => {
                item.classList.remove('active');
                item.querySelector('.accordion-content').style.maxHeight = null;
            });

            // Ouvrir l'accordéon cliqué s'il était fermé
            if (!isActive) {
                accordionItem.classList.add('active');
                accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
            }
        });
    });

    // ============================================
    // 12. PROTECTION CONTRE LE SPAM (Honeypot)
    // ============================================
    // Ajouter un champ honeypot invisible au formulaire de contact
    if (contactForm) {
        const honeypot = document.createElement('input');
        honeypot.type = 'text';
        honeypot.name = 'website';
        honeypot.style.display = 'none';
        honeypot.tabIndex = -1;
        honeypot.autocomplete = 'off';
        contactForm.appendChild(honeypot);

        contactForm.addEventListener('submit', function(e) {
            if (honeypot.value) {
                e.preventDefault();
                console.log('Spam détecté');
            }
        });
    }
});
