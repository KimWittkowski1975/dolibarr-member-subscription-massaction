# Member Subscription Massaction for Dolibarr

**Unreleased Phase 4: optional invoice, post-commit PDF and attached confirmation
email paths are implemented. G2 is complete for the tested Dolibarr V22.0.5 profile;
G3 is complete with documented limitations. Automated G4 pre-acceptance has been
performed; final owner browser/UX acceptance remains pending. V24 is not qualified.
No production approval.**

See [Phase 4 implementation validation](docs/phase4-implementation-validation.md)
for the accepted scope, historical evidence and retained limitations. The Phase 3B
acceptance below is historical and does not replace final Phase 4 acceptance.

Real booking/database integration and authenticated HTTP replay tests have been
performed; manual browser acceptance passed 9/9. The owner confirmed receipt of the
ten original emails. The independent final review concluded **APPROVED WITH CONDITIONS**;
its documentation and mail-comment findings are addressed by this focused follow-up,
subject to final diff/CI verification. This is not unrestricted production approval.
See [Phase 3B validation](docs/phase3b-validation.md) for evidence, limitations and
the owner's scoped residual-risk acceptance.

The module adds an authenticated member-list preview, an explicit confirmation and
per-member booking results. It can optionally create a linked third party, a validated
standard invoice and its PDF through Dolibarr Core APIs. It never creates payments or
bank entries. Invoice qualification is limited to the tested V22.0.5 profile documented above.

## Origin and scope

