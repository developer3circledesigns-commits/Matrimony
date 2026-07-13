<section class="page-header">
    <div class="container">
        <h1 class="page-title">Mutual Matches</h1>
        <p class="page-subtitle">Members who have also expressed interest in you - it's a match!</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="fw-bold mb-0"><?= count($profiles) ?> Mutual Match<?= count($profiles) !== 1 ? 'es' : '' ?></h5>
        </div>

        <?php if (empty($profiles)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--brand-gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h5 class="text-muted">No mutual matches yet</h5>
            <p class="text-muted small">When someone you're interested in also expresses interest in you, a mutual match is created.</p>
            <a href="/matches" class="btn btn-primary mt-2">Browse Matches</a>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($profiles as $v): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm border-0 h-100 border-success border-start border-3">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="flex-shrink-0">
                            <?php if (!empty($v['primary_photo'])): ?>
                            <img src="<?= e($v['primary_photo']) ?>" alt="" class="rounded-circle" width="56" height="56" style="object-fit:cover">
                            <?php else: ?>
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white fw-bold" style="width:56px;height:56px;font-size:1.25rem"><?= e(strtoupper(substr($v['first_name'] ?? '?', 0, 1))) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <h6 class="fw-bold mb-1"><?= e($v['first_name'] ?? '') ?> <span class="badge bg-success-subtle text-success" style="font-size:0.65rem"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Match</span></h6>
                            <p class="text-muted small mb-1">
                                <?php if (!empty($v['city'])): ?><?= e($v['city']) ?><?php endif; ?>
                                <?php if (!empty($v['state'])): ?>, <?= e($v['state']) ?><?php endif; ?>
                            </p>
                            <small class="text-muted">Matched <?= !empty($v['created_at']) ? date('d M Y', strtotime($v['created_at'])) : '' ?></small>
                        </div>
                        <a href="/profile/<?= (int) $v['match_id'] ?>" class="btn btn-success btn-sm flex-shrink-0">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
