# Bride/Groom Matrimony — Existing Project Audit, Admin Panel, Paid Plans & Secure Payment Implementation Plan

**Document purpose:** implementation specification for extending the existing PHP/MySQL matrimony application in `Matrimony.rar` without replacing its current user experience or breaking existing profile, match, privacy, and safety workflows.

**Deployment target:** PHP + MySQL on Hostinger shared hosting / `public_html` where supported. Keep the solution compatible with shared hosting; do not require a VPS, Docker, a background worker, or a permanently running Node.js process in production.

---

## 1. Executive summary

Extend the current application with:

1. A secure admin area with role-based access control and complete, audited CRUD for users, profiles, plan definitions, payment records, interests, reports, and configuration.
2. User registration, login, profile creation/editing, profile browsing, and existing match features.
3. Four configurable paid membership plans based on the supplied payment poster.
4. Razorpay payment checkout, server-side signature verification, and webhook verification.
5. An admin approval/activation queue: a payment must be verified first; only then can an authorized admin activate the purchased plan. The payment gateway callback alone must never grant membership.
6. Server-enforced membership limits for sending interests and viewing/using plan-restricted contact details.
7. Email notifications to the admin and user, plus an in-panel notification and audit trail.
8. Security, privacy, abuse prevention, recovery, testing, deployment, and operations controls.

**Recommended gateway:** Razorpay, subject to account onboarding and merchant approval. For an India-based PHP project, it is generally the most practical first integration because it supports common Indian payment methods including UPI. Availability, settlement, supported methods, fees, and approval times depend on the merchant account and must be confirmed with Razorpay. No gateway can guarantee immediate activation of a merchant account.

**Critical distinction:** `payment_status = paid` and `membership_status = active` are different facts. A verified payment creates an activation request. The admin reviews the request and activates the plan. The app must not trust a browser redirect, client-side JavaScript, a submitted amount, or an unverified payment ID.

---

## 2. Existing project inspection: observed structure

The supplied archive is a PHP application with a substantial existing feature set. The archive listing includes:

- `index.php`, `partials/header.php`, `partials/footer.php`, and `partials/layouts/main.php`.
- Application configuration and bootstrap files under `app/config/` and `app/includes/`.
- Database connection code under `app/src/Database/Connection.php`.
- HTTP/auth/CSRF infrastructure under `app/src/Http/` and `app/includes/helpers/csrf.php`.
- Existing user registration/login modules under `app/modules/users/` and `app/modules/login/`.
- Profile pages and APIs under `app/modules/profile/` and `app/api/profile/`.
- Match browsing, filtering, actions, saved searches, notifications, shortlists, viewed profiles, and interest-related views/APIs under `app/modules/matches/` and `app/api/matches/`.
- Existing privacy, safety, terms, cookies, FAQ, contact, and about pages.
- A main SQL schema at `app/database/matrimony_complete.sql`, seed data, and migrations.
- `.env`, `.env.example`, `.htaccess`, and `composer.json`.

### Important scope limitation

This is a structure-level audit based on the uploaded archive listing and the information available in this session, not a claim that every line of all 5,111 archive entries has been executed or security-tested. The listing did not show an obvious existing admin module or payment/plan module. Before implementation, inspect the full current source and database schema, then reuse existing functions and table conventions rather than duplicating them.

### Existing features to preserve

- Current registration and login flow.
- Existing personal, family, lifestyle, horoscope, preference, and photo/profile functionality.
- Match filtering, profile browsing, shortlisting, profile views, notifications, and interest-related pages.
- Existing block/report, privacy, account deactivate/reactivate, and view-as/privacy controls.
- Existing page layout, branding, CSS/JS, and responsive behavior unless a change is needed for a new feature.
- Existing database IDs and relationships. Do not drop or rename existing tables/columns without a documented migration and rollback plan.

### Immediate secret-handling action

The archive contains a `.env` file. Treat any credentials, mail passwords, database passwords, API keys, signing secrets, or other secrets included in an uploaded archive as exposed to everyone who received that archive. **Rotate them before deployment** and never commit or publicly upload `.env`. Keep only placeholders in `.env.example`. If real Razorpay keys or SMTP credentials were ever included in source control, revoke/rotate them.

---

## 3. Product rules and plan catalogue

The supplied artwork defines these plans. Store the values in the database; do not hard-code plan limits throughout PHP files.

| Plan | Price | Contact allowance | Duration |
|---|---:|---:|---|
| Silver Plan | ₹1,000 | 25 contacts | 45 days |
| Gold Plan | ₹3,000 | 45 contacts | 90 days |
| Platinum Plan | ₹10,000 | Unlimited contacts | 1 year |

### Define “contact” before coding

For enforcement, define a contact as a **unique member whose protected contact information is revealed to the paying member**. Opening a profile card or seeing public/non-sensitive fields should not consume a contact. A successful reveal consumes one contact only once per viewer/member pair, even if the same contact is opened repeatedly. The app should record the reveal event transactionally.

If the business instead intends “contact” to mean a sent interest, an accepted interest, or a phone-number reveal, that policy must be chosen explicitly and used consistently in the UI, database, and enforcement. This plan uses protected contact reveal as the default because the poster labels the allowance as contacts.

### Plan rules

- Plan prices, durations, allowances, currency, and active/inactive state are editable by authorized admins.
- The purchase records a snapshot of the plan name, price, currency, duration, and allowance at purchase time. Later edits to the plan catalogue must not silently change an existing purchase.
- Duration begins at the actual admin activation timestamp, not the payment initiation time.
- Expiration is calculated server-side using a consistent timezone policy (store timestamps in UTC; display in the configured local timezone).
- Unlimited allowance must be represented explicitly (e.g. `contact_limit = NULL` plus `is_unlimited = 1`), not by an arbitrary very large number.
- A user may have only one active membership at a time unless the product owner explicitly approves stacking/extension behavior.
- If an active user buys a new plan, place it in a pending activation queue and show the user the current plan and the requested plan. The admin must select whether the new plan replaces the current plan or starts after it; do not silently reset or stack plans.
- Refunds, chargebacks, disputed payments, duplicate payments, and failed payments must have defined handling. A refund/chargeback should flag the membership for review and may suspend access according to a documented admin policy.

