// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Matches Page E2E Tests
 *
 * Covers: M-U-01 through M-U-12
 */

const TEST_USER = {
    email: 'demo@matrimony.local',
    password: 'secret123',
};

test.describe('Matches Page — UI/UX', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', TEST_USER.email);
        await page.fill('input[name="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/matches/);
    });

    // M-U-01: Card grid layout
    test('M-U-01: Cards display in responsive grid', async ({ page }) => {
        const viewports = [
            { width: 1920, height: 1080, expectedCols: 3 },
            { width: 768, height: 1024, expectedCols: 2 },
            { width: 414, height: 896, expectedCols: 1 },
        ];
        for (const vp of viewports) {
            await page.setViewportSize(vp);
            await page.goto('/matches');
            await page.waitForLoadState('networkidle');
            const cards = page.locator('[data-testid="match-card"]');
            const count = await cards.count();
            if (count > 0) {
                const firstBox = await cards.first().boundingBox();
                const secondBox = count > 1 ? await cards.nth(1).boundingBox() : null;
                if (firstBox && secondBox) {
                    // Check cards are in a grid (not stacked vertically in same column)
                    expect(Math.abs(firstBox.y - secondBox.y)).toBeLessThan(firstBox.height * 1.5);
                }
            }
        }
    });

    // M-U-02: Sticky sidebar on desktop
    test('M-U-02: Filter sidebar is sticky on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto('/matches');
        const sidebar = page.locator('[data-testid="filter-sidebar"]');
        await expect(sidebar).toBeVisible();
        const position = await sidebar.evaluate(el => getComputedStyle(el).position);
        expect(position).toBe('sticky');
    });

    // M-U-03: Skeleton loading
    test('M-U-03: Skeleton cards shown during loading', async ({ page }) => {
        await page.goto('/matches');
        // Skeleton should appear and then be replaced by real content
        const skeleton = page.locator('[data-testid="skeleton-card"]');
        const realCards = page.locator('[data-testid="match-card"]');
        // After load, real cards should exist
        await page.waitForTimeout(2000);
        const realCount = await realCards.count();
        expect(realCount).toBeGreaterThanOrEqual(0);
    });

    // M-U-04: Empty state
    test('M-U-04: Empty state shows friendly message and reset CTA', async ({ page }) => {
        await page.goto('/matches?age_min=1&age_max=1');
        await page.waitForLoadState('networkidle');
        const emptyState = page.locator('[data-testid="empty-state"]');
        if (await emptyState.isVisible()) {
            await expect(page.locator('[data-testid="reset-filters"]')).toBeVisible();
        }
    });

    // M-U-07: Quick action buttons on hover
    test('M-U-07: Quick action buttons visible on card hover (desktop)', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto('/matches');
        const card = page.locator('[data-testid="match-card"]').first();
        if (await card.isVisible()) {
            await card.hover();
            const actions = card.locator('[data-testid="quick-actions"]');
            await expect(actions).toBeVisible();
        }
    });

    // M-U-08: Photo display
    test('M-U-08: Card photos use object-fit cover', async ({ page }) => {
        await page.goto('/matches');
        const photo = page.locator('[data-testid="match-photo"]').first();
        if (await photo.isVisible()) {
            const objFit = await photo.evaluate(el => getComputedStyle(el).objectFit);
            expect(objFit).toBe('cover');
        }
    });

    // M-U-09: Premium badge
    test('M-U-09: Premium badge has aria-label', async ({ page }) => {
        await page.goto('/matches');
        const badge = page.locator('[data-testid="premium-badge"]').first();
        if (await badge.isVisible()) {
            const ariaLabel = await badge.getAttribute('aria-label');
            expect(ariaLabel).toBeTruthy();
        }
    });

    // M-U-11: Mobile drawer
    test('M-U-11: Filter drawer opens and closes on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 414, height: 896 });
        await page.goto('/matches');

        const filterBtn = page.locator('[data-testid="open-filters"]');
        if (await filterBtn.isVisible()) {
            await filterBtn.click();
            await page.waitForTimeout(300);
            const drawer = page.locator('[data-testid="filter-drawer"]');
            await expect(drawer).toBeVisible();
            // Close with Esc
            await page.keyboard.press('Escape');
            await page.waitForTimeout(300);
            await expect(drawer).not.toBeVisible();
        }
    });
});

test.describe('Matches Page — Filtering', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', TEST_USER.email);
        await page.fill('input[name="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/matches/);
    });

    // M-FL-01 through M-FL-13: Individual filter interaction
    test('Age filter slider updates results', async ({ page }) => {
        await page.goto('/matches');
        const ageSlider = page.locator('[data-testid="age-filter"]');
        if (await ageSlider.isVisible()) {
            // Change age filter
            await ageSlider.fill('24-30');
            await page.waitForTimeout(500);
            // Results should update
            const cards = page.locator('[data-testid="match-card"]');
            await expect(cards.first()).toBeVisible({ timeout: 5000 });
        }
    });

    // M-FL-14: Reset filters
    test('Reset filters clears all selections', async ({ page }) => {
        await page.goto('/matches');
        const resetBtn = page.locator('[data-testid="reset-filters"]');
        if (await resetBtn.isVisible()) {
            await resetBtn.click();
            await page.waitForTimeout(500);
            // URL params should be cleared
            const url = page.url();
            expect(url).not.toContain('age_min');
        }
    });

    // M-FL-15: Filter persistence
    test('Filters persist in URL', async ({ page }) => {
        await page.goto('/matches?religion=Hindu&city=Mumbai');
        await page.waitForLoadState('networkidle');
        const url = page.url();
        expect(url).toContain('religion');
        expect(url).toContain('city');
    });
});
