# Implementation Plan

The initial project/database backup is at
`C:\xampp\backups\Complaint System\2026-11-20_1205\`. The current application
will stay local. Changes follow the matrix in small acceptance-tested groups.
The additive feature migrations (`2026-11-feature-set-1` and
`2026-11-admin-crud-1`) have been applied and rerun idempotently on the active
database. A fresh pre-CRUD database backup is at
`C:\xampp\backups\Complaint System\2026-11-20_142337\`. Test future migrations
on a disposable database restored from a backup; never run destructive
`database.sql` against the active database.

## Admin CRUD extension

- Admin can create and edit complaints, archive and restore them, and review
  archived records. History, messages, and mediation records remain linked.
- Admin can create/edit resident and staff accounts, reset a password from the
  edit form, and archive/restore access. Account records are not hard-deleted;
  self-lockout and removal of the last active Admin are blocked.
- Announcements support edit, archive, and restore instead of permanent
  deletion. Public-site settings manage the barangay contact details, officials,
  FAQs, logo, and homepage background.
- Dashboards, reports, exports, public announcements, and Official access
  exclude archived complaints; Officials remain assignment-scoped.
- The fresh-install schema and repeatable additive migration include the same
  archive and settings structures.

| Order | Work | Likely files/data changes | Acceptance test |
|---|---|---|---|
| 1 | Git/config hygiene and first-admin path | `.gitignore`, `config.local.php`, `config.live.example.php`, setup route, login/session helpers, README | No credentials in tracked files; fresh install creates a unique Admin password and forces its change; existing users remain unchanged. |
| 2 | Runtime/error handling baseline | `config/*`, `includes/init.php`, `.htaccess`, error pages | Local errors use E_ALL and a local log; live example disables display; friendly 403/404/500; response headers verified. |
| 3 | Numbered schema migrations | `database/migrations/001_*.sql`, rollback SQL, migration runner | Forward/rollback/forward succeeds on a restored copy; row counts and existing data checksums remain consistent. |
| 4 | RBAC and account controls | `includes/functions.php`, `auth/*`, `admin/users.php` | Visitors, residents, assigned staff, and Admin are tested against every protected page/action; first-login/password reset and throttling verified. |
| 5 | Complaint model and intake | `resident/*`, staff complaint-entry route, schema migration, category/status management | Resident and authorized staff can submit; IDs/reference numbers are unique; incident/time/place/people fields validate; duplicate refresh does not create duplicates. |
| 6 | Review, workflow, messages, notes, audit | `admin/view_complaint.php`, `resident/view_complaint.php`, notifications, migrations | Allowed transitions, assignment, resident-visible replies, private notes, resolution history, and notifications are verified. |
| 7 | Private evidence uploads | upload helpers, evidence endpoint, upload/storage rules, migration | Real MIME/signature, size, fake image, malformed, `.php.jpg`, metadata, CSRF, owner/staff/admin access and denied direct URL tests pass. Confirm GD availability first. |
| 8 | Site Settings and public content | Admin settings pages, public pages, content schema, image slots | Admin edits placeholders/content/images; public pages render; required alt text and restore-default work; settings changes are audited. |
| 9 | Public/about/developer pages | routes, template language file, supplied profile text | Home/About/How it Works/FAQ/Announcements/Contact/Privacy/Terms/Developer Profile open as visitor; placeholder appears instead of unapproved ID photo. |
| 10 | Accessibility settings and UI redesign | shared header/footer, CSS/JS, local fonts, settings schema | Preferences save for guest and logged-in users, apply across roles/pages, reset, and work with keyboard, zoom, forced colors, reduced motion, and screen readers. |
| 11 | Dashboard, reports, exports, overdue items | admin dashboard/reports/CSV | Counts/chart/table match fixtures across every status, date/category/purok; exports are scoped; print is usable. |
| 12 | Data/privacy requests, demo fixtures, docs | privacy workflows, `tests/`, docs, optional separate demo DB seed | Admin can review deletion requests; demo fixture can be created/removed only in a separate test DB; user/admin guides are accurate. |
| 13 | Quality gate and packaging | tests, reports, `/deploy`, deploy/rollback notes | Lint/tests/accessibility/security/role/network/log checks pass; deploy contains only allowlisted runtime files; nothing is uploaded. |

## Current local environment

- Application: `C:\xampp\htdocs\Complaint System`
- Local homepage smoke test: `http://localhost/Complaint%20System/` returned HTTP 200.
- CLI: PHP 8.2.12; MySQL-compatible server: MariaDB 10.4.32.
- PHP extensions available: PDO MySQL, fileinfo, OpenSSL, mysqli, and GD.
  GD is enabled and verified in both PHP CLI and Apache's `apache2handler` SAPI.
- Git for Windows 2.56 is installed at
  `C:\Program Files\Git\cmd\git.exe`; it is not on the current shell PATH.
  Node.js is not found on PATH.
- CLI PHP reports `display_errors=STDOUT`, `error_reporting=22527`, and
  `log_errors=On`; the expected local development configuration must still be
  reviewed against the Apache runtime.
- The PHP CLI reports 40 MB upload/post limits, but application-level limits
  must remain explicit and lower where appropriate.

## Risks and controls

- **Existing user data:** use a second database restored from backup; never run
  destructive `database.sql` on the existing database.
- **Known default Admin credentials in current files:** remove before any Git
  commit and replace with one-time setup; do not publish or reuse them.
- **Schema drift:** the feature migration marker is present and the migration
  was rerun idempotently. Test future migrations on a disposable copy.
- **Private complaint evidence:** storage/path rules must be verified under
  both local Apache and the eventual host.
- **GD:** enabled and verified in CLI and Apache; upload/re-encoding tests are
  still outstanding.
- **Legal workflow:** staff must verify status names, retention, privacy notice,
  and mediation handling; application must not make legal determinations.
- **Unknown host:** re-check host-specific limits after selection; no deploy
  package or live changes before explicit `DEPLOY`.
