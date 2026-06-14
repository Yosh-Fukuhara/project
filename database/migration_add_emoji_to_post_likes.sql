-- Add emoji column to post_likes table
USE cybersphere;

-- Add emoji column with default thumbs up
ALTER TABLE post_likes
ADD COLUMN IF NOT EXISTS emoji VARCHAR(10) NOT NULL DEFAULT '👍';

-- Verify
DESCRIBE post_likes;
