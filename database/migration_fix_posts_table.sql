-- Migration script to fix posts table schema
-- Run this in phpMyAdmin on your cybersphere database

USE cybersphere;

-- Step 1: Migrate existing attachment data from posts to post_attachments (if any)
INSERT IGNORE INTO post_attachments (post_id, file_path, mime_type)
SELECT post_id, attachment_path, attachment_mime
FROM posts
WHERE attachment_path IS NOT NULL AND attachment_path != '';

-- Step 2: Migrate existing job post details from posts to job_post_details (if any)
INSERT IGNORE INTO job_post_details (post_id, is_hiring, enable_apply)
SELECT post_id, is_hiring, enable_apply
FROM posts
WHERE is_hiring IS NOT NULL OR enable_apply IS NOT NULL;

-- Step 3: Drop the extra columns from posts table
ALTER TABLE posts
DROP COLUMN IF EXISTS attachment_path,
DROP COLUMN IF EXISTS attachment_mime,
DROP COLUMN IF EXISTS is_hiring,
DROP COLUMN IF EXISTS enable_apply;

-- Verify the schema is correct
DESCRIBE posts;
DESCRIBE post_attachments;
DESCRIBE job_post_details;
