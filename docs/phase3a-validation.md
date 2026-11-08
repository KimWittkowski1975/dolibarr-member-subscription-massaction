# Phase 3A validation (2026-11-07)

Status: **READY WITH CONDITIONS**. Read-only business preview is implemented;
booking, invoice/third-party creation, PDF generation and mail delivery are absent.

## Evidence

- Core: Dolibarr 22.0.5, commit `88ad132763be45e9620128c1830e41493e1cdd05`.
- Runtime: PHP 8.2.35, MariaDB 10.11.19, Chrome 157.0.8103.38.
- Existing independent scaffold runner: **87 assertions passed**.
- New preview runner: **353 assertions passed**, using real V22 calendar helpers
  and synthetic business fixtures, without a database connection.
- **13 real Chrome cases passed**: two-member selection and populated preview;
  individual start; manual end; manual-end preservation; inert common-date entry;
  explicit common application; configured calendar replacing manual ends; single-row
  reset; Core rejection of script input; resolved description placeholders; forged
  amount rejection; reload; cancellation and return to list.
- ST-11 rechecked using three separate real authenticated HTTP sessions: admin
  control succeeds, member-read-only user sees no action and cannot use the controller
  or a forged action POST, and a user without member rights cannot access either.
  The permission checks inspect content, not merely HTTP status (Core may return 200).
- PHP lint, language-key parity and whitespace checks are required by CI on PHP 7.4/8.2.
  CI fetches the exact V22 source revision for the pure calendar regression runner;
  it does not bootstrap a configured application or connect to a database.

## Reference and test boundaries

The 472 historical PR scenarios are a source reference, **not 472 passed module tests**.
The new runner ports the relevant behavioral assertions: amounts (including explicit
zero and creation-time/rowid fallback), periods, timezone/calendar boundaries,
manual/common/calendar edits, descriptions, duplicate starts, inclusive/open overlaps,
status/turnover restrictions, permission combinations, optional third-party/mail checks,
row failure isolation, fresh values, snapshots, state identity/expiry/limits and tampering.
The original 87 module contracts remain unmodified. No booking/mail/transaction claims
are derived from preview tests.

Real fixture-specific coverage for every amount/status/history combination, real
Multicompany entity switching, inaccessible linked products/third parties, concurrent
external database changes, Firefox and other Dolibarr versions is **NOT EXECUTED**.
No test records, member types, permissions or system settings were created/changed
just to make those tests possible. Synthetic tests cover the applicable pure rules;
they do not replace the missing integration evidence.

The browser harness initially assumed that script markup would reach module escaping;
V22 correctly rejected it before the controller. The final case explicitly checks Core
rejection, and separately checks safe placeholder resolution. HTML response assertions
were adjusted to decode entities before comparing localized errors. Final counts above
refer to the successful rerun, not the initial harness attempts.

## Calendar compatibility and explicit decisions

- Start: prior membership end + one day, otherwise validation date, otherwise today.
  START_AFTER overrides the base using today; FIRST_DAY_OF supports m, Y and 3m.
  The 3m rule preserves the reference's previous-end-minus-three-months convention;
  it is not silently replaced by a different quarter algorithm.
- Missing duration defaults to 1y; a bare d/w/m/y means quantity 1. Explicit zero,
  negative, malformed and sub-day units are rejected. No sub-day arithmetic is offered.
- Exactly 1d ends on the following day, intentionally preserving the source exception.
  Other durations end one calendar day before the shifted date. End must be later
  than start, including after an explicit month/year-end alignment.
- V22 `dol_time_plus_duree` lacks the V25 timezone parameter. The module uses an
  explicit UTC DateTimeImmutable/DateInterval shift, then reconstructs midnight in
  Dolibarr's input timezone. No global timezone/configuration is changed.
- The reference's PHP month/year overflow behavior is retained: for example,
  2027-01-31 + 1m - 1d is 2027-03-02. There is no unrequested month-end clamping.
- Configured month/year-end suggestions apply only on the explicit action, to each
  start month/year, including manual ends. Month wins when both are enabled.

## Architecture and security

`class/period.class.php` handles pure calendar rules. `class/preview.class.php`
composes a V22 Adherent; it owns eligibility, amount and period SELECTs, option
preflight and row/fingerprint evaluation. `lib/preview.lib.php` owns template/date
validation, pure input reconciliation and the separate one-hour preview store.
`lib/preview_renderer.lib.php` renders escaped EN/DE forms and summaries.
`subscription.php` authenticates, rechecks rights, validates CSRF/state/revision,
processes only preview/cancel actions and uses fixed internal 303 redirects.

The phase-2 selection store still expires after 15 minutes. Previews use an independent
owner/entity-bound namespace, 128-bit random keys, one-hour expiry and at most five
entries. The source member IDs remain server-side; forged amounts, types, linked
objects and extra IDs are rejected. Independent runs isolate tabs. Stale forms are
rejected by revision. GET and refresh re-read business data; calculation/database
failures do not silently show an old successful result.

Fingerprint and overlap snapshots prepare later review/confirmation design. They are
not a durable booking journal, locking protocol or atomic replay guarantee. Calendar,
permission and source changes must be rechecked inside the eventual booking transaction.

## Data integrity and limitations

The six authorized business-table counts (members, subscriptions, third parties,
invoices, payments, bank entries) were identical before and after the real tests.
Production-code review and the structural regression found no business create/update,
invoice validation, mail, PDF, transaction or locking path. Module activation was not
changed in Phase 3A. Authentication/session metadata can change during real logins.
Unchanged row counts alone are not a proof that every database byte is unchanged;
no full private dataset export or comparison was performed.

Core tracked files and HEAD were unchanged. The module symlink was retained.
No schema, users, permissions, Apache configuration, tags, releases or Core PRs changed.

## Remaining conditions

A permanent module ID is still required before release. Retain R04 (PDF/transaction),
R05 (single/batch concurrency), R06 (nested transaction counters), R07 (replay/crash),
R08 (mail hooks), R09 (real integration evidence), R16 (V22 update_end_date error
propagation). Booking-related portions of all 71 requirements remain explicitly
tracked as deferred or partial; none are removed by this phase.
