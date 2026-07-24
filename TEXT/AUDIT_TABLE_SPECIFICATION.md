# Audit Logs Database Table - Detailed Specification

## Table Definition

```sql
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user VARCHAR(255) NOT NULL,
    admin_id INT,
    action VARCHAR(500) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_value LONGTEXT,
    new_value LONGTEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'success',
    details LONGTEXT,
    INDEX idx_admin_id (admin_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Column Specifications

### id
- **Type:** INT (Auto-increment)
- **Constraint:** PRIMARY KEY
- **Purpose:** Unique identifier for each log entry
- **Example:** 1, 2, 3, ...

### admin_user
- **Type:** VARCHAR(255)
- **Constraint:** NOT NULL
- **Purpose:** Name of the administrator who performed the action
- **Example:** "John Doe", "Jane Smith", "Admin User"
- **Source:** $_SESSION['fullname']

### admin_id
- **Type:** INT
- **Constraint:** None (nullable)
- **Purpose:** User ID of the administrator
- **Example:** 5, 12, 42
- **Source:** $_SESSION['id']

### action
- **Type:** VARCHAR(500)
- **Constraint:** NOT NULL
- **Purpose:** Description of what action was performed
- **Example Values:**
  - "Logged in"
  - "Logged out"
  - "Created User - Email: john@example.com"
  - "Updated User #5 - Changed role from admin to citizen"
  - "Deleted User #5 - Email: john@example.com"
  - "Uploaded Document - Title: Ordinance 2025-001"
  - "Deleted Document #102 - Title: Resolution 2024-050"
  - "Created Public Consultation - Title: City Budget 2025"
  - "Updated Consultation #15 - Changed status to approved"
  - "Deleted Consultation #15"

### entity_type
- **Type:** VARCHAR(100)
- **Constraint:** None (nullable)
- **Purpose:** Type of entity that was affected
- **Allowed Values:**
  - "user" - User account action
  - "document" - Document management action
  - "consultation" - Public consultation action
  - "system" - System-level action (login, logout)
  - "feedback" - Feedback management action
  - NULL - For generic system actions

### entity_id
- **Type:** INT
- **Constraint:** None (nullable)
- **Purpose:** ID number of the affected entity
- **Example:** 5 (for user #5), 102 (for document #102), 15 (for consultation #15)
- **Default:** NULL for system-level actions

### old_value
- **Type:** LONGTEXT
- **Constraint:** None (nullable)
- **Purpose:** Previous value before update (for change tracking)
- **Example Values:**
  - NULL for create/delete actions
  - "admin" for role change
  - '{"status":"draft","date":"2025-01-15"}' for complex updates
  - Previous email address for email changes
- **Format:** Can be JSON string for complex objects

### new_value
- **Type:** LONGTEXT
- **Constraint:** None (nullable)
- **Purpose:** New value after update (for change tracking)
- **Example Values:**
  - NULL for delete actions
  - "citizen" for role change
  - '{"status":"approved","date":"2025-01-20"}' for complex updates
  - New email address for email changes
- **Format:** Can be JSON string for complex objects

### ip_address
- **Type:** VARCHAR(45)
- **Constraint:** None (nullable)
- **Purpose:** IP address from which the action was performed
- **Example Values:**
  - "192.168.1.100" (IPv4)
  - "::1" (IPv6 localhost)
  - "203.0.113.42" (public IP)
  - "2001:0db8:85a3:0000:0000:8a2e:0370:7334" (IPv6)
- **Source:** $_SERVER['REMOTE_ADDR']

### user_agent
- **Type:** TEXT
- **Constraint:** None (nullable)
- **Purpose:** Browser and device information
- **Example Values:**
  - "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
  - "Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15"
  - "Mozilla/5.0 (Linux; Android 13; SM-S911B) AppleWebKit/537.36"
- **Source:** $_SERVER['HTTP_USER_AGENT']

### timestamp
- **Type:** DATETIME
- **Constraint:** DEFAULT CURRENT_TIMESTAMP
- **Purpose:** Date and time when the action occurred
- **Format:** YYYY-MM-DD HH:MM:SS (24-hour format)
- **Example:** "2025-01-20 14:30:45"
- **Timezone:** MySQL server timezone (usually UTC)
- **Automatic:** Yes, server-side timestamp

### status
- **Type:** VARCHAR(50)
- **Constraint:** DEFAULT 'success'
- **Purpose:** Whether the action succeeded or failed
- **Allowed Values:**
  - "success" - Action completed successfully
  - "failure" - Action failed (error occurred)
  - "pending" - Action pending completion
  - Other values for custom status tracking
- **Default:** "success"

### details
- **Type:** LONGTEXT
- **Constraint:** None (nullable)
- **Purpose:** Additional context or notes about the action
- **Example Values:**
  - NULL for simple actions
  - "User account blocked due to multiple failed login attempts"
  - "Document approved by City Councilor Jane Smith"
  - "Error: File upload exceeded size limit (max 50MB)"
  - "Batch operation: 5 documents imported successfully"

## Indexes

### idx_admin_id (admin_id)
- **Purpose:** Fast lookup of logs by administrator
- **Query Example:** Find all actions by John Doe
- **Benefit:** Speeds up filtering by admin user

### idx_timestamp (timestamp)
- **Purpose:** Fast lookup by date/time range
- **Query Example:** Find all actions from Jan 20, 2025
- **Benefit:** Enables efficient date range searches

### idx_action (action)
- **Purpose:** Fast lookup by action type
- **Query Example:** Find all "Deleted" actions
- **Benefit:** Speeds up action filtering

### idx_entity (entity_type, entity_id)
- **Purpose:** Fast lookup of all actions affecting a specific entity
- **Query Example:** Find all actions affecting document #102
- **Benefit:** Composite index for entity lookups

## Sample Data

```
id  | admin_user | admin_id | action                          | entity_type | entity_id | timestamp            | status  | ip_address      | user_agent
────┼────────────┼──────────┼─────────────────────────────────┼─────────────┼───────────┼──────────────────────┼─────────┼─────────────────┼──────────
1   | John Doe   | 5        | Logged in                       | system      | NULL      | 2025-01-20 14:00:00  | success | 192.168.1.100   | Mozilla/5.0...
2   | John Doe   | 5        | Uploaded Document - ORD-2025-01 | document    | 102       | 2025-01-20 14:15:00  | success | 192.168.1.100   | Mozilla/5.0...
3   | Jane Smith | 12       | Created User - john@example.com | user        | 45        | 2025-01-20 14:30:00  | success | 203.0.113.42    | Mozilla/5.0...
4   | John Doe   | 5        | Deleted Document #102           | document    | 102       | 2025-01-20 14:45:00  | success | 192.168.1.100   | Mozilla/5.0...
5   | Admin Usr  | 1        | Deleted User #45                | user        | 45        | 2025-01-20 15:00:00  | success | 203.0.113.50    | Mozilla/5.0...
```

## Typical Query Examples

### Get all actions by a specific admin
```sql
SELECT * FROM audit_logs 
WHERE admin_user = 'John Doe' 
ORDER BY timestamp DESC;
```

### Get all deletions
```sql
SELECT * FROM audit_logs 
WHERE action LIKE '%Deleted%' 
ORDER BY timestamp DESC;
```

### Get all actions affecting documents
```sql
SELECT * FROM audit_logs 
WHERE entity_type = 'document' 
ORDER BY timestamp DESC;
```

### Get actions from specific date
```sql
SELECT * FROM audit_logs 
WHERE DATE(timestamp) = '2025-01-20' 
ORDER BY timestamp DESC;
```

### Get failed actions
```sql
SELECT * FROM audit_logs 
WHERE status = 'failure' 
ORDER BY timestamp DESC;
```

### Get all actions by admin from specific IP
```sql
SELECT * FROM audit_logs 
WHERE admin_id = 5 AND ip_address = '192.168.1.100' 
ORDER BY timestamp DESC;
```

## Maintenance Recommendations

### Archiving
```sql
-- Archive logs older than 1 year
INSERT INTO audit_logs_archive 
SELECT * FROM audit_logs 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 YEAR);

