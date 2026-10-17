<?php
/**
 * Shared account + profile form used by both /admin/users/create and
 * /admin/users/{id}/edit.
 *
 * Expects: $u (record|null), $isEdit, $isSuper, $errors, $old, $csrf.
 */
$service = \Matrimony\Services\AdminUserService::class;
$ctx = [
    'old' => is_array($old ?? null) ? $old : [],
    'record' => is_array($u ?? null) ? $u : null,
    'errors' => is_array($errors ?? null) ? $errors : [],
];

$isEdit = !empty($isEdit);
$isSuper = !empty($isSuper);
$subjectId = $ctx['record']['id'] ?? 0;
$isSelf = $isEdit && (int) $subjectId === (int) ($admin['id'] ?? 0);

// Horoscope chart ("kattam") preview — served from the DB via /api/horoscope-chart.
$hasKattam = !empty($ctx['record']['has_kattam']);
$kattamUrl = $hasKattam
    ? '/api/horoscope-chart?user=' . (int) $subjectId
        . '&v=' . rawurlencode((string) ($ctx['record']['kattam_updated_at'] ?? ''))
    : '';

$roleOptions = ['user' => 'User'];
if ($isSuper) {
    $roleOptions += [
        'moderator' => 'Moderator',
        'admin' => 'Admin',
        'super_admin' => 'Super admin',
    ];
} else {
    $roleOptions['moderator'] = 'Moderator';
}

$statusOptions = [
    'active' => 'Active — can sign in',
    'pending_verification' => 'Pending verification — sign-in blocked until verified',
    'suspended' => 'Suspended — sessions revoked, can be restored',
    'deactivated' => 'Deactivated — sign-in blocked',
];

/*
 * Enumerated profile attributes. Values mirror the member profile form so both
 * front-ends stay in sync and MatchEngine keeps comparing like for like.
 */
