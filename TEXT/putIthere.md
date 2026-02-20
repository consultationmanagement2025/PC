
# Product Backlog (PCMP)

Based on the actual PCMP codebase. Every item below is a real feature in the system.

---

## 3.4.1 Product Backlog (User Stories)

### MODULE 1: Authentication & User Management

| User Story No. | Features/Task | User Stories | Priority | Status |
|---|---|---|---|---|
| F1 | Admin Login | As an admin, I want to log in securely using my credentials so that I can access the admin dashboard. | High | Completed |
| F2 | Login Rate Limiting | As a system administrator, I want failed login attempts to be limited and temporarily locked so that brute-force attacks are prevented. | High | Completed |
| F3 | Two-Factor Authentication (2FA) | As an admin, I want to enable TOTP-based two-factor authentication so that my account has an extra layer of security. | High | Completed |
| F4 | Role-Based Access Control | As a system administrator, I want role-based access (admin vs citizen) so that each user type sees only authorized features. | High | Completed |
| F5 | Session Management & Timeout | As a system administrator, I want sessions to expire after 30 minutes of inactivity with secure cookie settings so that unauthorized access is prevented. | High | Completed |
| F6 | Logout | As a logged-in user, I want to log out and have my session destroyed so that my account stays secure on shared devices. | Medium | Completed |
| F7 | Admin Profile Management | As an admin, I want to view and update my profile (name, email, department) so that my account details remain accurate. | Medium | Completed |
| F8 | Password Change | As an admin, I want to change my password from the dashboard so that I can maintain account security. | High | Completed |
| F9 | Profile Photo Upload | As an admin, I want to upload a profile photo so that my account is personalized. | Low | Completed |
| F10 | User Management (Admin) | As an admin, I want to view all registered users, their roles, and status so that I can manage accounts. | Medium | Completed |

### MODULE 2: Public Consultation (Citizen Portal)

| User Story No. | Features/Task | User Stories | Priority | Status |
|---|---|---|---|---|
| F11 | Browse Active Consultations | As a citizen, I want to browse active consultations on the public portal so that I can see what topics are open for participation. | High | Completed |
| F12 | View Consultation Details | As a citizen, I want to view the full details of a consultation (description, dates, category) so that I can make an informed decision before submitting. | High | Completed |
| F13 | Submit Consultation Request | As a citizen, I want to submit a consultation request with my name, email, topic, and message so that I can formally participate. | High | Completed |
| F14 | Submit Feedback (Public) | As a citizen, I want to submit feedback with type/category, message, and optional attachment so that I can share my opinion on public matters. | High | Completed |
| F15 | OTP / Email Verification | As a citizen, I want to verify my email via OTP before submission is finalized so that spam submissions are reduced. | High | Completed |
| F16 | Email Confirmation on Submission | As a citizen, I want to receive an email confirmation after submitting a consultation or feedback so that I know my submission was received. | High | In Progress |
| F17 | View Past/Completed Consultations | As a citizen, I want to view past and completed consultations so that I can review previous topics and outcomes. | Medium | Completed |
| F18 | Public Announcements Display | As a citizen, I want to see announcements posted by the LGU on the public portal so that I stay informed about community updates. | Medium | Completed |
| F19 | Consultation Download | As a citizen, I want to download consultation details as a document so that I have an offline copy for reference. | Low | Completed |
| F20 | Responsive Mobile Layout | As a citizen, I want the public portal to work properly on mobile devices so that I can participate from my phone. | High | Completed |

### MODULE 3: Admin Dashboard (Consultation & Feedback Management)

| User Story No. | Features/Task | User Stories | Priority | Status |
|---|---|---|---|---|
| F21 | Admin Dashboard Overview | As an admin, I want a dashboard with summary stats (total consultations, active, closed, feedback counts) so that I get a quick overview. | High | Completed |
| F22 | Dashboard Navigation (Modular Sections) | As an admin, I want modular sidebar navigation to switch between sections (consultations, feedback, users, documents, audit, etc.) so that I can manage the system efficiently. | High | Completed |
| F23 | Consultation Management (CRUD) | As an admin, I want to create, view, edit, close, and delete consultations with title, description, category, and date range so that the consultation lifecycle is fully managed. | High | Completed |
| F24 | Consultation Status & Filtering | As an admin, I want to filter consultations by status (active, closed, scheduled) and search by keyword so that I can find specific records quickly. | High | Completed |
| F25 | Feedback Collection & Review | As an admin, I want to view all submitted feedback, update its status, and add an admin response so that feedback is reviewed without being altered. | High | Completed |
| F26 | Overdue Feedback Indicator | As an admin, I want feedback in "new" status older than 3 days to be automatically marked overdue so that I can prioritize delayed items. | Medium | Completed |
| F27 | Admin Reply / Email Notify Submitter | As an admin, I want to reply to submitters via email directly from the dashboard so that follow-ups are done quickly. | Medium | In Progress |
| F28 | Announcement Management (CRUD) | As an admin, I want to create, edit, delete, and toggle visibility of announcements (with images) so that I can communicate with citizens. | Medium | Completed |
| F29 | Document Management | As an admin, I want to manage official documents (e.g., Citizen Charter) so that citizens can access important files. | Medium | Completed |
| F30 | Admin Settings & Preferences | As an admin, I want to configure preferences (theme, language, email/announcement/feedback notification toggles) so that the dashboard suits my workflow. | Medium | Completed |

