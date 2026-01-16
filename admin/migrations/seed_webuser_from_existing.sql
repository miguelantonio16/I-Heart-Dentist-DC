-- Seed `webuser` table from existing patient, doctor, and admin tables.
-- Inserts email/usertype for any existing users that are missing from webuser.

INSERT INTO webuser (email, usertype)
SELECT p.pemail, 'p'
FROM patient p
WHERE p.pemail IS NOT NULL AND p.pemail <> ''
  AND NOT EXISTS (SELECT 1 FROM webuser w WHERE w.email = p.pemail);

INSERT INTO webuser (email, usertype)
SELECT d.docemail, 'd'
FROM doctor d
WHERE d.docemail IS NOT NULL AND d.docemail <> ''
  AND NOT EXISTS (SELECT 1 FROM webuser w WHERE w.email = d.docemail);

INSERT INTO webuser (email, usertype)
SELECT a.aemail, 'a'
FROM admin a
WHERE a.aemail IS NOT NULL AND a.aemail <> ''
  AND NOT EXISTS (SELECT 1 FROM webuser w WHERE w.email = a.aemail);
