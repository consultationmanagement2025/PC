# User Activity Logging System - Implementation Summary

## Overview
A comprehensive user action logging system has been added to track all user activities separately from admin audit logs.

## Files Created

### 1. DATABASE/user-logs.php
- **Purpose**: Database module for user action logging
- **Key Functions**:
  - `logUserAction()` - Records user actions to the database
  - `getUserLogs()` - Retrieves user logs with filtering
  - `getUserLogsCount()` - Gets total count of user logs
  - `getUserActivitySummary()` - Provides activity statistics
  - `getActionStats()` - Gets action type breakdown
- **Table Created**: `user_logs` (auto-created on first use)
  - Fields: id, user_id, username, action, action_type, entity_type, entity_id, description, ip_address, user_agent, timestamp, status, details

### 2. API/get_user_logs_api.php
- **Purpose**: REST API endpoint for fetching user logs
- **Authentication**: Requires admin role
- **Parameters**: 
  - `limit` - Number of records (default: 100)
  - `offset` - Pagination offset
  - `filter_user` - Filter by username
  - `filter_action` - Filter by action type
  - `filter_type` - Filter by entity type
  - `filter_date` - Filter by specific date
- **Returns**: JSON array of user logs

## Files Modified

### 1. ASSETS/js/app-features.js
- Added case for 'user-logs' in showSection() switch
- Added `renderUserLogs()` function with full UI
- Added `loadUserLogsFromDatabase()` async function
- Added `updateUserLogStats()` function for statistics
- Added `filterUserLogs()` function with filtering logic
- Added `resetUserLogFilters()` function
- Added `getUserActionBadge()` function for action badges

### 2. system-template-full.html
- Added "User Activity" menu item to desktop sidebar
- Added "User Activity" menu item to mobile sidebar
- Both use `onclick="showSection('user-logs')"`

### 3. system-template-full.php
- Added `require 'DATABASE/user-logs.php';` to load user log functions

### 4. AUTH/login.php
- Added user-logs.php requirement
- Log successful login with action='login'
- Log failed login attempts with status='failure'
- Logs include user details and authentication context

### 5. AUTH/logout.php
- Added user-logs.php requirement
- Logs logout action before session destruction

### 6. create_post.php
- Added user-logs.php requirement
- Log successful post submission with action='submitted_post'
- Log failed post creation with status='failure'

## User Actions Being Logged

| Action | Context | Entity Type | When Logged |
|--------|---------|-------------|------------|
| login | authentication | user | User logs in successfully |
| logout | authentication | user | User logs out |
| submitted_post | post | consultation_post | User creates new post |
| viewed_consultation | consultation | dashboard | User views consultation |
| edited_post | post | consultation_post | User edits existing post |
| commented | comment | post | User adds comment |
| approved | content | post | User approves content |
| rejected | content | post | User rejects content |

## User Logs UI Features

### Dashboard Section
- **Title**: "User Activity Logs"
- **Subtitle**: "Monitor all user actions and interactions"

### Summary Statistics
- **Total Actions**: Count of all recorded user actions
- **Active Users Today**: Count of unique users who acted today
- **Unique Users**: Total count of distinct users

### Filters
- **Action Filter**: Dropdown with predefined actions (Login, Logout, Submitted Post, Viewed Consultation, Edited Post, Commented)
- **Username Filter**: Text input for filtering by user
- **Date Filter**: Date picker for filtering by specific date
- **Reset Button**: Clears all filters

### Activity Table
Displays the following columns:
- **Timestamp**: When the action occurred
- **Username**: Who performed the action
- **Action**: What was done (with color-coded badge)
- **Entity Type**: Type of resource affected
- **Status**: Success/Failure status
- **IP Address**: User's IP address

## Color-Coded Action Badges

- **Submitted Post**: Blue badge
- **Viewed Consultation**: Purple badge
- **Login**: Green badge
- **Logout**: Gray badge
- **Edited Post**: Yellow badge
- **Commented**: Orange badge
- **Approved**: Green badge
- **Rejected**: Red badge

## Status Badges

- **Success**: Green badge with "Success" text
- **Failure**: Red badge with "Failed" text

## Data Flow

```
User Action
    ↓
logUserAction() [called from various files]
    ↓
user_logs table (MySQL)
    ↓
Admin views "User Activity" section
    ↓
API/get_user_logs_api.php fetches from database
    ↓
JavaScript renders with filtering/stats
    ↓
Admin dashboard displays filtered logs
```

## Integration Points

### Login/Logout Flow
- Users logging in/out are automatically logged to user_logs table
- Failed attempts are also recorded

### Post Creation
- Every post submission is tracked with post ID
- Includes success/failure status

### Future Integration Points
- Comment creation
- Consultation view events
- Post edits
- Content approvals/rejections

## Security Features

- **Authentication**: Only admins can view user logs via API
- **IP Tracking**: All actions record user's IP address
- **User Agent**: Browser/device information captured
- **Session-based**: Requires active session for logging
- **Database-backed**: Data persists in MySQL database

## Statistics Captured

- Total action count
- Per-user activity breakdown
- Daily/weekly activity trends (queryable via filters)
- Action type distribution
- Success/failure rates
- User engagement metrics

## Notes

- User logs are stored separately from admin audit logs
- System automatically initializes tables on first access
- Logs include comprehensive metadata (IP, User Agent, timestamps)
- Supports pagination through limit/offset parameters
- Filters can be combined for advanced searching
