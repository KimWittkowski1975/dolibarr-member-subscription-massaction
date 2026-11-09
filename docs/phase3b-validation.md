# Phase 3B validation and controlled integration gate

## Implemented scope

V22.0.5 baseline `88ad132763be45e9620128c1830e41493e1cdd05`; module main
`3459713c26b77ca9abfab109b344b669bf18b4da`, with the retained zero-duration fix
`d2b92fffc21e69fd3f8f13784bedc663a2832f1c`.

- Separate POST confirmation, owner/entity-bound random preview identifier, revision,
  CSRF, server-held selection/amounts and individually confirmed overlap snapshots.
- Invoice/third-party controls remain visible but disabled and forged requests are
  rejected. Legacy preview states are normalized and require a new confirmation.
  Mail is independent and retains ADHERENT_DEFAULT_SENDINFOBYMAIL as its initial default.
- Each member uses an owned InnoDB transaction, member/type row locks, freshly loaded
  rights/business data, duplicate-day detection and fingerprint comparison. The core
  Adherent::subscription API is called without complementary invoice/payment actions.
- The module-owned Adherent subclass observes the V22 update_end_date result. Before
  commit, the service checks subscription ownership, type, amount, dates and note,
  plus the member end against the **latest start date** in history, not MAX(datef)
  or the newly inserted historical contribution alone.
- The V22 transaction counter and server @@in_transaction are checked. Foreign
  transactions are refused. An unresolved depth, rollback or commit result stops
  the batch. Commit uncertainty is never reported as a safely retryable failure.
- Session consumption is persisted and read back before business writes. Per-member
  checkpoints preserve committed results before mail and mark pending mail uncertain.
  PRG and consumed phases prevent normal repeated POSTs from rebooking/resending.
- Post-commit mail rechecks user rights, global disable and recipient validity. It
  uses FormMail, ADHERENT_EMAIL_TEMPLATE_SUBSCRIPTION, membership type text, core
  substitutions and Adherent::sendEmail (including ADHERENT_MAIL_FROM). Optional
  __SUBSCRIPTION_DESCRIPTION__/START/END/AMOUNT__ tokens supplement core tokens.
  Template text/HTML mode is preserved. No invoice attachment lookup is performed.
- Fresh subscription hook managers isolate members. Negative/zero/positive hook
  returns mean failure/default transport/hook takeover. Valid object replacement is
  honored; replacement of the confirmed identity or recipient is rejected for the
  standard transport. Previous global hook manager/language are restored in finally.
- Escaped confirmation/result tables retain actual dates, descriptions, one-day
  guidance, subscription links, totals and separate transport/hook/warning counts.
  The module does not claim SMTP acceptance means delivery to the recipient inbox.

## Executed automated validation

| Command / scope | Result | Boundary |
| --- | --- | --- |
| `php test/unit/run.php` | 87 assertions PASS | Synthetic contracts and language parity |
| `DOLIBARR_TEST_ROOT=/path/to/v22 php test/unit/booking.php` | 385 preview + 70 booking/mail assertions PASS | Actual V22 calendar helpers and failing end-date-update path; synthetic DB/business/hook boundaries |
| `php test/unit/maildelivery.php` | 8 assertions PASS | Actual adapter body, synthetic FormMail/type/transport |
| PHP lint / Git whitespace | PASS locally | No production bootstrap |

Total: **550 assertions**, counted once. The booking runner includes the preview
runner. Existing preview-only no-write checks remain scoped to the preview services;
prior invoice-planning form assertions were updated for the expressly changed scope.
The 71 requirement IDs and original assessment columns remain intact.
CI runs these checks on PHP 7.4 and 8.2 with the pinned V22 source checkout.

Focused cases cover owned/nested/foreign transactions, positive and zero amounts,
R16 even with a positive subscription ID, historical inserts, mismatched saved data,
stale fingerprints/overlaps, duplicate days, simulated lock contention, rollback and
commit failures, per-member isolation, stopped batches, consumed/replayed/forged
forms, failed session checkpoints, hook outcomes/replacement/context restoration,
mail failures after commit, disabled/invalid recipients, substitutions, language,
text/HTML mode and attachment exclusion. Production renderers are exercised with
hostile synthetic labels and assertions on control order and replay-free results.

