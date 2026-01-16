-- Rollback: remove is_super column from admin table
ALTER TABLE `admin`
  DROP COLUMN `is_super`;