### MODULE 4: Notifications, Auditability & Records

| User Story No. | Features/Task | User Stories | Priority | Status |
|---|---|---|---|---|
| F31 | In-App Notifications (DB-backed) | As an admin, I want in-app notifications triggered when new consultations or feedback are submitted so that I can respond quickly. | High | Completed |
| F32 | Notification Management (Read/Unread/Delete) | As an admin, I want to mark notifications as read/unread, mark all as read, and delete individual notifications so that I can manage my inbox. | Medium | Completed |
| F33 | Unread Notification Count Badge | As an admin, I want to see an unread notification count on the bell icon so that I know at a glance if there are new items. | Medium | Completed |
| F34 | Audit Logging (Admin Actions) | As an admin, I want all admin actions logged (login, create/edit/delete consultation, approve/reject posts, user changes) with IP and timestamp so that actions are traceable. | High | Completed |
| F35 | Audit Log Viewer & Filters | As an admin, I want to view audit logs with filters (by admin, action type, entity, date range) so that I can investigate specific events. | High | Completed |
| F36 | User Activity Logging (Citizen) | As a system administrator, I want citizen actions logged (submissions, feedback, page views) so that engagement can be tracked separately from admin actions. | Medium | Completed |
| F37 | Audit Log Admin Login/Logout Tracking | As a security officer, I want admin login and logout events specifically logged so that access patterns are monitored. | High | Completed |
| F38 | SMTP Email Sending | As a system administrator, I want email sending to use SMTP (PHPMailer) instead of PHP mail() so that delivery is reliable and failures are reported accurately. | High | In Progress |
| F39 | Notification on New Consultation (Auto) | As an admin, I want a notification automatically created when a citizen submits a new consultation so that no submission is missed. | High | Completed |
| F40 | Notification on New Feedback (Auto) | As an admin, I want a notification automatically created when a citizen submits new feedback so that I can review it promptly. | High | Completed |

### MODULE 5: Analytics, Reporting & Decision Support

| User Story No. | Features/Task | User Stories | Priority | Status |
|---|---|---|---|---|
| F41 | Analytics API (Dashboard Stats) | As an admin, I want an analytics API that returns user counts, post counts, consultation stats, feedback stats, and login activity so that the dashboard displays real data. | High | Completed |
| F42 | Daily User & Post Trends | As an admin, I want to see daily user registration and post creation trends so that I can monitor platform growth. | Medium | Completed |
| F43 | Consultation Status Breakdown | As an admin, I want a breakdown of consultations by status (active, closed, scheduled) so that I can see the current state of all consultations at a glance. | Medium | Completed |
| F44 | Feedback Statistics Summary | As an admin, I want feedback statistics (total count, average rating, new feedback count) so that I can gauge citizen satisfaction. | Medium | Completed |
| F45 | Login Activity Chart (7-day) | As an admin, I want a chart showing admin login activity over the last 7 days so that I can monitor access patterns. | Low | Completed |
| F46 | Consultation Participation Reports | As an LGU official, I want to generate reports by consultation topic, date range, and barangay so that engagement gaps can be identified. | High | Not Started |
| F47 | Feedback Category Breakdown Chart | As an admin, I want a chart showing feedback counts by category (complaint, suggestion, inquiry) so that I can see which types are most common. | Medium | Not Started |
| F48 | Data Export (CSV) | As an admin, I want to export consultation and feedback records as CSV so that I can generate offline reports or share data with stakeholders. | Medium | Not Started |
| F49 | Decision Support Dashboard | As a decision-maker, I want a dashboard summarizing key consultation insights (top concerns, trends, submission counts) so that decisions are evidence-based. | High | Not Started |
| F50 | Printable Dashboard Summary | As an LGU official, I want to print a one-page dashboard summary so that I can present key metrics in meetings. | Low | Not Started |

---

## 3.4.2 Product Backlog for PCMP Information Security

