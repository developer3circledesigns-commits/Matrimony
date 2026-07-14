</main>
<footer class="site-footer">
    <div class="container py-3">
        <div class="row g-2">
            <div class="col-lg-4 col-md">
                <a href="/" class="footer-brand mb-3 d-inline-block">
                    <img src="/assets/Top_nav_logo_1.png" alt="Matrimony Logo" width="200" height="55">
                </a>
                <p class="footer-desc">Connecting hearts, building futures. A trusted platform helping families find meaningful alliances since 2004.</p>
                <div class="footer-social">
                    <a href="https://facebook.com/bridegroommatrimony" class="social-link" aria-label="Facebook" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://twitter.com/bridegroommatrimony" class="social-link" aria-label="Twitter" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="https://instagram.com/bridegroommatrimony" class="social-link" aria-label="Instagram" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="https://youtube.com/@bridegroommatrimony" class="social-link" aria-label="YouTube" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h5 class="footer-heading">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/matches">Profile Matches</a></li>
                    <li><a href="/about">About Us</a></li>
                    <li><a href="<?= !empty($_SESSION['user_id']) ? '/profile' : '/users/login' ?>">Profile</a></li>
                    <li><a href="/packages">Packages</a></li>
                    <li><a href="/contact">Contact Us</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5 class="footer-heading">Support</h5>
                <ul class="footer-links">
                    <li><a href="/faq">FAQ &amp; Help</a></li>
                    <li><a href="/privacy">Privacy Policy</a></li>
                    <li><a href="/terms">Terms of Service</a></li>
                    <li><a href="/cookies">Cookie Policy</a></li>
                    <li><a href="/safety">Safety Tips</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5 class="footer-heading">Contact Us</h5>
                <ul class="footer-links footer-contact-list">
                    <li class="footer-contact-address">No.24/36, Viswanathapuram Main Road, Kodambakkam, Chennai - 600 024.</li>
                    <li><a href="tel:+919384772710">+91 93847 72710</a></li>
                    <li><a href="tel:+919952619776">+91 99526 19776</a></li>
                    <li><a href="mailto:info@bridegroom-matrimony.com">info@bridegroom-matrimony.com</a></li>
                    
                    <li class="footer-contact-hours">Mon - Sat: 10 AM - 6 PM</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col text-center text-md-start mb-2 mb-md-0">
                    <p class="mb-0">&copy; <?= date('Y') ?> Matrimony. All rights reserved.</p>
                </div>
                <div class="col-auto text-center text-md-end ms-auto">
                    <p class="mb-0">Designed and Developed By <a href="https://thecircledesigns.com/" target="_blank"  style="text-decoration: none; color: inherit;">Circle Designs</a></p>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Enquiry Now Button -->
<a href="/contact#contact-form" class="enquiry-now-btn" aria-label="Enquiry Now">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span>Enquiry Now</span>
</a>

<!-- Global Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite" aria-relevant="additions"></div>

<!-- Global Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2 w-100">
          <div id="confirmModalIcon" class="flex-shrink-0"></div>
          <h5 class="modal-title fw-semibold" id="confirmModalLabel">Confirm</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3" id="confirmModalBody">
        <p class="mb-0 text-muted">Are you sure?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary" id="confirmModalCancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmModalConfirm">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- Global Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2 w-100">
          <span id="alertModalIcon" class="flex-shrink-0 fs-4"></span>
          <h5 class="modal-title fw-semibold" id="alertModalLabel">Alert</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3" id="alertModalBody">
        <p class="mb-0 text-muted"></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="alertModalClose">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Global Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 bg-transparent shadow-none">
      <div class="modal-body text-center py-5">
        <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-white fw-medium mb-0" id="loadingModalText" style="text-shadow:0 1px 4px rgba(0,0,0,0.5);">Please wait...</p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js?v=<?= filemtime(__DIR__ . '/../js/app.js') ?>"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, easing: 'ease-out-cubic', once: true, offset: 60 });
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
</script>
<?php if (!empty($pageScripts ?? '')) echo $pageScripts; ?>
</body>
</html>
