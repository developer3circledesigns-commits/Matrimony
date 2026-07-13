<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="container-fluid px-4 px-lg-5 hero-inner">
        <div class="row min-vh-100 align-items-center py-5">
            <div class="col-lg-7 col-md-9 text-center text-md-start mx-auto mx-md-0 mt-5 mt-md-0">
                <h1 class="hero-title text-white mb-4 hero-anim-1">Find Your Perfect Match</h1>
                <p class="hero-subtitle text-white-75 mb-5 hero-anim-2" style="text-align: justify;">A trusted platform helping families find meaningful alliances. Your journey toward a lifelong bond begins here.</p>
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 hero-anim-3">
                    <?php if (!$isLoggedIn): ?>
                    <button type="button" class="btn btn-accent btn-lg rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#quickRegisterModal">Register Free</button>
                    <?php else: ?>
                    <a href="/users/register" class="btn btn-accent btn-lg rounded-pill px-4">Register Free</a>
                    <?php endif; ?>
                    <a href="/matches" class="btn btn-outline-light btn-lg rounded-pill px-4">Browse Profiles</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!$isLoggedIn): ?>
<div class="modal fade" id="quickRegisterModal" tabindex="-1" aria-labelledby="quickRegisterLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-qr">
        <div class="modal-content modal-qr-content">
            <div class="modal-header modal-qr-header">
                <h5 class="modal-title" id="quickRegisterLabel">Create Free Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="hero-register-form" novalidate>
                <?= csrf_field() ?>
                <div class="modal-body modal-qr-body">
                    <p class="modal-qr-subtitle">Find your perfect life partner</p>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" placeholder="First name" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" placeholder="Last name">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">I'm looking for a</label>
                        <div class="d-flex gap-2">
                            <label class="qr-gender flex-fill">
                                <input type="radio" name="gender" value="female" checked>
                                <span class="qr-gender-label">Female</span>
                            </label>
                            <label class="qr-gender flex-fill">
                                <input type="radio" name="gender" value="male">
                                <span class="qr-gender-label">Male</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" placeholder="Min 8 characters" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Toggle password visibility">
                                <svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter password" required>
                            <button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Toggle password visibility">
                                <svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-qr-footer">
                    <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold qr-submit-btn">
                        <span class="qr-btn-text">Register Free</span>
                        <span class="qr-btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Creating account...</span>
                    </button>
                    <p class="text-center text-muted mt-2 mb-0 qr-terms">By registering, you agree to our Terms &amp; Privacy Policy</p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script>
(function() {
    var modalEl = document.getElementById('quickRegisterModal');
    if (!modalEl) return;

    var form = document.getElementById('hero-register-form');
    var submitBtn = form.querySelector('.qr-submit-btn');
    var btnText = form.querySelector('.qr-btn-text');
    var btnLoading = form.querySelector('.qr-btn-loading');

    var modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
    modal.show();

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';

        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');

        var data = {};
        var fd = new FormData(form);
        fd.forEach(function(v, k) { data[k] = v; });

        fetch('/users/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            form.dataset.submitting = '0';
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');

            if (result.success) {
                form.reset();
                modal.hide();
                window.location.href = result.redirect || '/profile';
            } else {
                var msg = '';
                if (result.errors && Array.isArray(result.errors)) {
                    msg = result.errors.join('\n');
                } else if (result.error) {
                    msg = result.error;
                } else {
                    msg = 'Registration failed. Please try again.';
                }
                alert(msg);
            }
        })
        .catch(function() {
            form.dataset.submitting = '0';
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            alert('Network error. Please try again.');
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function() {
        form.dataset.submitting = '0';
        submitBtn.disabled = false;
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
    });
})();
</script>
<?php
$pageScripts = ob_get_clean();
endif;
?>