DELETE FROM audit_logs 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Cleanup (if needed)
```sql
-- Remove test/dummy logs
DELETE FROM audit_logs 
WHERE admin_user = 'test' OR admin_user = 'admin_test';
```

### Performance Check
```sql
-- Check index usage
SHOW INDEX FROM audit_logs;

-- Check table size
SELECT table_name, 
       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'pc_db' AND table_name = 'audit_logs';
```

## Constraints and Limits

- **Max action length:** 500 characters (sufficient for detailed descriptions)
- **Max details length:** LONGTEXT (up to 4GB, practically unlimited)
- **Max IP address length:** 45 characters (supports both IPv4 and IPv6)
- **Timestamp format:** DATETIME (stores up to second precision)
- **Collation:** utf8mb4_unicode_ci (supports all Unicode characters)
- **Engine:** InnoDB (supports transactions and foreign keys)

## Related Tables (Future)

### audit_logs_archive (for long-term storage)
```sql
CREATE TABLE audit_logs_archive LIKE audit_logs;
-- Same structure as audit_logs, for archiving old records
```

### audit_log_exports (for tracking exports)
```sql
CREATE TABLE audit_log_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exported_by INT,
    export_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    record_count INT,
    export_format VARCHAR(50),
    file_path VARCHAR(255)
);
```

---

**Table Version:** 1.0
**Created:** January 20, 2025
**Status:** ✅ Active in Production
