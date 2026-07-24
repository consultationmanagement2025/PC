# LGU-2 Capstone — Live Deployment Checklist

Use this after uploading files, setting config (`.env`), and importing each system's `DEPLOY.sql` database.

**Live subdomains**

| System | URL | Database |
|--------|-----|----------|
| ORTS | https://ort.spvalenzuela.com | `ort_lgu` |
| LACS | https://lacs.spvalenzuela.com | `lacs` |
| CMS | https://cms.spvalenzuela.com | `legislative_cms` |
| PHMS | https://phms.spvalenzuela.com | `phms_capstone` |
| PCMS | https://consultation.spvalenzuela.com | `pc_db` |

**PHMS portal hostnames** (same PHMS app / `public_html`; add DNS + SSL):

| Portal | Hostname |
|--------|----------|
| Staff | `phms.spvalenzuela.com` |
| Employee | `employee.phms.spvalenzuela.com` |
| Citizens | `phms-citizens.spvalenzuela.com` (also accepts `phms-citizen.spvalenzuela.com`) |

---

## 1. Server folder layout (every subdomain)

Each hosting account should look like this:

```
/home/{subdomain}/
├── public_html/          ← That system's app files + .env
│   ├── .env
│   └── api/              ← or API/ on PCMS (Linux is case-sensitive)
│       └── .htaccess     ← Authorization passthrough (required on live)
└── shared/               ← Same copy on all 5 accounts (sibling of public_html)
    ├── app_env.php
    └── integration/
        ├── .env          ← Same file on all 5 servers
        ├── tokens.php
        ├── env.php
        ├── HttpClient.php
        ├── common.php
        └── smoke_test.php
```

Upload **only that system's folder** into `public_html` (not the whole monorepo).  
Upload the entire **`shared/`** folder to the account root (sibling of `public_html`).

When updating integration after a code fix, re-upload the **whole `shared/` folder** to all 5 servers, plus each system's `api/.htaccess` (see §6).

**PCMS path note:** folder is `API/` (uppercase). Peer URLs use `/api/v1`. On Linux, if lowercase `/api/v1` 404s, add a rewrite or symlink so `https://consultation.spvalenzuela.com/api/v1` reaches `API/v1`.

---

## 2. SQL — one file per system

Import **only** the matching `DEPLOY.sql` in phpMyAdmin:

| System | File |
|--------|------|
| ORTS | `ORTS/database/DEPLOY.sql` |
| LACS | `LACS/database/DEPLOY.sql` |
| CMS | `CMS/database/DEPLOY.sql` |
| PHMS | `PHMS/database/DEPLOY.sql` |
| PCMS | `PCMS/DATABASE/DEPLOY.sql` |

**ORTS note:** The repo has no full ORTS data dump. If the live DB is new:

1. Import your existing `ort_lgu` export from XAMPP/production first, **then**
2. Import `ORTS/database/DEPLOY.sql` for integration tables/columns.

Verify after import:

```sql
SHOW TABLES LIKE 'integration_clients';
```

---

## 3. Per-system `.env` (in `public_html`)

Copy from each system's `.env.example` (LACS: create `public_html/.env` from the values below) and set **live** values from CyberPanel / hosting.

| Variable | Example |
|----------|---------|
| `DB_HOST` | Usually `localhost` on same server |
| `DB_USER` / `DB_PASS` / `DB_NAME` | From hosting panel (each subdomain has its own DB user) |
| `APP_URL` | That system's full HTTPS URL (no trailing slash) |
| `APP_ENV` | `production` |
| `DEBUG` | `false` |

**APP_URL per system**

```
ORTS  → https://ort.spvalenzuela.com
LACS  → https://lacs.spvalenzuela.com
CMS   → https://cms.spvalenzuela.com
PHMS  → https://phms.spvalenzuela.com
PCMS  → https://consultation.spvalenzuela.com
```

### 3a. Database credentials — `.env` vs `config.php`

Live DB usernames/passwords are **different per subdomain** (and different from local XAMPP `root` / empty password). Set them in **`public_html/.env` first** — that is the source of truth. Config PHP files should only provide fallbacks for local/dev.

| System | Set live creds in | Config that reads `.env` | Do not leave as XAMPP defaults |
|--------|-------------------|--------------------------|--------------------------------|
| ORTS | `public_html/.env` | `config/db_config.php` | `DB_USER=root`, empty `DB_PASS`, local `DB_NAME` |
| LACS | `public_html/.env` | `config/config.php` | `DB_USER=root`, `DB_NAME=lacs` (live often uses names like `lacs_lacs`) |
| CMS | `public_html/.env` | `config/database.php` | `root` / empty pass / local DB name |
| PHMS | `public_html/.env` | `config/config.php` → `config/database.php` | Any fallback user/pass hardcoded in `config.php` |
| PCMS | `public_html/.env` | `config.php` + `db.php` | `root` / empty pass / `pc_db` if hosting uses another name |