<!-- How It Works Section -->
<section class="how-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="how-heading" data-aos="fade-up">How Does It Work</h2>
            <p class="how-subtitle" data-aos="fade-up" data-aos-delay="100">Four simple steps to begin your matrimony journey.</p>
        </div>

        <div class="row g-4 position-relative">
            <div class="how-connector-line d-none d-lg-block"></div>
            <div class="col-lg-3 col-md-6">
                <div class="how-card" data-aos="fade-right" data-aos-delay="0">
                    <div class="how-step-num">01</div>
                    <div class="how-icon-wrap">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                    </div>
                    <h3 class="how-card-title">Create Your Profile</h3>
                    <p class="how-card-text">Register and build a detailed profile with your preferences, photos, and what you seek in a life partner.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="how-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="how-step-num">02</div>
                    <div class="how-icon-wrap">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    </div>
                    <h3 class="how-card-title">Search &amp; Browse</h3>
                    <p class="how-card-text">Use smart filters to discover compatible matches based on age, community, profession, and lifestyle.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="how-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="how-step-num">03</div>
                    <div class="how-icon-wrap">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <h3 class="how-card-title">Express Interest</h3>
                    <p class="how-card-text">Send interests or messages to profiles you like. Start meaningful conversations when the interest is mutual.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="how-card" data-aos="fade-left" data-aos-delay="300">
                    <div class="how-step-num">04</div>
                    <div class="how-icon-wrap">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </div>
                    <h3 class="how-card-title">Meet &amp; Decide</h3>
                    <p class="how-card-text">Plan a meeting with your match and take the next step toward a lifelong bond of togetherness.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recently Joined Members Section -->
<section class="members-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="members-heading" data-aos="fade-up">Recently Joined Members</h2>
            <p class="members-subtitle" data-aos="fade-up" data-aos-delay="100">Meet some of our newest members who are ready to find their perfect match.</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php
            $aosAnimations = ['fade-right', 'zoom-in', 'fade-up', 'zoom-in', 'fade-up', 'fade-left'];
            $index = 0;
            foreach ($recentMembers ?? [] as $member):
                $aos = $aosAnimations[$index % count($aosAnimations)];
                $delay = $index * 100;
                $firstName = htmlspecialchars($member['first_name'] ?? '');
                $lastInitial = strtoupper(substr((string)($member['last_name'] ?? ''), 0, 1));
                $name = $firstName . ($lastInitial !== '' ? ' ' . $lastInitial . '.' : '');
                $age = (int)($member['age'] ?? 0);
                $photo = $member['primary_photo'] ?? '';
                $initials = htmlspecialchars($member['initials'] ?? '?');
                $gender = $member['gender'] ?? '';
                $city = htmlspecialchars($member['city'] ?? '');
                $state = htmlspecialchars($member['state'] ?? '');
                $location = trim($city . ($state !== '' ? ', ' . $state : ''));
                $isVerified = !empty($member['is_verified']);
                $profileUrl = '/profile/' . (int)$member['user_id'];
            ?>
            <div class="col-lg-2 col-md-4 col-6">
                <a href="<?= $profileUrl ?>" class="text-decoration-none">
                    <article class="rjm-card">
                        <div class="rjm-photo">
                            <?php if ($photo): ?>
                                <img src="<?= htmlspecialchars($photo) ?>" alt="<?= $name ?>" loading="lazy">
                            <?php else: ?>
                                <span class="rjm-initials"><?= $initials ?></span>
                            <?php endif; ?>
                            <?php if ($isVerified): ?>
                                <span class="rjm-verified" title="Verified profile" aria-label="Verified">
                                    <i class="bi bi-patch-check-fill"></i>
                                </span>
                            <?php endif; ?>
                            <span class="rjm-gender rjm-gender-<?= htmlspecialchars($gender) ?>" aria-label="<?= htmlspecialchars($gender) ?>">
                                <i class="bi <?= $gender === 'female' ? 'bi-gender-female' : 'bi-gender-male' ?>"></i>
                            </span>
                        </div>
                        <div class="rjm-body">
                            <h3 class="rjm-name"><?= $name ?: 'Member' ?></h3>
                            <p class="rjm-meta">
                                <span class="rjm-age"><?= $age ?> yrs</span>
                                <?php if ($location !== ''): ?>
                                    <span class="rjm-dot" aria-hidden="true">•</span>
                                    <span class="rjm-location"><i class="bi bi-geo-alt-fill"></i> <?= $location ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="rjm-footer">
                            <span class="rjm-new">New</span>
                            <span class="rjm-cta">View <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </article>
                </a>
            </div>
            <?php $index++; endforeach; ?>
        </div>
    </div>
</section>

