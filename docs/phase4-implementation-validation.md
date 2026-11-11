# Phase 4 implementation validation

## Scope and provenance

Phase 4B–4E implements the optional invoice workflow designed in Phase 4A. The implementation is based on module commit `c58231101b7fb8be20d0051575ab87011da5bcf1` and the inspected Dolibarr V22.0.5 Core commit `88ad132763be45e9620128c1830e41493e1cdd05`. Dolibarr V24.0.0 commit `769c7db907099643558e77d7002c109cfda919e5` remains a source comparison only.

The original Phase 4 implementation commit was `07e662231907c4d3344883fe51e321939ec6823c`. The review-correction implementation commit tested below is `c74e7a6ae5b41acd18012fe9f1f512196e859e55`. The four original local milestone commits are:

| Phase | Commit | Result |
| --- | --- | --- |
| 4B | `b646b23` | Invoice preflight and confirmed-state contracts |
| 4C | `4b20d8a` | Transactional contribution and invoice orchestration |
| 4D | `9e1d3d0` | Post-commit invoice PDF and mail attachment handling |
| 4E | `07e6622` | Preview, results, loading indicator and complete flow integration |

No Core file, database, configured Dolibarr document directory, module activation, installation symlink, SMTP configuration or existing test fixture was changed. At the original local completion stage, no push, pull request, merge or tag had been created. The branch was subsequently published as draft PR #4; later review corrections are recorded below.

## 4B — preflight and confirmed state

`MsmInvoiceProfile` resolves a deterministic, read-only invoice plan. It checks the current entity, invoice and customer permissions, linked-customer access, explicit customer-creation consent, base currency, invoice date, payment term, number and PDF models, configured product/service and tax settings. Unsupported local-tax, packaging and stock profiles fail closed.

The initial invoice selection follows O1: only `ADHERENT_BANK_USE=invoiceonly` suggests invoice creation. It creates no bank or payment dependency. User changes survive subsequent preview revisions. A versioned server-side state contains the effective invoice, customer, amount, description, product, tax, date, currency and model snapshot. Old state receives no implicit invoice consent, and consumed or processing confirmations cannot be reopened. Booking repeats permission, entity, object and configuration checks against the confirmed state.

## 4C — transactional database workflow

`MsmBookingService` remains the owner of one outer transaction per member. `MsmInvoiceService` uses Dolibarr Core APIs for optional `Societe::create_from_member()`, `Facture::create()`, `Facture::addline()`, contribution-to-invoice linking and `Facture::validate()`. It does not create payments or bank entries.

The service locks and reloads the relevant member, customer and product records and verifies the expected InnoDB profile and transaction depth. It then verifies the stored contribution, customer state, validated invoice identity and reference, currency, dates, terms, single gross line, product, quantity, VAT, description, totals and exact contribution link. R16 is rechecked after invoice operations. An invoice or customer ID becomes a confirmed result only after a verified commit.

Known failures attempt the owned rollback and allow the batch policy to continue. A rollback, transaction-depth or commit ambiguity produces an unknown result and stops later business and delivery actions. Diagnostic stage and unconfirmed object IDs are retained in the session result solely for reconciliation. There is no automatic retry, compensating deletion, transaction-counter drain or durable operation journal.

Customer creation is implemented inside the same service because its lifetime and rollback decision are inseparable from the invoice operation; no separate third-party adapter was needed. This is an internal structure choice and does not change O3.

## 4D — post-commit PDF and mail

`MsmInvoicePdfService` starts only from a confirmed committed invoice result. It reloads and authorizes the invoice and its contribution link, invokes the configured Core document model and derives the expected file below the current entity's invoice document root. It rejects pre-existing or stale output, paths outside the canonical root, symlinks, unreadable or non-regular files, invalid PDF signature/MIME data and invoice metadata mismatch.

Only a bounded descriptor containing invoice identity, reference, file name, size, modification time and hash enters the session checkpoint. The local path is derived and verified again immediately before standard mail transport; it is never accepted from HTTP or persisted in the result. Synthetic PDF tests use only isolated temporary directories.

`MsmSubscriptionMail` preserves the contribution-only path. For invoice mail it rechecks invoice permissions and requires the verified invoice PDF. Missing or changed output blocks standard delivery. Hook takeover, hook failure, standard send, transport failure and unknown result remain distinct. A PDF or mail failure after commit does not relabel or roll back the successful business objects, and no result GET regenerates or resends anything.