**Required steps on each live account**

1. In CyberPanel (or MySQL), note that subdomain’s **DB name**, **DB user**, and **DB password** (they are usually not shared across the five sites).
2. Edit **`public_html/.env`** and set:

```env
DB_HOST=localhost
DB_USER=the_live_db_user_from_panel
DB_PASS=the_live_db_password_from_panel
DB_NAME=the_live_database_name_from_panel
```

3. Confirm the matching config PHP is present and still loads `.env` via `shared/app_env.php` (do **not** paste live passwords into git / leave old local passwords in uploaded config unless you intentionally override).
4. If you previously hardcoded credentials inside a config PHP for testing, **remove or replace them** so live uses `.env` only — otherwise a wrong leftover password will override or confuse debugging.
5. After saving, test login / a simple page. Connection errors almost always mean `.env` `DB_*` does not match the panel (wrong user, pass, or DB name).

**PHMS note:** `PHMS/config/config.php` has local fallback defaults for `DB_USER` / `DB_PASS`. On live, always set real values in `public_html/.env` so those fallbacks are never used.

**Optional (PCMS → PHMS DB lookup):** if consultations need a direct PHMS database read, also set in `shared/integration/.env` on the PCMS server:

```env
PHMS_DB_HOST=localhost
PHMS_DB_NAME=phms_capstone   # or the live PHMS DB name
PHMS_DB_USER=...
PHMS_DB_PASS=...
```

Those must match the **PHMS** database credentials, not PCMS’s.

---

## 4. Integration `.env` (same on all 5 servers)

Path: `shared/integration/.env`  
Template: `shared/integration/.env.example`

```env
LGU2_DEPLOYMENT=subdomain
LGU2_DOMAIN=spvalenzuela.com
LGU2_URL_SCHEME=https

LGU2_ORTS_URL=https://ort.spvalenzuela.com/api/v1
LGU2_LACS_URL=https://lacs.spvalenzuela.com/api/v1
LGU2_CMS_URL=https://cms.spvalenzuela.com/api/v1
LGU2_PHMS_URL=https://phms.spvalenzuela.com/api/v1
LGU2_PCMS_URL=https://consultation.spvalenzuela.com/api/v1

LGU2_ORTS_SUBDOMAIN=ort
LGU2_PCMS_SUBDOMAIN=consultation
```

Localhost ignores this file automatically; local dev uses `http://localhost/CAPSTONE-LIVE/{SYSTEM}/api/v1`.

---

## 5. SSL / HTTPS

- Enable SSL on all five subdomains (and PHMS portal hosts if used).
- Integration URLs use `https://` — mixed HTTP/HTTPS will break cross-system calls.

---

## 6. Health check & seed integration tokens

### Important: browser vs curl

`/api/v1/health.php` is **not** a public page. It requires a Bearer token.

| How you call it | Result |
|-----------------|--------|
| Open URL in browser (no header) | Always `"success": false` — **Missing API key** (expected) |
| curl / PowerShell with `Authorization: Bearer …` | `"success": true` if deploy is correct |

A browser fail + curl success on the **same live URL** means the endpoint is working. Do **not** treat the browser alone as a failed deploy.

You can run health checks from your **local PC PowerShell** against the live domains — you do not need SSH on the server.

### Files required for live auth

On each system, ensure these are uploaded:

| Path in repo | Live destination |
|--------------|------------------|
| Entire `shared/` | `/home/{subdomain}/shared/` (all 5 servers) |
| `ORTS/api/.htaccess` | ORTS `public_html/api/.htaccess` |
| `LACS/api/.htaccess` | LACS `public_html/api/.htaccess` |
| `CMS/api/.htaccess` | CMS `public_html/api/.htaccess` |
| `PCMS/API/.htaccess` | PCMS `public_html/API/.htaccess` |
| `PHMS/api/.htaccess` | PHMS `public_html/api/.htaccess` |
| `PHMS/api/v1/bootstrap.php` | PHMS `public_html/api/v1/bootstrap.php` |

The `api/.htaccess` files pass the `Authorization` header through to PHP (CGI / LiteSpeed / some Apache hosts strip it otherwise).  
`tokens.php` seeds **every** system token into `integration_clients` (including that host’s own token) on first API hit — **no manual SQL INSERT**.

