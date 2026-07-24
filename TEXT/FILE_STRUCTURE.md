# PCMS File Structure (Current)

## Core Runtime
- `system-template-full.php` - main admin shell/layout
- `app-features.js` - main frontend modules and routing
- `public-portal.php` - public portal
- `public-consultations.php` - public consultation listing
- `login.php`, `logout.php`, `index.php`

## Backend Layers
- `API/` - JSON endpoints used by frontend modules
- `DATABASE/` - table initializers and data helpers
- `UTILS/` - shared utilities (security, PDF, OTP, etc.)
- `AUTH/` - auth-specific handlers/pages

## Assets & Uploads
- `ASSETS/` - app assets
- `images/` - legacy/static images
- `uploads/` - user uploaded files
- `VIEWS/` - UI fragments/templates

## Diagnostics & Test Utilities
- `TOOLS/diagnostics/` - debug/check scripts
- `TOOLS/tests/` - manual test scripts

## Notes
- Production runtime files remain in project root for compatibility.
- Non-production debug/test scripts were moved from root into `TOOLS/*` for cleaner structure.
