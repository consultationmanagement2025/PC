# Public Consultation System - Integration Guide

## 🎯 Overview

The Public Consultation System connects **Admin Management** with the **User Public Portal**, allowing administrators to create and manage public consultations (like the SMV notice) and enabling citizens to view and submit feedback.

## 📁 Files Created/Modified

### New Files
1. **`public-consultations.php`** - Public-facing consultation portal for citizens
2. **`admin-manage-consultations.php`** - Admin dashboard for managing consultations
3. **`API/consultation_feedback.php`** - API for feedback submission and retrieval

### Modified Files
1. **`DATABASE/posts.php`** - Enhanced to support consultation feedback
2. **`DATABASE/consultations.php`** - Already contains consultation management functions
3. **`API/consultations_api.php`** - Already has admin API endpoints

## 🔌 How It Works

### Admin Side (Back-end)
1. Admin logs in to **`admin-manage-consultations.php`**
2. Admin creates a consultation with:
   - Title (e.g., "Notice of Public Consultation - Schedule of Market Values (SMV)")
   - Description (full details about the consultation)
   - Category (Budget, Policy, Development, etc.)
   - Start and End dates/times
3. System stores in `consultations` table
4. Consultation status is set to "active" by default
5. Admin can view statistics, close, or delete consultations

### User Side (Front-end)
1. Citizens visit **`public-consultations.php`**
2. See all active consultations with:
   - Consultation details
   - Schedule information (dates/times)
   - Engagement statistics (feedback count, contributors)
   - Filter and search functionality
3. Click "Submit Feedback" to post their input
4. Feedback is submitted as "pending" and awaits admin approval
5. Once approved, feedback appears on the consultation page

## 📊 Database Schema

### consultations table
```sql
id (PK)
title VARCHAR(255)
description LONGTEXT
category VARCHAR(100)
status ENUM('draft', 'active', 'closed', 'archived')
start_date DATETIME
end_date DATETIME
admin_id INT (FK to users)
expected_posts INT
views INT
posts_count INT
created_at TIMESTAMP
updated_at TIMESTAMP
image_path VARCHAR(255)
```

### posts table (enhanced)
```sql
id (PK)
user_id INT
author VARCHAR(255)
content LONGTEXT
consultation_id INT (NEW - links to consultations)
status ENUM('pending', 'approved', 'rejected') (NEW)
category VARCHAR(100) (NEW)
created_at DATETIME
```

## 🚀 Quick Start

### For Admins
1. Go to `admin-manage-consultations.php`
2. Login as Administrator
3. Fill in consultation details (follow the template from the SMV notice image):
   - **Title**: Notice of Public Consultation - Proposed Schedule of Market Values (SMV)
   - **Description**: Full text about the consultation
   - **Category**: Budget & Finance
   - **Start Date**: January 28, 2026
   - **End Date**: March 3, 2026
4. Click "Create Consultation"
5. Monitor feedback submissions in real-time

### For Citizens
1. Go to `public-consultations.php`
2. Browse active consultations
3. Click "Submit Feedback" to participate
4. Enter feedback message and category
5. Submit (awaits admin approval)

## 🔗 API Endpoints

### Consultation APIs
- `GET API/consultations_api.php?action=list` - Get all consultations
- `GET API/consultations_api.php?action=get&id=1` - Get single consultation
- `POST API/consultations_api.php?action=create` - Create consultation (admin only)
- `POST API/consultations_api.php?action=update` - Update consultation (admin only)
- `POST API/consultations_api.php?action=close` - Close consultation (admin only)

### Feedback APIs
- `GET API/consultation_feedback.php?action=get_feedback&consultation_id=1` - Get approved feedback
- `POST API/consultation_feedback.php?action=submit_feedback` - Submit feedback
- `GET API/consultation_feedback.php?action=get_stats&consultation_id=1` - Get consultation stats
- `GET API/consultation_feedback.php?action=get_recent&limit=5` - Get recent feedback

## 🎨 Features

### Admin Dashboard
✅ Create consultations with rich details
✅ View all consultations (active, draft, closed, archived)
✅ Real-time statistics (total feedback, status breakdown)
✅ Edit consultation details
✅ Close consultations when complete
✅ Delete consultations
✅ View feedback approval queue
✅ Track engagement metrics