<!-- Experience Section -->
<section class="experience-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="experience-heading">Bride Groom Matrimony Experience</h2>
            <p class="experience-subtitle">Building relationships with care and commitment every day.</p>
        </div>
        <div class="accordion experience-accordion" id="experienceAccordion">
            <div class="accordion-item exp-accordion-item" data-aos="fade-up" data-aos-delay="0">
                <h2 class="accordion-header">
                    <button class="accordion-button exp-accordion-btn exp-btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#expCollapseOne" aria-expanded="true" aria-controls="expCollapseOne">
                        <span class="exp-accordion-value">20+</span>
                        <span class="exp-accordion-label">Years of Service</span>
                    </button>
                </h2>
                <div id="expCollapseOne" class="accordion-collapse collapse show" data-bs-parent="#experienceAccordion">
                    <div class="accordion-body exp-accordion-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-7">
                                <h4 class="fw-bold mb-3">Two Decades of Trusted Matchmaking</h4>
                                <p class="exp-accordion-desc">For over 20 years, we have been the bridge that connects families and individuals seeking meaningful life partnerships. Our journey began with a simple mission — to make matchmaking a dignified, respectful, and successful experience for everyone.</p>
                                <p class="exp-accordion-desc">Throughout these years, we have refined our processes, embraced technology, and built a team of dedicated relationship managers who personally guide families through every step of the alliance journey.</p>
                                <ul class="exp-accordion-list mt-3">
                                    <li>Established in 2004 with a vision to transform matchmaking</li>
                                    <li>Served over 50,000 families across India and abroad</li>
                                    <li>Dedicated support team available 7 days a week</li>
                                    <li>99.9% uptime with secure and private platform</li>
                                </ul>
                            </div>
                            <div class="col-md-5 text-center">
                                <div class="exp-stat-box">
                                    <span class="exp-stat-number">20+</span>
                                    <span class="exp-stat-label">Years of Excellence</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion-item exp-accordion-item" data-aos="fade-up" data-aos-delay="100">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed exp-accordion-btn exp-btn-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#expCollapseTwo" aria-expanded="false" aria-controls="expCollapseTwo">
                        <span class="exp-accordion-value">10K+</span>
                        <span class="exp-accordion-label">Connections Made</span>
                    </button>
                </h2>
                <div id="expCollapseTwo" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                    <div class="accordion-body exp-accordion-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-7">
                                <h4 class="fw-bold mb-3">Thousands of Happy Alliances</h4>
                                <p class="exp-accordion-desc">Behind every number is a beautiful love story. We have proudly facilitated over 10,000 successful alliances — each one a testament to our commitment to quality matchmaking. From traditional arranged marriages to modern love matches, we celebrate every union.</p>
                                <p class="exp-accordion-desc">Our success rate is driven by a deep understanding of cultural values, family expectations, and individual preferences. We don't just connect profiles — we connect hearts, values, and families.</p>
                                <ul class="exp-accordion-list mt-3">
                                    <li>Verified and screened profiles for authentic connections</li>
                                    <li>Smart compatibility matching based on 50+ dimensions</li>
                                    <li>Success stories across 15+ countries worldwide</li>
                                    <li>95% member satisfaction rate among matched couples</li>
                                </ul>
                            </div>
                            <div class="col-md-5 text-center">
                                <div class="exp-stat-box">
                                    <span class="exp-stat-number">10K+</span>
                                    <span class="exp-stat-label">Successful Alliances</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion-item exp-accordion-item" data-aos="fade-up" data-aos-delay="200">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed exp-accordion-btn exp-btn-accent" type="button" data-bs-toggle="collapse" data-bs-target="#expCollapseThree" aria-expanded="false" aria-controls="expCollapseThree">
                        <span class="exp-accordion-value">50K+</span>
                        <span class="exp-accordion-label">Active Profiles</span>
                    </button>
                </h2>
                <div id="expCollapseThree" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                    <div class="accordion-body exp-accordion-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-7">
                                <h4 class="fw-bold mb-3">A Vibrant Community of Seekers</h4>
                                <p class="exp-accordion-desc">With over 50,000 active profiles, our platform is a vibrant and diverse community of individuals and families seeking meaningful alliances. Each profile is carefully reviewed and verified to ensure authenticity and quality.</p>
                                <p class="exp-accordion-desc">Our members come from varied backgrounds — different communities, professions, and lifestyles — united by the common goal of finding a compatible life partner. Advanced filters help you find exactly what you're looking for.</p>
                                <ul class="exp-accordion-list mt-3">
                                    <li>Diverse community spanning 20+ languages and regions</li>
                                    <li>Professionals from IT, healthcare, finance, education & more</li>
                                    <li>Profiles verified with document checks for authenticity</li>
                                    <li>New profiles added daily — fresh matches every day</li>
                                </ul>
                            </div>
                            <div class="col-md-5 text-center">
                                <div class="exp-stat-box">
                                    <span class="exp-stat-number">50K+</span>
                                    <span class="exp-stat-label">Active Members</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion-item exp-accordion-item" data-aos="fade-up" data-aos-delay="300">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed exp-accordion-btn exp-btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#expCollapseFour" aria-expanded="false" aria-controls="expCollapseFour">
                        <span class="exp-accordion-value">Trusted</span>
                        <span class="exp-accordion-label">Member Contentment</span>
                    </button>
                </h2>
                <div id="expCollapseFour" class="accordion-collapse collapse" data-bs-parent="#experienceAccordion">
                    <div class="accordion-body exp-accordion-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-7">
                                <h4 class="fw-bold mb-3">Built on Trust and Transparency</h4>
                                <p class="exp-accordion-desc">Trust is the foundation of everything we do. Families continue to choose us because we prioritize privacy, security, and genuine connections above all else. Our platform is designed to create a safe and respectful environment for all members.</p>
                                <p class="exp-accordion-desc">From secure data handling to respectful communication guidelines, every aspect of our service is built with your peace of mind in mind. We are not just a platform — we are your trusted partner in this important journey.</p>
                                <ul class="exp-accordion-list mt-3">
                                    <li>Strict privacy controls — you decide what to share and with whom</li>
                                    <li>24/7 customer support for assistance whenever you need it</li>
                                    <li>Zero tolerance for fake profiles or inappropriate behavior</li>
                                    <li>Secure platform with encrypted data and verified profiles</li>
                                </ul>
                            </div>
                            <div class="col-md-5 text-center">
                                <div class="exp-stat-box">
                                    <span class="exp-stat-number">Trusted</span>
                                    <span class="exp-stat-label">By Families Worldwide</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials / Success Stories Section -->