### PowerShell (Windows) — preferred from your PC

Use `curl.exe` so PowerShell does not use its `curl` alias:

```powershell
curl.exe -s -H "Authorization: Bearer ort_live_7f3a9c2e1b5840d6a8e4f1c9b7d50362" https://ort.spvalenzuela.com/api/v1/health.php
curl.exe -s -H "Authorization: Bearer lacs_live_4e8b1d9f2a6c7053e9b4f8a1c6d20745" https://lacs.spvalenzuela.com/api/v1/health.php
curl.exe -s -H "Authorization: Bearer cms_live_9c1e5a7b3f8042d6b8e2a4c7f1d90638" https://cms.spvalenzuela.com/api/v1/health.php
curl.exe -s -H "Authorization: Bearer phms_live_2d6f8a4c1e9057b3a9c5e7f2b4d80156" https://phms.spvalenzuela.com/api/v1/health.php
curl.exe -s -H "Authorization: Bearer pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328" https://consultation.spvalenzuela.com/api/v1/health.php
```

### bash / Linux

```bash
curl -s -H "Authorization: Bearer ort_live_7f3a9c2e1b5840d6a8e4f1c9b7d50362" https://ort.spvalenzuela.com/api/v1/health.php
curl -s -H "Authorization: Bearer lacs_live_4e8b1d9f2a6c7053e9b4f8a1c6d20745" https://lacs.spvalenzuela.com/api/v1/health.php
curl -s -H "Authorization: Bearer cms_live_9c1e5a7b3f8042d6b8e2a4c7f1d90638" https://cms.spvalenzuela.com/api/v1/health.php
curl -s -H "Authorization: Bearer phms_live_2d6f8a4c1e9057b3a9c5e7f2b4d80156" https://phms.spvalenzuela.com/api/v1/health.php
curl -s -H "Authorization: Bearer pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328" https://consultation.spvalenzuela.com/api/v1/health.php
```

Tokens are defined in `shared/integration/tokens.php` (rotate before production if needed; keep the **same** file on all 5 servers).

**Success** looks like:

```json
{"success":true,"message":"OK","data":{"system":"LACS","authenticated_as":"LACS","client_name":"Legislative Agenda and Calendar System","time":"..."}}
```

---

## 7. Verify integration URLs

Confirm peer bases match live (from `shared/integration/.env` on each server):

- `https://ort.spvalenzuela.com/api/v1`
- `https://lacs.spvalenzuela.com/api/v1`
- `https://cms.spvalenzuela.com/api/v1`
- `https://phms.spvalenzuela.com/api/v1`
- `https://consultation.spvalenzuela.com/api/v1`

`shared/integration/` is blocked from the web (`.htaccess`). Prefer checking `.env` in File Manager / SSH, or run `smoke_test.php` via CLI. Do not rely on opening `tokens_info.php` in a browser on production (it returns 403 outside localhost/dev).

---

## 8. Run integration smoke test

On a machine with PHP CLI that can reach all five HTTPS domains (or on a server):

```bash
php shared/integration/smoke_test.php
```

Expect **PASS** on health checks and sample event flows (ORTS→LACS/CMS/PHMS, PHMS→PCMS, CMS→ORTS, etc.).  
Web requests to `smoke_test.php` return **403** (CLI only).

---

## 9. Log in to each application

| System | Check |
|--------|--------|
| ORTS | Staff login, documents load |
| LACS | Login, calendar/sessions |
| CMS | Login, committees/meetings |
| PHMS | Login, hearings list |
| PCMS | Public portal + admin login |

Change default passwords on live.

---

## 10. Test one end-to-end integration flow

Pick at least one path and confirm data appears in the peer system:

1. **ORTS → LACS + CMS** — New proposal → agenda slot + committee assignment  
2. **ORTS → PHMS** — Status “for public hearing” → hearing/notice in PHMS  
3. **PHMS → PCMS** — Hearing registration → `hearing_queue` / consultation in PCMS  
4. **CMS → ORTS** — Committee findings → ORTS document status update  
5. **PCMS → PHMS** — Consultation closed → PHMS sync event  

---

## 11. PHMS extras (if using live hearings)

- Node server running (`PHMS/app.js`, default port `3001`)
- `.env`: `NODE_SERVER_PORT`, LiveKit/Zoom keys as needed
- LiveKit webhook: `https://phms.spvalenzuela.com/api/livekit-webhook.php`
- SMTP and `FACEBOOK_POST_ENABLED` if posting notices
- Cron jobs for reminders, if configured

---

## 12. Email

