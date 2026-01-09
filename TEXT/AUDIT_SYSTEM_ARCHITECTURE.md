# Audit Log System Architecture

## System Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD (system-template-full.php)   │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Navigation Menu (Left Sidebar / Mobile)                        │
│  ┌────────────────────────────────────────┐                     │
│  │ ▸ Public Consultation                  │                     │
│  │ ▸ Consultation Management              │                     │
│  │ ▸ Feedback                             │                     │
│  │ ▸ Documents                            │                     │
│  │ ▸ Users                                │                     │
│  │ ★ AUDIT LOG ← CLICK HERE               │                     │
│  │ ▸ Profile                              │                     │
│  └────────────────────────────────────────┘                     │
│                           │                                      │
│                           ▼                                      │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │          AUDIT LOG VIEWER SECTION                       │   │
│  ├─────────────────────────────────────────────────────────┤   │
│  │ Audit Logs                              [Export] [CSV]  │   │
│  │ Track all administrative actions                        │   │
│  │                                                         │   │
│  │ FILTERS:                                                │   │
│  │ ┌─────────────────────────────────────────────────────┐ │   │
│  │ │ Admin User: [_________] Action: [Dropdown]  Type... │ │   │
│  │ │ [Apply] [Reset]                                     │ │   │
│  │ └─────────────────────────────────────────────────────┘ │   │
│  │                                                         │   │
│  │ TABLE:                                                  │   │
│  │ ┌───────────────────────────────────────────────────┐   │   │
│  │ │ Timestamp    │ Admin │ Action │ Entity │ IP │View │   │   │
│  │ ├───────────────────────────────────────────────────┤   │   │
│  │ │ Jan 20 14:30 │ John  │ Delete │ Doc    │... │[V] │   │   │
│  │ │ Jan 20 14:15 │ Jane  │ Login  │ System │... │[V] │   │   │
│  │ │ Jan 20 14:00 │ John  │ Upload │ Doc    │... │[V] │   │   │
│  │ │ Jan 20 13:45 │ Admin │ Create │ User   │... │[V] │   │   │
│  │ └───────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  │ Pagination: Showing 1 to 50 of 247 logs               │   │
│  │ [< Prev] [1] [2] [3] [4] [Next >]                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                              │
                              │ "View" clicked
                              ▼
┌──────────────────────────────────────────────┐
│        AUDIT DETAILS MODAL (audit-modal)     │
├──────────────────────────────────────────────┤
│                                              │
│  Admin User        John Doe                  │
│  Admin ID          5                         │
│  Action            Deleted Document #102    │
│  Entity Type       Document                  │
│  Entity ID         102                       │
│  IP Address        192.168.1.100             │
│  Timestamp         Jan 20, 2025 14:30:45    │
│  Status            ✓ Success                │
│  Old Value         [Previous content...]     │
│  New Value         [New content...]          │
│  Details           [Additional context...]   │
│                                              │
│                                    [Close]  │
└──────────────────────────────────────────────┘
```

## Data Flow Architecture

```
Admin Actions                          Logging Layer              Database
════════════════════════════════════════════════════════════════════════════

LOGIN
admin logs in → login.php
               ↓
           logAdminLogin()  ──────────→ audit-log.php ──→ INSERT audit_logs
           (admin_id,                  (prepared stmt)   │ admin_user
            admin_name)                                 │ action: "Login"
                                                         │ timestamp: NOW()
                                                         │ ip_address
                                                         │ status: success
                                                         ↓
                                                      MySQL DB


DOCUMENT DELETE
admin clicks    → document handler
delete button   ↓
           logDocumentDeletion()  ───→ audit-log.php ──→ INSERT audit_logs
           (admin_id,                 (prepared stmt)   │ admin_user
            admin_name,                                │ action: "Deleted..."
            doc_id,                                    │ entity_type: doc
            doc_title)                                 │ entity_id: ID#
                                                        │ status: success
                                                        ↓
                                                     MySQL DB


DOCUMENT UPLOAD
admin uploads  → file handler
file           ↓
           logDocumentUpload()  ───→ audit-log.php ──→ INSERT audit_logs
           (admin_id,              (prepared stmt)   │ admin_user
            admin_name,                             │ action: "Uploaded..."
            doc_id,                                 │ entity_type: doc
            doc_title,                              │ entity_id: ID#
            file_path)                              │ new_value: file path
                                                     │ status: success
                                                     ↓
                                                  MySQL DB


VIEW AUDIT LOGS
admin clicks    → system-template-full.php
Audit Log menu  ↓
           getAuditLogs()  ────────→ audit-log.php ──→ SELECT * FROM audit_logs
           ($limit,                 (prepared stmt)   ├ WHERE filters...
            $offset,                                  ├ ORDER BY timestamp DESC
            $filters)                                 ├ LIMIT & OFFSET
                                ↓                    ↓
                          render HTML table       MySQL returns rows
                          with results
```

## File Dependencies

```
login.php (User Authentication)
    │
    ├─→ audit-log.php
    │   └─→ db.php (MySQL Connection)
    │       └─→ MySQL [audit_logs table]
    │
    └─→ system-template-full.php (on redirect)


system-template-full.php (Admin Dashboard)
    │
    ├─→ audit-log.php
    │   └─→ db.php (MySQL Connection)
    │       └─→ MySQL [audit_logs table]
    │
    ├─→ script.js (showAuditDetails, exportAuditLogs)
    │
    └─→ styles.css (Styling)
        └─→ Tailwind CSS + Bootstrap Icons


