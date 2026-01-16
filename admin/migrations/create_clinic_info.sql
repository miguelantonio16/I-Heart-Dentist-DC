CREATE TABLE IF NOT EXISTS clinic_info (
  id INT NOT NULL PRIMARY KEY,
  clinic_name VARCHAR(255) DEFAULT 'Your Clinic Name',
  clinic_description TEXT,
  address TEXT,
  phone VARCHAR(50) DEFAULT '',
  email VARCHAR(255) DEFAULT '',
  facebook_url VARCHAR(255) DEFAULT '',
  instagram_url VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default row with id = 1 if it doesn't exist
INSERT INTO clinic_info (id, clinic_name, clinic_description, address, phone, email, facebook_url, instagram_url)
SELECT 1, 'Your Clinic Name', 'Clinic description goes here.', '', '', '', '', ''
WHERE NOT EXISTS (SELECT 1 FROM clinic_info WHERE id = 1);
