# Assumptions and Decisions

These decisions fill gaps or resolve conflicts between the feasibility report,
the DFD, the expanded project brief, and the existing application. They are
provisional until verified with barangay staff.

1. **Project root:** The active application is `C:\xampp\htdocs\Complaint System`
   because it is the current workspace. The separate lowercase `complaint-system`
   folder is not modified. The supplied profile text is supporting content, not
   the application root.
2. **Data preservation:** The current database has existing records. No setup,
   migration, or demo-data script may drop/recreate those records. Test
   migrations on a separate database restored from the dated SQL backup.
3. **Account model conflict:** The expanded brief says there are only USER and
   ADMIN account types, while the feasibility report names several barangay
   personnel and the existing app distinguishes Admin and assigned Officials.
   Treat these as two account classes (resident and staff) with explicit
   permission scopes. Preserve least-privilege assignment-only staff access;
   reserve all-record/user/site-management actions to the authorized
   administrator. Confirm this model with barangay personnel.
4. **Complaint intake:** Residents can submit online, and authorized personnel
   must also be able to record complaints received offline, as stated in the
   feasibility report. Staff-entered records must identify the staff recorder
   and distinguish that recorder from the complainant.
5. **Status vocabulary conflict:** The feasibility report and expanded brief
   list different status values. Preserve legacy labels/data; add a reviewed,
   documented staff workflow rather than silently rewriting existing records.
   Keep Katarungang Pambarangay scheduling as a related record and do not infer
   legal eligibility from a status.
6. **Public information:** Barangay name, address, hotline, office hours,
   officials, logo, and developer contact are unapproved. Use obvious editable
   placeholders, never invent real contact/official details.
7. **Privacy/legal:** The privacy notice is a draft. The barangay DPO/counsel
   must select the lawful basis, approve purposes/retention/contact details, and
   review handling of respondent and complaint data. The application will not
   claim legal compliance.
8. **Developer profile photo:** The supplied `ID_Photo.jpg` appears to be an
   identity document/photo and is not suitable for public display without
   explicit confirmation. Use a neutral original placeholder until an approved
   portrait is supplied.
9. **Language:** The user requests a language setting covering languages used
   in the Philippines. The current options cover only some speech-recognition
   locales; the interface itself remains English, so this requirement is not
   complete. Confirm the supported language list and translation scope before
   claiming full coverage. Voice-recognition locale choices are not a
   translation.
10. **Notifications:** Keep in-app notices. Do not claim SMS/email delivery;
    manual compose links may be offered, but no message is sent automatically.
11. **Evidence files:** Store private complaint evidence so direct static URLs
    cannot bypass authorization; serve through a controller after checking the
    resident owner or staff permission. A PDF may be allowed only after
    extension/type/size validation and a documented safe-serving policy.
12. **Local development:** Use XAMPP/MySQL locally, with verbose development
    logging and no raw production errors. Confirm that Apache and PHP use the
    expected configuration before changing runtime settings.
13. **Host/deployment:** No host has been selected. InfinityFree is only a
    research example. Create no live account, upload, DNS change, or deployment
    until the user supplies a host and explicitly says `DEPLOY`.
14. **Demo data:** Keep optional demo fixtures separate from the live/current
    database. Never seed the existing barangay database by default.
