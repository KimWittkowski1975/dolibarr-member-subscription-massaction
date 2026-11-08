# Member Subscription Massaction for Dolibarr

**Unreleased Phase 3B development: subscription booking and optional confirmation
email are implemented, but real write/SMTP integration is not yet approved or tested.
Do not use in production.**

The module adds an authenticated member-list preview, an explicit confirmation and
per-member booking results. It never creates invoices, third parties, PDFs, payments
or bank entries itself. These options are disabled and rejected server-side.

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
   rule. Select confirmation email independently, then review the separate confirmation.
   Confirm each displayed overlap explicitly and use **Book confirmed subscriptions now**.
   Missing overlap consent skips that row. Invoice and third-party options remain disabled.
8. Cancel invalidates only this run and returns to the list; the Back link leaves
   it available until expiry. Deactivation removes the action from the list.

V22 clears `massaction` without `confirmmassaction`. The module uses that existing
list contract and `doMassActions` before response output; it does not introduce a
second confirmation form into the core list. It appends an option with return 0.

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
commits can proceed to mail. Results survive reload within the session; uncertain
outcomes require manual database inspection, never an automatic retry.

Booking currently supports the mysqli driver with MySQL/MariaDB and InnoDB only.
At most 50 members may be confirmed together; use smaller batches when transport is
slow. This cap is not a timeout guarantee. A crash leaves pending rows uncertain.
There is no durable operation journal, SMTP queue or exactly-once claim. Core and
third-party actions need not honor the module's member-row lock. Core triggers still
run and can have nontransactional side effects; inventory them before real tests.

The results page uses plain escaped HTML and remains available without a timed toast.
No JavaScript is required. See [Phase 3B validation](docs/phase3b-validation.md) for
transaction, crash, hook and unexecuted integration-test boundaries.

## Checks and next phase

Run `php test/unit/run.php` and
`DOLIBARR_TEST_ROOT=/path/to/dolibarr php test/unit/booking.php` and
`php test/unit/maildelivery.php` (no dependencies
or database bootstrap). The booking runner includes the existing preview runner and requires a V22.0.5 checkout.
See [Phase 3A validation](docs/phase3a-validation.md) for evidence and limitations.
Lint PHP files and run `git diff --check`; CI also checks the initial commit.
Synthetic tests do not prove actual browser integration. Follow
[test/integration/smoke.md](test/integration/smoke.md) on an approved installation.
The complete phase-1 requirements and open risks are retained in
[docs/porting-plan.md](docs/porting-plan.md) and [docs/requirements.csv](docs/requirements.csv).
Do not publish a release until a permanent ID and real runtime checks are complete.

Future changes follow [the test strategy](docs/test-strategy.md). Precise coverage
references remain in [the requirement matrix](docs/requirements.csv); see
[the coverage legend](docs/test-coverage.md). Legacy Phase 3A previews are invalidated for confirmation and normalized to disabled
invoice/third-party options. Confirmation mail remains independent of invoices.
