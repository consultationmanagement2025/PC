-- Add assigned_to field to consultations table for resource person assignment
ALTER TABLE consultations 
ADD COLUMN assigned_to INT(11) DEFAULT NULL AFTER admin_id,
ADD KEY idx_consultations_assigned_to (assigned_to),
ADD CONSTRAINT fk_consultations_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;