## Not executed and required before real integration

**NOT EXECUTED:** real bookings, real rollback/commit fault injection, real parallel
connections, activated authenticated booking UI/HTTP flow, SMTP sandbox delivery and
external email. Module activation and business-write/SMTP tests remain separately
approval-gated. Synthetic success is not evidence of these integrations.

Before granting the next stage, confirm the isolated database/documents and module
symlink, usable current backups, explicit module-activation approval, named synthetic
members and allowed write cases, cleanup/restore strategy, and a specified SMTP sink.
A real external recipient requires additional explicit approval. Never restore or
replace backups automatically.

The two named local November 8 backups were checked read-only by the operator using
the supplied archive checker: GZIP integrity passed (120913 bytes; test schema,
zero-year type and subscription INSERT data present); ZIP CRC/integrity passed
(1629750 bytes, 23 entries, 1679933 uncompressed bytes). Both are dated 23:17 local
time, after the earlier test-data preparation. No extraction or restore was run.
This establishes archive plausibility, not a proven restore or row-for-row parity.
Private paths, dumps, credentials and documents are not included in this repository.

## Remaining limits and risk status

| Risk | Implemented control | Remaining qualification |
| --- | --- | --- |
| R05 | Member-row FOR UPDATE before fresh checks; unique core constraint remains in force | Synthetic contention only. Core/third-party writers need not honor the lock; timestamp uniqueness alone is not calendar-day uniqueness. No universal race-free claim. |
| R06 | Refuse foreign context, check depth/server state, rollback owned depth only, halt uncertainty | Real nested/core/trigger failure paths must still be verified on approved MariaDB. Supported adapter: mysqli + InnoDB with @@in_transaction. |
| R07 | Consume/read-back/checkpoint session, PRG, fresh locked duplicate check | No persistent operations journal or exactly-once guarantee across arbitrary process/session/DB/SMTP crashes. Session loss or expiration requires manual DB reconciliation. No blind retry/resend. |
| R08 | Isolated manager, reference parameters, replacement validation, return semantics, finally restoration | Installed third-party hooks are trusted code and can have external side effects. A takeover is not delivery proof; live hooks remain untested. |
| R09 | Reused preview suite plus targeted transaction/mail/renderer tests | Real DB/browser/SMTP tests remain NOT EXECUTED. PHP matrix is not a complete runtime compatibility claim. |
| R16 | Observe actual parent return and verify persisted history-derived end before commit | Fault double passes; real failure injection awaits approval. |

Core MEMBER_SUBSCRIPTION_CREATE triggers still run. In particular, installed
notifications/custom triggers can produce external, nontransactional effects before
the core method returns; the module cannot roll those back or promise their timing.
Inventory and isolate trigger transports before write tests. The module's own
confirmation adapter is strictly post-commit. No core modification or blanket trigger
suppression is used.

Synchronous confirmations are limited to **50** members (the existing selection limit
is otherwise retained). This is a conservative request-size limit, not a measured
runtime guarantee; use smaller selections for slow transports. Running states are
not evicted by new previews. Expired/crashed states are not automatically resumed;
results are session-bound, not a durable audit journal. No queue/cron or new table is
introduced. If future requirements demand automatic crash recovery, design a durable
operation journal before claiming it, including the unavoidable SMTP outcome boundary.

Invoice/third-party/PDF processing, production release, permanent module-ID reservation,
other Dolibarr versions and broad browser/performance/Multicompany testing remain out
of scope. The draft PR must not be merged before independent review and the necessary
approved integration tests.

## November 9 follow-up: real data reconciliation and controlled integration

This dated addendum supersedes the earlier **NOT EXECUTED** statements only for the
specific checks below. The preceding sections retain the original validation history.
The operator ran the agent-prepared CLI checkers under the web-server account because
that account alone can read the isolated configuration. Results were supplied to and
reviewed by the agent; this is not an agent-observed browser or mailbox session.

### Existing booking and mail evidence

On Dolibarr 22.0.5, PHP 8.2.35 and MariaDB 10.11.19, read-only reconciliation confirmed
all ten batch contributions #6–#15: correct member/type, amount, start/end and generated
description, total **EUR 844.00**. They use creator 1; the optional validating user is
null. V22 subscriptions have no separate entity column; their members belong to entity
1. The unvalidated selected member has no contribution. All stored membership ends
match the latest-start-date history. Invoice, payment and bank counts are zero.