Configure SMTP in PHMS, PCMS, and CMS (mail settings / `.env`).  
Send one test email per system (password reset or notification).

---

## 13. Security hardening

- [ ] `.env` not publicly downloadable (ensure web server blocks `.env` files)
- [ ] Confirm direct web access to sensitive directories is blocked (pre-configured via `.htaccess` files):
  - `shared/integration/` (denies web requests; CLI runs for `smoke_test.php` still allowed)
  - `PHMS/install/` (denies web requests to protect utility/setup scripts)
  - `PCMS/DATABASE/` (denies web requests to protect SQL schema and table setup scripts)
  - `{SYSTEM}/database/` (denies web requests to protect SQL deployment scripts)
  *Note: Make sure your Apache / LiteSpeed configuration allows `.htaccess` overrides (`AllowOverride All` or equivalent).*
- [ ] Verify file-level safety checks are active:
  - `shared/integration/tokens_info.php` returns `403 Forbidden` on non-localhost/non-dev environments.
  - `shared/integration/smoke_test.php` returns `403 Forbidden` when triggered via web server (only allowed via CLI).
- [ ] Upload folders writable only where needed (`uploads/`, etc.)  
- [ ] Replace default admin credentials  

**Typical permissions (Linux)**

```
public_html/         755
public_html/uploads/ 775 (or 755 per host policy)
shared/              755
.env                 640
```

---

## 14. Optional seed data

**CMS sample committees** (optional, after `DEPLOY.sql`):

```
CMS/seed_valenzuela_committees.sql
```

---

## Final checklist

- [ ] All 5 sites load over HTTPS  
- [ ] PHMS portal hostnames DNS + SSL (if used)  
- [ ] Each `public_html/.env` has correct DB + `APP_URL` (live `DB_USER` / `DB_PASS` / `DB_NAME` from hosting panel — not XAMPP `root`)  
- [ ] Config PHP files read `.env` (no leftover hardcoded local DB passwords on live)  
- [ ] Entire `shared/` folder present on all 5 servers (sibling of `public_html`)  
- [ ] `shared/integration/.env` identical on all 5 servers  
- [ ] Each system has `api/.htaccess` (or PCMS `API/.htaccess`) Authorization passthrough  
- [ ] Each database imported from `DEPLOY.sql`  
- [ ] `integration_clients` table exists in each DB  
- [ ] All 5 `/api/v1/health.php` return `"success":true` via **curl with Bearer** (browser alone will show false — ignore that)  
- [ ] At least one cross-system flow tested successfully  
- [ ] Email test sent  
- [ ] Dev/install scripts locked down  

---

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Browser shows `"success":false` / Missing API key | **Normal** — no Bearer header. Use PowerShell `curl.exe` with token |
| curl also Missing API key | Host stripped `Authorization` — upload `api/.htaccess` passthrough; confirm `AllowOverride` |
| **401** Invalid or inactive API key | Wrong token, or outdated `shared/integration/tokens.php` — re-upload whole `shared/` and hit health again |
| **Connection refused** between systems | Wrong `LGU2_*_URL` or `shared/` missing / not sibling of `public_html` |
| **DB error** on login | Wrong `DB_*` in that system’s `public_html/.env`, or still using XAMPP `root`/empty pass; check CyberPanel DB name/user/pass match `.env` (not only `config.php`) |
| Login works on one subdomain, not another | Each site has **its own** DB user/pass — copy-pasting one `.env` to all five will fail |
| Works locally, not live | `APP_URL` or integration `.env` still points to localhost |
| ORTS integration partial | Base `ort_lgu` missing — import base DB, then `ORTS/database/DEPLOY.sql` |
| PCMS `/api/v1` 404 on Linux | Folder is `API/` — add rewrite/symlink for lowercase `/api/v1` |
| **Duplicate column** on re-import | Safe to ignore if tables already exist; bootstrap also auto-migrates |

---

## Config layers (summary)

| Layer | File | Scope |
|-------|------|--------|
| Integration | `shared/integration/.env` + `tokens.php` | Same on all servers — peer API URLs + Bearer tokens |
| Application | `public_html/.env` | Unique per system — **live DB user/pass/name**, `APP_URL`, email |
| Config PHP | `config/*.php` / `db_config.php` | Reads `.env`; do not rely on local fallbacks on live |
| Database | `{SYSTEM}/database/DEPLOY.sql` | One import per system |
| Auth passthrough | `public_html/api/.htaccess` | Per system — keep Authorization header for PHP |

---

*Last updated for subdomain deployment on `*.spvalenzuela.com` (health checks via curl/PowerShell; browser-only checks are not valid).*
