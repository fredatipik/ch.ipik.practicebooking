# Practice Booking — installation and configuration

Appointment booking for CiviCRM, designed for practices where several
practitioners share one or more consulting rooms.

> **Interface language** — the administrative interface and the public form
> are currently in French. An English version is planned.

## Requirements

- CiviCRM 5.60 or later, tested on 6.15
- WordPress 6.0 or later
- PHP 8.1 or later
- cURL extension, for CalDAV synchronisation

## Installation

```bash
cd [civicrm]/ext/
tar xzf ch.ipik.practicebooking-x.y.z.tar.gz
cd [site root]
cv ext:enable ch.ipik.practicebooking
cv flush
```

Tables, migrations and message templates are set up automatically. On later
upgrades, replacing the files is enough: the schema updates itself the next
time a CiviCRM page is loaded.

## Configuration

### 1. General settings

**Booking → Settings**

- *Default availability mode* — see section 4
- *Default hours* — pre-fills the working-days calendar
- *Assignment strategy* — round-robin, least loaded, or random
- *Slot interval* — can be overridden per appointment type
- *Email reminder* — how long before the appointment

### 2. Rooms (optional)

**Booking → Rooms → New room**

A room is a place of consultation: name, address, colour, and its own CalDAV
URL. When an appointment is held there:

- the slot becomes unavailable to every practitioner
- an event is written to the room calendar, carrying only the practitioner's
  name — no patient data appears there
- conversely, any event already in that calendar blocks the corresponding
  slot, which allows booking the room without going through the form

Without any room configured, only personal calendars are consulted.

### 3. Practitioners

**Booking → Practitioners → New practitioner**

- Linked CiviCRM contact (required)
- CalDAV URL of the personal calendar
- Availability mode, or the general setting
- Buffer between appointments, booking horizon
- Usual room

Appointment types are attached from the *Appointment types* form; the
practitioner record shows them read-only.

### 4. Availability

Two modes coexist. The default is set in Settings, and each practitioner may
use the other one from their record.

**Weekly hours** — for regular schedules. Contact record → Agenda tab:

- *Recurring availability*: hours per weekday
- *Holidays*: a period with no slots at all
- *Exceptional slots*: additional one-off availability

**Declared working days** — for irregular availability. Contact record →
Agenda tab → *Working days calendar*: six months are shown, each working day
is ticked with a single click. Hours and room are set at the top of the page
and apply to subsequent clicks; a second click removes the day.

### 5. Appointment types

**Booking → Appointment types → New**

