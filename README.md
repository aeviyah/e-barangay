# E-Barangay Management System (merged edition)

This is the combination of the two uploaded projects:

* **e-barangay4** (PDO, CSRF, role-based workflow engine) is the **base**.
* **E-Brgy_management_system** (mysqli, Barangay Bigaan portal) contributed its features, rewritten on top of the base so there is one codebase, one database and one login.

Default barangay is **Barangay Bigaan, Calauag, Quezon** (change `APP_BARANGAY`, `APP_MUNICIPALITY`, `APP_PROVINCE` in `includes/app.php`).

## What came from where

| Feature | Source | In this merged version |
|---|---|---|
| Role-based access, workflow statuses, timeline, audit trail, reports, global search, household/purok/resident registry, public portal, document verification | e-barangay4 | Kept as-is |
| Resident and Official **portal selector** on login, sign-in by **username or email** | E-Brgy | `login.php` |
| Official sign-up with position (Captain, Kagawad, Secretary, Treasurer, SK) | E-Brgy | `register.php`. Officials are activated immediately after successful registration, as requested. |
| **Filipino certificates** (Pagpapatunay ng Kahirapan / Paninirahan / Negosyo / general) with print and PDF, Word, and Excel download | E-Brgy | `modules/certificate.php`, button appears on an approved document request |
| Document types (Clearance, Indigency, Residency, Business Permit, Good Moral, Low Income, No Property) with **fee schedule** | E-Brgy | `module_categories()` in `includes/app.php`; fee is applied automatically and residents can no longer type their own fee |
| Requirement / evidence **file upload** | E-Brgy | Any request can carry a JPG/PNG/PDF; served only to the owner and authorised staff via `modules/attachment.php` |
| Complaint filing with respondent and incident location, case types (Blotter, Complaint, Katarungang Pambarangay, VAWC, BCPC) | E-Brgy | Complaints module in the workflow engine |
| Payment transaction workflow | E-Brgy | E-Wallet / Bank Transfer method labels, Pending / Approved / Rejected status, official approval buttons, and payment edit/delete controls |
| **Community feed** with photo posts | E-Brgy | `modules/feed.php`, also shown on the dashboard |
| **Facilities** catalogue and **time-slot reservations**, approve/reject, cancel own | E-Brgy | `modules/facilities.php`. Double booking is now blocked by *overlapping time*, not just same date |
| **Events** with date, time, venue, organizer, edit/delete | E-Brgy | `modules/events.php` |
| Resident self-service **profile** and **password change** | E-Brgy | `modules/profile.php` (click your name in the top bar) |
| SQL backup download | older eBMS.zip snapshot | Settings page, administrator only |

## Setup

1. Copy this folder into `xampp/htdocs`.
2. For a **new demo database only**, import `database/schema.sql` in phpMyAdmin. **It drops and recreates the tables.**
3. Check `config/database.php` (`ebarangay_management`, `root`, your XAMPP password).
4. Open `http://localhost/<folder-name>/`. Make sure `uploads/posts` and `storage/uploads` are writable by Apache.

PHP 8.1+ with `pdo_mysql` and `fileinfo` (both are on by default in XAMPP).

## Demo accounts (password: `password`)

| Portal | Username | Email | Role |
|---|---|---|---|
| Officials | `admin` | admin@barangay.test | System Administrator |
| Officials | `secretary` | secretary@barangay.test | Barangay Secretary |
| Officials | `treasurer` | treasurer@barangay.test | Barangay Treasurer |
| Officials | `captain` | captain@barangay.test | Punong Barangay |
| Officials | `staff` | staff@barangay.test | Barangay Staff |
| Resident | `juan` | juan@example.com | Resident |

Set `APP_DEMO` to `false` in `includes/app.php` before real use.

## Security fixes applied while merging

* Every state-changing action (delete, approve, status change) is a **POST with a CSRF token**; the original used plain links like `?action=delete&id=5`.
* No `display_errors` / debug pages (the original treasury page exposed session data at `?debug=1`).
* Uploads are checked by real MIME type, renamed randomly, size-limited, and `uploads/posts` cannot execute PHP. Request attachments live in `storage/uploads`, which Apache blocks from direct access.
* Residents cannot set their own fee, cannot see draft announcements, and can only open their own certificates and attachments.
* No plain-text password fallback; all passwords are bcrypt hashes.

## Not carried over (and why)

* The original `schema.sql.sql` did not match its own code (e.g. no `users.position`, no `complaints`, `facilities`, `posts` or `treasury` tables), so it was not used. The archives contain schemas but no live database dump; customized live data was not available to inspect or migrate automatically.
* The 4 test images in `uploads/posts` (they belong to posts in a database that was not included).
* `eBMS.zip` nested inside the first project: an earlier snapshot of the e-barangay4 line. Only its backup download was reused. Its `public_contacts` admin page was not merged.
* The empty `modules/projects.php` and the `modules/test.php` placeholder. Projects is handled by the workflow engine's Projects module.

## Original e-barangay4 notes (still valid)


Functional Programming 3 web application based on the approved E-Barangay Management System scope, workflow, UI/UX blueprint, and beginner-friendly PHP/MySQL stack.