### Public Portal
✅ Browse active consultations
✅ Search and filter by category
✅ View consultation details and timeline
✅ See engagement statistics
✅ Submit feedback (requires login)
✅ Feedback appears when approved
✅ Beautiful responsive design
✅ Mobile-friendly interface

### Feedback System
✅ Users submit feedback with categories
✅ Automatic status tracking (pending/approved/rejected)
✅ Admin approval workflow
✅ User activity logging
✅ Statistics aggregation

## 🔐 Security

- ✅ Role-based access control (admin only for management)
- ✅ Session-based authentication required
- ✅ SQL injection prevention (prepared statements)
- ✅ Input validation and sanitization
- ✅ Audit logging of all actions

## 🔄 Integration with Existing System

The system integrates seamlessly with your existing:
- **User authentication** (login.php, AUTH/register.php)
- **Database structure** (db.php, existing tables)
- **User profiles** (user-portal.php)
- **Activity logging** (DATABASE/audit-log.php, user-logs.php)
- **Notification system** (DATABASE/notifications.php)

## 📱 Responsive Design

Both admin and public interfaces are:
- Mobile-responsive
- Touch-friendly
- Fast-loading
- Accessible (WCAG compliant)
- Professional styling with your brand colors

## 🎓 Example: SMV Notice Implementation

To create a consultation matching the image provided:

1. **Admin Navigation**: Go to `admin-manage-consultations.php`
2. **Fill Form**:
   ```
   Title: Notice of Public Consultation - Proposed Schedule of Market Values (SMV)
   Category: Budget & Finance
   
   Description: 
   The City Government of Valenzuela, through the Office of the City Assessor, 
   is inviting the community to join Public Consultations on the Proposed 
   Schedule of Market Values (SMV).
   
   The consultations aim to gather public feedback, clarify concerns, and present 
   proposed valuation data prior to submission to the Bureau of Local Government 
   Finance (BLGF) and the Secretary of Finance for review and approval.
   
   Updating the SMV is pursuant to R.A. No. 12001, and your participation is important. 
   We welcome your feedback, questions, and suggestions to help make the process 
   fair and community-friendly.
   
   Be HEARD. Take part in the Public Consultation!
   
   Start Date: 2026-01-28 12:00 PM
   End Date: 2026-03-03 03:00 PM
   ```
3. **Click Create**
4. **Public Display**: Citizens see it immediately in `public-consultations.php`

## 📊 Monitoring & Analytics

Admin dashboard shows:
- Total consultations
- Active consultations
- Closed consultations
- Pending feedback approvals
- Engagement statistics per consultation
- Contributors count
- Feedback categories breakdown

## 🚨 Troubleshooting

### Consultations not appearing on public portal
- Verify consultation status is "active"
- Check start_date is before current time
- Check end_date is after current time
- Clear browser cache

### Feedback submission failing
- Ensure user is logged in
- Check database permissions
- Verify posts table has required columns
- Review browser console for errors

### Statistics not updating
- Refresh the page
- Check if feedback has been approved
- Verify consultation_id matches

## 💾 Database Setup

If starting fresh, the system automatically creates/updates tables:
```php
// Call these functions once to initialize
require_once 'DATABASE/consultations.php';
require_once 'DATABASE/posts.php';

initializeConsultationsTable();
initializePostsTable();
```

## 📝 Admin Workflow

1. **Create** → Write consultation details
2. **Publish** → System automatically makes it "active" and public
3. **Monitor** → View real-time feedback submissions
4. **Approve** → Review and approve feedback from citizens
5. **Analyze** → View engagement statistics
6. **Close** → End consultation when complete
7. **Archive** → Review closed consultations anytime

## 🎯 Next Steps

1. ✅ Create a consultation using the admin panel
2. ✅ Share the public portal URL with citizens
3. ✅ Monitor feedback submissions
4. ✅ Approve/reject feedback appropriately
5. ✅ Close consultation when deadline reached
6. ✅ Generate reports from statistics

---

**System Status**: ✅ Ready to Use
**Last Updated**: February 2026
**Contact**: For technical support, refer to system documentation