## Review corrections F4-01–F4-07

- **F4-01:** confirmed plain-text descriptions are encoded exactly once at the `Facture::addline()` adapter boundary. Contribution notes retain the confirmed plain text, while stored invoice-line postconditions compare the encoded representation.
- **F4-02:** invoice availability and its O1 default no longer depend on the selected mail option or advanced send permission. The send permission is required only for the combined invoice-and-mail action.
- **F4-03:** invoice-PDF identity, descriptor and attachment availability are verified before `sendMail` hooks. A standard transport re-resolves the attachment after a zero hook response; negative and positive hook semantics remain distinct.
- **F4-04:** the canonical invoice directory is checked in addition to the PDF file. Symlinked invoice directories pointing inside or outside the trusted resolved entity root are rejected during generation and attachment resolution.
- **F4-05:** preview and confirmation render the server-confirmed product mapping, payment term, number model and PDF model. Explicit customer creation warns when the confirmed Dolibarr setting may also create a contact.
- **F4-06:** the bounded V22 transaction-engine preflight now includes `ecm_files`, which the V22 invoice reference update path can modify.
- **F4-07:** the requirement matrix records the O1 deviation and names the concrete regression assertions for the affected critical requirements.

## 4E — user flow and results

The preview exposes invoice intent, linked or planned customer, invoice amount, date, currency, product, VAT, payment term, number/PDF models and the mandatory PDF attachment relationship when mail is selected. The customer-creation option is available only with invoice selection and the required rights.

The result page separates contribution, customer, invoice, PDF, mail, known rollback and unknown transaction outcomes. Customer and invoice links are rendered only after a fresh access check. Unknown outcomes show the diagnostic stage and explicitly unconfirmed IDs; they do not offer a retry action.

The module JavaScript acts only on the final confirmation form. It sets the action before disabling the submit control, prevents duplicate submits, announces progress, moves focus to the status element and resets the state on `pageshow`, including browser back-forward cache restoration. Server-side form processing remains functional without JavaScript.

## Automated validation

The following commands were executed on review-correction implementation commit `c74e7a6ae5b41acd18012fe9f1f512196e859e55`, using PHP 8.2.35, Node.js 18.20.4 and the pinned local V22.0.5 source tree. Counts from runners that include another runner are reported separately and are not added as unique assertions.

