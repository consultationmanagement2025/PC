# 🚀 Production Setup Guide

## ⚠️ SECURITY - READ THIS FIRST!

### 1. Create Environment File
```bash
# Copy the template
cp .env.example .env

# Edit with your actual credentials
EMAIL_USERNAME=your_gmail@gmail.com
EMAIL_PASSWORD=your_app_password_here
EMAIL_FROM=Valenzuela City Government
```

### 2. NEVER Commit .env File
The `.env` file contains your Gmail password and is in `.gitignore` - **NEVER commit it!**

### 3. CloudServer Setup
On your production server:
```bash
# Upload files EXCEPT:
# ❌ .env (create on server)
# ❌ Debug files
# ❌ .git folder

# Create .env on server with production credentials
nano .env
```

### 4. Test Email on Production
```php
// Test email sending
<?php
require_once 'email_config.php';
try {
    sendGmailEmail('your@email.com', 'Test', 'Production email working!');
    echo "✅ Email working!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

## 🔐 Security Benefits

✅ **No Exposed Credentials** - Password in .env only
✅ **Git Safe** - .gitignore prevents commits
✅ **Portable** - Works with any Gmail account
✅ **CloudServer Ready** - No hardcoded secrets

## ⚠️ Important Notes

- Use a **dedicated Gmail account** for the system
- Generate **App Password** (not your real password)
- Keep `.env` file **secure** on production server
- **Never share** the .env file

## 🚨 If Gmail Gets Blocked

Alternative SMTP providers:
- SendGrid
- Mailgun  
- Amazon SES
- Your hosting provider's SMTP

Update .env accordingly:
```
EMAIL_HOST=smtp.sendgrid.net
EMAIL_PORT=587
EMAIL_USERNAME=apikey
EMAIL_PASSWORD=your_sendgrid_api_key
```