---

## 4. Roles and permissions

Use server-side role-based access control (RBAC). Hiding buttons in the browser is not authorization.

### Roles

1. **Super Admin**
   - Full system administration, role management, admin creation/deactivation, plan management, user/profile management, payment review, membership activation/suspension, reports, audit logs, and security settings.
   - Only Super Admin can assign or remove administrative roles.
   - Protect the last active Super Admin from deletion/demotion.
2. **Admin**
   - Manage user accounts and profiles, review verified payment requests, activate/suspend memberships, manage plan catalogue if explicitly permitted, moderate interests/reports, and view permitted reports.
   - Cannot create another Super Admin, alter core security settings, or erase audit records.
3. **User**
   - Register/login, manage their own profile and privacy settings, browse eligible profiles, save/shortlist profiles, send interests only while membership permits, view their own payment history and membership state, and submit reports/blocks.
   - Cannot edit another user's private data or membership, mark their own payment as paid, activate their own plan, or access admin routes.
4. **Optional Support/Moderator role** (only if needed)
   - Handle reported profiles and support tickets with restricted access; no payment or role administration by default.

### Permission matrix

| Action | Super Admin | Admin | User |
|---|---|---|---|
| Create/edit/deactivate user accounts | Yes | Yes, audited | Own account only, restricted fields |
| Create/edit profile data | Yes, audited | Yes, audited | Own profile only |
| Assign admin roles | Yes | No | No |
| Create/edit/disable plans | Yes | If enabled by policy | No |
| View all payment records | Yes | Yes, limited to operational need | Own records only |
| Mark payment verified | No manual override of gateway evidence; may reconcile | No manual override of gateway evidence; may reconcile | No |
| Activate verified plan | Yes | Yes, if authorized | No |
| Suspend/revoke membership | Yes | Yes, reason required | Request cancellation/support only |
| Read audit log | Yes | Relevant operational entries only | Own activity/payment history only |
| Delete audit log | No UI deletion | No | No |

“Complete CRUD” must not mean unrestricted destructive deletion. Prefer deactivate/archive/soft-delete for accounts, plans, and business records. Financial transactions and security/audit records must be retained according to applicable legal/accounting requirements and must not be editable or deletable through normal CRUD screens.

---

## 5. User workflow

### 5.1 Registration and profile creation

1. Visitor opens registration.
2. Validate required fields on the server; normalize email/phone; enforce unique identifiers.
3. Hash passwords using PHP `password_hash()` with `PASSWORD_DEFAULT` (or a supported stronger algorithm); verify using `password_verify()`.
4. Verify email and, if required by the product, phone before enabling sensitive actions.
5. Create a user with role `user`, account state `pending_verification` or `active` according to the current verification policy, and no active membership.
6. Create the linked profile using the existing profile flow. Keep sensitive fields private by default.
7. User can edit their own permitted fields and control visibility/privacy.
8. Existing profile/match pages continue to work, but all server endpoints enforce ownership and privacy rules.

### 5.2 Browse profiles

- Logged-in users can browse profiles according to current filters and privacy settings.
- Display only approved public/profile fields to users who are allowed to see them.
- Do not expose phone, email, exact address, private photos, date of birth, family contact details, or other sensitive data in page HTML, hidden DOM nodes, JSON responses, network payloads, logs, analytics, or cache unless the viewer is authorized.
- The UI can display a locked-contact indicator and the viewer's remaining contact allowance.
- Blocked users, suspended accounts, hidden profiles, and profiles under moderation must not appear where policy forbids them.

### 5.3 Send interest

When the user clicks **Send Interest**:

1. Server verifies the session, CSRF token, account state, profile visibility, and target user's eligibility.
2. Server checks the viewer's current membership status and expiry in the database.
3. If no active plan exists, or the plan is pending activation/expired/suspended, do not create an interest. Redirect to the plans page with a safe return path.
4. The plan page displays all available plans and clearly explains price, contacts, duration, and limitations.
5. If a valid active plan exists, enforce any plan-specific interest policy, duplicate-interest prevention, block rules, rate limits, and target privacy.
6. Save the interest with a server-generated timestamp and audit event. Return a clear success response.
7. A front-end check may improve UX but is not security enforcement; the server must repeat every check.

**Important product decision:** the poster lists contact allowances, not interest allowances. Therefore this plan treats sending interests as available to any active paid member and applies the plan's numeric limit to protected contact reveals. If the business wants interests to consume the allowance too, revise this rule before implementation and label it clearly in the UI.

### 5.4 Contact reveal

1. User requests to reveal a contact.
2. Server validates login, membership active state, plan expiry, remaining allowance, target profile visibility, and block/privacy conditions.
3. If the user has no eligible plan or the allowance is exhausted, redirect to plans/upgrade page.
4. Use a database transaction and unique constraint so concurrent requests cannot exceed the allowance or count the same target twice.
5. Record the reveal in `contact_reveals`; return the contact details only after authorization succeeds.
6. Repeated reveals of the same member do not consume another contact.
7. Never send protected contact data in the initial profile-list API response.

### 5.5 Payment and admin activation

