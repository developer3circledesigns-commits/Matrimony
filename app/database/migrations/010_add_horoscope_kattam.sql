-- Horoscope chart ("kattam") image uploaded from the admin user form.
ALTER TABLE profile_horoscope ADD COLUMN kattam_data MEDIUMBLOB NULL;

ALTER TABLE profile_horoscope ADD COLUMN kattam_mime VARCHAR(60) NULL;

ALTER TABLE profile_horoscope ADD COLUMN kattam_updated_at DATETIME NULL;
