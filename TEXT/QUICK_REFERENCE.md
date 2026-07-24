# 🎯 Quick Reference Card - Public Consultation System

## For Administrators

### Access Admin Panel
**URL**: `system-template-full.php`
**Required**: Administrator role
**Shortcut**: From main admin dashboard, click "Manage Consultations"

### Create a Consultation
1. **Fill Form Fields**:
   - Title: Main topic of consultation
   - Category: Select most relevant category
   - Description: Full details (copy from official document if available)
   - Start Date: When consultation begins
   - End Date: When consultation closes

2. **Click Create** ✅
3. **Appears Immediately** on public portal

### Sample Data - SMV Notice
```
Title: Notice of Public Consultation - Proposed Schedule of Market Values (SMV)
Category: Budget & Finance
Dates: January 28, 2026 to March 3, 2026
```

### Monitor Feedback
- **Dashboard Stats**: See total feedback, contributors, breakdown
- **Feedback Status**: Pending = awaiting your approval
- **Approve Feedback**: Click action buttons to review
- **Track Engagement**: View real-time statistics

### Manage Consultation
| Action | What It Does |
|--------|-------------|
| **View** | Opens detailed consultation information |
| **Close** | Ends consultation (stops new feedback) |
| **Delete** | Removes consultation completely |

### Quick Tip
> 📌 Consultations are PUBLIC immediately after creation. Only approved feedback appears to the public.

---

## For Citizens/Users

### Access Public Portal
**URL**: `public-consultations.php`
**Access**: Open to everyone (login not required to view)

### Browse Consultations
1. **Home Page**: See all active consultations
2. **Filters Available**:
   - 🔍 Search by title
   - 📁 Filter by category
   - 🔄 Reset to see all

3. **Click "View Details"** for full information

### Submit Feedback
1. **Click "Submit Feedback"** button
2. **Login Required** (if not already logged in)
3. **Fill Form**:
   - Your message (required)
   - Category: What type of feedback
   - Optional: Add details
4. **Click Submit** ✅
5. **Wait for Approval**: Admin reviews feedback
6. **Your feedback appears** once approved

### View Feedback
- **See Others' Input**: Read approved feedback from other citizens
- **Engagement Stats**: View how many people participated
- **Contributor Count**: See how many unique people contributed

### Quick Tip
> 💡 You must be logged in to submit feedback. Register first if you don't have an account.

---

## Navigation

### For Admins
**Dashboard** → "Manage Consultations" → `system-template-full.php`

### For Users
**Main Menu** → "Public Consultations" → `public-consultations.php`

---

## Key Features at a Glance

### Admin Features ⚙️
✅ Create/Edit/Delete consultations
✅ Monitor feedback submissions
✅ Approve/Reject feedback
✅ View real-time statistics
✅ Track engagement metrics
✅ Close consultations
✅ View contributor information

### User Features 📱
✅ Browse all active consultations
✅ Search and filter consultations
✅ View detailed information
✅ Submit feedback/comments
✅ See other people's feedback
✅ Track participation status
✅ Mobile-friendly interface

---

## Feedback Workflow

```
Citizen Submits Feedback
          ↓
Status: PENDING
          ↓
Admin Reviews
          ↓
   APPROVED ──→ Appears on Public Portal
          ↓
   REJECTED → Not shown publicly
```

---

## Status Meanings

| Status | Meaning | Color |
|--------|---------|-------|
| **Active** | ✅ Taking feedback now | 🟢 Green |
| **Closed** | ⏹️ No longer accepting | 🟠 Orange |
| **Draft** | 📝 Not published yet | ⚫ Gray |
| **Archived** | 📦 Completed & stored | ⚫ Dark |

---

## Category Options

When creating a consultation, choose from:
- 📊 Budget & Finance
- 📋 Policy & Governance
- 🏗️ Development & Planning
- 🌿 Environment
- 💼 Social Services
- 🛣️ Infrastructure
- 🎓 Education
- 🏥 Health
- ℹ️ Other

---

## Common Questions

### Q: How long does feedback approval take?
**A**: Typically within 24-48 hours. Admins review daily.

### Q: Can I edit my feedback after submitting?
**A**: Contact admin directly. System doesn't allow user edits once submitted.

### Q: How do I know if my feedback was approved?
**A**: Check the public portal - if you see your feedback, it was approved!

### Q: Can I submit feedback anonymously?
**A**: No, you must be logged in to submit feedback.

### Q: What happens to feedback if consultation is closed?
**A**: Already-approved feedback remains visible. New submissions are blocked.

### Q: Can I download a copy of all feedback?
**A**: Contact your administrator for this request.

---

## Getting Help

### For Admins:
- Check `PUBLIC_CONSULTATION_GUIDE.md`
- Review `SETUP_CHECKLIST.md`
- Check database directly with phpMyAdmin

### For Users:
- Look for "Help" section in navigation
- Contact your city government
- Email: [admin contact]
- Phone: [admin phone]

---

## Mobile Access

✅ **Fully responsive** on:
- iPhone/iPad
- Android phones/tablets
- Desktop computers
- Tablets

---

## Performance Tips

### Admin
- Use current date/time for consultation dates
- Keep descriptions under 5000 characters
- Approve feedback daily to keep queue short
- Archive old consultations to improve performance

### User
- Use the search function for faster browsing
- Submit clear, concise feedback
- Allow 24 hours for feedback approval
- Clear browser cache if issues occur

---

## Security & Privacy

🔒 Your information is secure:
- Password-protected admin area
- Only administrators can approve feedback
- Your email not publicly displayed
- System encrypted and backed up regularly

---

## Keyboard Shortcuts

**Admin Panel**:
- `Ctrl + S` - Save form (if form is open)
- `Esc` - Close modal
- `Tab` - Navigate between fields

**Public Portal**:
- `Ctrl + F` - Search page content
- `Enter` - Submit forms
- `Esc` - Close modals

---

## Error Messages & Solutions

| Error | Solution |
|-------|----------|
| "Not authenticated" | Login required - go to login page |
| "Consultation not found" | Consultation may be archived - contact admin |
| "Database error" | Refresh page, contact admin if persists |
| "Submission failed" | Check internet, try again, contact admin |

---

## Contact & Support

**Technical Issues**:
- Email: [tech support]
- Phone: [support number]
- Hours: [business hours]

**Feedback/Suggestions**:
- Go to Help section
- Or use contact form in main portal

---

## Quick Links

| Need | Link |
|------|------|
| Public Consultations | [public-consultations.php] |
| Admin Panel | [system-template-full.php] |
| User Portal | [user-portal.php] |
| Login | [login.php] |
| Register | [AUTH/register.php] |
| API Docs | [API/consultations_api.php] |

---

## System Status

✅ **Operational**
📅 **Last Updated**: February 8, 2026
🔄 **Auto-Update**: Every consultation cycle
📊 **Data Retention**: Permanent

---

**This Card Last Updated**: February 2026
**System Version**: 1.0 - Full Launch
**Status**: Ready for Production Use