1. User chooses a plan and submits a purchase request.
2. Server loads the current active plan price/currency from the database; never trusts amount or plan limits from the browser.
3. Server creates a local purchase record in `pending_payment` and a Razorpay order via the server SDK.
4. Browser opens Razorpay Checkout using the public key ID and server-created order ID.
5. Razorpay returns a payment result. The browser result is informational only.
6. Server verifies the Razorpay payment signature using the official SDK and secret held server-side. Also verify that the order, amount, currency, and local purchase match.
7. Razorpay webhook endpoint verifies the webhook signature against the **raw request body** and a dedicated webhook secret. Process relevant events idempotently.
8. Once authoritative payment evidence is verified, set payment to `paid_verified` and create an admin activation request. Do not activate membership yet.
9. Notify the admin in the admin panel and by email. Notify the user that payment is received and activation is pending.
10. Admin opens the queue, reviews user, plan snapshot, amount, gateway order/payment IDs, verification status, and risk flags.
11. Admin clicks **Activate Plan** and confirms. Server rechecks the verified payment and the current membership state, then creates/updates the membership transactionally.
12. Store `activated_by`, `activated_at`, start/end timestamps, allowance snapshot, and audit event.
13. Notify the user by email and in-app notification. The user dashboard reflects the active plan.
14. Duplicate webhooks, retries, browser refreshes, or repeated admin clicks must not create duplicate memberships or double-activate the purchase.

### Payment status vs activation status

Use separate state machines.

**Payment:** `created` → `pending` → `paid_verified` / `failed` / `cancelled` / `refunded` / `disputed` / `needs_review`.

**Activation request:** `not_required` → `awaiting_admin` → `approved_activated` / `rejected` / `on_hold`.

A payment can be verified while the membership is still pending admin activation. Admins must never be able to activate a plan whose payment is not verified, except through a separately controlled, logged, explicit complimentary/manual-grant workflow requiring a reason and Super Admin permission. Do not present that exception as a normal payment flow.

---

## 6. Admin panel specification

Use the existing layout and style system where practical. Keep the admin interface separate from the public site navigation and enforce access in every request.

### 6.1 Dashboard

Show:
- Total registered users, active users, pending verifications, suspended/deactivated users.
- Profiles complete/incomplete and profiles pending moderation.
- Active memberships by plan, expired memberships, memberships expiring soon.
- Payments today/this month, verified payments awaiting activation, failed payments, refunds/disputes needing review.
- Pending activation requests, reported profiles, blocked-user reports, contact/support submissions.
- Recent security events and recent admin actions, with appropriate permission restrictions.

Dashboard figures must come from database queries and be permission-filtered. Avoid exposing full sensitive information in charts or summary widgets.

### 6.2 User management

- Search, filter, sort, and paginate users.
- Create user profile on behalf of a user; show a clear audit event indicating admin-created profile.
- View account status and profile completion.
- Edit permitted user/profile fields with field-level restrictions.
- Reset password through a secure reset flow; admins should not see existing passwords or set a known plaintext password.
- Trigger/resend email verification.
- Suspend/reactivate/deactivate accounts with required reason and confirmation.
- Review a user's membership, payment history, interests, contact reveals, blocks/reports where authorized.
- Prevent admins from viewing unnecessary sensitive data; mask contact details by default.
- Do not allow changing a user's role through a general profile edit form.

### 6.3 Profile management

- Search and filter profiles.
- View profile moderation status and privacy settings.
- Edit/correct profile information only when authorized; log before/after changes while excluding passwords, tokens, and unnecessary sensitive values.
- Approve, reject, hide, or flag a profile with a required reason where applicable.
- Manage photos safely: MIME/content validation, file-size and dimension limits, random server-side names, storage outside public web root if possible, image re-encoding, and no executable uploads.
- Respect block, privacy, and profile visibility controls.
- Avoid permanently deleting profiles with related payment/interest/audit history; use soft-delete/anonymization when legally appropriate.

### 6.4 Plans and pricing

- Create, edit, enable/disable, and order plans.
- Fields: name, slug, description, price in paise (integer), currency, duration value/unit, contact limit/unlimited flag, display order, active flag, effective dates if needed.
- Validate that price is non-negative and duration/allowance values are valid.
- Display a preview of the user-facing plan card.
- Keep plan edits versioned or snapshot each purchase so existing purchases remain auditable.
- Never let a normal admin edit the price of an already-created payment order.

### 6.5 Payment and activation queue

- Queue filters: awaiting verification, verified awaiting activation, activated, failed, refunded, disputed, needs review.
- Show local order reference, user ID/name, plan snapshot, amount/currency, gateway order ID, gateway payment ID, verification timestamps, webhook status, and activation status.
- Mask unnecessary personal data.
- Actions: view details, retry safe reconciliation, place on hold, activate verified plan, reject activation with reason, suspend/revoke membership with reason, mark refund/dispute for review.
- Do not provide a “mark as paid” button that bypasses gateway verification.
- Activation confirmation must explain start date, expiry, and allowance.
- Record the acting admin, timestamp, reason, old/new state, and relevant purchase ID.
- Use database transactions and idempotency to prevent duplicate activation.

### 6.6 Interests, moderation, reports, and safety

- Review reported profiles/interests and user-submitted abuse reports.
- Allow authorized moderation actions: dismiss, warn, hide profile, suspend account, or escalate.
- Require reason codes and notes for material actions.
- Preserve report evidence and audit history according to retention policy.
- Respect blocking: a blocked relationship must prevent new interest/contact actions and suppress inappropriate exposure.
- Do not allow staff to secretly impersonate users. If a support “view as” feature exists, restrict it, visibly mark it, log it, and never reveal passwords or payment secrets.

### 6.7 Admin accounts and audit logs

- Only Super Admin can create, deactivate, or change administrative roles.
- Require MFA for admins if the chosen deployment/authentication stack supports it; otherwise use a secure TOTP implementation or another strong second factor.
- Require reauthentication for role changes, payment activation, refund-related actions, and other high-risk operations.
- Show immutable audit history for logins, failed logins, profile changes, admin CRUD, plan changes, payment verification, activation, suspension, role changes, export, and sensitive-data access.
- Audit entries must be append-only for application roles. Do not store passwords, session IDs, API secrets, full card details, raw authentication tokens, or complete webhook secrets in logs.

---

## 7. Razorpay implementation specification

### Why Razorpay first