$religionOptions = [
    '' => 'Not specified',
    'Hindu' => 'Hindu', 'Muslim' => 'Muslim', 'Christian' => 'Christian',
    'Sikh' => 'Sikh', 'Jain' => 'Jain', 'Buddhist' => 'Buddhist', 'Other' => 'Other',
];
$motherTongueOptions = [
    '' => 'Not specified',
    'Tamil' => 'Tamil', 'Telugu' => 'Telugu', 'Kannada' => 'Kannada', 'Malayalam' => 'Malayalam',
    'Hindi' => 'Hindi', 'Urdu' => 'Urdu', 'Marathi' => 'Marathi', 'Gujarati' => 'Gujarati',
    'Bengali' => 'Bengali', 'Punjabi' => 'Punjabi',
];
$incomeOptions = [
    '' => 'Not specified',
    'Below 1 Lakh' => 'Below 1 Lakh', '1-3 Lakhs' => '1-3 Lakhs', '3-5 Lakhs' => '3-5 Lakhs',
    '5-10 Lakhs' => '5-10 Lakhs', '10-20 Lakhs' => '10-20 Lakhs', '20-30 Lakhs' => '20-30 Lakhs',
    '30-50 Lakhs' => '30-50 Lakhs', '50 Lakhs+' => '50 Lakhs+',
];
$dietOptions = [
    '' => 'Not specified',
    'Vegetarian' => 'Vegetarian', 'Non-veg' => 'Non-veg', 'Vegan' => 'Vegan',
    'Jain' => 'Jain', 'Halal' => 'Halal',
];
$smokeOptions = ['' => 'Not specified', 'No' => 'No', 'Yes' => 'Yes', 'Occasionally' => 'Occasionally'];
$drinkOptions = ['' => 'Not specified', 'No' => 'No', 'Yes' => 'Yes', 'Socially' => 'Socially'];
$bodyTypeOptions = ['' => 'Not specified', 'Slim' => 'Slim', 'Average' => 'Average', 'Athletic' => 'Athletic', 'Heavy' => 'Heavy'];
$complexionOptions = ['' => 'Not specified', 'Fair' => 'Fair', 'Wheatish' => 'Wheatish', 'Dark' => 'Dark'];
$residencyOptions = [
    '' => 'Not specified', 'Citizen' => 'Citizen', 'Permanent Resident' => 'Permanent Resident',
    'Work Visa' => 'Work Visa', 'Student Visa' => 'Student Visa', 'Dependent Visa' => 'Dependent Visa',
    'Temporary' => 'Temporary',
];
$rashiOptions = [
    '' => 'Not specified', 'Mesham' => 'Mesham', 'Rishabam' => 'Rishabam', 'Mithunam' => 'Mithunam',
    'Katagam' => 'Katagam', 'Simmam' => 'Simmam', 'Kanni' => 'Kanni', 'Thulam' => 'Thulam',
    'Viruchigam' => 'Viruchigam', 'Dhanushu' => 'Dhanushu', 'Maharam' => 'Maharam',
    'Kumbam' => 'Kumbam', 'Meenam' => 'Meenam',
];
$nakshatraOptions = [
    '' => 'Not specified', 'Aswini' => 'Aswini', 'Bharani' => 'Bharani', 'Krithigai' => 'Krithigai',
    'Rohini' => 'Rohini', 'Mrigashirisham' => 'Mrigashirisham', 'Thiruvathirai' => 'Thiruvathirai',
    'Punarpoosam' => 'Punarpoosam', 'Poosam' => 'Poosam', 'Ayilyam' => 'Ayilyam', 'Maham' => 'Maham',
    'Puram' => 'Puram', 'Uthram' => 'Uthram', 'Hastham' => 'Hastham', 'Chitirai' => 'Chitirai',
    'Swathi' => 'Swathi', 'Visakam' => 'Visakam', 'Anusham' => 'Anusham', 'Kettai' => 'Kettai',
    'Moolam' => 'Moolam', 'Puradam' => 'Puradam', 'Uthradam' => 'Uthradam', 'Thiruvonam' => 'Thiruvonam',
    'Avittam' => 'Avittam', 'Sathayam' => 'Sathayam', 'Puratathi' => 'Puratathi',
    'Uthratathi' => 'Uthratathi', 'Revathi' => 'Revathi',
];
$prefIncomeOptions = [
    '' => 'No preference', '500000' => '5 Lakh+', '1000000' => '10 Lakh+',
    '2000000' => '20 Lakh+', '5000000' => '50 Lakh+',
];
$educationOptions = [
    'B.E' => 'B.E', 'B.Tech' => 'B.Tech', 'M.E' => 'M.E', 'M.Tech' => 'M.Tech',
    'B.Sc' => 'B.Sc', 'M.Sc' => 'M.Sc', 'B.Com' => 'B.Com', 'M.Com' => 'M.Com',
    'B.A' => 'B.A', 'M.A' => 'M.A', 'MBA' => 'MBA', 'MBBS' => 'MBBS', 'CA' => 'CA',
    'Ph.D' => 'Ph.D', 'Diploma' => 'Diploma', 'Other' => 'Other',
];
$occupationOptions = [
    'Software Engineer' => 'Software Engineer', 'Engineer' => 'Engineer', 'Doctor' => 'Doctor',
    'Teacher' => 'Teacher', 'Professor' => 'Professor', 'Business' => 'Business',
    'Government Employee' => 'Government Employee', 'Banking' => 'Banking', 'Lawyer' => 'Lawyer',
    'Accountant' => 'Accountant', 'Defence' => 'Defence', 'Self Employed' => 'Self Employed',
    'Other' => 'Other',
];
$maritalPrefOptions = [
    'never_married' => 'Never married', 'divorced' => 'Divorced',
    'widowed' => 'Widowed', 'awaiting_divorce' => 'Awaiting divorce',
];

/**
 * Append the stored value as an extra option when it is not already in the
 * list, so legacy free-text values display (and are preserved) instead of
 * silently collapsing to the first option on the next save.
 */
$withCurrent = static function (array $options, string $key) use ($ctx): array {
    $current = admin_field_value($ctx['old'], $ctx['record'], $key, '');
    if ($current !== '' && !array_key_exists($current, $options)) {
        $options[$current] = $current;
    }
    return $options;
};