Additional contribution #16 is separate from that batch: member 5, type 3, EUR 10.00,
November 10–11, 2026, created November 9 at 06:47:50. Contribution #10 covers November
8–9. They neither overlap nor share a start calendar day; the stored membership end
is November 11. Database contents alone do not establish the UI/API creation route.
Three older historical contributions remain present; there were 14 rows before the
controlled integration below. No unexpected batch rows were found.

All ten selected recipients match the approved test recipient. **The operator confirms
actual receipt of all ten emails.** No inbox was accessed by the agent and no messages
were resent. Source inspection and the existing adapter tests confirm the post-commit
call order, separate booking/mail outcomes and empty attachment lists. These observations
do not constitute a universal delivery guarantee or a retrospective mail-server audit.

### Backup and side-effect gate

The current November 9 07:02 GZIP (122029 bytes) passes decompression/integrity, schema
and completion checks. All **14** subscription rows, including #16, match the live
baseline across every column after numeric normalization and comparison of the dump's
UTC TIMESTAMP values with database UNIX_TIMESTAMP(tms). Earlier zero-row and tms-only
mismatches were checker format/timezone issues, not missing backup data. The current
ZIP (1751973 bytes, 24 entries, 1801962 uncompressed bytes) passes CRC/integrity.
No restore was performed. This baseline precedes the new integration fixtures.

Isolation was confirmed: separate documents and database identity, no access to the
old development schema, all 282 tables InnoDB. No custom trigger modules are registered;
the module's only registered hook context is memberlist. Enabled applicable core paths
were reviewed: security event logging and optional agenda logging; the contact-role
trigger does not handle subscription creation. Notification/workflow/webhook and other
external integration trigger modules are inactive. Agenda creation does not enqueue
mail reminders on this path. Core triggers were retained, not globally bypassed.
For the integration CLI processes only, MAIN_DISABLE_ALL_MAILS was set in memory and
sendmail was false. No persistent configuration, module state or existing roles changed.

### Controlled real-database integration

One guarded operator-run CLI execution used the production preview/booking services
and the V22 subscription API. New fixtures have the BULK-IT- prefix and no email address.
Existing business rows were snapshotted and compared after the run, not merely counted.
Each result captured before/after contribution rows and the member end date.

