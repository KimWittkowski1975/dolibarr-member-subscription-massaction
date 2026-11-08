# Functional porting baseline

Phase 1 revision 2 is the accepted architecture: an independent wizard entered through
`memberlist` / `addMoreMassActions` / `doMassActions`. The future result notice uses
`printFieldPreListTitle` when needed. No core hooks or Adherent overrides are added.

The accompanying `requirements.csv` preserves all **71** requirements from that audit.
Original porting-assessment columns are retained. Added phase/status/evidence columns
record actual implementation and distinguish read-only portions from deferred booking.
The source remains PR #42620, commit `14c4b07ba86e7a320d61e75b28cb88a50ce54f77`.
Phase 3A adds read-only calculations and interactive previews. Booking, invoice/PDF
creation and email remain future scope; preview success does not authorize writes.

Mandatory later risk work:

- R04: PDF generation is not atomic with a database transaction; verify failures and remnants.
- R05: concurrent individual and bulk processing do not necessarily share a locking protocol.
- R06: core nested transactions use counters, not independent savepoints.
- R07: session replay protection does not provide atomic database/SMTP crash recovery.
- R08: preserve sendMail hook return semantics, object replacement and context restoration.
- R09: historical synthetic scenarios are not proof of real database/filesystem integration.
- R16: V22 `Adherent::subscription()` does not fully propagate an `update_end_date()` failure;
  design and verify adapter consistency handling before accepting business writes.

Future scope includes per-member transactions, optional invoices/third parties/PDF/mail,
persistent results, atomic replay handling and V22–V25 qualification. Preview rows
include a fingerprint of computed values and relevant source/configuration fields,
and an overlap snapshot. These prepare the confirmation design but are NOT a booking
lock, durable journal, atomic replay guard or permission to skip revalidation.

See phase3a-validation.md for calendar compatibility details and current evidence.