/** Like $withCurrent, but for a multi-select: merge every stored value. */
$withCurrentMulti = static function (array $options, string $key) use ($ctx): array {
    if (array_key_exists($key, $ctx['old'])) {
        $raw = $ctx['old'][$key];
    } else {
        $raw = $ctx['record'][$key] ?? '';
    }
    $values = is_array($raw)
        ? $raw
        : (($raw === '' || $raw === 'null') ? [] : explode(',', (string) $raw));
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '' && !array_key_exists($value, $options)) {
            $options[$value] = $value;
        }
    }
    return $options;
};

/** Option map without the leading "" placeholder, for multi-selects. */
$multi = static function (array $options): array {
    unset($options['']);
    return $options;
};

// Values that must never silently disappear when the record omits them.
if ($ctx['record'] !== null) {
    $ctx['record']['marital_status'] = $ctx['record']['marital_status'] ?? 'never_married';
    $ctx['record']['created_by'] = $ctx['record']['created_by'] ?? 'self';
}

$errorCount = count($ctx['errors']);

/**
 * Checkbox with a hidden companion so an unchecked box still submits "0".
 * PHP takes the last value, so a checked box wins and an unchecked one posts 0.
 */
$checkField = static function (string $key, string $label, array $opts = []) use ($ctx): string {
    return '<input type="hidden" name="' . e($key) . '" value="0">'
        . admin_input_check($key, $label, $ctx, $opts);
};
?>

<?php if ($errorCount > 0): ?>
    <div class="alert alert-danger" role="alert">
        <?= admin_icon('alert-circle', 18) ?>
        <div class="admin-flash__body">
            <strong><?= $errorCount ?> field<?= $errorCount === 1 ? '' : 's' ?> need<?= $errorCount === 1 ? 's' : '' ?> attention.</strong>
            Nothing was saved — fix the highlighted fields below and submit again.
        </div>
    </div>
<?php endif; ?>

