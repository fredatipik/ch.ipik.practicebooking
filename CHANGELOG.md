# Changelog

## 0.6.0 — 2026-11-10

### Changed — read before upgrading a production site

- **Appointment types restricted to existing patients now require a login.**
  The visitor must be logged in to the site, with a WordPress account linked to
  a CiviCRM contact. The previous email-based check is removed: it proved
  nothing, and let anyone probe which addresses belong to patients. Patients
  therefore need an account linked to their CiviCRM contact *before* such a
  type is used. For these types the booking is made on the contact of the
  session, never on an address typed into the form.
- The *Existing contacts only* option is now labelled *Restricted to logged-in
  patients*. The two public-form messages linked to it were reworded; if you had
  customised them under Settings → Public form wording, read them again.
- The *All appointments* page now lists appointments from the first day of the
  current month onwards, in chronological order. It used to stop at the end of
  the current month, which hid upcoming appointments.

### Added

- **Edit an appointment** from the back office (✎ icon): type, practitioner,
  time, room and notes. Calendar events are moved and the CiviCRM activity is
  updated. A checkbox resends the appointment email; it is ticked automatically
  when the time, type or practitioner changes.
- `[ipik_booking type="2,3"]` offers only the listed types, in that order. A
  single type goes straight to the calendar, with the *Back* button hidden.
- The sender address of emails is chosen from the CiviCRM *From Email
  Addresses*, and every message refused by the SMTP server is now logged with
  its sender and recipient.

### Fixed

- The shortcode and its AJAX actions are registered even when CiviCRM starts
  after WordPress's `init` action. On such sites the page displayed the raw text
  `[ipik_booking]` and the form never loaded.
- The working-days calendar displayed oversized blue blocks under CiviCRM themes
  that restyle buttons. Days are no longer `<button>` elements, and can be
  toggled from the keyboard.
- The sender address menu showed escaped characters, and an unset address fell
  back to a made-up `noreply@` that SMTP servers reject (*Sender mismatch*).
- Stylesheet and script URLs now change when the files do, so browsers no
  longer keep serving an outdated copy after an upgrade.

## 0.5.0 — 2026-09-06

- The extension key changed from `ch.ipik.booking` to `ch.ipik.practicebooking`
  to avoid a clash with the existing CiviBooking extension. Installed sites need
  the key renamed in place; their data is untouched.
