<?php
/**
 * Plan & pricing editor. Expects $rows, $isSuper, $csrf.
 *
 * Each plan is a self-contained <form> inside a card rather than a row inside
 * one big table: a <form> may not be a child of <tr>, and even when it is
 * nested in a single <td> the browser cannot associate sibling <td> inputs
 * with it. A card-per-plan keeps every field inside its own form, so "Save"
 * always submits exactly what the admin typed.
 */
$rows = is_array($rows ?? null) ? $rows : [];
$canEditPlans = !empty($isSuper);
?>

<div class="admin-notice">
    <?= admin_icon('info', 18) ?>
    <div>
        Prices are stored in <strong>paise</strong> (₹1,000 = 100000). Editing a plan never changes existing
        purchases — those keep the snapshot taken at checkout — and the price of an already-created payment
        order is immutable.
        <?php if (!$canEditPlans): ?>
            <br><strong>Only a super admin can change plan pricing.</strong>
        <?php endif; ?>
    </div>
</div>

<?php if (!$rows): ?>
    <section class="admin-panel">
        <?= admin_empty('No membership plans found.', 'Expected SILVER, GOLD, PLATINUM and FREE rows.') ?>
    </section>
<?php else: ?>
    <div class="admin-plan-grid">
        <?php foreach ($rows as $p): ?>
            <?php
            $planId   = (int) ($p['id'] ?? 0);
            $planName = (string) ($p['name'] ?? '');
            $code     = (string) ($p['code'] ?? '');
            // Legacy schemas expose price_cents instead of price_paise.
            $paise    = (int) ($p['price_paise'] ?? $p['price_cents'] ?? 0);
            $limit    = $p['contact_limit'] ?? '';
            $unlimited = !empty($p['is_unlimited']);
            $active   = !empty($p['is_active']);
            ?>
            <section class="admin-panel admin-plan">
                <div class="admin-panel__head">
                    <div>
                        <h2 class="admin-panel__title"><?= e($planName) ?></h2>
                        <p class="admin-panel__sub admin-mono"><?= e($code) ?><?= !empty($p['slug']) ? ' · /' . e((string) $p['slug']) : '' ?></p>
                    </div>
                    <div class="admin-panel__actions">
                        <?= $active
                            ? '<span class="admin-badge admin-badge--success">Active</span>'
                            : '<span class="admin-badge admin-badge--muted">Disabled</span>' ?>
                    </div>
                </div>

                <form method="POST" action="/api/admin/plans/update"
                      data-confirm="Save changes to the &quot;<?= e($planName) ?>&quot; plan?"
                      data-confirm-title="Save plan"
                      <?= $canEditPlans ? '' : 'onsubmit="return false;"' ?>>
                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                    <input type="hidden" name="id" value="<?= $planId ?>">

                    <div class="admin-panel__body">
                        <fieldset <?= $canEditPlans ? '' : 'disabled' ?>>
                        <div class="admin-field mb-3">
                            <label class="form-label" for="desc<?= $planId ?>">Public description</label>
                            <textarea class="form-control" id="desc<?= $planId ?>" name="description"
                                      rows="3" maxlength="1000"
                                      placeholder="Shown on the public plans page"><?= e((string) ($p['description'] ?? '')) ?></textarea>
                        </div>

                        <div class="admin-plan__row">
                            <div class="admin-field">
                                <label class="form-label" for="price<?= $planId ?>">Price (₹)</label>
                                <input class="form-control" id="price<?= $planId ?>" name="price_inr"
                                       type="number" min="1" step="1" inputmode="numeric" required
                                       value="<?= (int) round($paise / 100) ?>">
                                <div class="form-text"><?= number_format($paise) ?> paise stored</div>
                            </div>

                            <div class="admin-field">
                                <label class="form-label" for="days<?= $planId ?>">Duration (days)</label>
                                <input class="form-control" id="days<?= $planId ?>" name="duration_days"
                                       type="number" min="1" max="3650" required
                                       value="<?= (int) ($p['duration_days'] ?? 0) ?>">
                            </div>

                            <div class="admin-field">
                                <label class="form-label" for="limit<?= $planId ?>">Contact limit</label>
                                <input class="form-control" id="limit<?= $planId ?>" name="contact_limit"
                                       type="number" min="1" max="100000" placeholder="Required"
                                       data-plan-unlimited="unl<?= $planId ?>"
                                       value="<?= e((string) $limit) ?>">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="unl<?= $planId ?>"
                                           name="is_unlimited" value="1" <?= $unlimited ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="unl<?= $planId ?>">Unlimited contacts</label>
                                </div>
                            </div>
                        </div>

                        <div class="admin-plan__row">
                            <div class="admin-field">
                                <label class="form-label" for="order<?= $planId ?>">Display order</label>
                                <input class="form-control" id="order<?= $planId ?>" name="display_order"
                                       type="number" step="1" value="<?= (int) ($p['display_order'] ?? 0) ?>">
                                <div class="form-text">Lower numbers appear first.</div>
                            </div>

                            <div class="admin-field">
                                <span class="form-label d-block">Availability</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="active<?= $planId ?>" name="is_active" value="1"
                                           <?= $active ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active<?= $planId ?>">
                                        <?= $active ? 'Purchasable' : 'Hidden from checkout' ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        </fieldset>
                    </div>

                    <div class="admin-panel__foot d-flex justify-content-end gap-2">
                        <a class="btn btn-secondary btn-sm" href="/admin/payments?status=paid_verified">
                            View purchases
                        </a>
                        <?php if ($canEditPlans): ?>
                        <button class="btn btn-primary btn-sm" type="submit">
                            <?= admin_icon('check-circle', 14) ?> Save plan
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
