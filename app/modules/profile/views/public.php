<section class="page-header">
    <div class="container">
        <h1 class="page-title">Profile</h1>
    </div>
</section>

<?php if (!$profile): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <h3 class="mb-3">Profile Not Found</h3>
            <p class="text-muted mb-4">The profile you're looking for doesn't exist or has been removed.</p>
            <a href="/" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</div>
<?php return; endif; ?>

<?php
$p = $profile;
$name = e($p['first_name'] ?? '') . ' ' . e($p['last_name'] ?? '');
$photo = $p['primary_photo'] ?? '';
$age = $p['age'] ?? 0;
$initials = e(strtoupper(substr($p['first_name'] ?? '?', 0, 1)));
$placeholder = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22><rect fill=%22%239565be%22 width=%22150%22 height=%22150%22/><text fill=%22white%22 font-size=%2264%22 x=%2275%22 y=%2296%22 text-anchor=%22middle%22>' . $initials . '</text></svg>';
$heightCm = (int) ($p['height_cm'] ?? 0);
$heightFt = $heightCm ? floor($heightCm / 30.48) . "'" . round(fmod($heightCm, 30.48) / 2.54) . '"' : '';
?>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Profile Card -->
            <div class="card shadow-sm mb-4 overflow-hidden">
                <div class="row g-0">
                    <div class="col-md-4 text-center p-4 bg-light">
                        <img src="<?= e($photo) ?>" alt="" class="img-fluid mb-3" style="width:150px;height:150px;object-fit:cover;" onerror="this.src='<?= $placeholder ?>'">
                        <h4 class="mb-1"><?= $name ?></h4>
                        <p class="text-muted mb-0"><?= $age ?> yrs</p>
                        <?php if (!empty($p['is_verified'])): ?>
                            <span class="badge bg-success mt-2">Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8 p-4">
                        <h5 class="mb-3">Basic Details</h5>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width:140px">Gender</td>
                                <td class="fw-medium"><?= e(ucfirst($p['gender'] ?? '')) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Religion</td>
                                <td class="fw-medium"><?= e($p['religion'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Caste</td>
                                <td class="fw-medium"><?= e($p['caste'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Mother Tongue</td>
                                <td class="fw-medium"><?= e($p['mother_tongue'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Marital Status</td>
                                <td class="fw-medium"><?= e(ucwords(str_replace('_', ' ', $p['marital_status'] ?? ''))) ?></td>
                            </tr>
                            <?php if ($heightFt): ?>
                            <tr>
                                <td class="text-muted">Height</td>
                                <td class="fw-medium"><?= $heightFt ?> (<?= $heightCm ?> cm)</td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Education & Career -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Education & Career</h5>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width:140px">Education</td>
                            <td class="fw-medium"><?= e($p['education'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Occupation</td>
                            <td class="fw-medium"><?= e($p['occupation'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Annual Income</td>
                            <td class="fw-medium"><?= e($p['annual_income'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Location -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Location</h5>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width:140px">City</td>
                            <td class="fw-medium"><?= e($p['city'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">State</td>
                            <td class="fw-medium"><?= e($p['state'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Country</td>
                            <td class="fw-medium"><?= e($p['country'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Lifestyle -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Lifestyle</h5>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width:140px">Diet</td>
                            <td class="fw-medium"><?= e($p['diet'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Body Type</td>
                            <td class="fw-medium"><?= e($p['body_type'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Complexion</td>
                            <td class="fw-medium"><?= e($p['complexion'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- About Me -->
            <?php if (!empty($p['about_me'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">About</h5>
                    <p class="mb-0"><?= e($p['about_me']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <?php if (!$isLoggedIn): ?>
            <div class="text-center mb-5">
                <p class="text-muted mb-3">Interested in this profile? Join our community to connect!</p>
                <a href="/users/register" class="btn btn-primary btn-lg">Create Free Account</a>
                <a href="/users/login" class="btn btn-outline-primary btn-lg ms-2">Login</a>
            </div>
            <?php elseif ($profile && $currentUserId !== (int)$profile['user_id']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <?php if ($existingStatus === 'interested' || $existingStatus === 'mutual'): ?>
                            <button class="btn btn-success" disabled>Interest Sent</button>
                        <?php elseif ($existingStatus === 'shortlisted'): ?>
                            <button class="btn btn-primary" disabled>Already Shortlisted</button>
                        <?php elseif ($existingStatus === 'declined' || $existingStatus === 'blocked'): ?>
                            <button class="btn btn-secondary" disabled>Action Taken</button>
                        <?php else: ?>
                            <button class="btn btn-primary" id="sendInterestBtn" data-id="<?= (int)$profile['user_id'] ?>" data-csrf="<?= e($csrfToken) ?>">Send Interest</button>
                            <button class="btn btn-outline-primary" id="shortlistBtn" data-id="<?= (int)$profile['user_id'] ?>" data-csrf="<?= e($csrfToken) ?>">Shortlist</button>
                            <button class="btn btn-outline-danger" id="declineBtn" data-id="<?= (int)$profile['user_id'] ?>" data-csrf="<?= e($csrfToken) ?>">Decline</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function sendAction(targetId, status, csrf, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';
        
        fetch('/api/matches/action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ 
                target_id: targetId, 
                status: status, 
                csrf: csrf 
            })
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(result) {
            if (result.success) {
                if (result.mutual) {
                    alert('It\'s a match! Mutual interest confirmed!');
                } else if (status === 'interested') {
                    alert('Interest sent successfully!');
                } else if (status === 'shortlisted') {
                    alert('Profile shortlisted!');
                } else if (status === 'declined') {
                    alert('Profile declined!');
                }
                location.reload();
            } else {
                alert(result.error || 'Action failed');
                btn.disabled = false;
                if (status === 'interested') {
                    btn.innerHTML = 'Send Interest';
                } else if (status === 'shortlisted') {
                    btn.innerHTML = 'Shortlist';
                } else {
                    btn.innerHTML = 'Decline';
                }
            }
        })
        .catch(function() {
            alert('Network error');
            btn.disabled = false;
            if (status === 'interested') {
                btn.innerHTML = 'Send Interest';
            } else if (status === 'shortlisted') {
                btn.innerHTML = 'Shortlist';
            } else {
                btn.innerHTML = 'Decline';
            }
        });
    }
    
    const sendBtn = document.getElementById('sendInterestBtn');
    const shortlistBtn = document.getElementById('shortlistBtn');
    const declineBtn = document.getElementById('declineBtn');
    
    if (sendBtn) {
        sendBtn.addEventListener('click', function() {
            sendAction(
                parseInt(this.getAttribute('data-id')), 
                'interested', 
                this.getAttribute('data-csrf'),
                this
            );
        });
    }
    
    if (shortlistBtn) {
        shortlistBtn.addEventListener('click', function() {
            sendAction(
                parseInt(this.getAttribute('data-id')), 
                'shortlisted', 
                this.getAttribute('data-csrf'),
                this
            );
        });
    }
    
    if (declineBtn) {
        declineBtn.addEventListener('click', function() {
            sendAction(
                parseInt(this.getAttribute('data-id')), 
                'declined', 
                this.getAttribute('data-csrf'),
                this
            );
        });
    }
});
</script>
