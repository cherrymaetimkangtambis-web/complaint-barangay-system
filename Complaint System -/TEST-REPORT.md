# Test Report — Baseline

Date: 20 November 2026  
Scope: Existing application before implementation changes.

## Baseline environment (before feature implementation)

- Windows/XAMPP project root: `C:\xampp\htdocs\Complaint System`
- PHP CLI: 8.2.12
- Database server: MariaDB 10.4.32
- PHP modules observed: fileinfo, mysqli, PDO, pdo_mysql, OpenSSL. GD is not
  loaded.
- Current DB counts: 1 user, 1 complaint, 12 activity log rows, 2
  notifications; history, feedback, message, mediation, and announcement
  tables have no rows. The migration marker table has zero rows.
- Verified backup:
  `C:\xampp\backups\Complaint System\2026-11-20_1205\`

## Baseline results

| Check | Result | Evidence |
|---|---|---|
| PHP syntax baseline | PASS | `php -l` passed all 33 PHP files. |
| Local homepage | PASS | `http://localhost/Complaint%20System/` returned HTTP 200. |
| Local assets | PASS | Homepage linked the local stylesheet; external script URL count was 0. |
| Project backup | PASS | 41 source files and 41 copied files; robocopy exit code 1 indicates files were copied. |
| Database export | PASS | `mysqldump` produced a 15,659-byte SQL file. |
| Application workflow suite | NOT RUN | No automated test directory or fixtures are present. |
| Browser console/network suite | NOT RUN | No browser page was shared for this workspace. |
| Accessibility audit | NOT RUN | No axe/Lighthouse/manual assistive-technology results exist. |
| Security/role/IDOR suite | NOT RUN | Must be added and run against isolated test data. |
| Migration forward/rollback/forward | NOT RUN | Existing database marker is inconsistent; test only on a restored copy. |
| Git branch/initial commit | BLOCKED | Git is not installed/on PATH at baseline. |

## Local PHP settings observed at baseline

The CLI reported `display_errors=STDOUT`, `error_reporting=22527` (not `E_ALL`),
`log_errors=On`, `error_log=C:\xampp\php\logs\php_error_log`,
`upload_max_filesize=40M`, `post_max_size=40M`,
`session.cookie_secure=Off`, and `session.use_strict_mode=Off`. These are CLI
observations; confirm Apache's runtime values before treating them as web
settings. They do not meet the intended live/development configuration split.

## Known baseline issues at baseline

- PHP code uses `@` to suppress upload/unlink warnings.
- The setup SQL drops existing tables and contains a known default Admin
  credential that is also written in `README.txt`.
- Complaint evidence is publicly addressable under `uploads/`.
- The existing accessibility script logs errors to the browser console.
- Git, the test suite, GD-based re-encoding, and the full accessibility/
  security acceptance tests are not available/passing yet.

The statements above describe the pre-implementation baseline.

## Post-implementation verification

Date: 20 November 2026  
Scope: Local feature workflows and runtime checks after implementation.

| Check | Result | Evidence |
|---|---|---|
| PHP syntax | PASS | `php -l` passed all 33 PHP files. |
| Public and staff visual layout | PASS | Homepage redesign and admin dashboard checked in a 531px browser viewport; neither introduced horizontal page overflow. |
| Apache restart and homepage | PASS | Restarted the verified XAMPP Apache processes; homepage returned HTTP 200. |
| Apache PHP and GD | PASS | Temporary localhost-only diagnostic reported `apache2handler`, PHP 8.2.12, and `gd=yes`; the diagnostic was removed. |
| Resident profile | PASS | Updated the synthetic resident's name, phone, and purok and verified the saved values. |
| Privacy consent | PASS | Registration rejected missing consent; registration succeeded after consent. |
| Resident/staff conversation | PASS | Resident message and Official response appeared in the authorized complaint thread. |
| Resolution feedback | PASS | Reopening returned a resolved complaint to In Progress; a later resolution was confirmed with a 5/5 rating. |
| Katarungang Pambarangay | PASS | Official saved respondent, contact/address, scheduled hearing, summons issue date, and staff outcome. |
| Assignment and Official scope | PASS | Admin assignment saved. Official list/dashboard/reports included assigned complaints; direct access, reports, and CSV excluded an unassigned complaint. |
| Reports and charts | PASS | Pie and monthly line SVGs rendered with fixture data and scoped counts. |
| Public announcements | PASS | Admin published a temporary announcement; it appeared on the public homepage. |
| Migration repeatability | PASS | Re-running `2026-11-feature-set-1` left the schema marker current. |
| Data cleanup | PASS | Temporary QA records were removed; users, complaints, history, notifications, activity logs, messages, feedback, mediation cases, and announcements returned to captured baseline counts. |
| Homepage after cleanup | PASS | Local homepage returned HTTP 200. |

Temporary synthetic fixtures were used against the local database for these
workflow checks, then removed by their exact marker values. The pre-test counts
(2 users, 1 complaint, 1 history row, 2 notifications, 12 activity-log rows,
1 message, and zero feedback, mediation cases, or announcements) were restored.
Existing records were preserved. Apache's log contains its local certificate
and process-start warnings; no new PHP application errors appeared during the
workflow checks.

## Admin CRUD extension verification

Date: 20 November 2026

| Check | Result | Evidence |
|---|---|---|
| Pre-migration database backup | PASS | `C:\xampp\backups\Complaint System\2026-11-20_142337\barangay_complaint_db.sql` created and size-verified before schema changes. |
| Admin CRUD migration | PASS | `2026-11-admin-crud-1` applied twice; migration marker and idempotent rerun verified. |
| PHP syntax | PASS | `php -l` passed all 36 PHP files after the final archived-view fix. |
| Browser route smoke checks | PASS | Admin dashboard, complaints, complaint detail, complaint intake, users, announcements, public settings, and homepage rendered; after cleanup, homepage, complaints, and public settings returned HTTP 200. |
| Existing business records | PRESERVED | CRUD checks used uniquely identified synthetic records; cleanup restored the pre-test counts without removing existing records. |
| Full CRUD POST workflows | PASS | Admin created, edited, archived, and restored test accounts and complaints; announcement create/edit/archive/restore flows were also exercised. |
| Public settings | PASS | Barangay details, officials/FAQ settings, and homepage image slots were saved and rendered. Temporary settings were removed after testing. |
| Site-image upload/replace/remove | PASS | Temporary PNG upload, preview, and image removal were verified; `uploads/site` is empty after cleanup. |
| QA data cleanup | PASS | The exact test complaint, resident account, dependent rows, 10 matching activity logs, and 9 test-created settings were removed. |
| Restored database counts | PASS | Users 3, complaints 2, announcements 0, complaint history 3, messages 1, feedback 0, mediation cases 0, notifications 4, activity logs 20, and site settings 0; no QA records remain. |

## Remaining validation

- Full UI translation for the requested Philippine languages is not complete;
  the current language choices are partial speech-recognition locales only.
- Upload type/signature, size, malformed-image, metadata, access-control, and
  private-serving tests remain outstanding.
- Full keyboard/screen-reader/forced-colors and accessibility audit remain
  outstanding.
- The privacy notice still needs barangay DPO/legal review; public contact and
  official details remain placeholders.
- SMS/email are manual compose links only; automated delivery is not configured.
- No production deployment has been performed. Do not deploy without the
  explicit `DEPLOY` instruction.
