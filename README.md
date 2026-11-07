# Member Subscription Massaction for Dolibarr

**Development scaffold: 0.1.0-dev. No subscriptions, invoices, payments,
third parties, PDFs or emails are created. This is not a feature release.**

An external module that adds a member-list action and an authenticated technical
assistant showing the selected member IDs. Existing core actions remain available.
The complete functional port is planned, not implemented in this scaffold.

## Origin and scope

Derived from the architecture of [Dolibarr PR #42620](https://github.com/Dolibarr/dolibarr/pull/42620),
feature commit `14c4b07ba86e7a320d61e75b28cb88a50ce54f77`.
The module does not import the core history or change core PHP/language files.
The descriptor follows the Dolibarr 22.0.5 ModuleBuilder template; its original
copyright notices are retained. Other scaffold logic is newly implemented.
Code is GPL-3.0-or-later; see COPYING. No KSeF or FacturX code is included.

## Development baseline

Dolibarr **22.0.5**, PHP **8.2** locally. PHP 7.4 is the provisional syntax minimum;
CI covers the synthetic scaffold contracts on 7.4 and 8.2. This is not a complete
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
   and press the existing **Confirm** button. The hook redirects to the technical
   assistant showing IDs and count. It does not display a financial preview.
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
Only IDs are stored in an owner/entity-bound session namespace, at most five runs,
with random 128-bit identifiers and a 15-minute lifetime. A run identifier is not a
replacement for authentication or CSRF. POST actions validate the session token;
redirect destinations are fixed internal paths. No untrusted return URL is used.

GET reload does not consume the selection or perform business work. Future booking
requires a separate single-use confirmation contract; do not reuse this read-only
run as authorization to create subscriptions.

No unnecessary JavaScript is included. `dolBlockUI()` belongs to the later actual
booking step, not this read-only assistant. The future result-notice hook is likewise
deferred until there is a result to display.

## Checks and next phase

Run `php test/unit/run.php` or `composer test` (no dependency installation needed).
Lint PHP files and run `git diff --check`; CI also checks the initial commit.
Synthetic tests do not prove actual browser integration. Follow
[test/integration/smoke.md](test/integration/smoke.md) on an approved installation.
The complete phase-1 requirements and open risks are retained in
[docs/porting-plan.md](docs/porting-plan.md) and [docs/requirements.csv](docs/requirements.csv).
Do not publish a release until a permanent ID and real runtime checks are complete.