## Stack

- HTML5, CSS3, basic JavaScript
- PHP with sessions, `password_hash()`, `password_verify()`, PDO prepared statements
- MySQL / phpMyAdmin
- XAMPP / Apache

## Setup

1. Copy this folder into `xampp/htdocs`.
2. For a **new demo database only**, open phpMyAdmin and import `database/schema.sql`.
   **Warning:** this script drops and recreates the app tables. Back up your database first; do not re-import it over data you need to keep.
3. Check database credentials in `config/database.php` (`ebarangay_management`, `root`, and the password configured in your XAMPP installation).
4. Visit `http://localhost/e-barangay/` (use whatever folder name you gave it in `htdocs`).

If you use PHP's built-in server instead of XAMPP, make sure the `pdo_mysql` extension is enabled. XAMPP normally includes it.

## Demo Accounts

All seeded accounts use password `password`.

- `admin@barangay.test` - System Administrator
- `secretary@barangay.test` - Barangay Secretary
- `treasurer@barangay.test` - Barangay Treasurer
- `captain@barangay.test` - Punong Barangay
- `staff@barangay.test` - Barangay Staff
- `juan@example.com` - Resident

## Implemented Scope

- Authentication, logout, registration, PHP sessions
- Role-based access control and hidden unauthorized actions
- Dashboard with KPIs, quick actions, pending workflow queue, notifications and audit activity
- Resident, household and Purok/Sitio management
- Shared workflow module for documents, permits, services, cases, governance, communication, events, facilities, assets, projects and operations
- Status tracking with timeline history
- Treasury payments, official receipt generation and collections
- Reports, global search, audit trail and user settings
- Public portal and document verification
- Locked future modules for SK, Health Center, Emergency/Disaster and other specialized systems

## Defense Flow

1. Log in as `admin@barangay.test`.
2. Show role-based sidebar and locked future modules.
3. Register or verify a resident.
4. Create a document request and move it through statuses.
5. Record payment and show the generated official receipt.
6. Show reports, global search and audit trail.
7. Log in as a resident to show the simplified resident view.


## Theme and customization

- Barangay name: edit `APP_BARANGAY` in `includes/app.php`.
- Logo: replace `assets/img/logo.svg` with your barangay seal.
- Background / "face of the barangay": drop a photo named `assets/img/brgy-photo.jpg` (hall, plaza, aerial shot). It is used automatically behind the glass UI and the hero; the illustrated scene `bg.svg` is the fallback.
- Demo shortcuts on the login page: set `APP_DEMO` to `false` in `includes/app.php` before real use.

## Hardening added in this version

- CSRF token on every POST form (automatic, see `includes/bootstrap.php`).
- General modules follow their configured workflow (`workflow_transitions()`). Document requests and payments use Pending, Approved and Rejected, with approval controls for authorized official roles and administrators.
- Facility double-booking is blocked on the same date.
- Failed logins are written to the audit trail; session cookie is HttpOnly + SameSite.

## Completion pass (everything from all three zips)

After comparing e-barangay-merged, e-barangay4s and s.zip (including its nested eBMS.zip) feature by feature, these gaps were filled:

| Feature | Source | Where |
|---|---|---|
| Resident status with notes (Active, Moved Out, Abroad, Deceased...), status tabs with counts, **Restore** | s.zip residents | `modules/residents.php` |
| Add a resident to an existing household with relationship; make a resident head of a new household | s.zip residents | `modules/residents.php` (More menu) |
| Permanent delete of a resident (administrator only, blocked if linked records exist) | s.zip residents | `modules/residents.php` |
| Youth, Head of Family, PWD ID no., Solo Parent ID no., other classification | s.zip citizen/resident forms | `modules/residents.php`, schema |
| **Public Contacts** admin (shown on the public portal) | eBMS.zip `public_contacts.php` | `modules/contacts.php`, Settings button |
| **Public request tracking** by reference number (status only, no personal data) | eBMS.zip portal/track | `public/track.php` |

Nothing from e-barangay4s was missing: the merged project already contained all of it.

### Upgrading an existing database
Do not re-import `schema.sql` into a database with data: it is a clean-install/demo reset script and drops application tables. Back up the database, then run `database/migration_complete.sql` against the canonical `ebarangay_management` database. The migration checks for each added column and seeds public contacts only when their labels are missing, so it can be rerun without dropping or recreating application records. It adds classification and recoverable-trash fields; it does not reshape an unrelated/custom database from the other ZIP projects.

### Deliberately not copied
Residents still cannot edit their own PWD/senior/solo-parent flags or ID numbers in the profile page; those stay staff-controlled because they need verification.

## UI / design pass

Both projects already shared the same design language (Bricolage Grotesque + Figtree, teal/gold/palm palette, glass panels), so the merged glass UI stays as the base. From the citizen portal of the older project I added:

| Item | Where |
|---|---|
| **Animated scene** (rotating sun rays, drifting clouds, birds, swaying palms, moving train). Used as the page background, with the old static `bg.svg` as fallback. Respects "reduce motion". | `assets/img/scene-animated.svg`, `assets/css/styles.css` |
| "Ngayon ay <date>" chip in the dashboard hero | `dashboard.php` |
| **Latest announcements** panel on the dashboard (staff and residents) | `dashboard.php` |
| **Personal profile record** card with Edit profile link (residents) | `dashboard.php` |

A real photo at `assets/img/brgy-photo.jpg` still takes priority over the scene.

## Branding: Barangay Bigaan, Calauag, Quezon

The seal (niyog palms, railway, gabi and palay, "BARANGAY BIGAAN / CALAUAG, QUEZON") from the older project replaces the generic logo:

* `assets/img/logo.svg` is now the Bigaan seal, so it also becomes the browser tab icon and the sidebar/portal logo.
* Login page: large seal, "Barangay Bigaan - Calauag, Quezon" line, and an "About Barangay Bigaan" box explaining the seal.
* Public portal: seal in the hero, "About" section, Calauag, Quezon in the eyebrow and top bar subtitle.
* Printed certificates: seal at the top of the header (Republika ng Pilipinas / Lalawigan ng Quezon / Bayan ng Calauag).
* The seal's lettering uses Arial Black as a fallback because web fonts cannot load inside an image.

## Printing and exports (PDF, Excel, Word)

Every list now has an **Export** group in its page header, backed by one endpoint, `modules/export.php`:

| Page | Exports |
|---|---|
| Residents (respects the status tab) | PDF, Excel, Word, CSV |
| Households, Purok / Sitio | PDF, Excel, Word, CSV |
| Permits, Services, Complaints, Cases, Budget, Announcements, Projects... | PDF, Excel, Word, CSV |
| Documents | One-request document: Print/PDF, Word, Excel; officials can also print the separate official summary list |
| Treasury / payments (with total row) | PDF, Excel, Word, CSV |
| Events, Facility reservations | PDF, Excel, Word, CSV |
| Reports summary | PDF, Excel, Word, CSV |
| Audit trail (latest 2,000) | PDF, Excel, Word, CSV |
| Certificates (single request) | Print, PDF, Word, Excel |

Notes:
* Access follows each page: you can only export what you can open, and residents only get their own rows. Each export is written to the audit trail.
* **Excel and Word** files are HTML-based `.xls` / `.doc` (no extra libraries needed). Excel may show a one-time "file format and extension don't match" prompt; choose Yes. They open and edit normally.
* **PDF** opens a print view with *Print* and *Download PDF* buttons. Direct download needs internet (html2pdf from a CDN); offline, use Print and "Save as PDF".
* The Documents detail page offers the actual requested certificate (not the request-list export) as a printable/PDF, Word, or Excel document once approved. It includes the resident's request, barangay name and seal, faint seal watermark, and the current active Barangay Captain's name.
* The Documents summary print preview is titled **All Certifications**. It balances **CALAUAG, QUEZON** on the left with the Barangay seal on the right. The supplied Calauag municipality seal is included in `assets/img/calauag-municipality-seal.jpg` and the individual certificate header.
* Text cells starting with `=`, `+`, `-` or `@` are prefixed to prevent spreadsheet formula injection.

## Current UI and authorization updates

The portal now uses the Ivy dark teal, navy, gold and green styling with the supplied animated barangay scene. The global header identifies the Barangay Officials & Admin Portal; sidebar labels include Certification Requests, Payment Transactions, Resident Profiles and User Accounts. The demo credential autofill panel is separate from the empty sign-in fields. A dark credit footer appears across portal pages.

All authorized Barangay Official roles with access to a request or payment screen can approve or reject it. Resident accounts stay limited to resident views and their own requests. Resident records support 4Ps, PWD, Senior Citizen, OFW and Other flags, and save household membership and relationship alongside classifications in one database transaction. Existing databases need `database/migration_complete.sql` to add missing fields.

Requests and payment transactions moved to trash remain in the database and can be restored from the corresponding “Deleted … / Restore” view. These changes are recorded in the audit trail. Resident archive/restore remains a separate resident status workflow.

## Certificate layout

Printed certificates (`modules/certificate.php`) now follow the barangay's sample: municipal seal left and barangay seal right, "Republic of the Philippines / Province / Municipality", "Office of the Sangguniang Barangay", the barangay name, a bold serif title, "To whom it may concern,", justified body text with the name, age and civil status underlined, "Issued this 24th day of MAY, 2026 ...", a faint barangay-seal watermark and the Punong Barangay's name at the bottom right.

Wording per type: Indigency, Residency, Barangay Clearance, Business Clearance, Good Moral, Low Income, No Property (anything else prints as "Certification").

Drop these optional files into `assets/img/` and they appear automatically (nothing breaks if they are missing):

| File | Used for |
|---|---|
| `seal-municipality.png` (or `.svg`) | Municipal seal, top left |
| `signature.png` | Punong Barangay's signature above the name (transparent PNG works best) |
| `dry-seal.png` | Embossed/dry seal at the bottom centre |

The Punong Barangay's name comes from the active user with that role (shown as "HON. NAME"). The Word download keeps the text layout but not the images.