| Case | Fixture / operation | Actual result | Scope |
| --- | --- | --- | --- |
| INT-01 | New member 12; January 1–December 31, 2030 | PASS: 0→1 contribution (#17), end updated | Real API + owned MariaDB transaction |
| INT-02 | Member 12; same start day again | PASS: rejected, still one contribution | Real fresh locked validation |
| INT-03 | Reuse consumed confirmation after INT-01 | PASS at request-contract layer; count unchanged | Actual msmConsumeConfirmation; **HTTP POST/session persistence NOT EXECUTED** |
| INT-04 | New member 13; change its type after preview | PASS: stale confirmation rejected, no contribution/end | Only this new fixture changed |
| INT-05 | Member 12; June 1–December 31 overlap, no consent | PASS: rejected, count stays one | Real overlap data |
| INT-06 | Same overlap with explicit consent | PASS: second contribution #18, consistent end | Real API + commit |
| INT-07 | New member 14; exception after real insert/verification | PASS: rollback, zero contributions, null end | Controlled service adapter; no server outage injected |
| INT-08 | New member 15; update_end_date returns -1 | PASS: MSMConsistencyError and rollback, zero rows/null end | Core-compatible member adapter; actual DB rollback (R16) |
| INT-09 | New member 16; two independent processes/connections | PASS: one success, one skipped; exactly #21 saved | Distinct connection IDs 150/151; both previews prepared before release |
| INT-10 | New members 17/18; good row then controlled failing row | PASS: #22 committed for 17; no row/end for 18 | Real per-member transaction isolation |
| INT-11 | New member 19; fresh permission check returns denied | PASS: NotEnoughPermissions, no contribution/end | Controlled identity adapter; existing users/roles untouched |

Eight new members (12–19) and four contributions (#17, #18, #21, #22) are retained for
inspection. The failed attempts consumed auto-increment values without leaving rows.
No blanket cleanup or restoration was performed. Any later cleanup must target the
recorded new fixture IDs and associated test events only, never the original baseline.
The harness has an exclusive once-only guard; do not blindly rerun it.

Exact before/after comparisons passed for the original members, contributions, types,
user role attributes, direct/group rights, invoices, payments and bank rows. The
integration created no email delivery and changed no existing test identity.

### UI and regression follow-up

The controller now chooses separate preview, confirmation, processing and completed
result headings. German result title is exactly `Mitgliedsbeiträge gesammelt - Buchungsergebnisse`.
The redundant preview Cancel button is removed; the existing back link, preview expiry,
backend cancel handling and consumed-booking lifecycle are unchanged. Names, column and
option order, descriptions, contribution links and ID labels remain unchanged.

Local regressions: **87 baseline + 385 preview + 78 booking/mail + 8 adapter = 558
assertions PASS**. Eight focused renderer/title assertions supplement the original
550; no replacement test matrix was introduced. Changed PHP syntax and whitespace
checks pass. The 71 requirement IDs are preserved. Final CI is linked in the draft PR.

### Acceptance qualification

**READY WITH CONDITIONS.** Real booking, concurrency, controlled rollback and R16
checks pass; no production defect was found by this run. Remaining qualifications:

- R05: the real two-module-writer test passes, but arbitrary core/third-party writers
  need not follow the module lock protocol; no universal concurrency guarantee.
- R06/R16: real transactions with controlled fault adapters pass; actual server loss,
  uncertain COMMIT acknowledgements and arbitrary external trigger effects remain untested.
- R07: consumed confirmation rejection passes at the function boundary; actual repeated
  authenticated HTTP POST/session persistence and process/session crash recovery were
  not exercised here. No durable journal or exactly-once claim.
- R08: current installed paths were reviewed and no new mail was sent. Third-party
  subscription hooks/takeovers remain outside this real environment's coverage.
- R09: operator-executed DB evidence is distinguished from synthetic tests and the
  operator's mailbox receipt statement. Independent review and targeted HTTP replay
  verification remain before unrestricted acceptance; this PR stays draft.

## 2026-11-09 — Authenticated HTTP/session replay follow-up

**HTTP REPLAY VALIDATED WITH LIMITATIONS.** This supplements, rather than relabels,
the function-level INT-03 evidence above. Feature code tested: `534659b8877578183c758b2a23f8592254e87459`;
Dolibarr 22.0.5, PHP 8.2.35 and MariaDB 10.11.19 in the isolated test installation.
No production code change was needed.

The operator expressly approved one new synthetic member and exactly one contribution.
Before execution, the operator-run read-only archive checker confirmed integrity of
the newer database/document archives (identifier `2611091844`), and all 18 existing
contributions matched the independently read database snapshot, including #17, #18,
#21 and #22. No restore or new administrative access was used.

The operator ran a narrow Python standard-library client using the existing HTTP
helper, an independent authenticated session and a concealed password prompt. Cookies,
preview identifiers and CSRF tokens remained in memory. Requests used normal Dolibarr
endpoints; no authentication or CSRF bypass was used. The agent subsequently inspected
the sanitized local evidence and independently queried MariaDB via the SELECT-only reader.

Member #20 (`BULK-HTTP-REPLAY-20261109-01`) was created and validated without validation
mail. Its type was the existing annual EUR 120 type. The subscription mail option was
explicitly unchecked and the confirmation rendered “Nein”. The result showed mail
not selected. No mail transport or inbox observation is claimed by this test.

| Case | Actual HTTP result | Database result | Evaluation |
| --- | --- | --- | --- |
| HTTP-01 | POST 303, redirected GET 200 | Exactly contribution #24: EUR 120, 2031-01-01 through 2031-12-31; matching description and member end | PASS |
| HTTP-02 | Exact original POST, cookie jar and token: 303 → 200 with invalid/outdated-form notice | Still only #24; properties/end unchanged | PASS |
| HTTP-03 | Regular page-provided CSRF token with original stale revision: 303 → 200 with invalid/outdated-form notice | Unchanged | PASS; revision interpretation correlated with controller source |
| HTTP-03-phase | Regular CSRF token and the consumed revision (original +1): 303 → 200 with invalid/outdated-form notice | Unchanged | PASS; done-phase interpretation correlated with source, not direct session inspection |
| HTTP-04 | Deliberately invalid CSRF token: HTTP 200 with access/unavailable-selection rejection, no redirect | Unchanged | PASS; HTTP 200 did not mean acceptance |
| HTTP-05 | Two result GETs: 200 each; same contribution ID and exact German results title | Unchanged | PASS |

Core rejects the invalid token by discarding POST data; the module subsequently
rejects the unavailable selection. For valid-token replays, revision validation precedes
the phase guard. Both render the same generic notice, so identification of the individual
guards relies on the request values and reviewed code as well as real HTTP observations.
No direct session-file inspection, process crash or session-loss simulation was performed.

Final independently observed totals: 20 members, 19 contributions, zero invoices,
payments and bank entries. Before/after hashes matched for existing members (#1–19),
contributions (IDs up to #22), membership types, direct user rights, third parties,
invoices, payments and bank rows. New member #20 and contribution #24 remain for review.

The client stopped **after all HTTP cases passed**, at its overbroad full-user-table
hash assertion. Core `User::update_last_login_date()` updates login timestamps and IP
fields during normal authentication; current login dates confirm that authentication
metadata changed. Only a pre-login aggregate hash was retained, however, so it cannot
prove that these were the only user-field differences. Direct rights matched; group
rights were not separately snapshotted in this HTTP run. The raw stopped result is
preserved alongside the independent qualified evaluation, not rewritten as a clean run.
An earlier pre-login attempt stopped because importing the helper generated bytecode;
that artifact was archived and bytecode generation disabled before the actual run.
Neither attempt was automatically repeated after a business write.

Mail replay prevention is additionally supported by the existing code/adapter evidence:
consume and persist confirmation before booking, checkpoint the booking result before
mail, retain unknown mail outcomes without automatic resend, and return immediately
when mail is not selected. This is not a new SMTP delivery test or an exactly-once
claim across lost sessions, process failures or external hooks. The real HTTP gap in
INT-03 is now covered within the qualifications above; independent final review remains.
PR #2 stays Draft. Existing regression/CI results remain historical evidence for the
unchanged implementation; this follow-up changes documentation only.


## 2026-11-09 — Manual Phase 3B browser acceptance

**MANUAL BROWSER ACCEPTANCE: PASS (9/9).**

The project owner completed the manual browser acceptance of the Phase 3B user interface in the isolated Dolibarr 22.0.5 test installation.

| Test | Acceptance criterion | Result |
|---|---|---|
| UI-01 | Correct subscription preview page heading | PASS |
| UI-02 | Redundant Cancel button removed from the preview | PASS |
| UI-03 | Back-to-member-list link remains functional | PASS |
| UI-04 | Correct booking confirmation heading | PASS |
| UI-05 | Correct German booking results heading | PASS |
| UI-06 | Member first and last names displayed correctly | PASS |
| UI-07 | Planned invoice-line description heading preserved | PASS |
| UI-08 | Contribution link opens the correct member contribution page with matching contribution ID | PASS |
| UI-09 | Refreshing the completed results page does not trigger another booking | PASS |

**Operator observations:** None.

**Evidence and interpretation:**

- All nine checks were performed manually and explicitly confirmed by the project owner.
- The requested UI adjustments are accepted.
- No additional UI corrections were requested.
- UI-09 confirms that refreshing the completed results page does not initiate another booking.
- The separate authenticated HTTP-02 test confirms that replaying the original booking POST does not create another contribution.
- The manual browser checks and authenticated HTTP integration tests are complementary evidence, not interchangeable tests.
- No new business-data modifications were performed as part of this documentation update.

**Conclusion:** Manual Phase 3B browser acceptance is complete. No additional UI changes or repeated bulk booking are required on the basis of these results.

The existing authenticated HTTP follow-up retains its classification **HTTP REPLAY VALIDATED WITH LIMITATIONS**. The documented aggregate user-table hash limitation, session-loss and crash limitations, and remaining independent final code review are unaffected.