- *Restricted to logged-in patients* — the visitor must be logged in to the site
  with a WordPress account linked to a CiviCRM contact. Others are invited to
  log in; an account with no linked contact gets a message asking them to get
  in touch. There is deliberately no email-based check: it would prove nothing
  (anyone can type someone else's address) and would let visitors probe which
  addresses belong to patients.
- *Require WordPress account* — creates an account on first booking
- *Slot interval* — empty falls back to the general setting
- Eligible practitioners

### 6. CalDAV calendars

**Booking → Settings → CalDAV calendar**

| Field | Value |
|---|---|
| Username | The Infomaniak account code, e.g. `FK03484` — not the email address |
| Password | The account password |

The calendar URL is entered on each practitioner and each room record. Find it
under kSuite → Calendar → calendar settings → CalDAV. Expected format:
`https://sync.infomaniak.com/calendars/FK03484/<uuid>`
Query strings (`?export`) are stripped automatically.

Check from the command line:

```bash
curl -s -o /dev/null -w "%{http_code}\n" \
  -u "FK03484:PASSWORD" -X PROPFIND \
  "https://sync.infomaniak.com/calendars/FK03484/<uuid>"
# expects 207
```

Synchronisation is non-blocking: if CalDAV is unreachable, the appointment is
still recorded and the failure logged in CiviCRM. Each calendar's status is
shown in Settings, with a cleanup button when events from cancelled
appointments are left behind.

Other CalDAV providers should work — the implementation uses standard
free-busy queries and iCalendar — but only Infomaniak has been tested.

### 7. Billing

**Booking → Settings → Billing**

Financial type and contribution status pre-filled when a contribution is
recorded from an appointment. On the *All appointments* page, each appointment
offers two buttons:

- **Invoice** — Swiss QR invoice form (ch.ipik.swissQRinvoice)
- **Contribution** — CiviCRM contribution form

The contact is passed as `cid` in both cases.

### 8. Creating or editing an appointment from the back office

A *New appointment* button appears on the *All appointments* page and on each
practitioner's agenda.

The patient is searched among existing contacts, or created on the fly. The
practitioner defaults to the logged-in user when they are one; anyone may
create an appointment for a colleague.

The time is set in one of two ways. *Choose among available slots* offers
genuinely free slots, computed as for an online booking. *Enter a date and
time* places an appointment outside declared availability, without
verification — useful on the phone, for urgent cases.

Confirmation emails are sent by default; a checkbox skips them when the person
has already been told.

**Editing.** An ✎ icon on the *All appointments* page and on each
practitioner's agenda opens the same form for a confirmed or pending
appointment. Type, practitioner, time, room and notes can change; the patient
cannot — for another patient, cancel the appointment and create a new one.

The time defaults to *Keep the current time*. The free slots offered exclude the
appointment's own slot, so to shift it by a few minutes use manual entry. The
room is kept by default; choose *Deduce automatically* to recompute it from the
working day when the date or the practitioner changes.

On saving, the events are removed from the previous practitioner's and room
calendars and written again, and the CiviCRM activity is updated. A checkbox
*Resend the appointment email* sends the confirmation again to the patient and
to the practitioner. It is ticked automatically as soon as the time, the type or
the practitioner changes, and can be unticked. The previous practitioner is not
notified by email.

### 9. Public form

Create a WordPress page containing:

```
[ipik_booking]
```

The `type` attribute restricts the form to some appointment types, by id:

| Shortcode | Result |
|---|---|
| `[ipik_booking]` | All active types are offered |
| `[ipik_booking type="2,3"]` | Only types 2 and 3 are offered, in that order |
| `[ipik_booking type="3"]` | Goes straight to the calendar for type 3 |

Unknown or inactive ids are ignored. If none remains, the form shows a
message instead of the full list.

`type` shapes what the form displays; it is not an access control. Types
restricted to existing patients are enforced on the server whatever the
shortcode says.

For a logged-in visitor whose WordPress account is linked to a CiviCRM
contact, the form is pre-filled and remains editable.

**Restricted types.** A type restricted to patients requires a logged-in
visitor with a linked contact. A visitor who is not logged in sees a login
prompt with a *Log in* button that brings them back to the form; the calendar is
not opened and the slots are not served. For these types the email address is
locked and the booking is made on the contact of the session, never on an
address typed into the form. Patients therefore need a WordPress account linked
to their CiviCRM contact before such a type is switched on.

### 10. Sending emails

**Booking → Settings → Sending emails**

The sender address for confirmations, reminders and cancellations, chosen from
those declared under **Administer → Communications → From Email Addresses** —
the same list as for individual emails. Without a choice, the domain default
applies.

This matters when `ch.ipik.smtprouter` is installed: the chosen address
determines which SMTP server is used, each Infomaniak address requiring its
own authentication. A dedicated address such as `booking@example.org` keeps
these messages apart from other outgoing mail.

### 11. Public form wording

**Booking → Settings → Public form wording**

Four sentences address patients directly and can be edited without touching
the code: the login prompt for restricted types, the message for a logged-in
account with no patient record, the absence of available slots, and the booking
confirmation.

An empty field keeps the supplied text, shown greyed out in the field.

### 12. Message templates

Four templates are created automatically and can be edited under
**Administer → Communications → Message Templates**:

| Template | Recipient |
|---|---|
| Appointment confirmation | Patient |
| New appointment | Practitioner |
| Appointment reminder | Patient |
| Appointment cancellation | Patient and practitioner |

Extension-specific tokens: `{booking.type}`, `{booking.date}`,
`{booking.time}`, `{booking.duration}`, `{booking.therapist}`,
`{booking.contact_name}`, `{booking.location}`, `{booking.location_address}`,
`{booking.notes}`, `{booking.cancel_reason}`.
Standard CiviCRM tokens (`{contact.first_name}`…) work as well.

Edit links are gathered under Booking → Settings.

### 13. Dashboard

A *My appointments* dashlet is added to the CiviCRM dashboard. It shows the
ten upcoming appointments of the logged-in person, including those earlier the
same day.

The dashlet only shows appointments whose practitioner is the CiviCRM contact
of the logged-in user. An administrator account that is not registered as a
practitioner sees no appointments, whatever their permissions: patient names
have no reason to travel beyond the person who receives them.

Anyone can remove it from *Configure your dashboard* and add it back later.

### 14. Email reminders

**Administer → System Settings → Scheduled Jobs → « Booking : rappels email »**

Disabled by default. The delay is set in Settings.

## Privacy

An appointment with a therapist is medical information. A few safeguards are
in place by default.

**The dashlet** shows only the appointments of the logged-in practitioner.

**CiviCRM activities** created for each appointment carry the appointment type
as subject, never the patient's name: that subject is reproduced verbatim in
calendars and searches, visible to anyone with access to activities. The
patient's identity sits in the *target* field, subject to contact visibility
permissions.

If the mere existence of an appointment should not appear in shared calendars,
untick *Create a CiviCRM activity for each appointment* in Settings. The
appointment is still recorded and synchronised with CalDAV calendars, but
leaves no trace among activities — at the cost of no longer appearing in the
patient's record history.

**Restricted types** require a login rather than an email address: the form
never confirms or denies that an address belongs to a patient, and a booking
can only be made on the contact of the logged-in session.

**Room calendars** receive only the practitioner's name, as they are shared
among everyone working in the place.

## How a slot is deemed available

A slot is offered only when all of these hold:

1. the practitioner works that day, during the relevant period
2. no appointment overlaps it, buffer included
3. their personal calendar is free
4. the room they work in that day is free, if any

The room is not chosen at booking time: it follows from the declared working
day, or from the practitioner's usual room. A practitioner working elsewhere
(video call, home visit) simply leaves the room empty, and room calendars are
then ignored.

## Uninstallation

**Administer → System Settings → Extensions → Practice Booking → Uninstall**

Drops the `civicrm_booking_*` tables, the migration tracker, the message
templates and the `ipik_therapist` WordPress role.

Activities whose appointment no longer exists are moved to the trash rather
than deleted: the history of consultations keeps its value.
