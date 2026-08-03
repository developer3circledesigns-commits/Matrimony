ALTER TABLE profile_photos
  ADD COLUMN image_data MEDIUMBLOB NULL AFTER path,
  ADD COLUMN image_mime VARCHAR(50) NULL AFTER image_data;
