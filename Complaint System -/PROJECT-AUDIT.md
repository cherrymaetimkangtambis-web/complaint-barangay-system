# Project Audit — Phase 0

## Scope and sources

- Application root: `C:\xampp\htdocs\Complaint System` (the active workspace).
- Feasibility source: `C:\xampp\htdocs\REQUIREMENTS\FEASIBILITY REPORT.docx`.
- Process/data-flow source: `C:\xampp\htdocs\REQUIREMENTS\DFD.docx`.
- Additional supplied profile source: `C:\xampp\htdocs\My Barangay Complaint System\My Profile Cherry Mae Tambis\protfolio info.txt`.
- Both Word documents were read from their text and tables. Neither contained embedded media. The profile text was read. The separate `ID_Photo.jpg` is not approved for public use and should not be used as a developer portrait without explicit permission.
- Other folders named `complaint-system` and `My Barangay Complaint System` are present under `htdocs`; they were not treated as the application root. The latter currently contains profile material and certificates, not the active application.

## Backup completed before project changes

- Project copy: `C:\xampp\backups\Complaint System\2026-11-20_1205\project`
- Database export: `C:\xampp\backups\Complaint System\2026-11-20_1205\barangay_complaint_db.sql`
- Verification: 41 source files and 41 copied files; `robocopy` exit code 1 (files copied successfully); SQL export is 15,659 bytes.
- These backups are outside the application. Do not overwrite or delete them.

## Structure and stack

- 33 PHP pages/helpers across `admin/`, `auth/`, `resident/`, `config/`, and `includes/`.
- Public assets: one shared CSS file and one accessibility JavaScript file.
- MySQL database, PDO helper, InnoDB tables, and an additive PHP migration helper.
- Public routes include Home and Privacy; Admin pages include dashboards, complaint handling, reports, users, announcements, logs, and system upgrade.
- There is no `.git` repository, `.gitignore`, numbered migration directory, rollback SQL, test directory, or build/package manifest.
- No external scripts/CDNs/hotlinked images were found. CSS and JavaScript are served locally.

## Database state (counts only)

The existing database is not empty. At the time of this audit it contains 1 user, 1 complaint, 12 activity-log rows, and 2 notifications. The history, feedback, message, mediation, and announcement tables have no rows. Preserve this data. The `schema_migrations` table exists but has zero rows, even though feature tables exist; this inconsistent marker must be investigated on a copied database before applying future migrations.

## Baseline verification

- `php -l` passed for all 33 PHP files.
- No application files or database rows were changed during discovery.
- Git was not found on PATH or at the checked standard installation paths; no branch or initial commit has been created.
- Browser, accessibility, and automated behavior tests have not yet been run in this phase.

## Existing capabilities

- Resident accounts, complaints, reference numbers, status history, pending-only resident edit/cancel, private resident/staff messages, resolution feedback/reopen, and in-app notifications.
- Admin-wide complaint handling; Officials are restricted to assigned complaints in list/detail/report/export and notification queries.
- Assignment, priority, public remarks, mediation record, CSV export, report charts, announcement management, and activity logging.
- Public FAQ/office contact/officials are represented by PHP constants; announcements are Admin-managed.
- Basic input validation, PDO prepared statements, output escaping helper, CSRF tokens, password hashing, session timeout, session ID regeneration on login, and upload MIME/size checks.

## Findings and risks

### Critical data and account setup

1. `database.sql` drops and recreates application tables. Re-importing it into an existing installation can destroy all application records. Keep it out of the existing-install upgrade workflow; replace this with an explicitly fresh-install flow and numbered migrations.
2. `database.sql` seeds a known default Admin account, and `README.txt` publishes its password. The config has a hard-coded local database account. These must be removed from versioned/deployment materials and replaced with local-only config plus a one-time Admin setup flow that forces a password change.
3. Current DB migration metadata is empty although feature tables exist. The migration runner/status must not be trusted until checked against a disposable database copy.

### Access, privacy, and transaction handling

4. Complaint evidence is stored under the publicly served `uploads/` path and complaint pages link directly to it. Apache blocks script execution there, but it does not enforce complaint-owner/staff authorization for reading an image.
5. Session cookies set HttpOnly and SameSite but do not set Secure when HTTPS is used. There is no explicit local/live error-logging configuration, and the root `.htaccess` only disables directory indexes.
6. Notification read state changes through a GET link; logout also changes session state through GET. These should be converted to CSRF-protected POST actions.
7. Login throttling is stored in the user's session, so it is not a robust account/IP-wide rate limit. Complaint filing has no rate limit or honeypot.
8. Several multi-step writes are transactional (for example complaint filing), but resident edit/cancel and other mutation paths are not consistently transactional or rechecked under a row lock.
9. Passwords require only eight characters. There is no first-login password change. Resident password recovery relies on email plus a publicly knowable contact number.

### Uploads and error handling

10. Complaint upload accepts JPG/PNG and enforces a 2 MB limit/MIME check, but does not re-encode, strip metadata, resize, support PDF, store privately, or authorize reads. `includes/functions.php` uses `@getimagesize()` and `@unlink()`, contrary to the no-suppressed-errors requirement.
11. `config/database.php` exits with an infrastructure explanation on a connection failure. Friendly 403/404/500 pages and a centralized error policy are absent.
12. There is no automated test suite, test report, accessibility audit, or repeatable role/IDOR/security test harness.

### Product and usability gaps

13. `config/config.php` hard-codes barangay details, categories, statuses, and FAQ/official content. There is no Admin Site Settings/editor for these values or public About, Contact, Terms, or Developer Profile pages.
14. Complaint form lacks incident date/time, people involved, optional PDFs, and an emergency warning. Current status choices differ from the feasibility report and expanded brief; private staff notes are absent.
15. Accessibility settings only provide three text sizes up to 125%, one high-contrast mode, a system readable-font toggle, reduced motion, speech-language selection, voice commands, and read-aloud. No reset, database persistence, theme set, spacing/ruler/cursor options, dyslexia font assets, or 200% settings exist.
16. `assets/js/accessibility.js` logs errors with `console.error`. `admin/users.php`, `resident/view_complaint.php`, and `admin/announcements.php` use JavaScript prompt/confirm dialogs despite the no-popup requirement.
17. Root `.htaccess` lacks HTTPS/security headers. There is no CSP, no meta description/favicon, and no measured Lighthouse or responsive/accessibility results.
18. `README.txt` points to the duplicate lowercase `complaint-system` directory and documents the default password. Documentation must be corrected and secret-free.

## Immediate caution

Do not run the current `database.sql` against the existing database. Keep work local. Never publish the attached identity photo unless the owner explicitly approves its use. Before any database migration, make and verify a fresh disposable copy and test forward/rollback/forward there.
