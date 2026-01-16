-- Seed/Helper script to mark an existing admin as superadmin or create a new one
-- Replace values as needed. If your `admin` table requires more columns, adapt accordingly.

-- Mark existing admin as superadmin (preferred)
-- UPDATE `admin` SET `is_super`=1 WHERE `aemail`='superadmin@example.com';

-- If you need to insert a new superadmin (modify columns to match your schema):
-- INSERT INTO `admin` (`aemail`, `apassword`, `is_super`) VALUES ('superadmin@example.com', 'plaintext_or_hashed_password', 1);

-- Note: Passwords in this project are stored in cleartext in some places; consider hashing and updating code to use password_hash()/password_verify().
