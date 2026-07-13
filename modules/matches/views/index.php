<section class="page-header">
    <div class="container">
        <h1 class="page-title">Find Your Perfect Match</h1>
        <p class="page-subtitle">Browse verified profiles and discover compatible matches based on your preferences</p>
    </div>
</section>

<div class="matches-page container pb-5" data-csrf="<?= e($csrfToken) ?>">

<?php if (!$isLoggedIn): ?>
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="login-prompt-card">
            <div class="login-prompt-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <h3 class="mb-2">Welcome!</h3>
            <p class="mb-4">Log in to browse matches, send interests, and connect with potential partners who share your values.</p>
            <a href="/users/login" class="btn btn-primary btn-lg w-100 mb-2">Login to Your Account</a>
            <a href="/users/register" class="btn btn-outline-primary btn-lg w-100">Create Free Account</a>
        </div>
    </div>
</div>
<?php endif; ?>

    <!-- Mobile Filter Toggle -->
    <button type="button" class="filter-mobile-toggle d-lg-none" onclick="openMobileDrawer()" aria-label="Open filter options" data-testid="open-filters">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"/></svg>
        Filters
        <span class="filter-count-badge" id="mobileFilterCount" aria-hidden="true">0</span>
    </button>

    <!-- Quick Preset Chips -->
    <div class="quick-chips" role="group" aria-label="Quick filter presets">
        <button type="button" class="quick-chip active" data-preset="all" aria-pressed="true">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            All Matches
        </button>
        <button type="button" class="quick-chip" data-preset="new" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            New Profiles
        </button>
        <button type="button" class="quick-chip" data-preset="premium" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Premium
        </button>
        <button type="button" class="quick-chip" data-preset="verified" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            Verified
        </button>
        <button type="button" class="quick-chip" data-preset="online" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
            Online Now
        </button>
    </div>

    <!-- Horizontal Filter Bar (sticky) -->
    <form id="filter-form" class="filter-bar" autocomplete="off" role="search" aria-label="Filter matches" data-testid="filter-sidebar">
        <div class="filter-bar-row">
            <div class="filter-bar-left" id="filterTriggers">

                <!-- Search -->
                <div class="filter-trigger">
                    <button type="button" class="filter-trigger-btn" data-panel="panel-search" onclick="togglePanel('panel-search')" aria-haspopup="true" aria-expanded="false" aria-label="Search by name or ID">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <span class="label">Search</span>
                        <span class="value" id="search-value"></span>
                        <svg class="chevron" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                    </button>
                    <div class="filter-panel" id="panel-search" role="dialog" aria-label="Search filter">
                        <div class="filter-panel-header">
                            <span class="filter-panel-title">Quick Search</span>
                            <button type="button" class="filter-panel-clear" onclick="clearFilter('search')" aria-label="Clear search">Clear</button>
                        </div>
                        <div class="filter-panel-content">
                            <label for="search-input" class="sr-only">Search by profile ID or name</label>
                            <input type="text" name="search" id="search-input" class="panel-input" placeholder="Search by ID or name..." oninput="onSearchChange()" autocomplete="off">
                        </div>
                        <div class="filter-panel-footer">
                            <button type="button" class="btn btn-primary" onclick="applyPanel('panel-search')">Apply</button>
                            <button type="button" class="btn btn-outline-primary" onclick="clearFilter('search')">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Age -->
                <div class="filter-trigger">
                    <button type="button" class="filter-trigger-btn" data-panel="panel-age" onclick="togglePanel('panel-age')" aria-haspopup="true" aria-expanded="false" aria-label="Filter by age range">
                        <span class="label">Age</span>
                        <span class="value" id="age-value" aria-live="polite">Any</span>
                        <svg class="chevron" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                    </button>
                    <div class="filter-panel" id="panel-age" role="dialog" aria-label="Age range filter">
                        <div class="filter-panel-header">
                            <span class="filter-panel-title">Age Range</span>
                            <button type="button" class="filter-panel-clear" onclick="clearFilter('age')" aria-label="Clear age filter">Clear</button>
                        </div>
                        <div class="filter-panel-content">
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" data-testid="age-filter" placeholder="e.g. 24-30" aria-label="Age range filter">
                            </div>
                            <div class="panel-select-grid">
                                <div>
                                    <label for="age-min" class="panel-field-label">Minimum Age</label>
                                    <select name="age_min" class="panel-select" id="age-min" onchange="onAgeChange()">
                                        <option value="">Min</option>
                                        <?php for ($i = 18; $i <= 70; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="age-max" class="panel-field-label">Maximum Age</label>
                                    <select name="age_max" class="panel-select" id="age-max" onchange="onAgeChange()">
                                        <option value="">Max</option>
                                        <?php for ($i = 18; $i <= 70; $i++): ?>
                                        <option value="<?= $i ?>"<?= $i === 50 ? ' selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="filter-panel-footer">
                            <button type="button" class="btn btn-primary" onclick="applyPanel('panel-age')">Apply</button>
                            <button type="button" class="btn btn-outline-primary" onclick="clearFilter('age')">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="filter-trigger">
                    <button type="button" class="filter-trigger-btn" data-panel="panel-location" onclick="togglePanel('panel-location')" aria-haspopup="true" aria-expanded="false" aria-label="Filter by location">
                        <span class="label">Location</span>
                        <span class="value" id="location-value" aria-live="polite">Any</span>
                        <svg class="chevron" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                    </button>
                    <div class="filter-panel" id="panel-location" role="dialog" aria-label="Location filter">
                        <div class="filter-panel-header">
                            <span class="filter-panel-title">Location</span>
                            <button type="button" class="filter-panel-clear" onclick="clearFilter('location')" aria-label="Clear location filter">Clear</button>
                        </div>
                        <div class="filter-panel-content">
                            <label for="location-state" class="panel-field-label">State</label>
                            <select name="state[]" class="panel-select mb-2" id="location-state" onchange="onLocationChange()">
                                <option value="">All States</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Andhra Pradesh">Andhra Pradesh</option>
                                <option value="Telangana">Telangana</option>
                                <option value="Maharashtra">Maharashtra</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Uttar Pradesh">Uttar Pradesh</option>
                            </select>
                            <label for="location-city" class="panel-field-label">City</label>
                            <input type="text" name="city" class="panel-input" id="location-city" placeholder="Enter city name" oninput="onLocationChange()" autocomplete="off">
                        </div>
                        <div class="filter-panel-footer">
                            <button type="button" class="btn btn-primary" onclick="applyPanel('panel-location')">Apply</button>
                            <button type="button" class="btn btn-outline-primary" onclick="clearFilter('location')">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Religion -->
                <div class="filter-trigger">
                    <button type="button" class="filter-trigger-btn" data-panel="panel-religion" onclick="togglePanel('panel-religion')" aria-haspopup="true" aria-expanded="false" aria-label="Filter by religion">
                        <span class="label">Religion</span>
                        <span class="value" id="religion-value" aria-live="polite">Any</span>
                        <svg class="chevron" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                    </button>
                    <div class="filter-panel" id="panel-religion" role="dialog" aria-label="Religion filter">
                        <div class="filter-panel-header">
                            <span class="filter-panel-title">Religion</span>
                            <button type="button" class="filter-panel-clear" onclick="clearFilter('religion')" aria-label="Clear religion filter">Clear</button>
                        </div>
                        <div class="filter-panel-content">
                            <fieldset style="border:none;padding:0;margin:0;">
                                <legend class="sr-only">Select one or more religions</legend>
                                <div class="panel-check-list" id="religion-list">
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Hindu" onchange="onReligionChange()"> Hindu</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Muslim" onchange="onReligionChange()"> Muslim</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Christian" onchange="onReligionChange()"> Christian</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Sikh" onchange="onReligionChange()"> Sikh</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Jain" onchange="onReligionChange()"> Jain</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Buddhist" onchange="onReligionChange()"> Buddhist</label>
                                    <label class="panel-check-item"><input type="checkbox" name="religion[]" value="Other" onchange="onReligionChange()"> Other</label>
                                </div>
                            </fieldset>
                        </div>
                        <div class="filter-panel-footer">
                            <button type="button" class="btn btn-primary" onclick="applyPanel('panel-religion')">Apply</button>
                            <button type="button" class="btn btn-outline-primary" onclick="clearFilter('religion')">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- More Filters -->
                <div class="filter-trigger">
                    <button type="button" class="more-filters-btn" data-panel="panel-more" onclick="togglePanel('panel-more')" aria-haspopup="true" aria-expanded="false" aria-label="More filter options">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"/></svg>
                        More Filters
                        <span class="filter-count-badge" id="moreFiltersBadge" style="display:none;" aria-hidden="true">0</span>
                        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                    </button>
                    <div class="filter-panel filter-panel-lg panel-right" id="panel-more" role="dialog" aria-label="Additional filters">
                        <div class="filter-panel-header">
                            <span class="filter-panel-title">More Filters</span>
                            <button type="button" class="filter-panel-clear" onclick="clearAllFilters()" aria-label="Clear all additional filters">Clear All</button>
                        </div>
                        <div class="filter-panel-content">
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Marital Status</div>
                                <fieldset style="border:none;padding:0;margin:0;">
                                    <legend class="sr-only">Select marital status</legend>
                                    <div class="panel-check-list" id="marital-list">
                                        <label class="panel-check-item"><input type="checkbox" name="marital_status[]" value="never_married" checked onchange="onMaritalChange()"> Never Married</label>
                                        <label class="panel-check-item"><input type="checkbox" name="marital_status[]" value="divorced" onchange="onMaritalChange()"> Divorced</label>
                                        <label class="panel-check-item"><input type="checkbox" name="marital_status[]" value="widowed" onchange="onMaritalChange()"> Widowed</label>
                                        <label class="panel-check-item"><input type="checkbox" name="marital_status[]" value="awaiting_divorce" onchange="onMaritalChange()"> Awaiting Divorce</label>
                                    </div>
                                </fieldset>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Caste</div>
                                <label for="caste-select" class="sr-only">Select caste preference</label>
                                <select name="caste" class="panel-select" id="caste-select" onchange="onMoreChange()">
                                    <option value="">Caste no bar</option>
                                    <option value="Nadar">Nadar</option>
                                    <option value="Pillai">Pillai</option>
                                    <option value="Mudaliar">Mudaliar</option>
                                    <option value="Naidu">Naidu</option>
                                    <option value="Chettiar">Chettiar</option>
                                    <option value="Gounder">Gounder</option>
                                    <option value="Nair">Nair</option>
                                    <option value="Reddy">Reddy</option>
                                    <option value="Yadav">Yadav</option>
                                    <option value="Kallar">Kallar</option>
                                    <option value="Vanniyar">Vanniyar</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Mother Tongue</div>
                                <label for="tongue-select" class="sr-only">Select mother tongue</label>
                                <select name="mother_tongue" class="panel-select" id="tongue-select" onchange="onMoreChange()">
                                    <option value="">Any</option>
                                    <option value="Tamil">Tamil</option>
                                    <option value="Telugu">Telugu</option>
                                    <option value="Kannada">Kannada</option>
                                    <option value="Malayalam">Malayalam</option>
                                    <option value="Hindi">Hindi</option>
                                    <option value="Urdu">Urdu</option>
                                    <option value="Marathi">Marathi</option>
                                    <option value="Gujarati">Gujarati</option>
                                    <option value="Bengali">Bengali</option>
                                    <option value="Punjabi">Punjabi</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Education</div>
                                <label for="education-select" class="sr-only">Select education level</label>
                                <select name="education" class="panel-select" id="education-select" onchange="onMoreChange()">
                                    <option value="">Any</option>
                                    <option value="B.E">B.E</option>
                                    <option value="B.Tech">B.Tech</option>
                                    <option value="M.E">M.E</option>
                                    <option value="M.Tech">M.Tech</option>
                                    <option value="B.Sc">B.Sc</option>
                                    <option value="M.Sc">M.Sc</option>
                                    <option value="B.Com">B.Com</option>
                                    <option value="M.Com">M.Com</option>
                                    <option value="B.A">B.A</option>
                                    <option value="M.A">M.A</option>
                                    <option value="MBA">MBA</option>
                                    <option value="MBBS">MBBS</option>
                                    <option value="CA">CA</option>
                                    <option value="Ph.D">Ph.D</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Occupation</div>
                                <label for="occupation-select" class="sr-only">Select occupation</label>
                                <select name="occupation" class="panel-select" id="occupation-select" onchange="onMoreChange()">
                                    <option value="">Any</option>
                                    <option value="Software Engineer">Software Engineer</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Engineer">Engineer</option>
                                    <option value="Teacher">Teacher</option>
                                    <option value="Business">Business</option>
                                    <option value="Government">Government</option>
                                    <option value="Banking">Banking</option>
                                    <option value="Lawyer">Lawyer</option>
                                    <option value="Accountant">Accountant</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Annual Income</div>
                                <label for="income-select" class="sr-only">Select minimum annual income</label>
                                <select name="income_min" class="panel-select" id="income-select" onchange="onMoreChange()">
                                    <option value="">Any</option>
                                    <option value="500000">5 Lakh+</option>
                                    <option value="1000000">10 Lakh+</option>
                                    <option value="2000000">20 Lakh+</option>
                                    <option value="3000000">30 Lakh+</option>
                                    <option value="5000000">50 Lakh+</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Diet</div>
                                <label for="diet-select" class="sr-only">Select diet preference</label>
                                <select name="diet" class="panel-select" id="diet-select" onchange="onMoreChange()">
                                    <option value="">Any</option>
                                    <option value="Vegetarian">Vegetarian</option>
                                    <option value="Non-veg">Non-veg</option>
                                    <option value="Vegan">Vegan</option>
                                    <option value="Jain">Jain</option>
                                    <option value="Halal">Halal</option>
                                </select>
                            </div>
                            <div class="filter-panel-section">
                                <div class="filter-panel-section-title">Other Preferences</div>
                                <fieldset style="border:none;padding:0;margin:0;">
                                    <legend class="sr-only">Other filter options</legend>
                                    <div class="panel-check-list">
                                        <label class="panel-check-item"><input type="checkbox" name="photo_required" value="1" id="photo-req" onchange="onMoreChange()"> Photo required</label>
                                        <label class="panel-check-item"><input type="checkbox" name="verified_only" value="1" id="verified-only" onchange="onMoreChange()"> Verified only</label>
                                        <label class="panel-check-item"><input type="checkbox" name="exclude_contacted" value="1" id="exclude-contacted" onchange="onMoreChange()"> Exclude contacted</label>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                        <div class="filter-panel-footer">
                            <button type="button" class="btn btn-primary" onclick="applyPanel('panel-more')">Apply</button>
                            <button type="button" class="btn btn-outline-primary" onclick="clearAllFilters()">Clear All</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-bar-right">
                <button type="button" class="clear-all-btn" onclick="clearAllFilters()" aria-label="Clear all filters">Clear all</button>
                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
            </div>
        </div>

        <!-- Hidden inputs for quick preset filters -->
        <input type="hidden" name="new_profiles" id="hf-new-profiles" value="">
        <input type="hidden" name="premium_only" id="hf-premium-only" value="">
        <input type="hidden" name="recently_active_days" id="hf-recently-active" value="">

        <!-- Active Filter Pills -->
        <div class="active-filters" id="activeFilters" aria-label="Active filters"></div>
    </form>

    <!-- Results Header -->
    <div class="results-header">
        <div>
            <span class="fw-medium" id="result-count" aria-live="polite" aria-atomic="true">Loading...</span>
        </div>
        <div class="results-controls">
            <label for="sort-select" class="sr-only">Sort results by</label>
            <select name="sort" class="form-select" id="sort-select" aria-label="Sort results">
                <option value="compatibility">Best Match</option>
                <option value="recently_joined">Recently Joined</option>
                <option value="last_active">Last Active</option>
                <option value="newest_first">Newest First</option>
            </select>
            <label for="per-page-select" class="sr-only">Results per page</label>
            <select id="per-page-select" class="form-select" aria-label="Results per page">
                <option value="12">12 / page</option>
                <option value="24" selected>24 / page</option>
                <option value="48">48 / page</option>
            </select>
        </div>
    </div>

    <!-- Match Cards Grid -->
    <div id="matches-grid" class="row g-3" aria-label="Match profiles" role="list">
        <div class="col-md-6 col-lg-3" data-testid="skeleton-card">
            <div class="match-card skeleton-card">
                <div class="skeleton-photo skeleton-pulse"></div>
                <div class="skeleton-details">
                    <div class="skeleton-line skeleton-pulse w-75"></div>
                    <div class="skeleton-line skeleton-pulse w-50"></div>
                    <div class="skeleton-line skeleton-pulse w-100"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3" data-testid="skeleton-card">
            <div class="match-card skeleton-card">
                <div class="skeleton-photo skeleton-pulse"></div>
                <div class="skeleton-details">
                    <div class="skeleton-line skeleton-pulse w-75"></div>
                    <div class="skeleton-line skeleton-pulse w-50"></div>
                    <div class="skeleton-line skeleton-pulse w-100"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3" data-testid="skeleton-card">
            <div class="match-card skeleton-card">
                <div class="skeleton-photo skeleton-pulse"></div>
                <div class="skeleton-details">
                    <div class="skeleton-line skeleton-pulse w-75"></div>
                    <div class="skeleton-line skeleton-pulse w-50"></div>
                    <div class="skeleton-line skeleton-pulse w-100"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3" data-testid="skeleton-card">
            <div class="match-card skeleton-card">
                <div class="skeleton-photo skeleton-pulse"></div>
                <div class="skeleton-details">
                    <div class="skeleton-line skeleton-pulse w-75"></div>
                    <div class="skeleton-line skeleton-pulse w-50"></div>
                    <div class="skeleton-line skeleton-pulse w-100"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <nav id="pagination-nav" class="mt-4 d-none" aria-label="Match results pagination">
        <ul class="pagination justify-content-center"></ul>
    </nav>
</div>

<!-- Filter Overlay & Mobile Drawer -->
<div class="filter-overlay" id="filterOverlay" aria-hidden="true"></div>
<div class="filter-mobile-drawer" id="mobileDrawer" role="dialog" aria-label="Filter options" aria-modal="true" data-testid="filter-drawer">
    <div class="drawer-handle" aria-hidden="true"></div>
    <div class="drawer-header">
        <span class="drawer-title">Filters</span>
        <button type="button" class="drawer-close" onclick="closeMobileDrawer()" aria-label="Close filters">&times;</button>
    </div>
    <div id="mobileDrawerContent"></div>
</div>

<!-- Profile Quick View Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h2 class="modal-title fs-5" id="profileModalLabel">Profile Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close profile"></button>
            </div>
            <div class="modal-body" id="profile-modal-body">
                <div class="text-center py-5" role="status">
                    <div class="spinner-border text-primary" aria-hidden="true"></div>
                    <p class="text-muted mt-2">Loading profile...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite" aria-relevant="additions"></div>
</div>
