# Design Notes

## Direction

Use a calm, official civic style that feels trustworthy without assuming that
the current barangay name or logo is final. Admin-editable placeholders remain
until the barangay supplies approved content. Keep the resident path simple:
understand the service, create an account, file, track, and follow up. Keep
staff pages focused on queue triage, assignment, action history, and reports.

Do not copy the layouts, text, photos, seals, or logos of reference portals.
Create original CSS and SVG illustrations.

## Palette and states

Initial design tokens:

| Token | Direction | Use |
|---|---|---|
| `--brand` | Deep civic green, approximately `#175C49` | Primary actions and identity |
| `--brand-strong` | Darker green, approximately `#104536` | Hover/active states |
| `--accent` | Muted blue, approximately `#315E85` | Secondary information |
| `--surface` | White | Cards and form surfaces |
| `--canvas` | Warm, very light neutral | Page background |
| `--text` | Deep charcoal green | Body text |
| `--muted` | Dark neutral, still contrast-checked | Secondary copy |
| `--danger` | Dark red | Errors/destructive actions |

Use text labels and icons as well as color for statuses. Define separate light,
dark, high-contrast light/dark, and yellow-on-black themes. Check each foreground
and background combination against WCAG contrast requirements; do not infer
contrast from color names or appearance.

## Typography

- Primary self-hosted option: OpenDyslexic, from its official site, which states
  that current releases use SIL Open Font License 1.1. Bundle its license and
  attribution with the font. Offer it as an optional preference, not a required
  default.
- Readable default font: self-hosted Atkinson Hyperlegible (SIL Open Font
  License 1.1) or a self-hosted system-friendly alternative after local size and
  rendering checks. Retain accessible fallback stacks.
- No Google Fonts or other runtime font/CDN requests. Keep language strings
  separate from style/font resources.

## Spacing and shape

- 4px base spacing scale: `4, 8, 12, 16, 24, 32, 48, 64px`.
- Cards and controls: 8–12px corner radius; 1px low-contrast borders plus
  visible focus rings.
- Forms use a single readable column by default, with related fields grouped.
- Keep text widths comfortable; avoid fixed-width layouts that break at zoom.

## Components and interaction

- Sticky but compact responsive header, skip link, breadcrumbs, landmarks.
- Buttons/links: clear default, hover, keyboard focus, active, disabled, and
  in-progress states. Important touch controls target 44×44 CSS pixels.
- Alerts: top-level summary plus field-level links and concise corrections;
  status/success announcements use live regions.
- Complaint filing: clear emergency caveat, concise instructions, optional
  multi-step indicator only if the workflow is actually split into steps.
- Complaint details: reference number, status, history timeline, assignment,
  private messages, and public updates separated from private staff notes.
- Tables: semantic headers, responsive overflow, keyboard-usable filters,
  sorting, pagination, empty states, and no color-only data.
- Charts: original accessible inline SVG and an equivalent text/table summary.
- Dialogs/accordions: native semantic elements when possible; keyboard behavior
  and focus return are tested.
- Micro-interactions are subtle and disabled/reduced when requested by the OS
  or user.

## Libraries and licenses

| Resource | Planned use | License |
|---|---|---|
| PHP, MySQL/MariaDB, HTML, CSS, JavaScript | Application stack | Existing platform licenses |
| OpenDyslexic font files | Optional reading font, bundled locally | SIL Open Font License 1.1 (official project page) |
| Atkinson Hyperlegible font files | Readability option/default candidate, bundled locally | SIL Open Font License 1.1 |
| Original SVG artwork/icons | Site illustrations and status symbols | Created for this project; no third-party library |
| Native SVG charts | Reports | Created for this project; no chart package |
| Third-party UI/chart/icon packages | None planned | No dependency/CDN is required |

Before bundling any font files, save the upstream license/attribution file and
record the exact upstream release. Do not add a package unless it solves a
specific requirement and its license can be documented.
