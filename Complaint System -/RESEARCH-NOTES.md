# Research Notes

Research accessed 20 November 2026. This is design and engineering research,
not legal advice or a claim that the system complies with any law or standard.

## Public complaint and service portals

The feasibility report and DFD define the core flow: receive a complaint,
review and assign it, resolve it, and return a status/result to the resident.
Useful public-service form patterns include:

- Explain the process before the user starts, then keep the form focused on the
  information needed to act on the report.
- Show a unique reference, current status, dated history, who is handling the
  case when appropriate, and a clear next action.
- Let staff search and filter the work queue, inspect the record, assign an
  owner, and record decisions/actions; provide aggregate reports separately
  from personally identifying information.
- Show clear, specific success and error messages. Errors should identify the
  field and how to fix it, with a summary at the top and inline guidance.
- Use a visible step indicator only when a form really has multiple steps.

These are interaction principles only. Do not copy the wording, images, or
visual identity of the examples below.

Sources:

- [WAI Forms Tutorial: User Notification](https://www.w3.org/WAI/tutorials/forms/notifications/)
- [U.S. Web Design System: Step indicator](https://designsystem.digital.gov/components/step-indicator/)
- The project's own [Feasibility Report](C:/xampp/htdocs/REQUIREMENTS/FEASIBILITY%20REPORT.docx)
  and [DFD](C:/xampp/htdocs/REQUIREMENTS/DFD.docx)

## Accessibility

- Use WCAG 2.2 Level AA as the engineering target, then validate with both
  automated checks and people using keyboards and assistive technology. Passing
  an automated checker alone is not proof of conformance.
- Keep keyboard focus visible, do not make color the only status cue, support
  zoom/reflow, label inputs, and give errors clear text and links to the
  affected controls.
- WCAG 2.2 AA defines a 24-by-24 CSS-pixel minimum target with exceptions.
  Important touch controls should aim for the larger 44-by-44 target stated in
  this project brief.
- A modal must keep keyboard navigation inside while open, move focus into the
  dialog, close with Escape, expose a label, and return focus to the opener.
  Prefer the native `<dialog>` element where browser behavior is reliable.
- Respect reduced-motion and forced-colors preferences; custom themes must not
  interfere with browser/OS accessibility settings.
- Make font, spacing, contrast, and focus preferences optional and reversible;
  no one font or palette is right for every user.

Sources:

- [W3C WCAG 2.2 Recommendation](https://www.w3.org/TR/WCAG22/)
- [WCAG 2.2: Contrast Minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
- [WCAG 2.2: Target Size Minimum](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)
- [WAI-ARIA Authoring Practices: Modal Dialog Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)
- [WAI Forms Tutorial: User Notification](https://www.w3.org/WAI/tutorials/forms/notifications/)
- [Republic Act No. 7277 (Lawphil)](https://lawphil.net/statutes/repacts/ra1992/ra_7277_1992.html)
- [Batas Pambansa Blg. 344 (Lawphil)](https://lawphil.net/statutes/bataspam/bp1983/bp_344_1983.html)

BP 344 concerns accessibility of physical buildings, public utilities, and
transport; it is not itself a web conformance checklist. The digital interface
will use WCAG as its technical reference and avoid making legal claims.

## Philippine privacy and barangay context

- RA 10173 describes principles including transparency, legitimate purpose, and
  proportionality; it also discusses data quality, retention, and safeguards.
  Design for minimal collection, purpose explanations, role-limited access,
  correction/deletion handling, retention review, and secure evidence handling.
- A consent checkbox is not a substitute for deciding and documenting the
  appropriate lawful basis, purpose, retention period, controller contacts, and
  privacy notice. A barangay DPO must review the notice and operating process.
- RA 7160 is the Local Government Code and contains barangay and local
  governance provisions. The application should support the barangay's
  documented process rather than decide legal eligibility, issue legal
  conclusions, or pretend that a database status is a court/official finding.
- The DFD's review/assign/resolve flow supports a recordkeeping workflow.
  Katarungang Pambarangay scheduling and summons tracking should remain
  administrative records unless barangay counsel/DPO approves a more specific
  process.
- The accessibility laws and privacy law are cited for design context only.
  This project does not provide legal advice or certify compliance.

Sources:

- [Republic Act No. 10173 (Lawphil)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- [Republic Act No. 7160 (Lawphil)](https://lawphil.net/statutes/repacts/ra1991/ra_7160_1991.html)
- [Republic Act No. 7277 (Lawphil)](https://lawphil.net/statutes/repacts/ra1992/ra_7277_1992.html)
- [Batas Pambansa Blg. 344 (Lawphil)](https://lawphil.net/statutes/bataspam/bp1983/bp_344_1983.html)

The Official Gazette and National Privacy Commission pages attempted during
research returned HTTP 403 to the research fetcher. The accessible Lawphil
copies above were used instead; source availability is not evidence that the
barangay's legal process has been reviewed.

## Safe file uploads

Use an allowlist of required formats, server-side size limits, server-detected
file types/signatures (not the browser's content-type), random stored names,
and defense in depth. Prefer storing files outside the public webroot; if that
is not available, deny direct web access and serve files through a controller
that checks ownership/authorization. Protect upload POSTs with CSRF. Image
re-encoding/resize can remove embedded metadata; PDF handling requires a
separate risk decision and content-disarm review. Do not trust a filename such
as `photo.php.jpg`.

Sources:

- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [PHP GD and Image Functions](https://www.php.net/manual/en/book.image.php)

The local PHP CLI currently does not load GD. Confirm the GD DLL and Apache
runtime before enabling image re-encoding; do not silently fall back to storing
unprocessed images.

## Session security

Use cookie-only session IDs, strict mode, HttpOnly, SameSite, and Secure on
HTTPS deployments; regenerate IDs after authentication and enforce server-side
idle expiration. Secure must be enabled in the live HTTPS configuration.

Source:

- [PHP: Securing Session INI Settings](https://www.php.net/manual/en/session.security.ini.php)

## Provisional host research (not a host selection)

The current [InfinityFree hosting page](https://www.infinityfree.com/) advertises
PHP 8.4, MySQL 8.0/MariaDB 11.4, 5 GB storage, unlimited bandwidth, free SSL,
400 databases, and `.htaccess` support. These are provider marketing statements,
not a service-level guarantee. Its publicly fetched page did not confirm the
specific limits needed here (SSH, cron frequency, mail sending, upload limits,
resource/time quotas, or availability of PHP GD). Do not design deployment
around unverified claims. The target host is unknown; verify its current
technical terms after the user selects it and before creating a deploy package.

## Research limitations

- No host has been selected and no account-specific host limits were inspected.
- No interviews with barangay personnel or residents have been conducted.
- No legal professional or DPO has reviewed the proposed notice/workflow.
- Sources were consulted for design and technical context; they do not prove
  conformance, legal suitability, or hosting reliability.
