<section class="page-header">
    <div class="container">
        <h1 class="page-title text-center">Create Account</h1>
        <p class="page-subtitle text-center">Join Matrimony and start your journey.</p>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div id="register-errors" class="alert alert-danger d-none">
                    <ul class="mb-0" id="register-errors-list"></ul>
                </div>

                <form id="register-form" method="POST" action="/users/register">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e($input['first_name'] ?? '') ?>" required autofocus autocomplete="given-name">
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e($input['last_name'] ?? '') ?>" autocomplete="family-name">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($input['email'] ?? '') ?>" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="gender" class="form-label">I am a</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Select</option>
                            <option value="male" <?= (($input['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= (($input['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= (($input['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Toggle password visibility">
                                <svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="form-text"><span id="password-strength"></span></div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Toggle password visibility">
                                <svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="form-text"><span id="confirm-match"></span></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Create Free Account</button>
                </form>
                <p class="text-center mt-3">
                    Already have an account? <a href="/users/login">Login</a>
                </p>
            </div>
        </div>
    </div>
</section>
