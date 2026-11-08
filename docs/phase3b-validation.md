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
