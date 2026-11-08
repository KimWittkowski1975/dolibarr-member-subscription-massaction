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
fixtures and some defaults remain explicitly identified gaps. They have no current
business-write consequence; review them before booking implementation.

Do not inflate assertion counts to disguise gaps. Revisit medium/high-risk missing
coverage when the relevant path changes or becomes capable of writing business data.

Phase 3B additions: `booking:<label>` references test/unit/booking.php;
`maildelivery:<label>` references test/unit/maildelivery.php. The booking runner
includes the existing preview runner once. Former OPT scenarios allowing invoice
planning are superseded by independent-mail and rejected-invoice form assertions.
Historical OPT-HTTP evidence is not evidence of the new booking controller.
All new database/SMTP outcomes are synthetic; real integration is NOT EXECUTED.
