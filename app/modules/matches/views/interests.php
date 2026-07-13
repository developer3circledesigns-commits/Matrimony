<section class="page-header">
    <div class="container">
        <h1 class="page-title">Interests Received</h1>
        <p class="page-subtitle">Members who have expressed interest in your profile.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="fw-bold mb-0"><?= count($profiles) ?> Interest<?= count($profiles) !== 1 ? 's' : '' ?></h5>
        </div>

        <?php if (empty($profiles)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--brand-gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </div>
            <h5 class="text-muted">No interests yet</h5>
            <p class="text-muted small">When someone expresses interest in your profile, they will appear here.</p>
            <a href="/matches" class="btn btn-primary mt-2">Browse Matches</a>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($profiles as $v): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="flex-shrink-0">
                            <?php if (!empty($v['primary_photo'])): ?>
                            <img src="<?= e($v['primary_photo']) ?>" alt="" class="rounded-circle" width="56" height="56" style="object-fit:cover">
                            <?php else: ?>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white fw-bold" style="width:56px;height:56px;font-size:1.25rem"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <h6 class="fw-bold mb-1"><?= e($v['first_name'] ?? '') ?></h6>
                            <p class="text-muted small mb-1">
                                <?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?>
                                <?php if (!empty($v['state'])): ?>, <?= e($v['state']) ?><?php endif; ?>
                            </p>
                            <small class="text-muted">
                                <?= !empty($v['gender']) ? ucfirst(e($v['gender'])) . ', ' : '' ?>
                                <?php if (!empty($v['date_of_birth'])): ?><?php $dob = new \DateTime($v['date_of_birth']); echo (int) (new \DateTime())->diff($dob)->y; ?> yrs<?php endif; ?>
                            </small>
                        </div>
                        <a href="/profile/<?= (int) $v['user_id'] ?>" class="btn btn-outline-primary btn-sm flex-shrink-0">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
