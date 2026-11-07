# Functional porting baseline

Phase 1 revision 2 is the accepted architecture: an independent wizard entered through
`memberlist` / `addMoreMassActions` / `doMassActions`. The future result notice uses
`printFieldPreListTitle` when needed. No core hooks or Adherent overrides are added.

The accompanying `requirements.csv` preserves all **71** requirements from that audit.
Its porting statuses describe the planned functional port, not completed scaffold work.
The source remains PR #42620, commit `14c4b07ba86e7a320d61e75b28cb88a50ce54f77`.
This phase implements only secure selection, navigation and technical integration.
It neither replaces nor narrows the remaining business scope.

Mandatory later risk work:

- R04: PDF generation is not atomic with a database transaction; verify failures and remnants.
- R05: concurrent individual and bulk processing do not necessarily share a locking protocol.
- R06: core nested transactions use counters, not independent savepoints.
- R07: session replay protection does not provide atomic database/SMTP crash recovery.
- R08: preserve sendMail hook return semantics, object replacement and context restoration.
- R09: historical synthetic scenarios are not proof of real database/filesystem integration.
- R16: V22 `Adherent::subscription()` does not fully propagate an `update_end_date()` failure;
  design and verify adapter consistency handling before accepting business writes.

Future scope includes all amount/date/calendar/null-amount rules, previews and warnings,
per-member transactions, optional invoices/third parties/PDF/mail, persistent results,
permissions, entity isolation, browser behavior and V22–V25 qualification.
