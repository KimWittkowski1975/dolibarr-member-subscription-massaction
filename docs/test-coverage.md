# Reading the existing requirement matrix

The 71 IDs and original columns in `requirements.csv` remain authoritative. Implemented/partial rows identify actual components, existing assertion labels,
real test references and explicit coverage gaps. No parallel requirement list is kept.

- `run:<label>` means the literal label (or clearly identified label prefix) in
  `test/unit/run.php`. Find it with a literal text search. Parameterized assertions
  share a label; inspect their adjacent data table for the covered cases.
- `preview:<label>` means the same in `test/unit/preview.php`. Error-key references
  identify `fails(...)` assertions. For eligibility, see the `Direct business
  eligibility contract` block: status, configured status list and subscription flag
  are separate cases even though they deliberately share one error key.
- `preview:OPT-01` covers independent mail in Phase 3B; adjacent failures reject
  forged invoice/third-party fields. `preview:OPT-09` exercises disabled controls.
  Former Phase 3A OPT-02..08 references describe historical behavior only.
- `ST-*` refers to `test/integration/smoke.md`; historical evidence has its date and
  baseline in `test/integration/browser-smoke.md`.
- `BR-*` refers to the historical 13 Chrome cases documented in
  `test/integration/browser-smoke.md`. Their original raw harness is unavailable;
  these references do not imply a new execution.
- `OPT-HTTP-*` refers to the targeted, real no-JavaScript HTTP test in
  `test/integration/options-http.py` and its anonymized evidence file.

A PASS applies only to the named, implemented portion. In particular, invoice amount
preflight is not an invoice creation test, a fingerprint is not an atomic replay test,
and synthetic entity cases are not real Multicompany integration. Stock/product access
fixtures and some defaults remain explicitly identified gaps. Invoice/product paths remain disabled in Phase 3B; review these deferred
paths before implementing invoice booking.

Do not inflate assertion counts to disguise gaps. Revisit medium/high-risk missing
coverage when the relevant path changes or becomes capable of writing business data.

Phase 3B additions: `booking:<label>` references test/unit/booking.php;
`maildelivery:<label>` references test/unit/maildelivery.php. The booking runner
includes the existing preview runner once. Former OPT scenarios allowing invoice
planning are superseded by independent-mail and rejected-invoice form assertions.
Historical OPT-HTTP evidence is not evidence of the new booking controller.
Current evidence is separated by provenance (see `docs/phase3b-validation.md`):

- Automated synthetic coverage: preview rules, booking/transaction control paths,
  mail adapters, hook outcomes and UI rendering regressions.
- Real integration: original ten-member batch and independent database reconciliation;
  INT-01–INT-11 within their stated scopes, including controlled rollback/R16 adapters
  and two parallel database connections. INT-03 itself remains a function-boundary test.
- `HTTP-01`–`HTTP-05` and `HTTP-03-phase`: separate authenticated HTTP/session follow-up,
  classified HTTP REPLAY VALIDATED WITH LIMITATIONS; no mail selected. Guard attribution
  uses request values and source; the full-user hash comparison has a documented limitation.
- `UI-01`–`UI-09`: owner-performed manual browser acceptance, 9/9 PASS; not an automated
  or agent-reproduced browser test.
- External confirmation: the owner reported receipt of ten original emails. This is
  not an agent-observed mailbox check or validation of every template/language variant.

Arbitrary external hooks, actual server/connection loss with uncertain COMMIT replies,
complete session loss and other Dolibarr versions remain unvalidated. No permanent
exactly-once guarantee or unrestricted production approval is claimed. Synthetic
mail-failure/hook tests do not become real SMTP-failure tests through successful receipts.
The owner accepted the documented R05–R09 boundaries for Phase 3B on V22.0.5; these
limits remain in force. No new requirement matrix or test execution is implied.