| IS No. | IS User Stories | IS Priority | Revision Priority | Status |
|---|---|---|---|---|
| IS-1 | As a system administrator, I want all user passwords encrypted using bcrypt hashing so that credentials are protected even if the database is compromised. | High | Medium | Completed |
| IS-2 | As a security officer, I want all forms protected against CSRF attacks using secure tokens so that malicious actors cannot perform unintended actions. | High | High | Completed |
| IS-3 | As a system administrator, I want sessions to have automatic timeout (30 min) with secure cookie flags (httponly, samesite=strict) so that unauthorized access is prevented. | High | Medium | Completed |
| IS-4 | As a developer, I want all user inputs validated and sanitized (prepared statements) so that SQL injection and input-based attacks are prevented. | High | Medium | Completed |
| IS-5 | As a security officer, I want login rate limiting (5 attempts / 15 min lockout) so that brute-force attacks are blocked. | High | High | Completed |
| IS-6 | As a system administrator, I want session IDs regenerated after login so that session fixation attacks are prevented. | High | Medium | Completed |

---

## 3.4.3 Product Backlog for PCMP Standards

### 3.4.3.1 UI/UX (Icons, Color, etc.)

| UI No. | UI Standard User Stories | UI Priority | Revision Priority | Status |
|---|---|---|---|---|
| UI-1 | As a UI/UX designer, I want a consistent color palette (Valenzuela red/blue primary, gray secondary) so that the system maintains visual consistency and brand identity. | High | High | Completed |
| UI-2 | As a frontend developer, I want custom scrollbar styling and smooth animations so that the user experience feels modern. | Medium | Medium | In Progress |
| UI-3 | As a user, I want a dark mode theme toggle so that I can switch to a comfortable viewing mode. | Low | Low | Completed |
| UI-4 | As a designer, I want standard button styles (Primary, Secondary, Danger, Success) with consistent hover/active states so that interactions are unified. | Medium | Medium | Completed |
| UI-5 | As a frontend developer, I want the admin dashboard to be responsive on tablets and mobile so that admins can work from any device. | High | High | Completed |
| UI-6 | As a frontend developer, I want the public portal footer and consultation cards to be responsive on mobile so that citizens have a good experience on phones. | High | High | Completed |

---

## 3.4.4 Product Backlog for PCMP Integration

| PCMP Integration No. | PCMP Integration User Stories | PCMP Integration Priority | Revision Priority | Status |
|---|---|---|---|---|
| INT-1 | As a backend developer, I want to implement RESTful API endpoints (users_api.php, consultations_api.php, feedback_api.php, analytics_api.php, audit_logs_api.php, notifications_api.php) so that core public consultation modules can securely exchange data and support transparent LGU operations. | High | High | In Progress |
| INT-2 | As an admin, I want to integrate an email notification system for consultation updates, submission confirmations, and admin reply/notify so that citizens and officials receive timely information even outside the platform. | High | Medium | In Progress |
| INT-3 | As a system architect, I want to integrate consultation management with APIs to handle citizen feedback submission, moderation, and status tracking so that public consultations are processed efficiently and transparently. | High | High | In Progress |
| INT-4 | As an admin, I want to integrate an SMS notification service for critical consultation updates so that citizens without reliable internet access remain informed and included. | Low | Low | Not Started |

---

## 3.4.5 Product Backlog for Analytics

### 3.4.5.1 Application System Analytics

| PCMP Analytics No. | PCMP Analytics User Stories | PCMP Analytics Priority | Revision Priority | Status |
|---|---|---|---|---|
| ASA-1 | As an LGU official, I want to track how many days a consultation stays in "Active" status on average so that I can identify bottlenecks in the review process and improve efficiency. | High | Medium | In Progress |
| ASA-2 | As a UX Lead, I want to see which dashboard sections (Consultations, Feedback, Audit Log) are used most frequently so that I can optimize the interface and improve user experience. | Medium | Low | In Progress |
| ASA-3 | As a staff member, I want to monitor the system's database size and uploaded file sizes so that I can ensure large attachments don't slow down the system. | High | Medium | Not Started |
| ASA-4 | As a supervisor, I want to track the "Edit History" of consultations to see who made the most recent modifications so that I can maintain accountability and audit trails. | Medium | High | Not Started |

---

### 3.4.5.2 PCMP Analytics

| PCMP Analytics No. | PCMP Analytics Stories | PCMP Analytics Priority | Revision Priority | Status |
|---|---|---|---|---|
| EA-1 | As an admin, I want to track citizen submission patterns and system activity through get_user_logs_api.php so that I can analyze engagement and identify usage trends. | High | Medium | Completed |
| EA-2 | As an admin, I want to measure consultation and feedback submission effectiveness using engagement metrics so that I can assess the impact of public participation initiatives. | Medium | High | In Progress |
| EA-3 | As an admin, I want to collect user satisfaction ratings and Net Promoter Scores (NPS) so that service quality and trust levels can be evaluated. | Low | Medium | Not Started |
| EA-4 | As an admin, I want to access a citizen engagement dashboard so that consultation performance can be monitored in real time. | High | High | In Progress |

