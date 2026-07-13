-- ============================================================================
-- Test Data Fixtures
-- Loaded by TestDatabase::loadFixtures() for integration and feature tests.
-- ============================================================================

-- Users
INSERT INTO users (id, email, password_hash, is_active, is_verified, last_login_at, created_at) VALUES
(1, 'demo@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 1, 1, NOW(), '2025-06-01 10:00:00'),
(2, 'alice@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 1, 1, NOW(), '2025-06-02 10:00:00'),
(3, 'bob@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 1, 0, NOW() - INTERVAL 10 DAY, '2025-06-03 10:00:00'),
(4, 'carol@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 1, 1, NOW() - INTERVAL 2 DAY, '2025-06-04 10:00:00'),
(5, 'dave@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 0, 0, NULL, '2025-06-05 10:00:00'),
(6, 'eve@matrimony.local', '$2y$10$Fgc/q7rVNj1jS15dBeIExe8GGGYkZdzN19Eb4OTIWPKFLYF08/Qb.', 1, 1, NOW() - INTERVAL 1 HOUR, '2025-06-06 10:00:00');

-- Profiles
INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth, marital_status, religion, caste, mother_tongue, height_cm, education, occupation, annual_income, city, state, country, about_me, created_at) VALUES
(1, 'Raj', 'Sharma', 'male', '1990-06-15', 'never_married', 'Hindu', 'Brahmin', 'Hindi', 175, 'Masters in Engineering', 'Software Engineer', '₹ 12,00,000', 'Mumbai', 'Maharashtra', 'India', 'I am a software engineer from Mumbai.', '2025-06-01 10:00:00'),
(2, 'Alice', 'Singh', 'female', '1995-03-20', 'never_married', 'Hindu', 'Kshatriya', 'Hindi', 162, 'MBA', 'Marketing Manager', '₹ 8,00,000', 'Mumbai', 'Maharashtra', 'India', 'Marketing professional who loves travelling.', '2025-06-02 10:00:00'),
(3, 'Bob', 'Verma', 'male', '1992-11-10', 'never_married', 'Hindu', 'Vaishya', 'Hindi', 180, 'B.Tech', 'Product Manager', '₹ 15,00,000', 'Delhi', 'Delhi', 'India', 'Product manager at a startup.', '2025-06-03 10:00:00'),
(4, 'Carol', 'Patel', 'female', '1998-05-25', 'never_married', 'Hindu', 'Patel', 'Gujarati', 158, 'B.Com', 'Accountant', '₹ 5,00,000', 'Ahmedabad', 'Gujarat', 'India', 'Chartered accountant in practice.', '2025-06-04 10:00:00'),
(5, 'Dave', 'Kumar', 'male', '1988-01-15', 'divorced', 'Sikh', 'Jatt', 'Punjabi', 170, 'PhD', 'Professor', '₹ 10,00,000', 'Amritsar', 'Punjab', 'India', 'Professor at a university.', '2025-06-05 10:00:00'),
(6, 'Eve', 'Nair', 'female', '1993-12-01', 'never_married', 'Hindu', 'Nair', 'Malayalam', 165, 'MBBS', 'Doctor', '₹ 18,00,000', 'Kochi', 'Kerala', 'India', 'Doctor at a hospital.', '2025-06-06 10:00:00');

-- Profile Photos
INSERT INTO profile_photos (id, user_id, path, is_primary, privacy_level, status) VALUES
(1, 1, '/uploads/1/photo_1.jpg', 1, 'public', 'approved'),
(2, 1, '/uploads/1/photo_2.jpg', 0, 'public', 'approved'),
(3, 2, '/uploads/2/photo_1.jpg', 1, 'public', 'approved'),
(4, 3, '/uploads/3/photo_1.jpg', 1, 'public', 'approved'),
(5, 4, '/uploads/4/photo_1.jpg', 1, 'public', 'approved'),
(6, 6, '/uploads/6/photo_1.jpg', 1, 'public', 'approved');

-- Profile Assets (lifestyle)
INSERT INTO profile_assets (user_id, diet, smoke, drink, body_type, complexion, languages_known, hobbies) VALUES
(1, 'vegetarian', 'no', 'no', 'athletic', 'fair', 'Hindi,English', 'Reading,Travel'),
(2, 'vegetarian', 'no', 'social', 'slim', 'fair', 'Hindi,English', 'Music,Dancing'),
(3, 'non-vegetarian', 'no', 'yes', 'average', 'wheatish', 'Hindi,English', 'Sports,Travel'),
(4, 'vegetarian', 'no', 'no', 'slim', 'fair', 'Gujarati,Hindi,English', 'Cooking,Reading'),
(5, 'non-vegetarian', 'yes', 'yes', 'heavy', 'dark', 'Punjabi,Hindi,English', 'Farming,Reading'),
(6, 'vegetarian', 'no', 'no', 'slim', 'fair', 'Malayalam,English,Hindi', 'Yoga,Reading');

-- Profile Family
INSERT INTO profile_family (user_id, father_name, father_occupation, mother_name, mother_occupation, brothers_count, sisters_count, family_type, family_values, family_income) VALUES
(1, 'Mr. Ravi Sharma', 'Business', 'Mrs. Sunita Sharma', 'Homemaker', 1, 1, 'nuclear', 'liberal', '₹ 20,00,000'),
(2, 'Mr. Raj Singh', 'Government Service', 'Mrs. Priya Singh', 'Teacher', 0, 1, 'nuclear', 'traditional', '₹ 15,00,000'),
(3, 'Mr. Vinod Verma', 'Business', 'Mrs. Asha Verma', 'Homemaker', 2, 0, 'joint', 'traditional', '₹ 30,00,000'),
(4, 'Mr. Rakesh Patel', 'Business', 'Mrs. Geeta Patel', 'Homemaker', 0, 0, 'nuclear', 'liberal', '₹ 12,00,000'),
(5, 'Mr. Gurpreet Kumar', 'Farmer', 'Mrs. Jaspreet Kaur', 'Homemaker', 1, 2, 'joint', 'traditional', '₹ 8,00,000'),
(6, 'Mr. Suresh Nair', 'Engineer', 'Mrs. Latha Nair', 'Doctor', 1, 0, 'nuclear', 'liberal', '₹ 25,00,000');

-- Profile Horoscope
INSERT INTO profile_horoscope (user_id, rashi, nakshatra, time_of_birth, place_of_birth) VALUES
(1, 'Mesh', 'Ashwini', '1990-06-15 08:30:00', 'Mumbai'),
(2, 'Vrishabha', 'Rohini', '1995-03-20 14:00:00', 'Mumbai'),
(3, 'Mithuna', 'Mrigashira', '1992-11-10 05:15:00', 'Delhi'),
(4, 'Karka', 'Pushya', '1998-05-25 10:45:00', 'Ahmedabad'),
(5, 'Simha', 'Magha', '1988-01-15 22:30:00', 'Amritsar'),
(6, 'Kanya', 'Uttara', '1993-12-01 03:00:00', 'Kochi');

-- Profile Lifestyle
INSERT INTO profile_lifestyle (user_id, latitude, longitude, willing_to_relocate, residency_status) VALUES
(1, 19.0760, 72.8777, 1, 'citizen'),
(2, 19.0760, 72.8777, 1, 'citizen'),
(3, 28.7041, 77.1025, 0, 'citizen'),
(4, 23.0225, 72.5714, 1, 'citizen'),
(5, 31.6340, 74.8723, 0, 'citizen'),
(6, 9.9312, 76.2673, 1, 'citizen');

-- Profile Preferences
INSERT INTO profile_preferences (user_id, min_age, max_age, min_height_cm, max_height_cm, pref_religion, pref_caste, pref_education, pref_location, pref_marital_status, pref_mother_tongue, pref_diet) VALUES
(1, 24, 32, 150, 175, '["Hindu"]', '["Brahmin","Kshatriya","Vaishya"]', '["Graduate","Masters","PhD"]', '["Mumbai","Maharashtra"]', '["never_married"]', '["Hindi","Marathi"]', '["vegetarian"]'),
(2, 26, 35, 165, 190, '["Hindu"]', '[]', '["Graduate","Masters"]', '["Mumbai","Maharashtra","Delhi"]', '["never_married","divorced"]', '["Hindi","English"]', '["vegetarian"]'),
(3, 24, 32, 150, 175, '["Hindu","Sikh"]', '["Vaishya"]', '["Graduate","Masters"]', '["Delhi","Mumbai"]', '["never_married"]', '["Hindi","Punjabi"]', '["vegetarian","non-vegetarian"]'),
(4, 25, 35, 165, 185, '["Hindu"]', '[]', '["Graduate","Masters"]', '["Gujarat","Maharashtra"]', '["never_married","divorced"]', '["Gujarati","Hindi"]', '["vegetarian"]'),
(6, 26, 35, 160, 185, '["Hindu","Christian"]', '[]', '["Graduate","Masters","MBBS"]', '["Kerala","Tamil Nadu"]', '["never_married"]', '["Malayalam","English"]', '["vegetarian"]');

-- Privacy Settings
INSERT INTO privacy_settings (user_id, profile_visibility, show_phone, show_email, show_photos, show_online_status, receive_interests, privacy_preset) VALUES
(1, 1, 1, 0, 1, 1, 1, 'members'),
(2, 1, 0, 0, 1, 1, 1, 'members'),
(3, 1, 0, 0, 1, 1, 1, 'members'),
(4, 1, 0, 0, 1, 1, 1, 'members'),
(5, 0, 0, 0, 0, 0, 1, 'private'),
(6, 1, 0, 0, 1, 1, 1, 'members');

-- Matches (interests, shortlists)
INSERT INTO matches (user_id, target_id, status, created_at) VALUES
(1, 2, 'mutual', '2025-07-01 10:00:00'),
(2, 1, 'mutual', '2025-07-02 10:00:00'),  -- Mutual between 1 and 2
(1, 3, 'shortlisted', '2025-07-03 10:00:00'),
(3, 1, 'interested', '2025-07-03 12:00:00'),
(4, 1, 'interested', '2025-07-04 10:00:00'),
(1, 5, 'declined', '2025-07-05 10:00:00');

-- Profile Views
INSERT INTO profile_views (viewer_id, profile_id, viewed_at) VALUES
(2, 1, NOW() - INTERVAL 1 DAY),
(3, 1, NOW() - INTERVAL 2 DAY),
(4, 1, NOW() - INTERVAL 3 DAY),
(6, 1, NOW() - INTERVAL 5 HOUR),
(1, 2, NOW() - INTERVAL 1 DAY),
(1, 3, NOW() - INTERVAL 2 DAY),
(1, 4, NOW() - INTERVAL 3 DAY),
(1, 6, NOW() - INTERVAL 4 DAY);

-- Match Scores
INSERT INTO match_scores (viewer_id, target_id, score, computed_at) VALUES
(1, 2, 85, NOW()),
(1, 3, 62, NOW()),
(1, 4, 45, NOW()),
(1, 6, 71, NOW()),
(2, 1, 88, NOW()),
(2, 3, 54, NOW());

-- Profile Verifications
INSERT INTO profile_verifications (user_id, type, status) VALUES
(1, 'id', 'verified'),
(1, 'phone', 'verified'),
(2, 'id', 'verified'),
(3, 'id', 'verified'),
(6, 'id', 'verified');

-- Membership Plans
INSERT IGNORE INTO membership_plans (id, code, name, duration_days, price_cents, features, is_active) VALUES
(1, 'FREE', 'Free', 36500, 0, 'Basic membership', 1),
(2, 'SILVER', 'Silver', 30, 99900, 'Silver membership with messaging', 1),
(3, 'GOLD', 'Gold', 30, 199900, 'Gold membership with contact visibility', 1),
(4, 'PLATINUM', 'Platinum', 30, 499900, 'Platinum membership with all features', 1);

-- Memberships
INSERT INTO memberships (user_id, plan_id, starts_at, ends_at, status) VALUES
(1, 3, '2025-06-01 00:00:00', '2026-06-01 00:00:00', 'active'),  -- Gold
(2, 1, '2025-06-01 00:00:00', '2099-12-31 00:00:00', 'active'),  -- Free
(3, 2, '2025-06-01 00:00:00', '2025-07-01 00:00:00', 'expired'), -- Expired Silver
(4, 1, '2025-06-01 00:00:00', '2099-12-31 00:00:00', 'active'),  -- Free
(6, 4, '2025-06-01 00:00:00', '2026-06-01 00:00:00', 'active');  -- Platinum

-- Profile Hobbies (M2M)
INSERT INTO profile_hobbies (profile_id, hobby) VALUES
(1, 'Reading'), (1, 'Travelling'), (1, 'Cooking'),
(2, 'Music'), (2, 'Dancing'), (2, 'Yoga'),
(3, 'Sports'), (3, 'Gaming'),
(4, 'Cooking'), (4, 'Reading'),
(6, 'Yoga'), (6, 'Reading'), (6, 'Travelling');

-- Activity Log
INSERT INTO activity_log (user_id, action, details, created_at) VALUES
(1, 'profile.updated', '{"section":"personal"}', '2025-07-01 10:00:00'),
(1, 'photo.uploaded', '{"photo_id":1}', '2025-07-01 10:30:00'),
(1, 'profile.updated', '{"section":"family"}', '2025-07-01 11:00:00'),
(2, 'profile.updated', '{"section":"personal"}', '2025-07-01 12:00:00');

-- Notifications
INSERT INTO match_notifications (user_id, type, payload, created_at) VALUES
(1, 'interested', '{"user_id":3}', NOW() - INTERVAL 2 HOUR),
(1, 'interested', '{"user_id":4}', NOW() - INTERVAL 5 HOUR);

-- Profile Blocks
INSERT INTO profile_blocks (blocker_id, blocked_id) VALUES
(2, 5);

-- Profile Reports
INSERT INTO profile_reports (reporter_id, profile_id, reason, created_at) VALUES
(3, 5, 'Fake profile with misleading information', '2025-07-05 10:00:00');

-- Saved Searches
INSERT INTO profile_searches (user_id, name, filters_json, alert_enabled, created_at) VALUES
(1, 'MBA Mumbai 26-30', '{"education":["MBA"],"city":["Mumbai"],"age_min":26,"age_max":30}', 1, '2025-07-01 10:00:00'),
(1, 'Doctor Kerala', '{"occupation":["Doctor"],"state":["Kerala"]}', 0, '2025-07-02 10:00:00');