| Command | Result |
| --- | --- |
| `php test/unit/run.php` | PASS — 87 synthetic contract assertions |
| `node test/unit/loading.js` | PASS — 6 synthetic DOM assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/booking.php` | PASS — 387 preview/calendar assertions and 87 booking/mail assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/maildelivery.php` | PASS — 17 mail-adapter assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/invoiceprofile.php` | PASS — 387 included preview/calendar assertions and 24 profile assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/invoice.php` | PASS — 387 included preview/calendar, 87 included booking/mail and 34 invoice assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/invoicepdf.php` | PASS — 387 included preview/calendar, 87 included booking/mail and 16 isolated PDF assertions |
| `DOLIBARR_TEST_ROOT=/home/marcel/dolibarr php test/unit/postcommit.php` | PASS — 387 included preview/calendar, 87 included booking/mail and 13 checkpoint assertions |
| PHP lint over every module PHP file | PASS |
| `git diff --check` | PASS |
| Requirements CSV integrity | PASS — 71 unique IDs, 18 columns, original first ten columns preserved |

The GitHub workflow includes the focused Phase 4 runners in its PHP 7.4 and PHP 8.2 matrix. The published correction HEAD d817bc44a5e68008a7163db8cb45ab972aa6e9f6 passed both PHP 7.4 and PHP 8.2 GitHub CI checks. The table above retains that historical local test baseline. PHP 7.4 was not installed locally, and no package was installed for this task.

## Tests not executed

All of the following remain **NOT EXECUTED – REQUIRES SEPARATE AUTHORIZATION**:

- real combined contribution, optional customer and invoice creation against a Dolibarr database;
- real invoice validation, number allocation, Core triggers, stored links, rollback failures, R16 and payment/bank absence reconciliation;
- real Core PDF generation, invoice-document metadata and document-root behavior;
- real SMTP delivery, mailbox receipt and invoice attachment inspection;
- authenticated browser acceptance in Chrome and Firefox, including no-JavaScript, keyboard, focus, responsive and BFCache behavior;
- real concurrency, external Core writers, connection loss and uncertain physical commit acknowledgement;
- real Multicompany and arbitrary installed third-party hook profiles;
- Dolibarr V24 runtime and schema qualification;
Independent overall code review and the first follow-up review have occurred: F4-02–F4-06 were closed; F4-01/F4-07 remained partial, with N4-01/N4-02 requiring the targeted corrections below. Final targeted follow-up review remains pending.

No claim of runtime integration or production readiness follows from the synthetic tests.

## Manual reconciliation for an unknown outcome

An unknown outcome must never be retried from the module. Preserve the result page and its run identifier, diagnostic stage and any unconfirmed subscription, customer or invoice IDs. With separately authorized read-only access, inspect the member and contribution, `llx_subscription`, `llx_facture`, `llx_facturedet`, the Core element link, the member/customer link and the R16 member end date. Also inspect the invoice reference/status and any document metadata before classifying the operation.

The absence of one row, ID, relation or file is insufficient evidence that all effects failed. Core triggers, number allocation and filesystem operations may have separate effects. Classify the outcome only as verified success, proven rollback, definitely not started or unresolved. An unresolved result remains unresolved and requires manual business reconciliation. Session loss can remove the module's diagnostic information because O6 deliberately retains a session checkpoint rather than a persistent journal.

## Residual risk and next gates

The implementation contains guards for R04–R09, R16 and P4-01–P4-05, but their invoice-specific acceptance requires real evidence. Core nesting, enabled triggers, shared customer/product state, numbering models, nontransactional effects, document hooks, filesystem replacement races and external transports cannot be established by doubles. The engine list is deliberately bounded to tables reached by the inspected V22 paths; installed hooks or triggers may touch additional nontransactional tables. Directory and file checks reduce symlink exposure but cannot eliminate a privileged filesystem actor racing between verification and Core generation or transport. Unsupported profiles are rejected rather than silently approximated, but the permitted real profile still needs qualification.

The next gate is a final targeted follow-up review of the remaining corrections and CI on the published HEAD. Bounded real V22 integration, authenticated browser and document/mail validation then require separate authorization and a current backup. V24 requires its own compatible installation and evidence. Production or general-use approval is a separate decision.

**PHASE 4 IMPLEMENTATION COMPLETE – SYNTHETICALLY VALIDATED – READY FOR INDEPENDENT REVIEW**

## Targeted follow-up completion — 2026-11-11

The original implementation `07e6622` received an independent overall review. Corrections `c74e7a6` and documentation `d817bc4` passed GitHub CI on PHP 7.4/8.2. The subsequent independent follow-up closed F4-02–F4-06 and identified remaining entity-fidelity/test-evidence issues (F4-01, F4-07, N4-01, N4-02).

Implementation `559db935334be8407432c4d04b599419997cd8b6` now uses `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true)` without prior decoding. This matches the inspected V22 `Facture::addline()` contract, which assigns the provided description to the stored line. Literal expected values cover `&amp;`, `&lt;`, numeric entities, actual tags, quotes, Unicode and unknown placeholders. Each case checks the stored value, one HTML decode back to the confirmed text, and the unchanged contribution note. Advanced permissions are explicitly enabled in the renderer regression and the prior synthetic configuration is restored.

Targeted local PHP 8.2 validation: invoice runner PASS (388 included preview, 87 included booking/mail, 55 invoice assertions); post-commit runner PASS (same included suites, 13 post-commit assertions); standalone contracts/language parity PASS (87); mail adapter PASS (17); synthetic JavaScript PASS (6). Lint of all three changed PHP files and whitespace checks PASS. These results supersede only the affected counts above. The requirement matrix retains 71 IDs, 18 columns and the first ten historical columns, with literal link/zero-invoice/post-commit assertion references corrected. Final published-HEAD CI is recorded in PR #4.

Real V22 invoice/rollback, Core PDF, SMTP and browser integration remain **NOT EXECUTED**. The final targeted independent review remains pending; no runtime or production approval is implied.
