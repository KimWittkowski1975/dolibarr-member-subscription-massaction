# Required risk-based test strategy

This is the default validation contract for subsequent phases. User authorization and
safe test-environment boundaries remain mandatory; this document does not authorize
business writes, migrations or changes to accounts, rights or configuration.

| Level | Scope | Required when |
| --- | --- | --- |
| T1 – Baseline | PHP lint, existing automated runners, language parity, Git status/diff/whitespace | Relevant code changes and CI |
| T2 – Change-specific | Changed behavior, regression cases, relevant business boundaries | Every functional change |
| T3 – Integration | Real Dolibarr/DB/HTTP/browser and permission boundaries | Integration risks and milestones |
| T4 – Extended | Version matrix, concurrency, failure injection, performance, broad regression | Concrete risks and before releases |

## Execution rules

- Reuse established tests and tools. Do not re-read every unchanged test or all 71
  requirements for a small change. Select the affected matrix rows first.
- Add tests for new requirements, observed defects, security risks or uncovered
  meaningful boundaries. Test/assertion counts are evidence, not targets.
- Permissions, integrity, booking, transactions, invoices, replay and email require
  deeper validation than text changes or presentation-only adjustments.
- Preserve working automated regressions in CI. Do not repeatedly run the same checks
  without changed code, a failure, or a concrete unresolved concern. CI-triggered runs
  are expected and need no artificial suppression.
- Keep successful output concise; save full logs to private files and inspect failures
  narrowly. Create a new harness only when available tools cannot cover the change.
- Real business-write tests require an explicitly authorized, suitable test environment.
  Read-only approval never authorizes creation of convenient fixtures or failure injection.
- Report synthetic and real tests separately. Missing integration evidence stays
  **NOT EXECUTED**, regardless of synthetic results. Unchanged row counts alone cannot
  prove that existing business records were not modified; also review executed paths.
- Retain anonymized reproducible evidence outside temporary storage before finishing.
  Never publish credentials, cookies, tokens, profiles, real member data or private DB
  details. Do not retrospectively claim missing artifacts were restored.

## Commands and traceability

`php test/unit/run.php` runs established module contracts and language-key parity.
`DOLIBARR_TEST_ROOT=/path/to/core php test/unit/preview.php` runs preview regressions
against the pinned V22 calendar APIs without application/database bootstrap.
Lint changed PHP files locally, inspect `git diff --check`, then inspect the final CI.
The existing workflow runs the entire automated suite on PHP 7.4/8.2.

`docs/requirements.csv` is the sole requirement-to-test mapping. The small legend in
`docs/test-coverage.md` explains its identifiers; it does not duplicate the matrix.
Use `test/integration/browser-smoke.md` for historical artifact provenance and the
focused read-only HTTP options check. Full historical browser repetition is unnecessary
when a changed path can be proved by a focused integration test.

Before accepting/merging, review the final diff, exact tested SHA, CI, target branch
and applicable protection/review rules. Never bypass repository protections.

## Compact completion report

Normally one or two pages:
1. Implemented changes.
2. Changed files and commits.
3. Executed tests, results and evidence.
4. Tests not executed and why.
5. New/remaining risks (link historical analysis rather than repeating it).
6. Status and recommended next action.

Expand only for significant new findings. Preserve prior detailed reports as history.