Derived from the architecture of [Dolibarr PR #42620](https://github.com/Dolibarr/dolibarr/pull/42620),
feature commit `14c4b07ba86e7a320d61e75b28cb88a50ce54f77`.
The module does not import the core history or change core PHP/language files.
The descriptor follows the Dolibarr 22.0.5 ModuleBuilder template; its original
copyright notices are retained. Preview rules are ported by composition into module-owned services; no Core classes are patched.
Code is GPL-3.0-or-later; see COPYING. No KSeF or FacturX code is included.

## Development baseline

Dolibarr **22.0.5**, PHP **8.2** locally. PHP 7.4 is the provisional syntax minimum;
CI covers the scaffold contracts and preview regressions using pinned V22 calendar helpers on 7.4 and 8.2. This is not a complete
runtime compatibility claim. V23–V25 compatibility will be tested in later phases.

Module ID **584620 is temporary**, checked against local descriptors and the
[official ID rules](https://wiki.dolibarr.org/index.php?title=List_of_modules_id&oldid=68837).
It is an unreserved development value above 500000, only for approved isolated
tests. Reserve and replace it before distributing a module release. Public source
availability does not imply a released, installable production module.

## Development installation

1. Use an explicitly approved Dolibarr 22.0.5 test installation. Do not point older
   core code at an upgraded database. Activation writes module configuration.
2. Clone this repository separately. Its root is the module root (no `htdocs` layer).
3. Link or copy that root to `htdocs/custom/membersubscriptionmassaction`.
   Never replace an existing installation blindly. Apache needs traversal/read
   access and permission to follow the link, not write access to module sources.
4. Ensure Dolibarr already recognizes `custom` as an alternative module directory.
5. In Setup → Modules, enable Members first, then Member Subscription Massaction.
   No new tables, document directories, menus or redundant permissions are created.
6. Internal users need both member read and subscription creation permissions.
7. Select members in the member list, choose **Create member subscriptions (bulk)**
   and press the existing **Confirm** button. The hook opens the financial preview.
   Change individual dates, reset one end, or explicitly apply a common start/calendar
   rule. Select invoice and confirmation email independently. Missing linked third
   parties can be created only after selecting the separate explicit option. Then review the confirmation.
   Confirm each displayed overlap explicitly and use **Book confirmed subscriptions now**.
   Missing overlap consent skips that row. An invoice email requires the verified PDF attachment.
8. The preview provides a Back link to the member list without invalidating the
   preview. It remains available until normal expiry. No separate Cancel button is
   rendered; an internal cancel route is retained. Deactivation removes the list action.

V22 clears `massaction` without `confirmmassaction`. The module uses that existing
list contract and `doMassActions` before response output; it does not introduce a
second confirmation form into the core list. It appends an option with return 0.

## Display conditions

Recommended desktop screen resolution: **1440 × 900 pixels or higher**.
The targeted minimum browser viewport for regular desktop use is **1024 × 768 pixels**, qualified
by focused headless Chromium/Firefox layout checks on Dolibarr V22.0.5. Final
visual and owner acceptance remains pending.

These are module-specific conditions, not official Dolibarr minimum requirements.
The usable browser viewport can be smaller than the screen resolution. Wide member
tables scroll horizontally within their own table area when space is limited.
The module does not provide a dedicated smartphone interface. Checks at 768 and
390 px viewport widths cover layout regression only; unchanged Core layout
limitations are assessed separately.

## Security and limitations

Selections are strictly positive unique IDs, bounded by `MAIN_LIMIT_FOR_MASS_ACTIONS`
(default 1000). Malformed, duplicate, missing and inaccessible IDs reject the whole
selection. Shared entities follow `getEntity('adherent')` and object access checks.
Permissions and access are rechecked when opening the assistant.
The original selection store keeps IDs for 15 minutes. A separate `msm_previews`
namespace holds at most five owner/entity-bound previews for one hour, with random
128-bit identifiers. Each form carries a revision to reject stale POSTs. Every
refresh reloads business values; POST cannot override member IDs, amounts or types. A run identifier is not a
replacement for authentication or CSRF. POST actions validate the session token;
redirect destinations are fixed internal paths. No untrusted return URL is used.

GET never books. Confirmation is consumed and saved before processing. Member rows
are locked and revalidated within independent InnoDB transactions; the V22 end-date
update return and actual saved values are checked before commit. Only confirmed
commits can proceed to PDF generation and mail. Results survive reload within the session; uncertain
outcomes require manual database inspection, never an automatic retry.

Booking currently supports the mysqli driver with MySQL/MariaDB and InnoDB only.
At most 50 members may be confirmed together; use smaller batches when transport is
slow. This cap is not a timeout guarantee. A crash leaves pending rows uncertain.
There is no durable operation journal, SMTP queue or exactly-once claim. Core and
third-party actions need not honor the module's member-row lock. Core triggers still
run and can have nontransactional side effects; inventory them before real tests.

The results page uses escaped HTML and remains available without a timed toast. Its
final-submit status indicator resets on BFCache restoration; the complete form remains
usable without JavaScript. See [Phase 4 implementation validation](docs/phase4-implementation-validation.md) and [Phase 3B validation](docs/phase3b-validation.md) for
transaction, crash, hook and unexecuted integration-test boundaries.

## Checks and next phase

Run `php test/unit/run.php` and
`DOLIBARR_TEST_ROOT=/path/to/dolibarr php test/unit/booking.php` and
`php test/unit/maildelivery.php`, `php test/unit/invoiceprofile.php`,
`php test/unit/invoice.php`, `php test/unit/invoicepdf.php`,
`php test/unit/postcommit.php` and `node test/unit/loading.js` (no dependencies
or database bootstrap). The booking runner includes the existing preview runner and requires a V22.0.5 checkout.
See [Phase 3B validation](docs/phase3b-validation.md) for current evidence and limitations;
[Phase 3A validation](docs/phase3a-validation.md) preserves historical evidence.
Lint PHP files and run `git diff --check`; CI also checks the initial commit.
Synthetic tests alone do not prove browser integration; the separate owner-performed
UI-01–UI-09 acceptance is recorded in Phase 3B validation. For future checks, follow
[test/integration/smoke.md](test/integration/smoke.md) on an approved installation.
The complete phase-1 requirements and open risks are retained in
[docs/porting-plan.md](docs/porting-plan.md) and [docs/requirements.csv](docs/requirements.csv).
Do not publish a release until a permanent ID and real runtime checks are complete.

Future changes follow [the test strategy](docs/test-strategy.md). Precise coverage
references remain in [the requirement matrix](docs/requirements.csv); see
[the coverage legend](docs/test-coverage.md). Legacy Phase 3A previews are invalidated for confirmation and normalized to disabled
invoice/third-party options. Confirmation mail remains independent of invoices.
