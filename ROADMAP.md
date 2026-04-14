# Media Buying Automation Platform — Roadmap

**Tech Stack:** ColdFusion 2023 · HTML5 · HTMX · Bootstrap 5 · MySQL 8 · Twilio (SMS) · Twilio SendGrid (Email)

---

## Phase 1 — Foundation (Complete in this build)

### 1.1 Infrastructure Setup
- [ ] Install ColdFusion 2023 on server
- [ ] Configure MySQL 8 datasource in CF Administrator (`mediapurchasing`)
- [ ] Run `sql/schema.sql` to create all tables and seed data
- [ ] Configure SendGrid account and obtain API key
- [ ] Configure Twilio account and obtain Account SID / Auth Token / phone number
- [ ] Set `app_base_url` and all API keys via `/admin/settings.cfm`
- [ ] Set up SSL certificate (HTTPS required for webhooks)
- [ ] Change default admin password (admin@example.com / Admin@1234)

### 1.2 Application Deployed
- [x] Application.cfc with session management and auth gate
- [x] MySQL schema (12 tables) with seed data
- [x] Full RBAC: Admin, Buyer, Client, Vendor roles
- [x] BCrypt password hashing (Spring Security library)
- [x] Login / logout / forgot password pages
- [x] Audit log on all write operations

---

## Phase 2 — Core Workflow (Complete in this build)

### 2.1 Media Buy Management
- [x] Create / edit media buys with line items
- [x] Dynamic line item calculator (JS, HTMX-ready)
- [x] Status pipeline: Draft → Sent to Vendor → Evaluation → Negotiating → Client Approval → Finalized
- [x] Visual status timeline bar
- [x] Filter and paginated media buy list

### 2.2 Email Communication (SendGrid)
- [x] `EmailService.cfc` — send via SendGrid v3 REST API
- [x] Template-based email sending with `{{variable}}` merge
- [x] All outbound/inbound email logged to `communications` table
- [x] Ad-hoc email composer (`/communications/compose.cfm`)
- [x] HTMX partial communication log per media buy
- [x] Send vendor request email from buy detail page
- [x] Send negotiation counter-offer email to vendor

### 2.3 Client Approval Portal
- [x] Approval token generated (UUID, 72-hr expiry, configurable)
- [x] Email sent to client with secure approval link
- [x] Public portal page (`/approvals/portal.cfm?token=...`) — no login
- [x] Client can Approve / Request Revision / Reject
- [x] Media buy status auto-updates on client response
- [x] Approval history on buy detail page

### 2.4 Rate Negotiation
- [x] Multi-round negotiation tracking in `negotiations` table
- [x] Counter-offer email sent to vendor automatically
- [x] Finalize agreed rate updates `negotiated_cost`
- [x] Negotiation history table on buy detail page

---

## Phase 3 — Billing Queue (Complete in this build)

### 3.1 Bill Intake
- [x] Manual bill entry with file upload
- [x] SendGrid Inbound Parse webhook (`/api/sendgrid_inbound.cfm`) — auto-creates bill from email
- [x] Vendor matched by from-address to `vendors.billing_email`
- [x] Bill linked to media buy (optional)

### 3.2 Bill Queue Management
- [x] `bill_queue` table with priority levels (urgent/high/normal/low)
- [x] Visual queue with priority-colored left border
- [x] Assign bills to team members
- [x] Status workflow: Queued → Under Review → Approved → Paid / Disputed
- [x] Dashboard count cards for queue state
- [x] Overdue highlighting when due date past

---

## Phase 4 — CMS & Administration (Complete in this build)

### 4.1 Email Template CMS
- [x] Full CRUD for email/SMS templates (`/admin/templates.cfm`)
- [x] Template categories: media_buy_request, negotiation, client_approval, bill_received, etc.
- [x] `{{variable}}` merge system — variables documented per template
- [x] Multi-channel: email, SMS, both
- [x] 5 default templates seeded

### 4.2 Workflow Settings CMS
- [x] `/admin/settings.cfm` — accordion grouped settings
- [x] API keys (SendGrid, Twilio) stored in DB, masked in UI
- [x] Approval link expiry, max negotiation rounds configurable
- [x] Live cache refresh — changes apply immediately without restart

### 4.3 CRM
- [x] Client management (create/edit)
- [x] Vendor management with media types, billing email
- [x] User management with role assignment

### 4.4 Audit Log
- [x] Every create/update/status change logged
- [x] Paginated audit log viewer (`/admin/audit_log.cfm`)

---

## Phase 5 — Next Steps (Future Sprints)

### 5.1 Twilio SMS Notifications
- Configure `/api/twilio_sms.cfm` webhook in Twilio console
- Send SMS approval notification to client mobile
- Two-way SMS: client replies "APPROVE" / "REVISE" to act on approval
- Buyer alerts for urgent bills, approval responses

### 5.2 SendGrid Inbound Parse — Enhanced Email Parsing
- Configure inbound parse domain in SendGrid Dashboard
- DNS: add MX record `mail.yourdomain.com → mx.sendgrid.net`
- Extract invoice number, amount, date from email body (regex or AI)
- Auto-match inbound emails to open media buys by subject/thread

