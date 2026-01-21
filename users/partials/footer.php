
<!-- Footer Section -->
<footer class="footer">
    <div class="footer-container">
        <!-- Logo Section -->
        <section class="footer-section logo-section" aria-label="Logo Politeknik Nest">
            <div class="logo-wrapper">
                <img src="assets/logo.png" alt="Logo Politeknik Nest" class="footer-logo">
                <div class="logo-text">
                    <h3>POLITEKNIK</h3>
                    <h3>NEST</h3>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="footer-section contact-section" aria-labelledby="contact-title">
            <h3 id="contact-title" class="footer-title">Hubungi Kami</h3>
            <address class="contact-address">
                <ul class="contact-list">
                    <li class="contact-item">
                        <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                        <a href="tel:+6281129510003">+6281129510003</a>
                    </li>
                    <li class="contact-item">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        <a href="https://wa.me/6281129510003" target="_blank" rel="noopener noreferrer">+6281129510003</a>
                    </li>
                    <li class="contact-item">
                        <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                        <a href="mailto:info@politekniknest.ac.id">info@politekniknest.ac.id</a>
                    </li>
                </ul>
            </address>
        </section>

        <!-- Address Section -->
        <section class="footer-section address-section" aria-labelledby="address-title">
            <h3 id="address-title" class="footer-title">Alamat Kantor</h3>
            <address class="address-text">
                Jl. Telukan - Cuplik, RT 03 RW 10,<br>
                Parangjoro, Kec.Grogol, Kab.Sukoharjo,<br>
                Jawa Tengah
            </address>
        </section>
    </div>

    <!-- Bottom Section -->
    <div class="footer-bottom">
        <div class="footer-bottom-container">
            <p class="copyright">
                <small>Copyright <time datetime="2026">2026</time>. Politeknik Nest</small>
            </p>
            
            <!-- Social Media Navigation -->
            <nav class="social-nav" aria-label="Social Media Links">
                <ul class="social-icons">
                    <li>
                        <a href="https://instagram.com/politekniknest" class="social-link" aria-label="Instagram" data-bs-toggle="tooltip" title="Instagram" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-instagram" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://facebook.com/politekniknest" class="social-link" aria-label="Facebook" data-bs-toggle="tooltip" title="Facebook" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-facebook" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://twitter.com/politekniknest" class="social-link" aria-label="Twitter" data-bs-toggle="tooltip" title="Twitter" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-twitter-x" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://tiktok.com/@politekniknest" class="social-link" aria-label="TikTok" data-bs-toggle="tooltip" title="TikTok" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-tiktok" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://linkedin.com/company/politekniknest" class="social-link" aria-label="LinkedIn" data-bs-toggle="tooltip" title="LinkedIn" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-linkedin" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://youtube.com/@politekniknest" class="social-link" aria-label="YouTube" data-bs-toggle="tooltip" title="YouTube" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-youtube" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</footer>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Inline CSS -->
<style>
    /* Footer Styling */
    .footer {
        background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
        color: white;
        padding: 40px 20px 0;
        margin: 50px auto 0;
        max-width: 100%;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .footer-container {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Logo Section */
    .logo-section {
        flex: 1;
        min-width: 250px;
    }

    .logo-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .footer-logo {
        width: 60px;
        height: 60px;
        background: white;
        border-radius: 60%;
        padding: 5px;
        object-fit: contain;
    }

    .logo-text h3 {
        margin: 0;
        font-size: 18px;
        font-weight: bold;
        line-height: 1.2;
        color: white;
    }

    /* Contact & Address Section */
    .contact-section,
    .address-section {
        flex: 1;
        min-width: 250px;
    }

    .footer-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 15px;
        color: white;
    }

    /* Address tag reset */
    address {
        font-style: normal;
    }

    .contact-address {
        margin: 0;
    }

    /* Contact List */
    .contact-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        font-size: 15px;
        color: rgba(255, 255, 255, 0.95);
    }

    .contact-item i {
        font-size: 20px;
        width: 24px;
        text-align: center;
    }

    .contact-item a {
        color: rgba(255, 255, 255, 0.95);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .contact-item a:hover {
        color: white;
        text-decoration: underline;
    }

    .address-text {
        font-size: 15px;
        line-height: 1.8;
        color: rgba(255, 255, 255, 0.95);
        margin: 0;
    }

    /* Footer Bottom */
    .footer-bottom {
        padding: 20px 0;
    }

    .footer-bottom-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .copyright {
        margin: 0;
    }

    .copyright small {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.9);
    }

    /* Social Navigation */
    .social-nav {
        margin: 0;
    }

    .social-icons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .social-icons li {
        margin: 0;
    }

    .social-link {
        width: 42px;
        height: 42px;
        background: rgba(0, 0, 0, 0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .social-link:hover,
    .social-link:focus {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-5px) scale(1.1);
        border-color: rgba(255, 255, 255, 0.5);
        color: white;
        outline: 2px solid rgba(255, 255, 255, 0.3);
        outline-offset: 2px;
    }

    .social-link i {
        font-size: 20px;
    }

    /* Responsive Design */
    @media (max-width: 100%) {
        .footer {
            border-radius: 15px;
            margin: 30px 10px 0;
            padding: 30px 15px 0;
        }

        .footer-container {
            flex-direction: column;
            gap: 25px;
        }

        .footer-section {
            min-width: 100%;
        }

        .footer-bottom-container {
            flex-direction: column;
            text-align: center;
        }

        .social-icons {
            justify-content: center;
        }

        .logo-wrapper {
            justify-content: center;
        }

        .logo-text {
            text-align: left;
        }
    }

    @media (max-width: 480px) {
        .footer-logo {
            width: 50px;
            height: 50px;
        }

        .logo-text h3 {
            font-size: 16px;
        }

        .footer-title {
            font-size: 18px;
        }

        .contact-item,
        .address-text {
            font-size: 14px;
        }

        .social-link {
            width: 38px;
            height: 38px;
        }

        .social-link i {
            font-size: 18px;
        }
    }
</style>

<!-- Bootstrap JS & Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

<!-- Inline JavaScript -->
<script>
    // Initialize Bootstrap Tooltips
    document.addEventListener('DOMContentLoaded', function() {
        // Enable tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Smooth scroll animation for social links
        const socialLinks = document.querySelectorAll('.social-link');
        socialLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Footer fade-in animation
        const footer = document.querySelector('.footer');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateY(30px)';
                    entry.target.style.transition = 'all 0.8s ease-out';
                    
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, 100);
                    
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        if (footer) {
            observer.observe(footer);
        }

        // Update copyright year
        const copyrightTime = document.querySelector('.copyright time');
        if (copyrightTime) {
            const currentYear = new Date().getFullYear();
            copyrightTime.textContent = currentYear;
            copyrightTime.setAttribute('datetime', currentYear);
        }
    });
</script>
