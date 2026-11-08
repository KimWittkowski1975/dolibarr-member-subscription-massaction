# Changelog

## Unreleased

- Add explicit, single-use booking confirmation and persistent per-member results.
- Book through the V22 member API with owned transactions, member locks and R16 checks.
- Send optional confirmation mail only after commit, preserving subscription hooks and templates.
- Disable invoice/third-party options and add synthetic transaction, replay and mail regressions.

- Match V22 single-subscription defaults for zero membership-duration quantities.

- Keep planned third-party creation dependent on invoice mode, including no-JavaScript forms.
- Map preview requirements to concrete tests and retain sanitized available browser evidence.
- Establish risk-based validation and compact reporting for subsequent phases.

- Add read-only per-member amount, eligibility, period, description and overlap services.
- Add interactive dates, manual-end preservation, explicit common/calendar actions and summaries.
- Add simulation-only invoice, third-party and mail preflight, without a booking endpoint.
- Separate one-hour previews from selection runs; enforce CSRF, owner/entity and stale-form checks.
- Add V22 calendar compatibility regression coverage, EN/DE UI and browser validation.

### Initial module scaffold and Dolibarr V22.0.5 hook integration

- Add an external module descriptor and member-list massaction entry.
- Validate member selection and open an authenticated session-bound technical assistant.
- Add EN/DE labels, synthetic contracts and minimal CI.
- Keep all subscription, invoice and email business processing for later phases.
