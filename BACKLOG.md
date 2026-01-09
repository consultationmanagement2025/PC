# Project Backlog - PCMP/LLRM System

**Project**: Public Consultation Management Portal (PCMP) / Legislative Records Management (LLRM) System  
**Last Updated**: January 9, 2026  
**Status**: Active Development

---

## Table of Contents
1. [Product Backlog](#product-backlog)
2. [Sprint Priorities](#sprint-priorities)
3. [Technical Debt](#technical-debt)
4. [Bug Fixes](#bug-fixes)
5. [Performance Improvements](#performance-improvements)

---

## Product Backlog

### Epic 1: Core Features - COMPLETED ✅
- [x] User Authentication (Login/Register/Logout)
- [x] Dashboard with Statistics
- [x] Document Management (CRUD)
- [x] User Management
- [x] Audit Logging System
- [x] Announcement System
- [x] Notification System
- [x] Profile Management with Picture Upload

---

### Epic 2: Document Management - IN PROGRESS 🔄

#### Story 1: Advanced Document Features
- [ ] Document Versioning
  - Track document changes over time
  - Allow rollback to previous versions
  - Display version history timeline
  - Priority: High
  - Estimated: 13 story points

- [ ] Bulk Document Operations
  - Multi-select documents
  - Bulk approve/reject functionality
  - Bulk delete with confirmation
  - Bulk export (PDF/CSV)
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Document Collaboration
  - Comments on documents
  - Discussion threads
  - Mention users with @mention
  - Email notifications for mentions
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Document Templates
  - Create reusable document templates
  - Quick document creation from templates
  - Template management interface
  - Priority: Low
  - Estimated: 8 story points

- [ ] Document Workflows
  - Define custom approval workflows
  - Multi-level approval process
  - Workflow status tracking
  - Deadline management
  - Priority: High
  - Estimated: 21 story points

---

### Epic 3: User Management & Roles - IN PROGRESS 🔄

#### Story 1: Enhanced Role-Based Access Control (RBAC)
- [ ] Custom Role Creation
  - Define custom roles with specific permissions
  - Role permission matrix
  - Role templates
  - Priority: High
  - Estimated: 13 story points

- [ ] Fine-Grained Permissions
  - Document-level permissions
  - Field-level access control
  - Department-level restrictions
  - Priority: High
  - Estimated: 13 story points

- [ ] User Groups/Teams
  - Create user groups/teams
  - Assign group permissions
  - Group-based notifications
  - Team collaboration features
  - Priority: Medium
  - Estimated: 13 story points

- [ ] User Activity Tracking
  - Track user logins (already partially done)
  - Track document access
  - Track search queries
  - User behavior analytics
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 4: Search & Filtering Enhancements - PLANNED 📋

#### Story 1: Advanced Search
- [ ] Full-Text Search
  - Search across all documents and content
  - Search result highlighting
  - Search suggestions/autocomplete
  - Priority: High
  - Estimated: 13 story points

- [ ] Saved Searches
  - Allow users to save search filters
  - Quick access to saved searches
  - Shared saved searches
  - Priority: Low
  - Estimated: 5 story points

- [ ] Advanced Filters
  - Filter by tags (currently exists, enhance)
  - Filter by file type
  - Filter by size
  - Filter by author
  - Filter by department
  - Combined filters with AND/OR logic
  - Priority: High
  - Estimated: 8 story points

- [ ] Search Analytics
  - Track popular searches
  - Search trend analysis
  - Provide search suggestions based on trends
  - Priority: Low
  - Estimated: 8 story points

---

### Epic 5: Reports & Analytics - IN PROGRESS 🔄

#### Story 1: Enhanced Reporting
- [ ] Custom Report Builder
  - Drag-and-drop report builder
  - Multiple chart types
  - Export reports (PDF, Excel, CSV)
  - Schedule automated reports
  - Priority: High
  - Estimated: 21 story points

- [ ] Dashboard Customization
  - Allow users to customize dashboard widgets
  - Save custom dashboard layouts
  - Multiple dashboard templates
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Performance Metrics
  - System uptime/availability
  - Response time metrics
  - User engagement metrics
  - Document lifecycle metrics
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Export Functionality
  - Export reports to PDF
  - Export to Excel
  - Export to CSV
  - Email report delivery
  - Priority: High
  - Estimated: 8 story points

---

### Epic 6: Communication & Notifications - IN PROGRESS 🔄

#### Story 1: Enhanced Notification System
- [ ] Email Notifications
  - Document upload notifications
  - Approval/rejection notifications
  - Deadline reminder notifications
  - User mention notifications
  - Priority: High
  - Estimated: 8 story points

- [ ] In-App Notifications
  - Real-time notifications (currently exists, enhance)
  - Notification center/inbox
  - Notification preferences
  - Read/unread status
  - Priority: Medium
  - Estimated: 8 story points

- [ ] SMS Notifications
  - SMS for critical alerts
  - SMS for deadline reminders
  - Two-factor authentication via SMS
  - Priority: Low
  - Estimated: 13 story points

- [ ] Push Notifications
  - Browser push notifications
  - Mobile app push notifications
  - Push notification preferences
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 7: Announcement & Updates - IN PROGRESS 🔄

#### Story 1: Enhanced Announcement System
- [ ] Scheduled Announcements
  - Schedule announcements for future dates
  - Recurring announcements
  - Announcement expiration
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Rich Text Editor
  - WYSIWYG editor for announcements
  - Image/media embedding
  - Code syntax highlighting
  - Priority: Medium
  - Estimated: 5 story points

- [ ] Announcement Targeting
  - Target announcements to specific roles
  - Target by department
  - Target by user groups
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Announcement Analytics
  - Track announcement views
  - Track clicks/engagement
  - Announcement effectiveness metrics
  - Priority: Low
  - Estimated: 8 story points

---

### Epic 8: Audit & Compliance - COMPLETED ✅

- [x] Audit Log System (Implemented)
- [x] Activity Tracking
- [x] Admin Action Logging
- [ ] Compliance Reports
  - Generate GDPR compliance reports
  - Data retention reports
  - User access audit reports
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Data Export & Deletion
  - Allow users to export their data
  - GDPR right to be forgotten
  - Data deletion workflows
  - Priority: High
  - Estimated: 13 story points

---

### Epic 9: User Experience & Interface - IN PROGRESS 🔄

#### Story 1: UI/UX Improvements
- [ ] Mobile App
  - Native mobile app (iOS/Android)
  - Responsive design enhancements
  - Mobile-specific features
  - Priority: High
  - Estimated: 55+ story points

- [ ] Dark Mode
  - Complete dark mode theme (partially exists)
  - User preference saving
  - System-wide dark mode toggle
  - Priority: Low
  - Estimated: 5 story points

- [ ] Accessibility Improvements
  - WCAG 2.1 AA compliance
  - Screen reader optimization
  - Keyboard navigation
  - Color contrast adjustments
  - Priority: High
  - Estimated: 13 story points

- [ ] Performance Optimization
  - Page load time optimization
  - Image optimization
  - Caching strategies
  - Priority: High
  - Estimated: 13 story points

- [ ] UI Polish
  - Consistent icon usage
  - Animation refinements
  - Toast notifications enhancement
  - Modal dialog improvements
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 10: Integration & API - PLANNED 📋

#### Story 1: API Development
- [ ] REST API
  - Create comprehensive REST API
  - API documentation
  - Rate limiting
  - API versioning
  - Priority: Medium
  - Estimated: 34 story points

- [ ] Third-Party Integrations
  - Email service integration (SendGrid, AWS SES)
  - SMS service integration (Twilio)
  - Cloud storage integration (Google Drive, OneDrive)
  - Single Sign-On (Google, Microsoft)
  - Priority: Low
  - Estimated: 21 story points

- [ ] Webhook Support
  - Outgoing webhooks for document events
  - Webhook management interface
  - Webhook testing tool
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 11: Citizen Portal Enhancements - IN PROGRESS 🔄

#### Story 1: Portal Features
- [ ] Public Consultation Features
  - Submit feedback/comments on public documents
  - Public opinion survey system
  - Voting/rating system for documents
  - Priority: High
  - Estimated: 13 story points

- [ ] Knowledge Base
  - FAQ section
  - How-to guides
  - Video tutorials
  - User documentation
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Document Tracking
  - Track document status for citizens
  - Timeline view of document progress
  - Notifications for status changes
  - Priority: Medium
  - Estimated: 8 story points

- [ ] User Profiles Enhancement
  - User activity history
  - Submission history
  - Profile badges/achievements
  - Priority: Low
  - Estimated: 8 story points

---

## Sprint Priorities

### Current Sprint (Next 2 Weeks)
1. **Email Notification System** (High Priority, 8 points)
   - Implement email notifications for key events
   - Set up email service provider
   
2. **Document Workflows** (High Priority, 21 points)
   - Define multi-level approval workflows
   - Implement workflow status tracking

3. **Custom RBAC** (High Priority, 13 points)
   - Allow creation of custom roles
   - Implement permission matrix

### Next Sprint (2-4 Weeks)
1. **Bulk Document Operations** (Medium Priority, 8 points)
2. **Advanced Filtering** (High Priority, 8 points)
3. **Accessibility Improvements** (High Priority, 13 points)

### Future Sprints (4+ Weeks)
1. **Mobile App Development**
2. **REST API Development**
3. **Document Versioning**
4. **Custom Report Builder**

---

## Technical Debt

### Priority: HIGH 🔴

1. **Database Optimization**
   - Create indexes on frequently queried columns
   - Optimize audit_logs table for large datasets
   - Implement database partitioning
   - Estimated effort: 13 story points

2. **Code Refactoring**
   - Extract repeated code into reusable functions
   - Separate concerns in script.js
   - Create utility functions library
   - Estimated effort: 13 story points

3. **Security Hardening**
   - Implement CSRF protection tokens
   - Add rate limiting on API endpoints
   - Implement password hashing best practices
   - Estimated effort: 8 story points

4. **Error Handling**
   - Implement comprehensive error handling
   - Add error logging system
   - Create user-friendly error messages
   - Estimated effort: 8 story points

### Priority: MEDIUM 🟡

5. **Session Management**
   - Implement session timeout
   - Add session security features
   - Implement remember-me functionality
   - Estimated effort: 5 story points

6. **Logging System**
   - Centralized application logging
   - Log levels (debug, info, warning, error)
   - Log rotation and archiving
   - Estimated effort: 8 story points

7. **Configuration Management**
   - Move hardcoded values to config file
   - Environment-based configuration
   - Secret management
   - Estimated effort: 5 story points

### Priority: LOW 🟢

8. **Code Documentation**
   - Add JSDoc comments to JavaScript files
   - Add PHP documentation
   - Create architecture documentation
   - Estimated effort: 8 story points

9. **Unit Testing**
   - Write unit tests for PHP functions
   - Write tests for JavaScript functions
   - Set up CI/CD pipeline
   - Estimated effort: 21 story points

---

## Bug Fixes

### Critical 🔴

- [ ] None currently identified

### High Priority 🟠

1. **Login Session Issues**
   - Verify session persistence across page refreshes
   - Fix potential session conflicts
   - Estimated effort: 3 story points

2. **Profile Picture Upload Validation**
   - Enhance file type validation
   - Verify file size limits work correctly
   - Estimated effort: 2 story points

### Medium Priority 🟡

3. **Modal Dialog Closing**
   - Verify all modals close properly
   - Check for modal overlay issues
   - Estimated effort: 2 story points

4. **Search Filter Clearing**
   - Verify all filters clear properly
   - Check for filter state issues
   - Estimated effort: 2 story points

5. **Responsive Design Issues**
   - Test on various screen sizes
   - Fix sidebar collapse issues
   - Estimated effort: 5 story points

### Low Priority 🟢

6. **Tooltip Display Issues**
   - Fix tooltip positioning on small screens
   - Improve tooltip styling
   - Estimated effort: 2 story points

---

## Performance Improvements

### High Priority 🔴

1. **Database Query Optimization**
   - Reduce N+1 queries
   - Implement query caching
   - Use database indexes effectively
   - Estimated effort: 13 story points

2. **Asset Optimization**
   - Minify CSS and JavaScript
   - Optimize image sizes
   - Implement lazy loading for images
   - Estimated effort: 8 story points

3. **Caching Strategy**
   - Implement browser caching
   - Server-side caching for frequently accessed data
   - Cache busting strategy
   - Estimated effort: 13 story points

### Medium Priority 🟡

4. **API Response Time**
   - Profile API endpoints
   - Optimize slow queries
   - Implement pagination for large datasets
   - Estimated effort: 8 story points

5. **Frontend Performance**
   - Code splitting for large JavaScript files
   - Reduce JavaScript bundle size
   - Defer non-critical JavaScript
   - Estimated effort: 8 story points

### Low Priority 🟢

6. **Monitoring & Analytics**
   - Implement performance monitoring
   - Real User Monitoring (RUM)
   - Performance dashboards
   - Estimated effort: 13 story points

---

## Story Point Scale

- 1: Trivial (< 1 hour)
- 2: Very Small (1-2 hours)
- 3: Small (2-4 hours)
- 5: Medium (4-8 hours)
- 8: Large (1-2 days)
- 13: Very Large (2-3 days)
- 21: Epic (3-5 days)
- 34: Major Epic (5+ days)
- 55+: Major Project (requires decomposition)

---

## Legend

- ✅ COMPLETED - Fully implemented and tested
- 🔄 IN PROGRESS - Currently being worked on
- 📋 PLANNED - Scheduled for upcoming sprints
- 🔴 HIGH PRIORITY - Critical for system functionality
- 🟡 MEDIUM PRIORITY - Important but not blocking
- 🟢 LOW PRIORITY - Nice to have
- 🔴 CRITICAL BUG - System-breaking issue
- 🟠 HIGH PRIORITY BUG - Significant functionality issue
- 🟡 MEDIUM PRIORITY BUG - Minor functionality issue
- 🟢 LOW PRIORITY BUG - Cosmetic issue

---

## Notes

- **Total Backlog Estimate**: 450+ story points
- **Current Implementation Status**: ~40% complete
- **Estimated Timeline**: 6-9 months for full feature set at 1 sprint/week
- **Team Recommendation**: Prioritize authentication enhancements and core features before expanding to advanced features

---

**Next Steps**: Review this backlog with your team, adjust priorities based on business needs, and begin planning the first sprint.