### 5.3 Vendor Portal
- Self-service portal for vendors to submit invoices
- View negotiation status for their buys
- Digital signature / acceptance workflow

### 5.4 Client Portal Login
- Clients log in to view all their campaigns, approvals, invoices
- Dashboard showing spend by month, active campaigns

### 5.5 Reporting & Analytics
- Spend by client / vendor / media type / period
- Savings report (original_cost vs negotiated_cost)
- Approval turnaround time metrics
- Bill aging report (open invoices by due date)

### 5.6 PDF Generation
- Generate professional media buy proposal PDFs (for client approval emails)
- Invoice receipt PDFs
- Use CF's `cfdocument` tag

### 5.7 Calendar / Flight Schedule View
- Timeline/Gantt view of active campaigns by flight dates
- Conflict detection for overlapping buys at same vendor

### 5.8 Two-Factor Authentication
- TOTP (Google Authenticator) for admin/buyer roles
- SMS OTP via Twilio for login verification

---

## Twilio Reconfiguration Checklist

1. **Log into** [console.twilio.com](https://console.twilio.com)
2. **Account SID + Auth Token** → copy to `/admin/settings.cfm` under SMS Settings
3. **Phone Number** → Manage → Phone Numbers → Active Numbers
   - Under Messaging: Webhook URL = `https://yourdomain.com/api/twilio_sms.cfm` (HTTP POST)
4. **SendGrid Inbound Parse**:
   - Settings → Inbound Parse → Add Host & URL
   - Hostname: `mail.yourdomain.com`
   - URL: `https://yourdomain.com/api/sendgrid_inbound.cfm`
   - Add MX record at your DNS: `mail.yourdomain.com → mx.sendgrid.net` (priority 10)

---

## Directory Structure

```
mediapurchasing/
├── Application.cfc              — App bootstrap, auth gate, session
├── dashboard.cfm                — Main dashboard
├── index.cfm                    — Redirect to dashboard
├── ROADMAP.md                   — This file
│
├── sql/
│   └── schema.sql               — Full MySQL schema + seed data
│
├── config/
│   ├── settings.cfm             — App constants
│   └── reload.cfm               — Application reload endpoint
│
├── components/                  — ColdFusion CFCs
│   ├── BaseService.cfc          — Shared helpers (paginate, auditLog)
│   ├── AuthService.cfc          — Login, user management, role enforcement
│   ├── MediaBuyService.cfc      — Buy CRUD, negotiation, status workflow
│   ├── ApprovalService.cfc      — Token generation, portal, client response
│   ├── EmailService.cfc         — SendGrid API, template merge, inbound parse
│   ├── SMSService.cfc           — Twilio SMS send/receive
│   ├── BillingService.cfc       — Bill queue management
│   └── CRMService.cfc           — Clients, vendors, templates, settings
│
├── includes/
│   ├── header.cfm               — HTML head, Bootstrap, flash messages
│   ├── nav.cfm                  — Navbar with role-based menu
│   ├── footer.cfm               — JS includes, closing tags
│   └── error.cfm                — Error display page
│
├── auth/
│   ├── login.cfm
│   ├── logout.cfm
│   └── forgot_password.cfm
│
├── media-buys/
│   ├── index.cfm                — Paginated list with status filters
│   ├── create.cfm               — New buy form with dynamic line items
│   ├── edit.cfm                 — Edit buy
│   └── view.cfm                 — Buy detail + workflow actions + comm log
│
├── approvals/
│   ├── index.cfm                — All approvals list
│   └── portal.cfm               — PUBLIC client approval portal (no login)
│
├── billing/
│   ├── index.cfm                — Bill queue with priority cards
│   ├── create.cfm               — Manual bill entry
│   └── view.cfm                 — Bill detail + status/assignment actions
│
├── communications/
│   ├── index.cfm                — Full communication log
│   ├── compose.cfm              — Ad-hoc email composer
│   └── partial_log.cfm          — HTMX partial for buy detail comm log
│
├── admin/
│   ├── users.cfm                — User CRUD
│   ├── clients.cfm              — Client CRM
│   ├── vendors.cfm              — Vendor CRM
│   ├── templates.cfm            — Email/SMS template CMS
│   ├── settings.cfm             — Workflow settings CMS
│   └── audit_log.cfm            — Audit trail viewer
│
├── api/
│   ├── sendgrid_inbound.cfm     — SendGrid inbound parse webhook
│   └── twilio_sms.cfm           — Twilio SMS webhook
│
├── assets/
│   ├── css/app.css              — Custom styles + status badge colors
│   └── js/app.js                — HTMX config, line items, confirm dialogs
│
└── uploads/
    └── bills/                   — Uploaded invoice files
```

---

## Security Notes

- All form inputs use `encodeForHTML()` / `encodeForHTMLAttribute()` (XSS protection)
- All DB queries use named `cfqueryparam` bindings (SQL injection protection)
- Approval tokens are UUID-based, single-use, time-limited
- API keys stored in database, never hardcoded
- Webhook endpoints return HTTP 200 always (prevents retry loops)
- BCrypt password hashing (requires Spring Security JAR in CF classpath)
- File uploads restricted to safe extensions; stored with UUID names
- Session-based auth gate in `Application.cfc::onRequestStart`