<section class="testimonials-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="testimonials-heading" data-aos="fade-up">Success Stories</h2>
            <p class="testimonials-subtitle" data-aos="fade-up" data-aos-delay="100">Real couples who found their life partners through our platform.</p>
        </div>

        <div id="testimonialsCarousel" class="carousel slide" data-aos="fade-up" data-aos-delay="200" data-bs-ride="carousel" data-bs-interval="5000" data-bs-pause="hover">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#testimonialsCarousel" data-bs-slide-to="0" class="active" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#testimonialsCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#testimonialsCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>

            <div class="carousel-inner">
                <div class="carousel-item active">
                    <div class="testimonial-card">
                        <div class="testimonial-quote-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151C7.563 6.068 6 8.789 6 11h4v10H0z"/></svg>
                        </div>
                        <p class="testimonial-text">"We were searching for the right match for years, but this platform made everything so simple. The detailed profiles and genuine members helped us find our daughter's perfect life partner. Forever grateful!"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <span class="testimonial-name">Priya &amp; Arjun Sharma</span>
                                <span class="testimonial-location">Chennai, Tamil Nadu</span>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                    </div>
                </div>

                <div class="carousel-item">
                    <div class="testimonial-card">
                        <div class="testimonial-quote-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151C7.563 6.068 6 8.789 6 11h4v10H0z"/></svg>
                        </div>
                        <p class="testimonial-text">"I had almost given up on finding the right match until a friend recommended this site. Within a month, I connected with someone who shared my values, interests, and dreams. We are getting married next month!"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <span class="testimonial-name">Ananya &amp; Rohan Desai</span>
                                <span class="testimonial-location">Mumbai, Maharashtra</span>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                    </div>
                </div>

                <div class="carousel-item">
                    <div class="testimonial-card">
                        <div class="testimonial-quote-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151C7.563 6.068 6 8.789 6 11h4v10H0z"/></svg>
                        </div>
                        <p class="testimonial-text">"As parents, finding a trustworthy alliance platform was our biggest concern. The verified profiles, privacy controls, and responsive support team gave us complete confidence. Our son found his ideal match within weeks!"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div>
                                <span class="testimonial-name">Meena &amp; Suresh Iyer</span>
                                <span class="testimonial-location">Bangalore, Karnataka</span>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--brand-accent-dark)"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</section>


