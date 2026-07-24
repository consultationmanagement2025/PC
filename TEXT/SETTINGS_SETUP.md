## Database Setup Instructions

The Settings page now has fully working functionality! Follow these steps to set up the database:

### 1. Run the Database Migration
Visit this URL in your browser to add the required columns to the users table:
```
http://localhost/CAP101/PC/migrate_db.php
```

This will add the following columns if they don't exist:
- `username` - For user username
- `profile_photo` - For profile photo path
- `language` - For language preference (en/fil)
- `theme` - For theme preference (light/dark)
- `email_notif` - For email notifications toggle
- `announcement_notif` - For announcement notifications toggle
- `feedback_notif` - For feedback notifications toggle

### 2. Settings Features Now Available

#### Profile Tab (Working)
✅ Update full name
✅ Update email address
✅ Update username
✅ Upload profile photo
✅ Change password (with current password verification)

#### Preferences Tab (Working)
✅ Change language (English/Filipino)
✅ Change theme (Light/Dark mode)
✅ Toggle notification preferences (Email, Announcements, Feedback)
✅ Clear saved data

#### FAQs Tab
✅ 6 frequently asked questions with answers

#### Privacy Tab
✅ Privacy policy information
✅ Data collection details
✅ Data protection measures
✅ User rights information

### 3. API Endpoint
All settings are saved to: `/API/update_profile.php`

The endpoint handles these actions:
- `update_profile` - Save profile changes
- `change_password` - Change password with verification
- `upload_photo` - Upload profile photo
- `save_preferences` - Save language and notification preferences
