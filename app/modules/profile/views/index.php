<section class="page-header">
    <div class="container">
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your matrimony profile, photos, preferences, and privacy settings</p>
    </div>
</section>

<?php if (!$isLoggedIn): ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <h3 class="mb-3">Please Log In</h3>
            <p class="text-muted mb-4">Log in to view and edit your profile.</p>
            <a href="/users/login" class="btn btn-primary btn-lg">Login</a>
            <a href="/users/register" class="btn btn-outline-primary btn-lg ms-2">Register</a>
        </div>
    </div>
</div>
<?php return; endif; ?>

<?php if (!$profile): ?>
<div class="container py-5">
    <div class="alert alert-danger">
        <h5>Unable to load profile.</h5>
        <?php if (!empty($errorMsg)): ?>
            <p class="mb-1"><strong>Error:</strong> <?= e($errorMsg) ?></p>
            <?php if (strpos($errorMsg, 'Unknown column') !== false): ?>
                <p class="mb-0 small">Database migration needed. Run <code>database/migrations/003_profile_tables.sql</code> against your database.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="mb-0">Please ensure you have a profile record. Try re-registering or contact support.</p>
        <?php endif; ?>
    </div>
</div>
<?php return; endif; ?>

<?php
$p = $profile;
$name = e($p['first_name'] ?? '') . ' ' . e($p['last_name'] ?? '');
$photo = $p['primary_photo'] ?? '';
$age = $p['age'] ?? 0;
$completion = $p['completion_percentage'] ?? 0;
$profileId = $p['profile_id'] ?? '';
$prefs = $p['preferences'] ?? [];
$privacy = $p['privacy'] ?? [];
$stats = $p['stats'] ?? [];
$photos = $p['photos'] ?? [];
$membership = $p['membership'] ?? null;
$verifications = $p['verifications'] ?? [];
$isPremium = $membership && $membership['plan_code'] !== 'FREE';
$isVerified = !empty($p['is_verified']);
$gender = $p['gender'] ?? '';
?>