script.js (JavaScript Functions)
    │
    ├─→ showAuditDetails(logData)
    │   └─→ Opens #audit-modal with formatted details
    │
    ├─→ exportAuditLogs()
    │   └─→ Triggers CSV download
    │
    └─→ showSection('audit')
        └─→ Updates navigation and displays audit section
```

## Database Table Structure

```
audit_logs Table
═══════════════════════════════════════════════════════════════════════════

┌─ id (INT, Primary Key, Auto-increment)
├─ admin_user (VARCHAR 255) ........................ WHO: Admin name
├─ admin_id (INT) ................................. WHO: Admin user ID
├─ action (VARCHAR 500) ............................ WHAT: Action description
├─ entity_type (VARCHAR 100) ....................... TYPE: user/doc/consult
├─ entity_id (INT) ................................. ID: Entity's ID #
├─ old_value (LONGTEXT) ............................ BEFORE: Previous value
├─ new_value (LONGTEXT) ............................ AFTER: New value
├─ ip_address (VARCHAR 45) ......................... WHERE: Admin's IP
├─ user_agent (TEXT) ............................... HOW: Browser/Device info
├─ timestamp (DATETIME) ............................. WHEN: Action timestamp
├─ status (VARCHAR 50) ............................. SUCCESS/FAILURE
└─ details (LONGTEXT) .............................. WHY: Additional context

INDEXES:
  ├─ PRIMARY KEY (id)
  ├─ idx_admin_id (admin_id) ...................... Fast filtering by admin
  ├─ idx_timestamp (timestamp) .................... Fast date range queries
  ├─ idx_action (action) .......................... Fast action filtering
  └─ idx_entity (entity_type, entity_id) ........ Fast entity lookups
```

## Function Call Map

```
Core Logging
═════════════════════════════════════════════════════════════════════════════

logAction()
├─ Parameters: admin_id, admin_user, action, entity_type, entity_id, 
│              old_value, new_value, status, details
├─ Actions: INSERT INTO audit_logs with prepared statements
│           Captures IP address from $_SERVER['REMOTE_ADDR']
│           Captures User Agent from $_SERVER['HTTP_USER_AGENT']
└─ Returns: true/false


Helper Functions (Built on logAction)
═════════════════════════════════════════════════════════════════════════════

logUserCreation(admin_id, admin_user, user_id, user_email, user_role)
  └─→ calls logAction() with entity_type="user", action="Created User..."

logUserDeletion(admin_id, admin_user, user_id, user_email)
  └─→ calls logAction() with entity_type="user", action="Deleted User..."

logDocumentUpload(admin_id, admin_user, doc_id, doc_title, doc_path)
  └─→ calls logAction() with entity_type="document", action="Uploaded..."

logDocumentDeletion(admin_id, admin_user, doc_id, doc_title)
  └─→ calls logAction() with entity_type="document", action="Deleted..."

logConsultationCreation(admin_id, admin_user, consultation_id, title)
  └─→ calls logAction() with entity_type="consultation", action="Created..."

logConsultationUpdate(admin_id, admin_user, consultation_id, old_data, new_data)
  └─→ calls logAction() with entity_type="consultation", action="Updated..."

logConsultationDeletion(admin_id, admin_user, consultation_id, title)
  └─→ calls logAction() with entity_type="consultation", action="Deleted..."

logAdminLogin(admin_id, admin_user)
  └─→ calls logAction() with entity_type="system", action="Login"
      ★ CURRENTLY INTEGRATED IN login.php

logAdminLogout(admin_id, admin_user)
  └─→ calls logAction() with entity_type="system", action="Logout"


Retrieval Functions
═════════════════════════════════════════════════════════════════════════════

getAuditLogs(limit, offset, filters)
  ├─ SELECT * FROM audit_logs WHERE ... ORDER BY timestamp DESC
  ├─ Supports filtering by: admin_user, action, entity_type, date_range
  └─ Returns: Array of audit log records

getAuditLogCount(filters)
  ├─ SELECT COUNT(*) FROM audit_logs WHERE ...
  └─ Returns: Integer count of matching records
```

## Security Architecture

```
SQL Injection Prevention
═════════════════════════════════════════════════════════════════════════════

User Input                Prepared Statements              Database
        │                        │
        ├─→ $admin_user ────→ $stmt→bind_param() ────→ Safe Query
        ├─→ $action ────────→ $stmt→bind_param() ────→ Escaped values
        ├─→ $entity_type ───→ $stmt→bind_param() ────→ SQL Injection
        └─→ $details ───────→ $stmt→bind_param() ────→ PREVENTED


XSS Prevention (Output)
═════════════════════════════════════════════════════════════════════════════

Database Value        HTML Escaping         Browser Display
        │                    │
        ├─→ escapeHtml() ───→ Safe HTML ────→ Rendered safely
        ├─→ htmlspecialchars()─→ &lt; &gt; etc.
        └─→ Never directly echo user input


IP Address Logging (Forensics)
═════════════════════════════════════════════════════════════════════════════

Admin Action      ──→ $_SERVER['REMOTE_ADDR'] ──→ Stored in DB
                      $_SERVER['HTTP_USER_AGENT']
                      
Enables:
  ├─ Track where admin accessed from
  ├─ Detect unauthorized access
  ├─ Identify device/browser used
  └─ Forensic analysis
```

---

**Architecture Version:** 1.0
**Last Updated:** January 20, 2025
**Status:** ✅ Production Ready
