# Database Connection Setup - Complete Guide

## Overview
This document explains the complete database setup for the admin-user portal connection system in PCMP (Public Consultation Management Portal).

## What Was Implemented

### 1. Database Tables
Three main tables were created/configured:

#### **announcements** Table
- Stores admin announcements visible to all users
- Columns: `id`, `title`, `content`, `admin_id`, `admin_user`, `visibility`, `status`, `liked_by`, `saved_by`, `created_at`
- Admin posts announcements → automatically appear in user portal's "Updates" section

#### **posts** Table (for user concerns/suggestions)
- Stores user posts, suggestions, and concerns
- Columns: `id`, `user_id`, `author`, `content`, `created_at`
- Users post concerns → automatically appear in admin dashboard's "User Posts" section

#### **audit_logs** Table
- Tracks all administrative actions and user activities
- Logs announcements created, posts submitted, and admin actions
- Enables full audit trail for compliance and monitoring

### 2. PHP Backend Changes

#### New Files Created:
- **get_announcements_api.php** - API endpoint to fetch latest announcements (used by user portal)
- **get_posts_api.php** - API endpoint to fetch user posts (used by admin dashboard)
- **test_db_connection.php** - Database connectivity test page
- **test_create_sample_data.php** - Sample data creation for testing

#### Modified Files:
- **create_post.php** - Added audit logging when users create posts
- **announcements.php** - Fixed status field to ensure announcements are marked as 'published'
- **user-portal.php** - Added auto-refresh JavaScript for announcements
- **script.js** - Added admin dashboard auto-refresh functions for posts and announcements

### 3. Real-Time Updates

#### User Portal (user-portal.php)
- Announcements auto-refresh every 10 seconds
- User posts auto-refresh every 5 seconds
- Functions: `loadServerAnnouncements()`, `loadServerPosts()`
- Displays in "Updates" section on the right sidebar

#### Admin Dashboard (system-template-full.php)
- User posts auto-refresh every 5 seconds
- Recent announcements auto-refresh every 10 seconds
- Functions: `loadAdminPosts()`, `loadAdminAnnouncements()`
- Admin can moderate user posts with action buttons (Notify, Mark Inappropriate, etc.)

### 4. Audit Logging

All actions are logged to `audit_logs` table:
- User creates post → logged
- Admin creates announcement → logged
- Admin marks post as reviewed → logged
- Admin takes moderation action → logged

### 5. Data Flow

```
ADMIN SIDE:
Admin creates announcement
    ↓
create_announcement.php → INSERT into announcements table
    ↓
Logged to audit_logs table
    ↓
User portal fetches from get_announcements_api.php
    ↓
Appears in user's "Updates" section (auto-refreshes every 10s)

---

USER SIDE:
User posts concern/suggestion
    ↓
create_post.php → INSERT into posts table
    ↓
Logged to audit_logs table
    ↓
Admin dashboard fetches from get_posts_api.php
    ↓
Appears in admin's "User Posts" section (auto-refreshes every 5s)
    ↓
Admin can review, notify, moderate
    ↓
User can see admin's response/notification
```

## Testing the Setup

### Quick Test Pages:
1. **test_db_connection.php** - Verify all tables exist and are accessible
2. **test_create_sample_data.php** - Create sample announcements and posts for testing

### Manual Testing:
1. **Admin Posts Announcement:**
   - Login as admin
   - Go to System Template (Admin Dashboard)
   - Fill in "Announcement title" and "announcement_content"
   - Click "Publish"
   - Switch to user portal (refresh)
   - Should see announcement in "Updates" section within 10 seconds

2. **User Posts Concern:**
   - Login as user
   - Go to User Portal
   - Type in the post box "What's on your mind?"
   - Click "Post"
   - Switch to admin dashboard
   - Should see post in "User Posts" section within 5 seconds

3. **Check Audit Logs:**
   - Go to Admin Dashboard
   - Scroll to "Audit Logs" section
   - Should see entries for:
     - User posts created
     - Admin announcements posted
     - Admin actions taken

## API Endpoints

### get_announcements_api.php
```
GET /get_announcements_api.php?limit=10
Returns: JSON array of announcements
```

### get_posts_api.php
```
GET /get_posts_api.php?limit=30&offset=0
Requires: Admin authentication
Returns: JSON array of user posts
```

## Database Queries Used

### Fetch Latest Announcements
```sql
SELECT id, title, content, admin_user, created_at 
FROM announcements 
WHERE status = 'published' AND visibility = 'public' 
ORDER BY created_at DESC 
LIMIT 10
```

### Fetch User Posts
```sql
SELECT id, user_id, author, content, created_at 
FROM posts 
ORDER BY created_at DESC 
LIMIT 30
```

### Fetch Audit Logs
```sql
SELECT * FROM audit_logs 
ORDER BY timestamp DESC 
LIMIT 100
```

## Troubleshooting

### Announcements not showing in user portal:
1. Check if admin is logged in with correct role
2. Verify announcement was created (check announcements table in phpMyAdmin)
3. Check browser console for JavaScript errors
4. Run test_db_connection.php to verify table exists

### Posts not showing in admin dashboard:
1. Check if user is logged in
2. Verify post was created (check posts table in phpMyAdmin)
3. Ensure admin is viewing the correct page (system-template-full.php)
4. Check admin refresh interval (every 5 seconds by default)

### Audit logs not recording:
1. Verify audit_logs table exists
2. Check that logAction() function is being called
3. Verify session has user_id and fullname set

### Auto-refresh not working:
1. Check browser's Network tab (DevTools) to see if API calls are being made
2. Verify get_announcements_api.php and get_posts_api.php files exist
3. Check JavaScript console for errors
4. Clear browser cache and refresh

## Configuration

### Refresh Intervals (in milliseconds):
- Admin announcements: 10000 (10 seconds)
- User posts: 5000 (5 seconds)

To change intervals, edit in user-portal.php and script.js:
```javascript
setInterval(loadServerAnnouncements, 10000); // Change 10000 to desired milliseconds
setInterval(loadServerPosts, 5000); // Change 5000 to desired milliseconds
```

## Security Notes

- All inputs are properly sanitized using htmlspecialchars()
- Prepared statements are used for all database queries (SQL injection prevention)
- Admin actions require proper authentication checks
- User sessions are validated before allowing actions
- Audit logs store IP addresses and user agents for security tracking

## Database Connection Details

Located in: **db.php**
```php
$conn = new mysqli("localhost", "root", "", "pc_db");
```

All functions that access the database use this global $conn connection.

## Files Summary

| File | Purpose |
|------|---------|
| db.php | Database connection |
| announcements.php | Announcement CRUD functions |
| posts.php | Post CRUD functions |
| audit-log.php | Audit logging functions |
| create_announcement.php | API to create announcements |
| create_post.php | API to create user posts |
| get_announcements_api.php | API to fetch announcements |
| get_posts_api.php | API to fetch user posts |
| user-portal.php | User dashboard with auto-refresh |
| system-template-full.php | Admin dashboard with auto-refresh |
| script.js | JavaScript for admin dashboard |
| test_db_connection.php | Connection testing page |
| test_create_sample_data.php | Sample data creation page |

## Next Steps

1. Test the setup using test pages
2. Create real announcements and posts
3. Monitor audit logs for activity
4. Adjust auto-refresh intervals if needed
5. Monitor database size as data accumulates
6. Consider archiving old logs periodically
