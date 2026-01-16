-- Add is_super column to admin table
ALTER TABLE `admin`
  ADD COLUMN `is_super` TINYINT(1) NOT NULL DEFAULT 0;

-- Optional: grant is_super to an existing admin by email (replace example@example.com)
-- UPDATE `admin` SET `is_super`=1 WHERE `aemail`='superadmin@example.com';