For this India-oriented PHP project, Razorpay is the recommended first choice due to common Indian payment methods and an established PHP SDK/Checkout workflow. Confirm current eligibility, supported payment methods, settlement arrangements, pricing, and business KYC directly with Razorpay. If the merchant account cannot be approved, evaluate Cashfree or PhonePe based on the business's eligibility and integration requirements. Stripe may be suitable for some international businesses, but country/business availability and payment-method support must be confirmed before committing.

### Required integration approach

- Use the official Razorpay PHP SDK installed through Composer during development/build.
- Keep SDK dependencies in `vendor/` and ensure the production deployment includes the required dependencies. Do not depend on running Composer on the Hostinger server if the hosting environment does not support it; build/package dependencies locally and upload safely.
- Store `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, and `RAZORPAY_WEBHOOK_SECRET` in environment configuration outside the public web root when possible. The key ID may be sent to the browser; the key secret and webhook secret must never be sent to the browser.
- Use test mode until end-to-end testing and admin reconciliation pass. Switch to live keys only after merchant onboarding, HTTPS, webhook setup, and operational checks.
- Create orders on the server with amount in integer paise, currency `INR`, and an internal receipt/reference. The server selects the amount from the database.
- Verify Checkout signatures server-side with the official SDK, and validate order ID/payment ID/amount/currency against the local purchase.
- Configure the webhook URL in the gateway dashboard and verify the signature using the exact raw request body and dedicated webhook secret.
- Support retries and duplicate webhook delivery. Store the provider event ID (or a deterministic unique event key) with a unique constraint and make processing idempotent.
- A webhook can arrive before or after the browser return; both paths must converge safely on the same payment record.
- Do not trust `success=true`, a URL query parameter, client-side payment status, or a user-supplied amount.
- Do not store card number, CVV, UPI PIN, netbanking credentials, or other payment credentials. Let Razorpay handle the payment UI and sensitive payment data.
- Add a reconciliation process that checks local pending payments against gateway records through a safe admin-triggered action or scheduled job if supported. Shared hosting cron may be used; do not require a persistent worker.
- Verify current Razorpay API/SDK methods against official documentation during implementation; do not copy unreviewed sample code into production.

### Webhook behavior

1. Read the raw request body before JSON decoding.
2. Verify the webhook signature using the configured webhook secret and constant-time comparison/official SDK verification.
3. Reject invalid signatures; log only a safe event summary, not secrets.
4. Validate event type and required payload fields.
5. Store the event with a unique provider event key and processing status.
6. Match provider order ID to exactly one local purchase.
7. Validate amount and currency; unexpected mismatches go to `needs_review`, never activation.
8. Update payment state in a transaction and create the activation request once.
9. Return a successful HTTP response only after the event has been safely recorded/processed. Design retry behavior so repeated events are harmless.
10. Handle provider outages and malformed events gracefully.

### Refunds and disputes

- Never silently change a paid record to failed.
- Keep the original payment record immutable and create a separate refund/dispute record or append-only status event.
- Refund initiation should require a specific permission, reason, confirmation, and gateway API verification.
- Reconcile refund results by webhook/API; update membership access according to the approved refund policy.
- A payment refund does not automatically erase profile/interest history or audit logs.

---

## 8. Database design and migration plan

First inspect `app/database/matrimony_complete.sql` and existing user/profile/interest tables. Reuse existing IDs and tables wherever possible. The names below are logical recommendations; map them to the real schema and avoid creating duplicate user/profile tables.

### Core tables or equivalent existing entities

#### `users` (extend existing table only if necessary)

- `id`
- `email_normalized` (unique)
- `phone_normalized` (unique if phone is required)
- `password_hash`
- `role` (`user`, `admin`, `super_admin`, optional `moderator`)
- `account_status` (`pending_verification`, `active`, `suspended`, `deactivated`, `deleted`)
- `email_verified_at`, optional `phone_verified_at`
- `created_at`, `updated_at`, `last_login_at`
- Add indexes for unique email/phone and common status/role queries.

#### Existing profile tables

Preserve current profile data model. Ensure profile ownership is enforced by the server and the database relationship is indexed. Use privacy flags/visibility status already present if possible.

#### `plans`

- `id`, `name`, `slug`, `description`
- `price_paise` (integer), `currency` (`INR`)
- `duration_days`
- `contact_limit` nullable, `is_unlimited`
- `is_active`, `display_order`
- `created_by`, `updated_by`, timestamps
- Constraints and indexes for active plans and unique slug.

Seed the three poster plans: 100000 paise/45 days/25 contacts; 300000 paise/90 days/45 contacts; 1000000 paise/365 days/unlimited. Confirm whether “1 year” should be calendar-year arithmetic or 365 days; use one documented rule consistently.

#### `purchases` or `payment_orders`

- `id`, `public_reference` (unguessable/unique)
- `user_id`, `plan_id`
- Immutable snapshots: `plan_name_snapshot`, `amount_paise`, `currency`, `duration_days_snapshot`, `contact_limit_snapshot`, `is_unlimited_snapshot`
- `gateway` (`razorpay`), `gateway_order_id` (unique where present), `gateway_payment_id` (unique where present)
- `payment_status`, `signature_verified_at`, `paid_at`
- `created_at`, `updated_at`
- Never store secrets or payment instrument credentials.

#### `payment_events` / `gateway_webhook_events`

- `id`, `gateway`, `provider_event_key` (unique), `event_type`
- `purchase_id` nullable until matched, `received_at`, `processed_at`
- `processing_status`, safe error code/message, payload hash or carefully redacted payload
- Store only what is necessary for reconciliation; restrict access and define retention.

#### `activation_requests`

- `id`, `purchase_id` (unique if one activation per purchase)
- `user_id`, `status` (`awaiting_admin`, `approved_activated`, `rejected`, `on_hold`)
- `requested_at`, `reviewed_by`, `reviewed_at`, `decision_reason`
- `activated_membership_id` nullable.

#### `memberships`

- `id`, `user_id`, `purchase_id` (unique)
- `plan_id` nullable plus immutable plan snapshots
- `status` (`pending`, `active`, `expired`, `suspended`, `revoked`, `replaced`)
- `starts_at`, `ends_at`, `activated_by`, `activated_at`
- `contacts_used` may be cached, but the reveal ledger is the source of truth.
- Index `(user_id, status, ends_at)` and purchase ID.

#### `contact_reveals`

- `id`, `viewer_user_id`, `target_user_id`, `membership_id`, `revealed_at`
- Unique constraint `(viewer_user_id, target_user_id)` if policy allows only one lifetime counted reveal per target. If a new plan should allow re-counting, use a separate entitlement-period key and define the policy explicitly.
- Use transaction-safe allowance enforcement.

#### Existing interests table or equivalent

- Keep existing interest records and statuses.
- Add a unique constraint preventing duplicate active interests for the same sender/recipient if consistent with the product.
- Add appropriate indexes for sender, recipient, status, and timestamps.

#### `notifications`

- `id`, `user_id`, `type`, `title`, `message` (no secrets), `related_type`, `related_id`, `read_at`, `created_at`.
- Admin queue alerts can be represented by notifications or derived from pending activation requests.

#### `audit_logs`

- `id`, `actor_user_id`, `action`, `entity_type`, `entity_id`, `request_id`
- `created_at`, safe metadata JSON, outcome, IP address and user agent only as justified by security/retention policy.
- Never include passwords, reset tokens, session cookies, gateway secrets, or unnecessary profile/health/family details.
- Application roles should only append; no ordinary update/delete UI.

#### `email_outbox` (recommended)

- `id`, recipient reference/email as necessary, template key, redacted variables, status, attempts, next_attempt_at, sent_at, safe error summary.
- Avoid sending email inline in a transaction. Commit business state, then queue the notification. A shared-hosting cron can retry failed sends.

#### `refunds` / `payment_adjustments` (if refunds are supported)

- Link to purchase and gateway refund ID; amount, status, reason, requested/approved by, timestamps.
- Preserve payment history and reconcile gateway events.

### Migration safety

1. Back up the database and files.
2. Apply migrations to a staging copy first.
3. Add tables and nullable/indexed fields before enforcing new non-null constraints.
4. Seed the four plans with an idempotent seed/migration.
5. Add indexes after reviewing existing data for duplicates.
6. Do not overwrite existing users, passwords, profiles, interests, or seed production data.
7. Test upgrade from a copy of the current production database.
8. Maintain a rollback plan and verify backup restoration before release.

---

## 9. Application routes and service boundaries

Adapt route names to the current module/router conventions.

### Public/user pages

- `/plans` — list active plans and terms.
- `/checkout/{plan}` — authenticated purchase start; server creates local purchase and gateway order.
- `/payment/return` — display payment result and activation status; not proof of payment.
- `/payment/history` — user's own purchases and statuses.
- `/membership` — current plan, start/end, allowance used/remaining, activation pending.
- `/profiles` or existing matches page — preserve current browsing/filtering.
- Existing profile and interest routes — enforce server-side entitlement and privacy checks.
- `/notifications` — user's own notifications.

### Server API/actions

- Create order.
- Verify Checkout result.
- Receive webhook.
- Fetch own purchase status.
- Request protected contact reveal.
- Send interest after entitlement validation.
- Admin list/view/activate/reject payment requests.
- Admin create/edit/disable plan.
- Admin user/profile CRUD.
- Admin membership suspend/revoke.
- Admin reports/audit access.

All state-changing actions require authenticated session, role/permission check, CSRF protection for browser requests, server-side validation, and audit logging. Webhooks are not browser forms and use gateway signature verification rather than CSRF tokens.

### Suggested service classes

Keep business logic out of page templates/controllers:

- `PlanService` — active plans and immutable purchase snapshots.
- `PaymentService` — order creation, signature checks, reconciliation, refunds.
- `WebhookService` — event validation, idempotency, state transitions.
- `MembershipService` — activation, expiry, entitlement checks, suspension.
- `InterestService` — interest policy, duplicates, block rules, rate limits.
- `ContactRevealService` — transactional allowance consumption and protected data response.
- `AdminUserService` — user/profile administration and role restrictions.
- `AuditService` — append-only audit events.
- `NotificationService` / `EmailOutboxService` — in-app and email notifications.
- `Authorization` helper/middleware — role and ownership checks.

Use the existing bootstrap, database connection, CSRF, request, and authentication infrastructure where it is sound. Do not create a second independent session/authentication system.

---

## 10. Email and in-app notifications

Configure SMTP using a maintained mail library such as PHPMailer, installed and packaged through Composer. Use TLS, authentication, environment-based credentials, and a verified sender domain. Do not use a personal mailbox password in source code.

### Admin notifications

Send an email and create an admin-panel notification when:
- A payment is verified and awaits activation.
- A payment amount/order mismatch needs review.
- A refund, chargeback, or dispute event arrives.
- A user submits a safety report or a configured high-priority support request.
- Repeated webhook failures or delivery failures require operational attention.

### User notifications

Send email and in-app notification when:
- Purchase is initiated (optional confirmation).
- Payment is verified and awaiting admin approval.
- Plan is activated, including plan name, amount paid, activation date, expiry, and contact allowance.
- Activation is rejected/held, with a safe reason and support route.
- Membership is nearing expiry, expires, is suspended, or is revoked.
- Refund/dispute changes the membership state.

### Email safety

- Avoid including sensitive profile data, passwords, payment secrets, full phone numbers, or unnecessary family details.
- Use generic wording for account-security messages to reduce account enumeration.
- Escape template variables and avoid unsafe HTML injection.
- Do not block payment-state persistence because SMTP is down. Queue emails and retry.
- Rate-limit contact/support forms and prevent email header injection.

---

## 11. Security requirements — release blockers

### Authentication and sessions

- Use HTTPS everywhere; redirect HTTP to HTTPS and enable HSTS only after HTTPS is confirmed.
- Use secure PHP session settings: `session.use_strict_mode=1`, cookies `Secure`, `HttpOnly`, and appropriate `SameSite`; regenerate session ID after login and privilege changes.
- Rate-limit login, registration, password reset, verification, contact, interest, and payment-order creation.
- Use generic login/reset errors to reduce account enumeration.
- Implement password reset with cryptographically random, single-use, expiring tokens stored hashed in the database.
- Revoke sessions after password reset, account suspension, role changes, and security-sensitive account changes.
- Add admin MFA and reauthentication for high-risk operations.
- Do not store passwords in logs or send passwords by email.

### Authorization and IDOR protection

- Check role and ownership on every request, API endpoint, file request, and AJAX call.
- Use prepared statements/PDO parameter binding for every database query.
- Do not accept a `user_id`, role, price, membership status, contact allowance, or activation status from the client as authoritative.
- Prevent IDOR by checking that the logged-in user owns the requested record or has an explicit permission.
- Return only fields the current viewer is allowed to see.

### CSRF, XSS, SQL injection, and validation

- Use unique CSRF tokens for all browser-originated state-changing requests.
- Escape output by context (HTML, attributes, URL, JavaScript); prefer safe JSON encoding for JSON output.
- Avoid inserting untrusted strings into `innerHTML`; sanitize only when rich text is intentionally supported.
- Validate and normalize all inputs server-side; client validation is convenience only.
- Use strict allowlists for sort fields, filters, enums, and dynamic SQL identifiers.
- Set appropriate Content Security Policy and security headers after testing existing assets.
- Configure `display_errors=Off` in production and log errors privately.

### File/photo safety

- Validate MIME type using server-side inspection, not filename alone.
- Enforce maximum file size and image dimensions; re-encode raster images where possible.
- Generate random filenames; never use user-supplied paths.
- Block PHP/script execution in upload directories and store private photos outside public web root when possible.
- Serve private photos through an authenticated controller with authorization and no-cache headers.
- Prevent path traversal, SVG/script upload issues, and direct public access to private media.

### Data privacy

- Data minimization: collect only necessary profile and family details.
- Apply privacy-by-default for contact information and photos.
- Use `Cache-Control: no-store` or suitable private cache headers on sensitive account, payment, and profile pages.
- Do not cache personalized JSON/API responses in public proxies.
- Redact sensitive fields from logs, error reports, email, analytics, and exports.
- Use least-privilege database credentials; the web application account must not have administrative database privileges or permission to drop schemas.
- Encrypt backups and restrict access; protect `.env`, SQL dumps, uploaded documents, and logs.
- Define retention/deletion/anonymization rules and comply with applicable Indian privacy, consumer, payment, and accounting obligations. Obtain qualified legal advice for final policy wording.
- Provide a privacy policy, terms, refund/cancellation policy, contact/support route, and clear membership terms.

### Payment and business-logic safety

- Verify gateway signatures server-side and webhook signatures against the raw body.
- Check order ID, amount, currency, user, plan snapshot, and state transitions.
- Use unique constraints and idempotency keys to prevent duplicate payment records and activation.
- Use transactions for payment-state changes, membership activation, and contact allowance consumption.
- Use decimal-safe/integer paise for INR; never float for money.
- Do not store card data or UPI credentials.
- Keep gateway secrets out of browser JavaScript, logs, Git, SQL dumps, and public files.
- Use a dedicated webhook secret; rotate secrets if exposed.
- Add anomaly handling for repeated payments, amount mismatch, unknown order, late/duplicate webhook, and refunds.
- Do not allow an admin to bypass payment verification through a normal activation action.

### Shared-hosting hardening

- Keep `.env`, database dumps, logs, backups, and private uploads outside `public_html` where possible.
- Deny web access to `.env`, `.git`, `vendor` metadata, SQL dumps, backups, logs, and configuration files via hosting configuration and `.htaccess`; verify with direct URL tests.
- Disable directory listing.
- Set correct file permissions; never make all files/directories world-writable.
- Keep PHP and dependencies supported and patched.
- Confirm HTTPS, PHP extensions, Composer dependency compatibility, cron support, outbound SMTP, and webhook reachability on the selected Hostinger plan.
- Docker may remain useful for local development but is not required for shared-hosting production.

---

## 12. Admin activation transaction (required logic)

The activation handler must follow this sequence:

1. Authenticate the admin.
2. Check explicit permission and require recent reauthentication for this high-risk action.
3. Validate CSRF and input.
4. Begin database transaction and lock the purchase/activation row (`SELECT ... FOR UPDATE` where supported).
5. Confirm purchase belongs to the expected user, payment status is `paid_verified`, amount/currency match, and no duplicate activation exists.
6. Confirm activation request is awaiting review and plan snapshot is valid.
7. Apply the defined policy if the user already has an active membership (reject, replace, or queue/extend; no silent behavior).
8. Create membership with server-generated activation/start/end timestamps and plan snapshot.
9. Update activation request to approved/activated and link membership.
10. Write audit event and in-app notification/outbox record.
11. Commit transaction.
12. Send email asynchronously/retryably after commit.

If any check fails, roll back and show a safe error. Do not mark the membership active if the transaction or audit write fails.

---

## 13. Expiration and entitlement enforcement

- Never rely on a cron job alone to expire plans. Every entitlement check must compare current server time with `starts_at`, `ends_at`, and membership status.
- A cron task may mark old memberships `expired` for reporting, but authorization must still deny an expired membership even if the scheduled task did not run.
- When a membership expires, keep history intact and show the user the renewal/plan page.
- If a membership is suspended or revoked, deny restricted actions immediately.
- Contact reveals must be checked atomically; two concurrent requests must not exceed the allowance.
- A plan's “unlimited” status should bypass only the numeric contact limit, not safety, privacy, block, rate-limit, or abuse controls.
- Use a consistent timezone policy. Store UTC timestamps and convert only for display.

---

## 14. User-facing pages and content

### Plans page

For each plan show:
- Plan name, price in INR, number of contacts/unlimited, duration, included features, and eligibility.
- Whether interest sending is enabled while the plan is active.
- What counts as a contact and when the allowance is consumed.
- Clear activation workflow: “Payment verified — awaiting admin activation” until activated.
- Terms, refund/cancellation policy, support link, and secure checkout notice.

### User dashboard

Show:
- Profile completion and verification status.
- Current membership: plan, status, activated date, expiry date, days remaining.
- Contact allowance: used and remaining (or Unlimited).
- Pending activation request and payment reference.
- Recent interests, notifications, and payment history.
- Clear CTA to view plans if unpaid/expired/allowance exhausted.

### Safe redirects

When an unpaid user clicks Send Interest or Contact Reveal:
- Store only a validated internal return path or a server-side pending action reference.
- Redirect to `/plans` or the relevant checkout selection.
- After payment, do not automatically perform a sensitive action without rechecking authorization, blocks, profile visibility, and membership activation. Prefer returning to the profile and asking the user to retry.

---

## 15. Testing plan and acceptance criteria

### A. Existing regression tests

- Registration/login/logout and password reset work.
- Existing profile create/edit, photo, family, lifestyle, horoscope, preferences, and privacy settings work.
- Match list/filter, shortlists, viewed profiles, mutual matches, notifications, and existing interest pages work.
- Block/report, deactivate/reactivate, and profile visibility rules continue to work.
- Responsive layout and current public pages remain intact.
- Existing database users and profile relationships survive migrations.

### B. Admin tests

- Anonymous users cannot open admin pages or APIs.
- A normal user cannot reach admin endpoints by guessing URLs or editing requests.
- Admin cannot create/promote Super Admin.
- Last Super Admin cannot be deactivated/demoted.
- CRUD validates fields and records audit history.
- Soft-deleted/deactivated records do not unexpectedly disappear from payment history.
- Sensitive fields are masked and exports are permission-controlled.

### C. Payment tests (Razorpay test mode)

- Order amount is calculated from the database, not the browser.
- Successful test payment with valid signature moves payment to verified/pending activation, not active membership.
- Invalid signature, altered amount, altered currency, mismatched order, and unknown payment ID are rejected/flagged.
- Browser success redirect without valid server verification never grants membership.
- Webhook valid signature is processed; invalid signature is rejected.
- Duplicate webhook and out-of-order webhook do not duplicate purchases or activation.
- Gateway timeout, browser close, failed payment, retry, delayed webhook, and refresh are handled safely.
- Admin cannot activate pending/failed/unverified payment.
- Authorized admin can activate a verified payment exactly once.
- Email failure does not undo a verified payment or activation; outbox retry works.
- Refund/dispute flow updates status and access according to policy.

### D. Entitlement tests

- Unpaid user cannot send interest; server denies it and directs to plans.
- Pending activation user cannot send interest or reveal contacts.
- Active membership can send interest under the defined policy.
- Expired/suspended/revoked membership cannot send interest or reveal contacts.
- Silver allows 25 unique contact reveals; 26th is denied.
- Gold allows 45; Platinum has no numeric cap.
- Repeated reveal of the same target follows the documented deduplication rule.
- Parallel requests cannot exceed allowance.
- Blocked users cannot send interest/reveal contacts to each other.
- Protected contact fields do not appear in unauthorized HTML/API/network responses.
- A profile's privacy settings are respected even for paid users.

### E. Security tests

- SQL injection, XSS, CSRF, IDOR, session fixation, privilege escalation, path traversal, unsafe upload, brute-force, and enumeration checks.
- Verify `.env`, `.git`, SQL dumps, logs, backups, and private uploads cannot be fetched through HTTP.
- Verify production errors do not expose stack traces, SQL, file paths, secrets, or session data.
- Verify security headers and cache controls.
- Verify all state-changing APIs have authorization, validation, CSRF (where applicable), and audit coverage.
- Run dependency vulnerability checks and review PHP configuration.

### Acceptance gate

Do not release until all critical tests pass, payment signatures/webhooks are verified, admin activation is gated by verified payment, no sensitive contact data leaks through APIs, backups restore successfully, and the production secret rotation is complete.

---

## 16. Deployment plan for Hostinger shared hosting

1. **Inventory and backup**
   - Back up current files and database.
   - Keep a tested rollback copy.
   - Rotate any credentials that were included in the supplied `.env` or repository history.

2. **Local/staging setup**
   - Use a copy of the project and a copy of the database.
   - Add schema migrations and implementation incrementally.
   - Configure test gateway credentials and test SMTP.
   - Keep debug mode enabled only locally.

3. **Dependencies**
   - Install the official Razorpay PHP SDK and mail library in a controlled development/build environment.
   - Commit `composer.lock`; do not commit secrets.
   - Upload required production dependencies if the hosting environment cannot run Composer.
   - Ensure the PHP version/extensions match the app and SDK.

4. **Environment**
   - Put `.env` outside public web root if possible, or use Hostinger's supported environment configuration.
   - Set database, mail, gateway, application URL, and webhook secrets.
   - Keep production keys separate from test keys.

5. **Database**
   - Apply migrations after backup.
   - Seed plans idempotently.
   - Verify indexes, constraints, and database user privileges.

6. **Files and web server**
   - Upload to a staging subdomain or protected staging path first.
   - Set document root to the intended public directory if the application layout permits.
   - Deny access to configuration, Git metadata, SQL dumps, logs, backups, and private files.
   - Disable directory listing and verify HTTPS.

7. **Gateway configuration**
   - Configure live Razorpay keys only after merchant approval and successful test-mode validation.
   - Register the webhook URL, choose the required events, and configure the webhook secret.
   - Verify that the endpoint is reachable over HTTPS and that the server receives raw request bodies correctly.

8. **Cron and email**
   - Configure a shared-hosting cron for email outbox retries, membership housekeeping, and reconciliation if supported.
   - Keep core entitlement checks synchronous; do not rely on cron for security.
   - Verify SMTP delivery, SPF/DKIM/DMARC for the sender domain where available, and retry behavior.

9. **Go-live checks**
   - Complete a low-value live transaction only when approved and appropriate.
   - Confirm gateway dashboard and local purchase amounts match.
   - Confirm payment is pending activation until an admin activates it.
   - Activate through the admin panel and confirm the user receives the correct membership.
   - Test one expired/suspended/blocked scenario.
   - Monitor errors, webhook failures, and activation queue.
   - Keep rollback ready.

---

## 17. Recommended implementation phases

### Phase 0 — Audit and decisions
- Review all current PHP modules, database schema, session/auth logic, CSRF, `.htaccess`, upload handling, and environment configuration.
- Map current user/profile/interest tables.
- Confirm contact definition, active-plan replacement policy, refund policy, verification requirements, and admin permissions.
- Rotate exposed credentials.

**Deliverable:** source/schema map, risk register, approved business rules.

### Phase 1 — Security foundation
- Centralize authentication, authorization, ownership checks, CSRF, validation, safe output, session settings, and audit helpers.
- Fix critical existing vulnerabilities before adding payment features.
- Add staging and backup/restore workflow.

**Deliverable:** secure base layer and regression baseline.

### Phase 2 — Database and plans
- Add migrations for plans, purchases, webhook events, activation requests, memberships, contact reveals, notifications, audit events, and email outbox as needed.
- Seed the four plans.
- Add constraints/indexes and migration rollback notes.

**Deliverable:** migrated staging database with preserved existing data.

### Phase 3 — Admin panel
- Build dashboard, user/profile CRUD, plan CRUD, payment queue, activation actions, membership administration, moderation, admin account controls, and audit viewer.
- Enforce RBAC in server code.

**Deliverable:** admin panel with audited, permission-controlled workflows.

### Phase 4 — Payment gateway
- Integrate Razorpay test mode, server-side order creation, signature verification, webhooks, idempotency, reconciliation, failure handling, and payment history.

**Deliverable:** verified test payments create activation requests but do not activate memberships automatically.

### Phase 5 — Membership and access enforcement
- Implement membership activation/expiry/suspension.
- Enforce paid-only interest sending.
- Enforce contact reveal limits and privacy at the server/API layer.
- Update plans page and user dashboard.

**Deliverable:** plan restrictions work even when users call endpoints directly.

### Phase 6 — Email and notifications
- Configure SMTP and outbox retries.
- Add admin payment/activation alerts and user payment/activation/expiry messages.

**Deliverable:** reliable in-panel and email notifications.

### Phase 7 — QA and security review
- Run regression, payment, entitlement, abuse, privacy, and security tests.
- Test concurrency and duplicate webhooks.
- Review logs, permissions, file access, and backup restoration.

**Deliverable:** signed-off release checklist.

### Phase 8 — Production deployment and monitoring
- Deploy to staging, validate, then deploy to production.
- Configure live gateway/webhook secrets and cron.
- Monitor payment/activation queue and security logs.
- Document rollback, credential rotation, and incident response.

**Deliverable:** production-ready release with operational runbook.

---

## 18. Operational runbook

### Daily
- Review verified payments awaiting activation.
- Review webhook failures and unmatched/amount-mismatched payments.
- Review high-priority user reports.
- Check mail outbox failures.

### Weekly
- Review failed login spikes, admin actions, suspicious profile/contact activity, and storage/backup status.
- Verify a recent backup is restorable in a safe environment.
- Review pending refunds/disputes and membership anomalies.

### When a user says they paid but their plan is inactive
1. Search by internal purchase reference and gateway order/payment ID.
2. Check gateway dashboard and server-side verification/webhook history.
3. Confirm amount/currency/order match.
4. If verified, ensure an activation request exists and is awaiting review.
5. Activate through the authorized admin action only after checks pass.
6. If evidence is inconsistent, put it on hold and reconcile; never “fix” it by blindly setting paid/active.

### Incident response
- Disable compromised gateway keys and rotate secrets.
- Suspend compromised admin accounts and revoke sessions.
- Preserve relevant logs and payment references.
- Restore from a known-good backup only after assessing data integrity.
- Notify affected users/regulators where legally required and follow applicable incident-response obligations.

---

## 19. Definition of done

The implementation is complete only when:

- Existing profile, match, privacy, block/report, and account workflows still work.
- Admin can create/manage users and profiles, but all actions are permission-checked and audited.
- Four plans match the supplied poster and are database-driven.
- Razorpay payment is verified server-side and through signed webhooks.
- Verified payment creates an admin activation request and sends an admin email/in-panel notification.
- Only an authorized admin can activate a plan after payment verification.
- User receives an activation notification and sees correct start/end dates and allowance.
- Unpaid, pending, expired, suspended, or revoked users cannot send interests or reveal protected contacts.
- Contact limits are enforced server-side, atomically, and without leaking sensitive data.
- Refund/dispute, duplicate webhook, timeout, and email failure scenarios are handled.
- Production secrets are outside public source and rotated if exposed.
- Security, privacy, migration, backup/restore, and acceptance tests pass.
- Deployment works on the selected Hostinger shared-hosting plan without requiring a VPS.

---

## 20. Implementation cautions

- This document is a development plan, not a statement that the archive has already been modified or that the system is secure.
- Do not replace the existing app wholesale. Implement in small migrations and reuse current services where appropriate.
- Do not trust browser-side payment success or front-end membership checks.
- Do not expose private contact data just because a user has paid; profile privacy, consent, blocking, moderation, and legal requirements still apply.
- Do not allow ordinary CRUD to rewrite financial history or erase audit evidence.
- Do not promise “unlimited contacts” as unlimited access to every private profile. It means no numeric plan cap; all safety and privacy checks still apply.
- Verify current Razorpay documentation and merchant eligibility at implementation time. Gateway APIs, event names, onboarding requirements, and supported methods can change.
