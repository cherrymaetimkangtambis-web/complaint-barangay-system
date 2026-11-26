BARANGAY COMPLAINT MANAGEMENT SYSTEM - Barangay San Roque, Tomas Oppus
=======================================================================

HOW TO INSTALL (XAMPP + VISUAL STUDIO CODE)
-------------------------------------------
1. Open XAMPP Control Panel -> Start "Apache" and "MySQL".
2. Copy the whole folder "complaint-system" into:  C:\xampp\htdocs\
   (final path: C:\xampp\htdocs\complaint-system\index.php)
3. Open http://localhost/phpmyadmin -> click "Import" tab -> choose
   database.sql (inside the folder) -> click "Import".
   This creates a NEW database: barangay_complaint_db  (your old one is untouched).
4. Open the website:  http://localhost/complaint-system/
5. Open the folder in Visual Studio Code:  File > Open Folder > C:\xampp\htdocs\complaint-system

DEFAULT ADMIN LOGIN (change the password after login!)
   Email:    admin@barangay.local
   Password: Admin@123

Residents register by themselves. Admin can add Officials (Kagawad/Captain/Secretary)
in:  Users > Add staff account.

UPGRADING AN EXISTING INSTALLATION
----------------------------------
Do NOT re-import database.sql into an existing installation; that setup script recreates
the tables. Back up the database, sign in as Admin, then choose "System Upgrade" and
"Run database upgrade". The upgrade adds tables/fields without deleting existing
residents or complaints and can safely be run again if interrupted.

THINGS TO EDIT
--------------
config/config.php
  - PUROKS      : change to the real puroks of Barangay San Roque
  - CATEGORIES  : complaint categories
  - BARANGAY_OFFICIALS, BARANGAY_OFFICE_HOURS, BARANGAY_CONTACT_NUMBER, BARANGAY_EMAIL:
    publish only details approved by the barangay
  - DB_USER / DB_PASS : only if your MySQL has a password (XAMPP default: root, no password)

FOLDER GUIDE
------------
index.php              Home page
auth/                  register, login, logout, forgot password, change password
resident/              dashboard, profile, file complaint, my complaints, view, edit, cancel
admin/                 dashboard, complaints (search/filter/sort), view+update, reports,
                       announcements, users, activity logs, export CSV, system upgrade
notifications.php      In-app notifications (residents and staff)
privacy.php            Resident data privacy notice
includes/              header, footer, shared functions
config/                settings + database connection
uploads/               photo evidence (scripts are blocked in this folder)
database.sql           database structure + default admin

FEATURES ADDED
--------------
- Admin / Official side: view all complaints, update status (Pending > In Progress > Resolved / Cancelled), remarks
- Role-based access: Resident, Official, Admin
- In-app notifications (resident sees status changes; staff see new complaints)
- Search, filter (status, category, purok, priority, date), sorting, pagination
- Reports per month / category / purok, print or Save as PDF, export to Excel (CSV)
- Forgot password + change password
- Edit / cancel complaint while Pending
- Reference number (e.g. BSR-2026-0002), priority level, assigned official
- Activity logs, user management (activate/deactivate, reset password, change role)
- Resident dashboard with Pending / In Progress / Resolved counts
- Purok dropdown (consistent data), contact number validation (09XXXXXXXXX), unique email
- Shared help and accessibility panel, adjustable text size, high contrast, readable font,
  reduced motion, browser voice commands, and page read-aloud
- Speech-recognition language choices for commonly used Philippine languages. Browser
  support varies; the interface itself currently remains in English.
- Resident profile editing and registration consent records (notice version and timestamp)
- Private resident/staff complaint messages, resolution ratings, and resident reopen requests
- Separate staff-only Katarungang Pambarangay mediation records linked to complaints
- Public FAQs, configurable barangay details, and admin-managed announcements
- Manual SMS/email compose links; no messages are sent without staff action
- Official complaint lists, details, reports, exports, and notifications are limited to
  complaints assigned to that official; Admin retains barangay-wide access
- Accessible SVG status pie and monthly trend charts in reports

The privacy notice is a plain-language draft. Have the barangay Data Protection Officer
review and approve it before public use. The mediation feature records schedules and
summons issue/service dates only; it does not determine legal eligibility or generate or
serve legal documents.

SECURITY
--------
- password_hash() / password_verify()
- PDO prepared statements (SQL injection protection)
- CSRF token on every form
- Photo upload validation (real file type JPG/PNG, max 2 MB, random file name)
- Session timeout (30 min), session ID regenerated on login
- Login lockout after 5 wrong tries (60 seconds)
- Output escaping (XSS protection)

NOTE ON "FORGOT PASSWORD"
-------------------------
Resident enters email + registered contact number to set a new password (no email server
needed in XAMPP). For production, real email/SMS reset would be better. Staff accounts
are reset by the Admin.

TROUBLESHOOTING
---------------
- "Database connection failed": MySQL not started, or database.sql not imported.
- Links/CSS broken: set BASE_URL_OVERRIDE in config/config.php, e.g. '/complaint-system'.
- Photo upload fails: make sure the "uploads" folder exists inside the project.
