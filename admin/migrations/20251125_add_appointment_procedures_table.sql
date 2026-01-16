-- Migration: Add appointment_procedures table for stacking multiple procedures per appointment
-- Run this SQL once (e.g., via phpMyAdmin or MySQL CLI)

CREATE TABLE IF NOT EXISTS appointment_procedures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  procedure_id INT NOT NULL,
  agreed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_proc_appointment FOREIGN KEY (appointment_id) REFERENCES appointment(appoid) ON DELETE CASCADE,
  CONSTRAINT fk_appt_proc_procedure FOREIGN KEY (procedure_id) REFERENCES procedures(procedure_id) ON DELETE RESTRICT,
  INDEX idx_appt_proc_appointment (appointment_id),
  INDEX idx_appt_proc_procedure (procedure_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