---

# 3.4.6 Sprint Backlog (User Stories)

| Task No. | User Story No. | User Stories | Tasks | Timeline | Responsible Team Member/s |
|---|---|---|---|---|---|
| | | **SPRINT 1: Core Authentication & Security** | | | |
| S1_1 | IS-1 | As an admin, I want user passwords encrypted using bcrypt so that user credentials remain secure even if the database is compromised. | Planning<br>Design<br>Coding<br>Documentation | Week 2 | Ombina |
| S1_2 | IS-2 | As an admin, I want CSRF protection implemented so that malicious requests are prevented. | Planning<br>Design<br>Implementation<br>Security Testing | Week 2 | Ombina |
| S1_3 | F-1 | As an admin, I want to log in securely using my credentials so that I can access the admin dashboard. | UI Design<br>Backend Integration<br>Testing | Week 2 | Ombina |
| S1_4 | F-2 | As a system administrator, I want failed login attempts to be limited and temporarily locked so that brute-force attacks are prevented. | Authentication<br>Rate Limiting Logic<br>Testing | Week 2 | Ombina |
| S1_5 | F-3 | As an admin, I want to enable TOTP-based two-factor authentication so that my account has an extra layer of security. | Email Service Setup<br>2FA Logic<br>Validation<br>Testing | Week 3 | Ombina |
| | | **SPRINT 2: User Management & Announcement** | | | |
| S2_1 | IS-3 | As an admin, I want inactive sessions to timeout automatically so that unauthorized access is prevented. | Session Configuration<br>Auto-Logout Logic<br>Testing | Week 4 | Ombina |
| S2_2 | IS-4 | As a developer, I want user inputs validated and sanitized so that SQL injection and XSS attacks are avoided. | Form Validation<br>SQL Injection Prevention<br>Testing | Week 4 | Ombina |
| S2_3 | F-7 | As an admin, I want to update my profile information so that my account details stay accurate. | Profile UI<br>Update Logic<br>Image Upload<br>Testing | Week 5 | Ombina |
| S2_4 | F-28 | As an admin, I want to create announcements so that users are informed of updates. | Admin UI<br>CRUD Functions<br>Testing | Week 6 | Ombina |
| S2_5 | F-28 | As an admin, I want to manage announcements so that outdated information can be edited or removed. | Edit, Delete<br>Schedule Logic<br>Testing | Week 4 | Ombina |

---

# 3.4.7 Increment

| Sprint No. | Increment Feature Delivered | User Story / Backlog Reference | Definition of Done (DoD) Criteria | Status | Remarks |
|---|---|---|---|---|---|
| Sprint 1 | Admin Authentication & Security Module | F1, F2, IS-1 | - Code completed<br>- Passwords encrypted (bcrypt)<br>- Unit & security tested<br>- Documentation updated | Done | Basic and secure authentication operational |
| Sprint 1 | Database Schema Setup | F-1 | - Database schema created<br>- Tables normalized<br>- Tested using sample consultation data | Done | Ready for system integration |
| Sprint 2 | Public Consultation Portal | F11, F12, F13, F14 | - Citizen can browse, view, and submit consultations<br>- Feedback submission functional<br>- Tested in staging environment | Done | Core citizen-facing portal operational |
| Sprint 2 | Admin Consultation Management | F23, F24, IS-3, IS-4 | - CRUD operations functional<br>- Status filtering implemented<br>- Input validation and session timeout in place<br>- Tested with sample data | Done | Pending UI review |
| Sprint 3 | Notification & Audit System | F31, F34, F35 | - Notifications triggered on new submissions<br>- Audit logging for admin actions implemented<br>- Audit log viewer with filters operational<br>- Error handling mechanisms in place | Done | Core accountability features in place |
| Sprint 3 | Announcement & Document Management | F28, F29 | - Announcement CRUD functional<br>- Document management section operational<br>- Toggle visibility working<br>- Tested end-to-end | Done | Ready for public portal integration |
| Sprint 4 | Analytics & Dashboard Stats | F41, F42, F43, F44, F45 | - Analytics API returning real data<br>- Daily trends, status breakdown, feedback stats displayed<br>- Login activity chart functional | Done | Dashboard data-driven |
| Sprint 4 | Email Integration (SMTP) | F16, F27, F38 | - PHPMailer configured<br>- Email confirmation on submission working<br>- Admin reply/notify functional<br>- Failures reported accurately | In Progress | Requires SMTP configuration to be finalized |

