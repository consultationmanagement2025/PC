# Google Sign-In Setup Guide

## Steps to Configure Google Sign-In

### 1. Create a Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing one)
3. Enable the Google+ API

### 2. Create OAuth 2.0 Credentials
1. Go to **APIs & Services** > **Credentials**
2. Click **Create Credentials** > **OAuth client ID**
3. Choose **Web application**
4. Add Authorized JavaScript origins:
   - `http://localhost`
   - `http://localhost:80`
   - `http://your-domain.com`
   - `https://your-domain.com`

5. Add Authorized redirect URIs:
   - `http://localhost/xampp/PC/`
   - `https://your-domain.com/`

6. Copy the **Client ID** (you'll need this)

### 3. Update Configuration Files

#### In `login.php`:
Replace `YOUR_GOOGLE_CLIENT_ID` with your actual Client ID:
```javascript
data-client_id="YOUR_CLIENT_ID.apps.googleusercontent.com"
```

#### In `AUTH/google_auth.php`:
Replace `YOUR_GOOGLE_CLIENT_ID` with your actual Client ID:
```php
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
```

### 4. Database Requirements

Make sure your `users` table has these columns:
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `username` (VARCHAR)
- `email` (VARCHAR, UNIQUE)
- `fullname` (VARCHAR)
- `password` (VARCHAR)
- `role` (VARCHAR, DEFAULT 'citizen')
- `created_at` (TIMESTAMP)
- `last_login` (TIMESTAMP, nullable)

### 5. How It Works

1. User clicks the Google Sign-In button
2. Google OAuth dialog appears
3. User authenticates with their Google account
4. Google returns an ID token
5. Frontend sends token to `AUTH/google_auth.php`
6. Backend verifies the token with Google's servers
7. If user exists: logs in with existing account
8. If user doesn't exist: creates new account automatically
9. User is redirected to appropriate dashboard

### 6. Testing

1. Navigate to `login.php`
2. Click on the Google Sign-In button
3. You should see Google's OAuth dialog
4. After authentication, you'll be logged in and redirected

### Security Notes

- The token verification is done server-side using Google's API
- Passwords for Google-authenticated users are randomly generated
- User data comes directly from Google (email verified)
- Always use HTTPS in production
- Keep your Client ID and secret secure

### Troubleshooting

**"Invalid or expired token"**
- Make sure your domain is listed in authorized origins
- Check that Client ID is correct

**Button not appearing**
- Check browser console for JavaScript errors
- Verify Google Sign-In script is loaded
- Check Client ID configuration

**Login fails but no error**
- Check server logs for PHP errors
- Verify database connection in `db.php`
- Ensure `users` table has all required columns