<form method="POST"
      enctype="multipart/form-data"
      action="<?= $isEdit ? '/api/admin/users/update' : '/api/admin/users/create' ?>"
      data-confirm-title="<?= $isEdit ? 'Save user' : 'Create user' ?>"
      <?php if (!$isEdit): ?>data-confirm="Create this account and send the member their welcome notification?"<?php endif; ?>>
    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="user_id" value="<?= (int) $subjectId ?>">
        <input type="hidden" name="return_to" value="/admin/users/<?= (int) $subjectId ?>">
    <?php else: ?>
        <input type="hidden" name="return_to" value="/admin/users/create">
    <?php endif; ?>

    <div class="admin-formgrid">

        <?php
        echo admin_form_section(
            'Account',
            'Sign-in credentials and access level.',
            [
                admin_input('email', 'Email address', $ctx, [
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'member@example.com',
                    'width' => 'admin-col-6',
                    'hint' => 'Used to sign in. Must be unique across all accounts.',
                ]),
                admin_input('phone', 'Phone number', $ctx, [
                    'type' => 'tel',
                    'placeholder' => '+91 98765 43210',
                    'width' => 'admin-col-6',
                    'hint' => '7–15 digits. Indian mobiles are accepted with or without 91.',
                ]),
                admin_input('password', 'Password', $ctx, [
                    'type' => 'password',
                    'required' => !$isEdit,
                    'width' => 'admin-col-6',
                    'placeholder' => $isEdit ? 'Leave blank to keep the current password' : '',
                    'hint' => 'Minimum 10 characters with an uppercase letter, a lowercase letter and a number.'
                        . ($isEdit ? ' Changing it revokes every active session.' : ''),
                    'autocomplete' => 'new-password',
                ]),
                admin_input_select('role', 'Role', $roleOptions, $ctx, [
                    'width' => 'admin-col-6',
                    'disabled' => $isSelf,
                    'hint' => $isSuper
                        ? 'User = matchmaking only. Moderator = profiles and reports. Admin and Super Admin grant privileged access.'
                        : 'User = matchmaking only. Moderator = profiles and reports. Only a Super Admin can grant admin access.',
                ]),
                admin_input_select('account_status', 'Account status', $statusOptions, $ctx, [
                    'width' => 'admin-col-6',
                    'disabled' => $isSelf,
                    'hint' => 'Anything other than Active revokes the member\'s sessions immediately.',
                ]),
                '<div class="admin-field admin-col-6 admin-field--check-wrap">'
                    . admin_input_check('is_verified', 'Email address is verified', $ctx, [
                        'hint' => 'Unverified accounts stay blocked at sign-in.',
                    ])
                    . '</div>',
            ],
            'id-card'
        );

        echo admin_form_section(
            'Identity',
            'Legal name and demographics. These appear on the public profile.',
            [
                admin_input('first_name', 'First name', $ctx, [
                    'required' => true,
                    'width' => 'admin-col-6',
                    'max' => 80,
                ]),
                admin_input('last_name', 'Last name', $ctx, [
                    'width' => 'admin-col-6',
                    'hint' => 'Defaults to the first name when left blank.',
                ]),
                admin_input_select('gender', 'Gender', [
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ], $ctx, ['required' => true, 'width' => 'admin-col-6']),
                admin_input('date_of_birth', 'Date of birth', $ctx, [
                    'type' => 'date',
                    'required' => true,
                    'width' => 'admin-col-6',
                    'hint' => 'Members must be 18 or older.',
                    'max' => date('Y-m-d', strtotime('-18 years')),
                ]),
                admin_input_select('marital_status', 'Marital status', [
                    'never_married' => 'Never married',
                    'divorced' => 'Divorced',
                    'widowed' => 'Widowed',
                    'awaiting_divorce' => 'Awaiting divorce',
                ], $ctx, ['width' => 'admin-col-6']),
                admin_input_select('has_children', 'Children', [
                    '' => 'Not specified',
                    'no' => 'No children',
                    'yes' => 'Has children',
                ], $ctx, ['width' => 'admin-col-6']),
                admin_input_select('created_by', 'Profile created by', [
                    'self' => 'Self',
                    'parent' => 'Parent',
                    'guardian' => 'Guardian',
                    'sibling' => 'Sibling',
                    'friend' => 'Friend',
                ], $ctx, ['width' => 'admin-col-6']),
                admin_input('created_by_name', 'Created by — name', $ctx, [
                    'width' => 'admin-col-6',
                    'placeholder' => 'e.g. Rajalakshmi (parent)',
                ]),
            ],
            'user'
        );

        echo admin_form_section(
            'Lifestyle',
            'Optional background used for matching.',
            [
                admin_input_select('religion', 'Religion', $withCurrent($religionOptions, 'religion'), $ctx, ['width' => 'admin-col-4']),
                admin_input('caste', 'Caste', $ctx, ['width' => 'admin-col-4']),
                admin_input('sub_caste', 'Sub-caste', $ctx, ['width' => 'admin-col-4']),
                admin_input_select('mother_tongue', 'Mother tongue', $withCurrent($motherTongueOptions, 'mother_tongue'), $ctx, ['width' => 'admin-col-4']),
                admin_input('education', 'Education', $ctx, ['width' => 'admin-col-4']),
                admin_input('institution', 'Institution', $ctx, ['width' => 'admin-col-4']),
                admin_input('occupation', 'Occupation', $ctx, ['width' => 'admin-col-4']),
                admin_input('company', 'Company', $ctx, ['width' => 'admin-col-4']),
                admin_input_select('annual_income', 'Annual income', $withCurrent($incomeOptions, 'annual_income'), $ctx, ['width' => 'admin-col-4']),
                admin_input('height_cm', 'Height (cm)', $ctx, [
                    'type' => 'number', 'min' => 90, 'max' => 250, 'width' => 'admin-col-6',
                ]),
                admin_input('weight_kg', 'Weight (kg)', $ctx, [
                    'type' => 'number', 'min' => 25, 'max' => 250, 'width' => 'admin-col-6',
                ]),
            ],
            'activity'
        );

        echo admin_form_section(
            'Habits & appearance',
            'Lifestyle habits and physical description used in matching.',
            [
                admin_input_select('diet', 'Diet', $withCurrent($dietOptions, 'diet'), $ctx, ['width' => 'admin-col-4']),
                admin_input_select('smoke', 'Smoking', $withCurrent($smokeOptions, 'smoke'), $ctx, ['width' => 'admin-col-4']),
                admin_input_select('drink', 'Drinking', $withCurrent($drinkOptions, 'drink'), $ctx, ['width' => 'admin-col-4']),
                admin_input_select('body_type', 'Body type', $withCurrent($bodyTypeOptions, 'body_type'), $ctx, ['width' => 'admin-col-4']),
                admin_input_select('complexion', 'Complexion', $withCurrent($complexionOptions, 'complexion'), $ctx, ['width' => 'admin-col-4']),
                admin_input('languages_known', 'Languages known', $ctx, [
                    'width' => 'admin-col-4',
                    'placeholder' => 'e.g. Hindi, English, Tamil',
                    'hint' => 'Comma separated.',
                ]),
                admin_input_textarea('hobbies', 'Hobbies', $ctx, [
                    'rows' => 3, 'max' => 1000, 'width' => 'admin-col-6',
                    'placeholder' => 'Reading, trekking, music…',
                ]),
                admin_input_textarea('interests', 'Interests', $ctx, [
                    'rows' => 3, 'max' => 1000, 'width' => 'admin-col-6',
                    'placeholder' => 'Travel, cooking, cricket…',
                ]),
            ],
            'activity'
        );

        echo admin_form_section(
            'Family',
            'Parents, siblings and family background.',
            [
                admin_input('father_name', 'Father\'s name', $ctx, ['width' => 'admin-col-4']),
                admin_input('father_occupation', 'Father\'s occupation', $ctx, ['width' => 'admin-col-4']),
                admin_input('mother_name', 'Mother\'s name', $ctx, ['width' => 'admin-col-4']),
                admin_input('mother_occupation', 'Mother\'s occupation', $ctx, ['width' => 'admin-col-4']),
                admin_input('brothers_count', 'Brothers', $ctx, [
                    'type' => 'number', 'min' => 0, 'max' => 50, 'width' => 'admin-col-4',
                ]),
                admin_input('sisters_count', 'Sisters', $ctx, [
                    'type' => 'number', 'min' => 0, 'max' => 50, 'width' => 'admin-col-4',
                ]),
                admin_input('siblings', 'Siblings note', $ctx, [
                    'width' => 'admin-col-4', 'placeholder' => 'e.g. 1 elder brother',
                ]),
                admin_input_select('family_type', 'Family type', [
                    '' => 'Not specified',
                    'joint' => 'Joint family',
                    'nuclear' => 'Nuclear family',
                ], $ctx, ['width' => 'admin-col-4']),
                admin_input_select('family_values', 'Family values', [
                    '' => 'Not specified',
                    'traditional' => 'Traditional',
                    'moderate' => 'Moderate',
                    'liberal' => 'Liberal',
                ], $ctx, ['width' => 'admin-col-4']),
                admin_input('family_income', 'Family income', $ctx, [
                    'width' => 'admin-col-4', 'placeholder' => 'e.g. 15,00,000',
                ]),
                admin_input('family_origin', 'Family origin', $ctx, [
                    'width' => 'admin-col-8', 'placeholder' => 'Native place / ancestral town',
                ]),
                admin_input_textarea('about_family', 'About the family', $ctx, [
                    'rows' => 3, 'max' => 2000, 'width' => 'admin-col-12',
                ]),
            ],
            'users'
        );

        echo admin_form_section(
            'Horoscope',
            'Birth details used for horoscope compatibility.',
            [
                admin_input_select('rashi', 'Rashi (moon sign)', $withCurrent($rashiOptions, 'rashi'), $ctx, ['width' => 'admin-col-4']),
                admin_input_select('nakshatra', 'Nakshatra', $withCurrent($nakshatraOptions, 'nakshatra'), $ctx, ['width' => 'admin-col-4']),
                admin_input('time_of_birth', 'Time of birth', $ctx, [
                    'type' => 'time', 'width' => 'admin-col-4', 'hint' => '24-hour HH:MM.',
                ]),
                admin_input('place_of_birth', 'Place of birth', $ctx, [
                    'width' => 'admin-col-12', 'placeholder' => 'City / town',
                ]),
                '<div class="admin-field admin-col-12">'
                    . '<label class="form-label" for="f_kattam">Kattam (horoscope chart)</label>'
                    . '<input class="form-control' . admin_invalid($ctx['errors'], 'kattam') . '" type="file"'
                    . ' id="f_kattam" name="kattam" accept="image/jpeg,image/png,image/webp,image/gif">'
                    . '<div class="form-text">Optional JPG, PNG, WebP or GIF up to 5 MB. Recommended size: 800 × 800 px (square).'
                    . ($isEdit && $hasKattam ? ' Uploading replaces the current chart.' : '') . '</div>'
                    . admin_field_error($ctx['errors'], 'kattam')
                    . ($hasKattam
                        ? '<div class="admin-kattam">'
                            . '<img src="' . e($kattamUrl) . '" alt="Current horoscope chart">'
                            . '<label class="admin-kattam__remove">'
                            . '<input type="checkbox" name="remove_kattam" value="1"> Remove current chart</label>'
                            . '</div>'
                        : '')
                    . '</div>',
            ],
            'moon'
        );

        echo admin_form_section(
            'Location',
            'Where the member lives and works.',
            [
                admin_input('city', 'City', $ctx, ['width' => 'admin-col-4']),
                admin_input('state', 'State', $ctx, ['width' => 'admin-col-4']),
                admin_input('country', 'Country', $ctx, [
                    'width' => 'admin-col-4',
                    'default' => 'India',
                ]),
                admin_input_select('residency_status', 'Residency status', $withCurrent($residencyOptions, 'residency_status'), $ctx, ['width' => 'admin-col-4']),
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('willing_to_relocate', 'Willing to relocate')
                    . '</div>',
                admin_input('work_location', 'Work location', $ctx, [
                    'width' => 'admin-col-4',
                    'placeholder' => 'City or "Work from home"',
                ]),
                admin_input('latitude', 'Latitude', $ctx, [
                    'type' => 'number', 'step' => '0.0000001', 'min' => -90, 'max' => 90,
                    'width' => 'admin-col-6',
                ]),
                admin_input('longitude', 'Longitude', $ctx, [
                    'type' => 'number', 'step' => '0.0000001', 'min' => -180, 'max' => 180,
                    'width' => 'admin-col-6',
                ]),
                admin_input_textarea('about_me', 'About me', $ctx, [
                    'rows' => 5,
                    'max' => 2000,
                    'width' => 'admin-col-12',
                    'placeholder' => 'A short introduction shown on the public profile.',
                ]),
            ],
            'id-card'
        );

        echo admin_form_section(
            'Partner preferences',
            'What the member is looking for. Open a list to tick every acceptable value; leave a list empty for no preference.',
            [
                admin_input('min_age', 'Min age', $ctx, [
                    'type' => 'number', 'min' => 18, 'max' => 100, 'width' => 'admin-col-6',
                ]),
                admin_input('max_age', 'Max age', $ctx, [
                    'type' => 'number', 'min' => 18, 'max' => 100, 'width' => 'admin-col-6',
                ]),
                admin_input('min_height_cm', 'Min height (cm)', $ctx, [
                    'type' => 'number', 'min' => 50, 'max' => 280, 'width' => 'admin-col-6',
                ]),
                admin_input('max_height_cm', 'Max height (cm)', $ctx, [
                    'type' => 'number', 'min' => 50, 'max' => 280, 'width' => 'admin-col-6',
                ]),
                admin_input_select('pref_income_min', 'Minimum income', $withCurrent($prefIncomeOptions, 'pref_income_min'), $ctx, ['width' => 'admin-col-6']),
                admin_input_multiselect('pref_marital_status', 'Preferred marital status', $withCurrentMulti($maritalPrefOptions, 'pref_marital_status'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
                admin_input_multiselect('pref_religion', 'Preferred religion', $withCurrentMulti($multi($religionOptions), 'pref_religion'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
                admin_input('pref_caste', 'Preferred caste', $ctx, [
                    'width' => 'admin-col-6', 'placeholder' => 'e.g. Brahmin, Nair',
                ]),
                admin_input_multiselect('pref_mother_tongue', 'Preferred mother tongue', $withCurrentMulti($multi($motherTongueOptions), 'pref_mother_tongue'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
                admin_input_multiselect('pref_education', 'Preferred education', $withCurrentMulti($multi($educationOptions), 'pref_education'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
                admin_input_multiselect('pref_occupation', 'Preferred occupation', $withCurrentMulti($multi($occupationOptions), 'pref_occupation'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
                admin_input('pref_location', 'Preferred location', $ctx, [
                    'width' => 'admin-col-6', 'placeholder' => 'e.g. Bengaluru, Dubai',
                ]),
                admin_input_multiselect('pref_diet', 'Preferred diet', $withCurrentMulti($multi($dietOptions), 'pref_diet'), $ctx, [
                    'width' => 'admin-col-6',
                ]),
            ],
            'search'
        );

        echo admin_form_section(
            'Privacy',
            'Who can see the member\'s details. Defaults follow the standard member preset.',
            [
                admin_input_select('privacy_preset', 'Preset', [
                    'members' => 'Members — contact hidden (default)',
                    'public' => 'Public — everything visible',
                    'private' => 'Private — hidden from search',
                ], $ctx, ['width' => 'admin-col-4', 'hint' => 'Choose a starting point, then fine-tune below.']),
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('profile_visibility', 'Profile visible in search', ['default' => true])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('show_photos', 'Show photos', ['default' => true])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('show_phone', 'Show phone number', ['default' => false])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('show_email', 'Show email address', ['default' => false])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('show_online_status', 'Show online status', ['default' => true])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('receive_interests', 'Receive interests', ['default' => true])
                    . '</div>',
                '<div class="admin-field admin-col-4 admin-field--check-wrap">'
                    . $checkField('allow_contact_reveal', 'Allow contact reveal', ['default' => true])
                    . '</div>',
            ],
            'shield'
        );

        $primaryPhoto = (string) ($ctx['record']['primary_photo'] ?? '');
        echo admin_form_section(
            'Photos',
            $isEdit
                ? 'Uploading adds a new photo to the member\'s gallery.'
                : 'Optional — the uploaded photo becomes the primary photo.',
            [
                '<div class="admin-field admin-col-12">'
                    . '<label class="form-label" for="f_photo">Profile photo</label>'
                    . '<input class="form-control' . admin_invalid($ctx['errors'], 'photo') . '"'
                    . ' type="file" id="f_photo" name="photo"'
                    . ' accept="image/jpeg,image/png,image/webp,image/gif">'
                    . '<div class="form-text">JPG, PNG, WebP or GIF up to 5 MB. Recommended size: 800 × 1000 px (4:5 portrait).</div>'
                    . admin_field_error($ctx['errors'], 'photo')
                    . '</div>'
                    . ($primaryPhoto !== ''
                        ? '<div class="admin-field admin-col-12"><label class="form-label">Current primary photo</label>'
                            . '<img src="' . e($primaryPhoto) . '" alt="Current primary photo"'
                            . ' style="max-height:140px;border-radius:10px;border:1px solid rgba(0,0,0,.1)"></div>'
                        : ''),
            ],
            'user'
        );
        ?>
    </div>

    <div class="admin-formactions">
        <div class="admin-formactions__note">
            <?= admin_icon('shield', 15) ?>
            <?php if ($isEdit): ?>
                Saving writes every changed field to the audit log with its before and after value.
            <?php else: ?>
                The member is notified and queued a welcome email the moment the account is created.
            <?php endif; ?>
        </div>
        <div class="admin-formactions__buttons">
            <a class="btn btn-secondary" href="<?= $isEdit ? '/admin/users/' . (int) $subjectId : '/admin/users' ?>">Cancel</a>
            <button class="btn btn-primary" type="submit">
                <?= admin_icon($isEdit ? 'check-circle' : 'plus', 15) ?>
                <?= $isEdit ? 'Save changes' : 'Create user' ?>
            </button>
        </div>
    </div>
</form>

<?php if ($isEdit): ?>
    <?php
    $deleted = (string) ($ctx['record']['account_status'] ?? '') === 'deleted';
    $canDelete = !$isSelf && in_array((string) ($ctx['record']['role'] ?? 'user'), \Matrimony\Services\AdminUserService::PRIVILEGED_ROLES, true) === false;
    ?>
    <div class="admin-dangerzone">
        <div class="admin-dangerzone__head">
            <h2 class="admin-panel__title">Danger zone</h2>
            <p class="admin-panel__sub">These actions are audited and, for privileged accounts, restricted to Super Admins.</p>
        </div>

        <div class="admin-dangerzone__body">
            <?php if ($deleted): ?>
                <form method="POST" action="/api/admin/users/restore"
                      data-confirm="Restore this account? The member will be able to sign in again."
                      data-confirm-title="Restore account" data-confirm-text="Restore">
                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $subjectId ?>">
                    <input type="hidden" name="return_to" value="/admin/users/<?= (int) $subjectId ?>">
                    <div>
                        <strong>This account is deleted.</strong>
                        <div class="form-text">Restoring sets the status back to Active. The profile stays hidden until it is re-approved.</div>
                    </div>
                    <button class="btn btn-success" type="submit"><?= admin_icon('refresh', 14) ?> Restore account</button>
                </form>
            <?php elseif ($canDelete): ?>
                <form method="POST" action="/api/admin/users/delete"
                      data-confirm="Delete this account? Every active session is revoked, the profile is hidden and active memberships are suspended. The record is kept for audit and financial history."
                      data-confirm-title="Delete this account" data-confirm-danger data-confirm-text="Delete account">
                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $subjectId ?>">
                    <input type="hidden" name="return_to" value="/admin/users/<?= (int) $subjectId ?>">
                    <div class="admin-field">
                        <label class="form-label" for="deleteReason">Deletion reason <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-control" id="deleteReason" name="reason" required maxlength="255"
                               placeholder="Recorded in the audit log">
                        <div class="form-text">Required. Stored with the deletion audit entry.</div>
                    </div>
                    <button class="btn btn-danger" type="submit"><?= admin_icon('ban', 14) ?> Delete account</button>
                </form>
            <?php else: ?>
                <div class="admin-dangerzone__blocked">
                    <?= admin_icon('lock', 16) ?>
                    <div>
                        <strong>Deletion unavailable.</strong>
                        <div class="form-text">
                            <?= $isSelf
                                ? 'You cannot delete your own account.'
                                : 'This is a privileged account — a Super Admin must delete it.' ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="admin-dangerzone__actions">
                <h3 class="admin-dangerzone__title">Reset password</h3>
                <p class="admin-panel__sub">Sets a new password immediately and revokes every session.</p>
                <form method="POST" action="/api/admin/users/password" class="admin-rowform admin-rowform--left"
                      data-confirm="Set a new password for this account and sign them out everywhere?"
                      data-confirm-title="Reset password" data-confirm-danger data-confirm-text="Reset password">
                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $subjectId ?>">
                    <input type="hidden" name="return_to" value="/admin/users/<?= (int) $subjectId ?>">
                    <div class="admin-field">
                        <label class="admin-visually-hidden" for="newPassword">New password</label>
                        <div class="input-group">
                        <input class="form-control form-control-sm" id="newPassword" type="password"
                               name="password" required minlength="10" style="width:15rem"
                               placeholder="New password (10+ characters)"
                               aria-label="New password">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-pw-toggle aria-label="Show password">
                            <svg class="pw-icon pw-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="pw-icon pw-eye-off d-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="mustChange" name="must_change" value="1" checked>
                        <label class="form-check-label" for="mustChange">Force change at next sign-in</label>
                    </div>
                    <button class="btn btn-danger btn-sm" type="submit"><?= admin_icon('lock', 13) ?> Reset</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
