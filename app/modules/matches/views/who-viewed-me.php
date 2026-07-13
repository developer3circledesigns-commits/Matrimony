<section class="page-header">
    <div class="container">
        <h1 class="page-title">Who Viewed Me</h1>
        <p class="page-subtitle">See who has visited your profile recently.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="fw-bold mb-0"><?= count($profiles) ?> Profile View<?= count($profiles) !== 1 ? 's' : '' ?></h5>
        </div>

        <?php if (empty($profiles)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--brand-gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <h5 class="text-muted">No views yet</h5>
            <p class="text-muted small">When someone views your profile, they will appear here.</p>
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
                            <small class="text-muted">Viewed <?= !empty($v['viewed_at']) ? date('d M Y', strtotime($v['viewed_at'])) : '' ?></small>
                        </div>
                        <a href="/profile/<?= (int) $v['viewer_id'] ?>" class="btn btn-outline-primary btn-sm flex-shrink-0">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