<div class="profile-page pb-5" data-csrf="<?= e($csrfToken) ?>" data-user-id="<?= (int) ($p['user_id'] ?? 0) ?>">
    <div class="profile-layout">

    <!-- ============================================================ -->
    <!-- SIDEBAR: Views / Interests / Matches / Shortlists             -->
    <!-- ============================================================ -->
    <aside class="profile-sidebar" data-testid="sidebar">
        <div class="sidebar-card card shadow-sm">

            <!-- Views -->
            <div>
                <div class="sidebar-header" data-bs-toggle="collapse" data-bs-target="#sideViews">
                    <div class="d-flex align-items-center">
                        <div class="side-icon side-icon-views">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </div>
                        <span class="fw-semibold">Profile Views</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="side-count"><?= count($sidebarData['views']) ?></span>
                        <svg class="side-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="collapse show" id="sideViews">
                    <div class="side-body">
                        <?php if (empty($sidebarData['views'])): ?>
                            <p class="side-empty">No views yet</p>
                        <?php else: ?>
                            <?php foreach (array_slice($sidebarData['views'], 0, 5) as $v): ?>
                             <div class="side-person">
                                 <div class="side-avatar"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                                 <div class="side-info">
                                     <div class="side-name"><?= e($v['first_name'] ?? '') ?></div>
                                     <div class="side-meta"><?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?></div>
                                 </div>
                                 <a href="/profile/<?= (int) $v['viewer_id'] ?>" class="side-action">View</a>
                             </div>
                             <?php endforeach; ?>
                             <?php if (count($sidebarData['views']) > 5): ?><a href="/matches/who-viewed-me" class="side-view-all">View all →</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Interests -->
            <div>
                <div class="sidebar-header collapsed" data-bs-toggle="collapse" data-bs-target="#sideInterests">
                    <div class="d-flex align-items-center">
                        <div class="side-icon side-icon-interests">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </div>
                        <span class="fw-semibold">Interests Received</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="side-count"><?= count($sidebarData['interests']) ?></span>
                        <svg class="side-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="collapse" id="sideInterests">
                    <div class="side-body">
                        <?php if (empty($sidebarData['interests'])): ?>
                            <p class="side-empty">No interests yet</p>
                        <?php else: ?>
                            <?php foreach (array_slice($sidebarData['interests'], 0, 5) as $v): ?>
                             <div class="side-person">
                                 <div class="side-avatar"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                                 <div class="side-info">
                                     <div class="side-name"><?= e($v['first_name'] ?? '') ?></div>
                                     <div class="side-meta"><?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?></div>
                                 </div>
                                 <a href="/profile/<?= (int) $v['user_id'] ?>" class="side-action">View</a>
                             </div>
                             <?php endforeach; ?>
                             <?php if (count($sidebarData['interests']) > 5): ?><a href="/matches/interests" class="side-view-all">View all interests →</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Matches -->
            <div>
                <div class="sidebar-header collapsed" data-bs-toggle="collapse" data-bs-target="#sideMatches">
                    <div class="d-flex align-items-center">
                        <div class="side-icon side-icon-matches">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <span class="fw-semibold">Mutual Matches</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="side-count"><?= count($sidebarData['matches']) ?></span>
                        <svg class="side-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="collapse" id="sideMatches">
                    <div class="side-body">
                        <?php if (empty($sidebarData['matches'])): ?>
                            <p class="side-empty">No matches yet</p>
                        <?php else: ?>
                            <?php foreach (array_slice($sidebarData['matches'], 0, 5) as $v): ?>
                             <div class="side-person">
                                 <div class="side-avatar"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                                 <div class="side-info">
                                     <div class="side-name"><?= e($v['first_name'] ?? '') ?></div>
                                     <div class="side-meta"><?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?></div>
                                 </div>
                                 <a href="/profile/<?= (int) $v['match_id'] ?>" class="side-action">View</a>
                             </div>
                             <?php endforeach; ?>
                             <?php if (count($sidebarData['matches']) > 5): ?><a href="/matches/mutual" class="side-view-all">View all →</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Shortlist -->
            <div>
                <div class="sidebar-header collapsed" data-bs-toggle="collapse" data-bs-target="#sideShortlist">
                    <div class="d-flex align-items-center">
                        <div class="side-icon side-icon-shortlist">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <span class="fw-semibold">My Shortlist</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="side-count"><?= count($sidebarData['shortlists']) ?></span>
                        <svg class="side-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
                <div class="collapse" id="sideShortlist">
                    <div class="side-body">
                        <?php if (empty($sidebarData['shortlists'])): ?>
                            <p class="side-empty">No shortlisted profiles</p>
                        <?php else: ?>
                            <?php foreach (array_slice($sidebarData['shortlists'], 0, 5) as $v): ?>
                             <div class="side-person">
                                 <div class="side-avatar"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                                 <div class="side-info">
                                     <div class="side-name"><?= e($v['first_name'] ?? '') ?></div>
                                     <div class="side-meta"><?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?></div>
                                 </div>
                                 <a href="/profile/<?= (int) $v['target_id'] ?>" class="side-action">View</a>
                             </div>
                             <?php endforeach; ?>
                             <?php if (count($sidebarData['shortlists']) > 5): ?><a href="/matches/shortlists" class="side-view-all">View all →</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </aside>

    <!-- ============================================================ -->
    <!-- MAIN CONTENT                                                 -->
    <!-- ============================================================ -->
    <main class="profile-main">
        <div class="container">

    <!-- ============================================================ -->
    <!-- SECTION 1: PROFILE HEADER                                     -->
    <!-- ============================================================ -->
    <div class="profile-header card shadow-sm mb-4" data-testid="profile-header">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-auto text-center mb-3 mb-md-0">
                    <div class="profile-avatar-wrap position-relative d-inline-block">
                        <?php if ($photo): ?>
                            <img src="<?= e($photo) ?>" alt="Profile photo of <?= e($p['first_name'] ?? '') ?>" class="profile-avatar" id="profile-avatar-img" data-testid="profile-photo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22><rect fill=%22%239565be%22 width=%22120%22 height=%22120%22/><text fill=%22white%22 font-size=%2248%22 x=%2260%22 y=%2276%22 text-anchor=%22middle%22><?= e(strtoupper(substr($p['first_name']??'?',0,1))) ?></text></svg>'">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder" data-testid="profile-photo"><?= e(strtoupper(substr($p['first_name']??'?',0,1))) ?></div>
                        <?php endif; ?>
                        <label class="profile-photo-upload" title="Change Photo">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            <input type="file" accept="image/*" class="d-none" id="photo-upload-input">
                        </label>
                    </div>
                </div>
                <div class="col-md">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h2 class="mb-0" data-testid="profile-name"><?= $name ?></h2>
                        <?php if ($isVerified): ?><span class="badge bg-success" data-testid="badge" aria-label="Verified profile"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Verified</span><?php endif; ?>
                        <?php if ($isPremium): ?><span class="badge bg-warning text-dark" data-testid="badge" aria-label="Premium member">Premium</span><?php endif; ?>
                        <?php if (!empty($p['is_active'])): ?><span class="badge bg-success" data-testid="badge" aria-label="Active profile">Active</span><?php else: ?><span class="badge bg-secondary" data-testid="badge" aria-label="Deactivated profile">Deactivated</span><?php endif; ?>
                    </div>
                    <p class="text-muted mb-2">Profile ID: <?= e($profileId) ?> &middot; <span data-testid="profile-age"><?= $age ?> yrs</span> &middot; <?= e($p['gender'] ?? '') ?> &middot; <?= e($p['religion'] ?? '') ?> &middot; <span data-testid="profile-city"><?= e($p['city'] ?? '') ?></span></p>

                    <!-- Completion bar -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <div class="progress" style="height:8px;" role="progressbar" aria-valuenow="<?= $completion ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width:<?= $completion ?>%"></div>
                            </div>
                        </div>
                        <small class="text-muted fw-medium"><?= $completion ?>% Complete</small>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary btn-sm" onclick="document.querySelector('[data-section=personal]').click()"><?= $completion < 100 ? 'Complete Profile' : 'Edit Profile' ?></button>
                        <button class="btn btn-outline-primary btn-sm" onclick="viewAsPublic()">View Public Profile</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="shareProfile()">Share</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- PROFILE TABS                                                  -->
    <!-- ============================================================ -->
    <ul class="nav nav-tabs profile-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-personal" data-section="personal" type="button">Personal</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-family" data-section="family" type="button">Family</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lifestyle" data-section="lifestyle" type="button">Lifestyle & Interests</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-preferences" data-section="preferences" type="button">Partner Preferences</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-photos" data-section="photos" type="button">Photos</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-privacy" data-section="privacy" type="button">Privacy & Settings</button></li>
    </ul>

    <div class="tab-content">

        <!-- ============================================================ -->
        <!-- TAB: PERSONAL DETAILS                                         -->
        <!-- ============================================================ -->
        <div class="tab-pane fade show active" id="tab-personal">
            <form class="profile-section-form" data-section="personal">
                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Basic Information</h5>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3" data-testid="info-grid">
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?= e($p['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?= e($p['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?= e($p['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="male" <?= ($p['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($p['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($p['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Marital Status</label>
                                <select class="form-select" name="marital_status">
                                    <option value="never_married" <?= ($p['marital_status'] ?? '') === 'never_married' ? 'selected' : '' ?>>Never Married</option>
                                    <option value="divorced" <?= ($p['marital_status'] ?? '') === 'divorced' ? 'selected' : '' ?>>Divorced</option>
                                    <option value="widowed" <?= ($p['marital_status'] ?? '') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
                                    <option value="awaiting_divorce" <?= ($p['marital_status'] ?? '') === 'awaiting_divorce' ? 'selected' : '' ?>>Awaiting Divorce</option>
                                    </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Height</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="height_cm" value="<?= e($p['height_cm'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                                    <span class="input-group-text">cm</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Weight</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="weight_kg" value="<?= e($p['weight_kg'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                                    <span class="input-group-text">kg</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Body Type</label>
                                <select class="form-select" name="body_type">
                                    <option value="">Select</option>
                                    <option value="Slim" <?= ($p['body_type'] ?? '') === 'Slim' ? 'selected' : '' ?>>Slim</option>
                                    <option value="Average" <?= ($p['body_type'] ?? '') === 'Average' ? 'selected' : '' ?>>Average</option>
                                    <option value="Athletic" <?= ($p['body_type'] ?? '') === 'Athletic' ? 'selected' : '' ?>>Athletic</option>
                                    <option value="Heavy" <?= ($p['body_type'] ?? '') === 'Heavy' ? 'selected' : '' ?>>Heavy</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Complexion</label>
                                <select class="form-select" name="complexion">
                                    <option value="">Select</option>
                                    <option value="Fair" <?= ($p['complexion'] ?? '') === 'Fair' ? 'selected' : '' ?>>Fair</option>
                                    <option value="Wheatish" <?= ($p['complexion'] ?? '') === 'Wheatish' ? 'selected' : '' ?>>Wheatish</option>
                                    <option value="Dark" <?= ($p['complexion'] ?? '') === 'Dark' ? 'selected' : '' ?>>Dark</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="has-children-field">
                                <label class="form-label">Has Children</label>
                                <select class="form-select" name="has_children">
                                    <option value="">Select</option>
                                    <option value="yes" <?= ($p['has_children'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= ($p['has_children'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Religion & Culture</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Religion</label>
                                <select class="form-select" name="religion">
                                    <option value="">Select</option>
                                    <option value="Hindu" <?= ($p['religion'] ?? '') === 'Hindu' ? 'selected' : '' ?>>Hindu</option>
                                    <option value="Muslim" <?= ($p['religion'] ?? '') === 'Muslim' ? 'selected' : '' ?>>Muslim</option>
                                    <option value="Christian" <?= ($p['religion'] ?? '') === 'Christian' ? 'selected' : '' ?>>Christian</option>
                                    <option value="Sikh" <?= ($p['religion'] ?? '') === 'Sikh' ? 'selected' : '' ?>>Sikh</option>
                                    <option value="Jain" <?= ($p['religion'] ?? '') === 'Jain' ? 'selected' : '' ?>>Jain</option>
                                    <option value="Buddhist" <?= ($p['religion'] ?? '') === 'Buddhist' ? 'selected' : '' ?>>Buddhist</option>
                                    <option value="Other" <?= ($p['religion'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Caste</label>
                                <input type="text" class="form-control" name="caste" value="<?= e($p['caste'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sub Caste</label>
                                <input type="text" class="form-control" name="sub_caste" value="<?= e($p['sub_caste'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother Tongue</label>
                                <select class="form-select" name="mother_tongue">
                                    <option value="">Select</option>
                                    <option value="Tamil" <?= ($p['mother_tongue'] ?? '') === 'Tamil' ? 'selected' : '' ?>>Tamil</option>
                                    <option value="Telugu" <?= ($p['mother_tongue'] ?? '') === 'Telugu' ? 'selected' : '' ?>>Telugu</option>
                                    <option value="Kannada" <?= ($p['mother_tongue'] ?? '') === 'Kannada' ? 'selected' : '' ?>>Kannada</option>
                                    <option value="Malayalam" <?= ($p['mother_tongue'] ?? '') === 'Malayalam' ? 'selected' : '' ?>>Malayalam</option>
                                    <option value="Hindi" <?= ($p['mother_tongue'] ?? '') === 'Hindi' ? 'selected' : '' ?>>Hindi</option>
                                    <option value="Urdu" <?= ($p['mother_tongue'] ?? '') === 'Urdu' ? 'selected' : '' ?>>Urdu</option>
                                    <option value="Marathi" <?= ($p['mother_tongue'] ?? '') === 'Marathi' ? 'selected' : '' ?>>Marathi</option>
                                    <option value="Gujarati" <?= ($p['mother_tongue'] ?? '') === 'Gujarati' ? 'selected' : '' ?>>Gujarati</option>
                                    <option value="Bengali" <?= ($p['mother_tongue'] ?? '') === 'Bengali' ? 'selected' : '' ?>>Bengali</option>
                                    <option value="Punjabi" <?= ($p['mother_tongue'] ?? '') === 'Punjabi' ? 'selected' : '' ?>>Punjabi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Profile Created By</label>
                                <select class="form-select" name="created_by">
                                    <option value="self" <?= ($p['created_by'] ?? '') === 'self' ? 'selected' : '' ?>>Self</option>
                                    <option value="parent" <?= ($p['created_by'] ?? '') === 'parent' ? 'selected' : '' ?>>Parent</option>
                                    <option value="guardian" <?= ($p['created_by'] ?? '') === 'guardian' ? 'selected' : '' ?>>Guardian</option>
                                    <option value="sibling" <?= ($p['created_by'] ?? '') === 'sibling' ? 'selected' : '' ?>>Sibling</option>
                                    <option value="friend" <?= ($p['created_by'] ?? '') === 'friend' ? 'selected' : '' ?>>Friend</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Education & Career</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Education</label>
                                <input type="text" class="form-control" name="education" value="<?= e($p['education'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Institution</label>
                                <input type="text" class="form-control" name="institution" value="<?= e($p['institution'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Occupation</label>
                                <input type="text" class="form-control" name="occupation" value="<?= e($p['occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" name="company" value="<?= e($p['company'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Annual Income</label>
                                <select class="form-select" name="annual_income">
                                    <option value="">Select</option>
                                    <option value="Below 1 Lakh" <?= ($p['annual_income'] ?? '') === 'Below 1 Lakh' ? 'selected' : '' ?>>Below 1 Lakh</option>
                                    <option value="1-3 Lakhs" <?= ($p['annual_income'] ?? '') === '1-3 Lakhs' ? 'selected' : '' ?>>1-3 Lakhs</option>
                                    <option value="3-5 Lakhs" <?= ($p['annual_income'] ?? '') === '3-5 Lakhs' ? 'selected' : '' ?>>3-5 Lakhs</option>
                                    <option value="5-10 Lakhs" <?= ($p['annual_income'] ?? '') === '5-10 Lakhs' ? 'selected' : '' ?>>5-10 Lakhs</option>
                                    <option value="10-20 Lakhs" <?= ($p['annual_income'] ?? '') === '10-20 Lakhs' ? 'selected' : '' ?>>10-20 Lakhs</option>
                                    <option value="20-30 Lakhs" <?= ($p['annual_income'] ?? '') === '20-30 Lakhs' ? 'selected' : '' ?>>20-30 Lakhs</option>
                                    <option value="30-50 Lakhs" <?= ($p['annual_income'] ?? '') === '30-50 Lakhs' ? 'selected' : '' ?>>30-50 Lakhs</option>
                                    <option value="50 Lakhs+" <?= ($p['annual_income'] ?? '') === '50 Lakhs+' ? 'selected' : '' ?>>50 Lakhs+</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Work Location</label>
                                <input type="text" class="form-control" name="work_location" value="<?= e($p['work_location'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contact</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= e($p['email'] ?? '') ?>" disabled>
                                <small class="text-muted">Email cannot be changed here</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?= e($p['phone'] ?? '') ?>" placeholder="+91 98765 43210" maxlength="20">
                                <small class="text-muted">Visible to matches only</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" value="<?= e($p['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <select class="form-select" name="state">
                                    <option value="">Select</option>
                                    <option value="Tamil Nadu" <?= ($p['state'] ?? '') === 'Tamil Nadu' ? 'selected' : '' ?>>Tamil Nadu</option>
                                    <option value="Karnataka" <?= ($p['state'] ?? '') === 'Karnataka' ? 'selected' : '' ?>>Karnataka</option>
                                    <option value="Kerala" <?= ($p['state'] ?? '') === 'Kerala' ? 'selected' : '' ?>>Kerala</option>
                                    <option value="Andhra Pradesh" <?= ($p['state'] ?? '') === 'Andhra Pradesh' ? 'selected' : '' ?>>Andhra Pradesh</option>
                                    <option value="Telangana" <?= ($p['state'] ?? '') === 'Telangana' ? 'selected' : '' ?>>Telangana</option>
                                    <option value="Maharashtra" <?= ($p['state'] ?? '') === 'Maharashtra' ? 'selected' : '' ?>>Maharashtra</option>
                                    <option value="Delhi" <?= ($p['state'] ?? '') === 'Delhi' ? 'selected' : '' ?>>Delhi</option>
                                    <option value="Other" <?= ($p['state'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="<?= e($p['country'] ?? 'India') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Willing to Relocate</label>
                                <select class="form-select" name="willing_to_relocate">
                                    <option value="0" <?= empty($p['willing_to_relocate']) ? 'selected' : '' ?>>No</option>
                                    <option value="1" <?= !empty($p['willing_to_relocate']) ? 'selected' : '' ?>>Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">About Me</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" name="about_me" rows="4" maxlength="2000"><?= e($p['about_me'] ?? '') ?></textarea>
                        <small class="text-muted">Tell us about yourself, your values, and what you are looking for.</small>
                    </div>
                </div>

                <div class="text-end mb-4">
                    <button type="submit" class="btn btn-primary">Save All Personal Details</button>
                </div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: FAMILY DETAILS                                           -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-family">
            <form class="profile-section-form" data-section="family">
                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Family Background</h5>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" value="<?= e($p['father_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Father's Occupation</label>
                                <input type="text" class="form-control" name="father_occupation" value="<?= e($p['father_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" class="form-control" name="mother_name" value="<?= e($p['mother_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother's Occupation</label>
                                <input type="text" class="form-control" name="mother_occupation" value="<?= e($p['mother_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Brothers</label>
                                <input type="text" class="form-control" name="brothers_count" value="<?= e($p['brothers_count'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sisters</label>
                                <input type="text" class="form-control" name="sisters_count" value="<?= e($p['sisters_count'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Type</label>
                                <select class="form-select" name="family_type">
                                    <option value="">Select</option>
                                    <option value="nuclear" <?= ($p['family_type'] ?? '') === 'nuclear' ? 'selected' : '' ?>>Nuclear</option>
                                    <option value="joint" <?= ($p['family_type'] ?? '') === 'joint' ? 'selected' : '' ?>>Joint</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Values</label>
                                <select class="form-select" name="family_values">
                                    <option value="">Select</option>
                                    <option value="traditional" <?= ($p['family_values'] ?? '') === 'traditional' ? 'selected' : '' ?>>Traditional</option>
                                    <option value="moderate" <?= ($p['family_values'] ?? '') === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                                    <option value="liberal" <?= ($p['family_values'] ?? '') === 'liberal' ? 'selected' : '' ?>>Liberal</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Origin</label>
                                <input type="text" class="form-control" name="family_origin" value="<?= e($p['family_origin'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Income</label>
                                <input type="text" class="form-control" name="family_income" value="<?= e($p['family_income'] ?? '') ?>" placeholder="e.g. 10 Lakhs">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About My Family</label>
                                <textarea class="form-control" name="about_family" rows="3" maxlength="1000"><?= e($p['about_family'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mb-4"><button type="submit" class="btn btn-primary">Save Family Details</button></div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: LIFESTYLE & INTERESTS                                    -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-lifestyle">
            <form class="profile-section-form" data-section="lifestyle">
                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Lifestyle</h5>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Diet</label>
                                <select class="form-select" name="diet">
                                    <option value="">Select</option>
                                    <option value="Vegetarian" <?= ($p['diet'] ?? '') === 'Vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                                    <option value="Non-veg" <?= ($p['diet'] ?? '') === 'Non-veg' ? 'selected' : '' ?>>Non-veg</option>
                                    <option value="Vegan" <?= ($p['diet'] ?? '') === 'Vegan' ? 'selected' : '' ?>>Vegan</option>
                                    <option value="Jain" <?= ($p['diet'] ?? '') === 'Jain' ? 'selected' : '' ?>>Jain</option>
                                    <option value="Halal" <?= ($p['diet'] ?? '') === 'Halal' ? 'selected' : '' ?>>Halal</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Smoking</label>
                                <select class="form-select" name="smoke">
                                    <option value="">Select</option>
                                    <option value="No" <?= ($p['smoke'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($p['smoke'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="Occasionally" <?= ($p['smoke'] ?? '') === 'Occasionally' ? 'selected' : '' ?>>Occasionally</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Drinking</label>
                                <select class="form-select" name="drink">
                                    <option value="">Select</option>
                                    <option value="No" <?= ($p['drink'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($p['drink'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="Socially" <?= ($p['drink'] ?? '') === 'Socially' ? 'selected' : '' ?>>Socially</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Languages Known</label>
                                <input type="text" class="form-control" name="languages_known" value="<?= e($p['languages_known'] ?? '') ?>" placeholder="e.g. Tamil, English, Hindi">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Hobbies & Interests</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Hobbies</label>
                                <input type="text" class="form-control" name="hobbies" value="<?= e($p['hobbies'] ?? '') ?>" placeholder="e.g. Reading, Travel, Music">
                                <small class="text-muted">Separate with commas</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Interests</label>
                                <input type="text" class="form-control" name="interests" value="<?= e($p['interests'] ?? '') ?>" placeholder="e.g. Sports, Cooking, Yoga">
                                <small class="text-muted">Separate with commas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Horoscope Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Rashi (Moonsign)</label>
                                <select class="form-select" name="rashi">
                                    <option value="">Select</option>
                                    <option value="Mesham" <?= ($p['rashi'] ?? '') === 'Mesham' ? 'selected' : '' ?>>Mesham</option>
                                    <option value="Rishabam" <?= ($p['rashi'] ?? '') === 'Rishabam' ? 'selected' : '' ?>>Rishabam</option>
                                    <option value="Mithunam" <?= ($p['rashi'] ?? '') === 'Mithunam' ? 'selected' : '' ?>>Mithunam</option>
                                    <option value="Katagam" <?= ($p['rashi'] ?? '') === 'Katagam' ? 'selected' : '' ?>>Katagam</option>
                                    <option value="Simmam" <?= ($p['rashi'] ?? '') === 'Simmam' ? 'selected' : '' ?>>Simmam</option>
                                    <option value="Kanni" <?= ($p['rashi'] ?? '') === 'Kanni' ? 'selected' : '' ?>>Kanni</option>
                                    <option value="Thulam" <?= ($p['rashi'] ?? '') === 'Thulam' ? 'selected' : '' ?>>Thulam</option>
                                    <option value="Viruchigam" <?= ($p['rashi'] ?? '') === 'Viruchigam' ? 'selected' : '' ?>>Viruchigam</option>
                                    <option value="Dhanushu" <?= ($p['rashi'] ?? '') === 'Dhanushu' ? 'selected' : '' ?>>Dhanushu</option>
                                    <option value="Maharam" <?= ($p['rashi'] ?? '') === 'Maharam' ? 'selected' : '' ?>>Maharam</option>
                                    <option value="Kumbam" <?= ($p['rashi'] ?? '') === 'Kumbam' ? 'selected' : '' ?>>Kumbam</option>
                                    <option value="Meenam" <?= ($p['rashi'] ?? '') === 'Meenam' ? 'selected' : '' ?>>Meenam</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nakshatra (Star)</label>
                                <select class="form-select" name="nakshatra">
                                    <option value="">Select</option>
                                    <option value="Aswini" <?= ($p['nakshatra'] ?? '') === 'Aswini' ? 'selected' : '' ?>>Aswini</option>
                                    <option value="Bharani" <?= ($p['nakshatra'] ?? '') === 'Bharani' ? 'selected' : '' ?>>Bharani</option>
                                    <option value="Krithigai" <?= ($p['nakshatra'] ?? '') === 'Krithigai' ? 'selected' : '' ?>>Krithigai</option>
                                    <option value="Rohini" <?= ($p['nakshatra'] ?? '') === 'Rohini' ? 'selected' : '' ?>>Rohini</option>
                                    <option value="Mrigashirisham" <?= ($p['nakshatra'] ?? '') === 'Mrigashirisham' ? 'selected' : '' ?>>Mrigashirisham</option>
                                    <option value="Thiruvathirai" <?= ($p['nakshatra'] ?? '') === 'Thiruvathirai' ? 'selected' : '' ?>>Thiruvathirai</option>
                                    <option value="Punarpoosam" <?= ($p['nakshatra'] ?? '') === 'Punarpoosam' ? 'selected' : '' ?>>Punarpoosam</option>
                                    <option value="Poosam" <?= ($p['nakshatra'] ?? '') === 'Poosam' ? 'selected' : '' ?>>Poosam</option>
                                    <option value="Ayilyam" <?= ($p['nakshatra'] ?? '') === 'Ayilyam' ? 'selected' : '' ?>>Ayilyam</option>
                                    <option value="Maham" <?= ($p['nakshatra'] ?? '') === 'Maham' ? 'selected' : '' ?>>Maham</option>
                                    <option value="Puram" <?= ($p['nakshatra'] ?? '') === 'Puram' ? 'selected' : '' ?>>Puram</option>
                                    <option value="Uthram" <?= ($p['nakshatra'] ?? '') === 'Uthram' ? 'selected' : '' ?>>Uthram</option>
                                    <option value="Hastham" <?= ($p['nakshatra'] ?? '') === 'Hastham' ? 'selected' : '' ?>>Hastham</option>
                                    <option value="Chitirai" <?= ($p['nakshatra'] ?? '') === 'Chitirai' ? 'selected' : '' ?>>Chitirai</option>
                                    <option value="Swathi" <?= ($p['nakshatra'] ?? '') === 'Swathi' ? 'selected' : '' ?>>Swathi</option>
                                    <option value="Visakam" <?= ($p['nakshatra'] ?? '') === 'Visakam' ? 'selected' : '' ?>>Visakam</option>
                                    <option value="Anusham" <?= ($p['nakshatra'] ?? '') === 'Anusham' ? 'selected' : '' ?>>Anusham</option>
                                    <option value="Kettai" <?= ($p['nakshatra'] ?? '') === 'Kettai' ? 'selected' : '' ?>>Kettai</option>
                                    <option value="Moolam" <?= ($p['nakshatra'] ?? '') === 'Moolam' ? 'selected' : '' ?>>Moolam</option>
                                    <option value="Puradam" <?= ($p['nakshatra'] ?? '') === 'Puradam' ? 'selected' : '' ?>>Puradam</option>
                                    <option value="Uthradam" <?= ($p['nakshatra'] ?? '') === 'Uthradam' ? 'selected' : '' ?>>Uthradam</option>
                                    <option value="Thiruvonam" <?= ($p['nakshatra'] ?? '') === 'Thiruvonam' ? 'selected' : '' ?>>Thiruvonam</option>
                                    <option value="Avittam" <?= ($p['nakshatra'] ?? '') === 'Avittam' ? 'selected' : '' ?>>Avittam</option>
                                    <option value="Sathayam" <?= ($p['nakshatra'] ?? '') === 'Sathayam' ? 'selected' : '' ?>>Sathayam</option>
                                    <option value="Puratathi" <?= ($p['nakshatra'] ?? '') === 'Puratathi' ? 'selected' : '' ?>>Puratathi</option>
                                    <option value="Uthratathi" <?= ($p['nakshatra'] ?? '') === 'Uthratathi' ? 'selected' : '' ?>>Uthratathi</option>
                                    <option value="Revathi" <?= ($p['nakshatra'] ?? '') === 'Revathi' ? 'selected' : '' ?>>Revathi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time of Birth</label>
                                <input type="time" class="form-control" name="time_of_birth" value="<?= e($p['time_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" class="form-control" name="place_of_birth" value="<?= e($p['place_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Kattam</label>
                                <input type="file" class="form-control" name="kattam" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mb-4"><button type="submit" class="btn btn-primary">Save Lifestyle & Horoscope</button></div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: PARTNER PREFERENCES                                      -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-preferences">
            <form class="profile-section-form" data-section="preferences">
                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Basic Preferences</h5>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Min Age</label>
                                <input type="text" class="form-control" name="min_age" value="<?= e($prefs['min_age'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max Age</label>
                                <input type="text" class="form-control" name="max_age" value="<?= e($prefs['max_age'] ?? '') ?>" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preferred Religion</label>
                                <input type="text" class="form-control" name="pref_religion" value="<?= e(is_string($prefs['pref_religion'] ?? '') ? ($prefs['pref_religion'] ?? '') : '') ?>" placeholder="e.g. Hindu, Muslim">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preferred Caste</label>
                                <input type="text" class="form-control" name="pref_caste" value="<?= e(is_string($prefs['pref_caste'] ?? '') ? ($prefs['pref_caste'] ?? '') : '') ?>" placeholder="e.g. Nadar, Mudaliar">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Education & Career Preferences</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Min Education</label>
                                <input type="text" class="form-control" name="pref_education" value="<?= e(is_string($prefs['pref_education'] ?? '') ? ($prefs['pref_education'] ?? '') : '') ?>" placeholder="e.g. B.E, B.Tech">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preferred Occupations</label>
                                <input type="text" class="form-control" name="pref_occupation" value="<?= e(is_string($prefs['pref_occupation'] ?? '') ? ($prefs['pref_occupation'] ?? '') : '') ?>" placeholder="e.g. Doctor, Engineer">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Min Annual Income</label>
                                <select class="form-select" name="pref_income_min">
                                    <option value="">No preference</option>
                                    <option value="500000" <?= ($prefs['pref_income_min'] ?? '') === '500000' ? 'selected' : '' ?>>5 Lakh+</option>
                                    <option value="1000000" <?= ($prefs['pref_income_min'] ?? '') === '1000000' ? 'selected' : '' ?>>10 Lakh+</option>
                                    <option value="2000000" <?= ($prefs['pref_income_min'] ?? '') === '2000000' ? 'selected' : '' ?>>20 Lakh+</option>
                                    <option value="5000000" <?= ($prefs['pref_income_min'] ?? '') === '5000000' ? 'selected' : '' ?>>50 Lakh+</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" data-testid="section-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Location & Lifestyle Preferences</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Preferred Locations</label>
                                <input type="text" class="form-control" name="pref_location" value="<?= e(is_string($prefs['pref_location'] ?? '') ? ($prefs['pref_location'] ?? '') : '') ?>" placeholder="e.g. Chennai, Bangalore">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preferred Diet</label>
                                <input type="text" class="form-control" name="pref_diet" value="<?= e(is_string($prefs['pref_diet'] ?? '') ? ($prefs['pref_diet'] ?? '') : '') ?>" placeholder="e.g. Vegetarian">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marital Status Preference</label>
                                <input type="text" class="form-control" name="pref_marital_status" value="<?= e(is_string($prefs['pref_marital_status'] ?? '') ? ($prefs['pref_marital_status'] ?? '') : '') ?>" placeholder="e.g. never_married">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mb-4"><button type="submit" class="btn btn-primary">Save Preferences</button></div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: PHOTO GALLERY                                            -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-photos">
            <div class="card shadow-sm mb-4" data-testid="section-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Photo Gallery</h5>
                    <label class="btn btn-primary btn-sm mb-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg> Upload Photo
                        <input type="file" accept="image/*" class="d-none" id="gallery-upload-input">
                    </label>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="photo-gallery">
                        <?php if (empty($photos)): ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <p class="mb-2">No photos uploaded yet.</p>
                                <p>Upload photos to increase your profile visibility.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($photos as $ph): ?>
                                <div class="col-md-3 col-6">
                                    <div class="photo-card position-relative" data-photo-id="<?= (int) $ph['id'] ?>">
                                        <img src="<?= e($ph['path']) ?>" class="img-fluid" alt="Gallery photo" data-testid="gallery-photo" onerror="this.src='uploads/photos/default-avatar.svg'">
                                        <div class="photo-overlay">
                                            <?php if ($ph['is_primary']): ?>
                                                <span class="badge bg-success">Primary</span>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-light set-primary-btn" data-id="<?= (int) $ph['id'] ?>">Set as Primary</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger delete-photo-btn" data-id="<?= (int) $ph['id'] ?>">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB: PRIVACY & SETTINGS                                       -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-privacy">
            <div class="row">
                <div class="col-md-8">
                    <!-- Privacy Settings -->
                    <div class="card shadow-sm mb-4" data-testid="section-card">
                        <div class="card-header"><h5 class="mb-0">Privacy Settings</h5></div>
                        <div class="card-body" id="privacy-settings">
                            <div class="mb-3">
                                <label class="fw-medium">Privacy Preset</label>
                                <div class="d-flex gap-2 mt-1">
                                    <button class="btn btn-sm <?= ($privacy['privacy_preset'] ?? 'members') === 'public' ? 'btn-primary' : 'btn-outline-primary' ?>" data-preset="public">Public</button>
                                    <button class="btn btn-sm <?= ($privacy['privacy_preset'] ?? 'members') === 'members' ? 'btn-primary' : 'btn-outline-primary' ?>" data-preset="members">Members Only</button>
                                    <button class="btn btn-sm <?= ($privacy['privacy_preset'] ?? 'members') === 'private' ? 'btn-primary' : 'btn-outline-primary' ?>" data-preset="private">Private</button>
                                </div>
                            </div>
                            <hr>
                            <?php
                            $toggles = [
                                'profile_visibility' => 'Profile Visibility',
                                'show_phone' => 'Show Phone Number',
                                'show_email' => 'Show Email Address',
                                'show_photos' => 'Show Photos',
                                'show_online_status' => 'Show Online Status',
                                'receive_interests' => 'Receive Interest Requests',
                            ];
                            ?>
                            <?php foreach ($toggles as $key => $label): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span><?= e($label) ?></span>
                                    <div class="form-check form-switch" data-testid="privacy-toggle">
                                        <input class="form-check-input privacy-toggle" type="checkbox" data-key="<?= e($key) ?>" <?= !empty($privacy[$key]) ? 'checked' : '' ?>>
                                        <span data-testid="toggle-knob" style="display:none"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Account Settings -->
                    <div class="card shadow-sm mb-4" data-testid="section-card">
                        <div class="card-header"><h5 class="mb-0">Account Settings</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" value="<?= e($p['email'] ?? '') ?>" disabled>
                                    <button class="btn btn-outline-secondary" type="button" onclick="showInfoAlert('Coming Soon', 'Verification feature coming soon')">Verify</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="showInfoAlert('Coming Soon', 'Change password feature coming soon')">Change Password</button>
                                    <small class="text-muted ms-2">Last changed: --</small>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <h6>Membership</h6>
                                <?php if ($membership): ?>
                                    <p class="mb-1"><strong><?= e($membership['plan_name'] ?? '') ?></strong> &middot; Status: <?= e($membership['status'] ?? '') ?></p>
                                    <small class="text-muted">Valid until <?= e(date('d M Y', strtotime($membership['ends_at'] ?? ''))) ?></small>
                                <?php else: ?>
                                    <p class="text-muted">Free Member</p>
                                <?php endif; ?>
                                <a href="/packages" class="btn btn-sm btn-outline-primary mt-2">Upgrade Plan</a>
                            </div>
                            <hr>
                            <?php if (!empty($p['is_active'])): ?>
                            <div>
                                <label class="form-label fw-medium">Deactivate Account</label>
                                <p class="text-muted small mb-2">Your profile will be hidden from searches and you won't be able to log in. You can reactivate anytime.</p>
                                <div class="mb-2">
                                    <textarea class="form-control form-control-sm" id="deactivation-reason" rows="2" placeholder="Reason for deactivation (optional)"></textarea>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" onclick="deactivateAccount()">Deactivate Account</button>
                            </div>
                            <?php else: ?>
                            <div>
                                <label class="form-label fw-medium text-danger">Account Deactivated</label>
                                <p class="text-muted small mb-2">Your account is currently deactivated. Enter your password to reactivate.</p>
                                <div class="mb-2">
                                    <input type="password" class="form-control form-control-sm" id="reactivate-password" placeholder="Enter your password" autocomplete="current-password">
                                </div>
                                <button class="btn btn-success btn-sm" onclick="reactivateAccount()">Reactivate Account</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Sidebar -->
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4" data-testid="section-card">
                        <div class="card-header"><h5 class="mb-0">Quick Actions</h5></div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="showCompletionChecklist()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Complete Profile (<?= $completion ?>%)
                                </button>
                                <button class="btn btn-outline-primary" onclick="viewAsPublic()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg> View as Others
                                </button>
                                <button class="btn btn-outline-secondary" onclick="shareProfile()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg> Share Profile
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Timeline -->
                    <div class="card shadow-sm mb-4" data-testid="section-card">
                        <div class="card-header"><h5 class="mb-0">Recent Activity</h5></div>
                        <div class="card-body p-0" id="activity-timeline">
                            <div class="text-center py-4 text-muted small">
                                <div class="spinner-border spinner-border-sm me-1"></div> Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.tab-content -->
        </div><!-- /.container -->
    </main><!-- /.profile-main -->
    </div><!-- /.profile-layout -->
</div><!-- /.profile-page -->

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite" aria-relevant="additions"></div>

<!-- Completion Checklist Modal -->
<div class="modal fade" id="completionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Profile Completion Checklist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="completion-modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-medium">Overall Completion</span>
                        <span class="badge bg-success"><?= $completion ?>%</span>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-success" style="width:<?= $completion ?>%"></div>
                    </div>
                </div>
                <ul class="list-group list-group-flush" id="completion-checklist">
                    <?php foreach (($p['completion_fields'] ?? []) as $field): ?>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <?php if ($field['done']): ?>
                                <span class="text-success"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span>
                                <span class="text-success"><?= e($field['group']) ?></span>
                            <?php else: ?>
                                <span class="text-muted"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></span>
                                <span><?= e($field['group']) ?> <small class="text-danger">(<?= e(implode(', ', $field['missing'])) ?>)</small></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
