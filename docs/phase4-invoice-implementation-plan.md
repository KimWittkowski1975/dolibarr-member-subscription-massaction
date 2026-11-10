# Phase 4 — Invoice implementation and validation plan

Status: **READY FOR OWNER REVIEW**, design only, 2026-11-10.
Baseline: `5c511b7a2c6dee6d810ffc0c1469d1539d7e5a1c`.
Read with [architecture and source evidence](phase4-invoice-architecture.md), [71-row authoritative matrix](requirements.csv) and [test strategy](test-strategy.md).

No feature, test, configuration, schema, version or runtime changes are part of this architecture commit. The following PRs are proposals, not created PRs or approval to execute them. New invoice behavior remains disabled until its full path and gates are complete. No partial PR should expose a working-looking invoice checkbox backed only by preview code.

## 1. Sequence and prerequisites

1. Review architecture, original-versus-Phase-3B differences and O1–O7. Record decisions before affected implementation starts.
2. Implement pure preflight/snapshot contracts without enabling invoice booking.
3. Add combined database booking and its focused synthetic tests; qualify it on an explicitly authorized isolated V22 installation before activation.
4. Add post-commit document/attachment processing and its tests; separately authorize any real SMTP test.
5. Finish UI, real integration, independent review and owner acceptance. Decide remaining historical UI requirements explicitly.
6. Qualify V24 separately before any support/release claim; source compatibility alone does not permit upgrading the existing V22 environment.

If O6 requires a durable journal, insert a dedicated design/schema/privacy/recovery PR before 4C. The no-new-table plan below must then be revised rather than quietly supplemented during booking implementation. A release/module-ID reservation and production authorization remain separate from these development PRs.

## 2. Bounded proposed PRs

### 4A — Architecture and owner decisions (this deliverable)

- **Scope/IDs:** reconstruct C05-01–11, C06-05 and the dependency slice; compare actual V22/V24 contracts and original Core feature. No behavior change.
- **Files:** only the two Phase 4 documents. No matrix/test/status rewrites or production code.
- **Dependencies:** merged baseline, reachable historical sources, immutable V22/V24 source refs.
- **Risks:** incorrectly treating old invoice preview or Phase 3B acceptance as invoice evidence; undocumented requirements drift.
- **Checks:** source/ref verification, documentation consistency, relative links, 71-row matrix preservation, whitespace and exact changed-file list. No business tests or unnecessary regression rerun.
- **Acceptance:** viable recommended DB/PDF/mail boundary; alternatives, owner decisions and real-test gaps explicit; baseline/source/runtime unchanged.
- **Phase 3B impact:** none. **Next gate:** owner approval plus a separate implementation instruction; record O1–O7, especially O2/O4/O6.

### 4B — Invoice preflight and confirmed-state design

- **Scope/IDs:** C01-06/07; C02-01–03/11; C03-06–11; C05-01/03/04/05/06/08/10; C08-02/03. Add deterministic read-only invoice/customer/product/config context; preserve amounts/dates. Invoice consent remains unavailable for actual writes until 4C/4D are qualified.
- **Likely files:** `class/preview.class.php`, `lib/preview.lib.php`, strict request/state helpers in `lib/booking.lib.php`, `subscription.php`; focused preview/unit fixtures. Update affected CSV coverage/status only in the later implementation task, preserving all original IDs/columns. Add a small invoice-preflight DTO/helper only if necessary.
- **Dependencies:** O1 defaults, O3 customer effects, O4 VAT/date/currency policy; verified Core signatures and supported configuration profile.
- **Work:** recheck rights/object/entity; detect missing/inaccessible linked customer distinctly; plan current-entity autocreation; snapshot payment terms, invoice date/currency, customer tax/address, product/tax/packaging, rounding/model/config. Introduce state-version invalidation without reviving consumed runs. Never accept raw POST invoice/customer/link IDs or amounts.
- **Risks:** P4-01/02/04, stale configuration, field omissions; legacy confirmations unexpectedly obtaining new consent.
- **Tests:** T1 existing suites/lint/language parity; T2 invoice off/on, autocreate dependency, rights loss, changed customer/product/config, zero amount, unsupported stock/packaging, forged fields, old state migration. Existing invoice-rejection tests must still prove writes are disabled at this stage; add adapter/preflight tests rather than deleting protection assertions.
- **Acceptance:** pure preflight has no business writes; exact snapshot contract documented; unsupported configurations fail clearly. Invoice-off output and required rights unchanged.
- **Phase 3B impact:** only shared preview/state paths; regression is mandatory. **Next gate:** reviewed snapshot contract and owner-approved supported scope.

### 4C — Combined contribution/customer/invoice database booking

- **Scope/IDs:** C04-01–09, C05-02–08/10/11, C03-09/11 and C01-06/07. One owned transaction per member, all business writes through Core APIs. No payment/bank calls, no PDF/mail inside the transaction.
- **Likely files/components:** `class/booking.class.php`; new narrow `class/invoice.class.php`, optionally `class/thirdparty.class.php`; `lib/booking.lib.php` result contract; targeted `test/unit/booking.php` extensions/new focused invoice runner if existing harness cannot express it. Keep `class/bookingmember.class.php` R16 behavior unless a demonstrated adaptation is necessary. No Core patches.
- **Dependencies:** 4B; O2 side-effect policy; O6 persistence decision; same connection/driver and engine profile; inventory of enabled triggers, document callbacks and numbering modules. Add guards for every actually touched table/profile, not an arbitrary claim that the original three-table check suffices.
- **Work:** lock/reload→contribution→customer if approved→invoice with exact subscription link→gross line→validate→stored-value/R16/link checks→owned commit. Map failures to known rollback versus unknown stop. Verify actual invoice identity/status, quantity, totals and relationship, not just positive method returns. Record contribution/invoice IDs only as confirmed successes after commit.
- **Risks:** R05/R06/R08/R16 and P4-01/02/03. Core nesting and trigger effects can invalidate atomicity assumptions. Existing customer promotion/contact creation and provisional-file renames are part of review.
- **Tests:** T1/T2 normal and zero combined booking; each Core return failure/exception; link failure; validation rights; stale customer; R16 failure after invoice work; nested depth mismatch; commit/rollback uncertainty; one row succeeds/one fails; invoice-off path unchanged. T3 only after separate authorization: real Core DB methods, rollback at each meaningful stage, persisted link/totals/end, unchanged preexisting records and zero payment/bank effects. T4 concurrency/connection loss under a separately approved scenario.
- **Acceptance:** known rollback leaves no row-owned invoice/customer/contribution/link; verified commit retains all required objects; unknown stops without delivery/retry; triggers remain enabled in the approved profile. Do not enable full user invoice workflow while required PDF handling is missing.
- **Phase 3B impact:** highest shared-path risk; exact subscription-only transaction/R16/mail behavior must remain. **Next gate:** independent transaction review and real V22 evidence for supported configuration; no blanket transfer of old risk acceptance.

### 4D — Post-commit PDF and verified invoice mail attachment

- **Scope/IDs:** C05-09/11, C06-01–08 and related rights/results. Explicit Core PDF generation after confirmed commit; retain existing independent subscription mail.
- **Likely files/components:** new `class/invoicepdf.class.php`; `class/mail.class.php`, `lib/booking.lib.php`; focused artifact/mail tests and localized warnings. PDF adapter accepts invoice identity, not request paths; mail accepts a verified descriptor.
- **Dependencies:** 4C result/checkpoint contract, O5 attachment selection, approved model/entity-root/hook policy. No global PDF suppression or new mail queue implied.
- **Work:** persist committed result before generation; reload authorized invoice; select Core model/language; capture errors/warnings; verify canonical readable PDF under correct entity root; checkpoint before standard mail. Handle hook 0/positive/negative/exception and identity replacement; keep positive handled distinct from sent. Preserve successful booking after any document/mail failure.
- **Risks:** R04/R07/R08, P4-02/05; metadata writes after file generation, symlink/path races, stale files and external hook transport.
- **Tests:** T1/T2 PDF negative/exception/positive-with-missing-file, wrong entity, traversal/symlink, wrong invoice, unsafe template path, metadata failure, no invoice/no attachment, missing PDF blocks required standard send, hook takeover/replacement, language/template and special-character rendering. Existing mail suites reused. T3 real selected model and document metadata checks with synthetic invoice, then separately authorized recipient/SMTP checks only if needed. No live email failure injection without explicit scope.
- **Acceptance:** PDF status truthful and separate; canonical own PDF only; no delivery before confirmed commit; missing/unsafe PDF never silently omitted from required invoice mail; no automatic retry/resend. Subscription-only standard mail still has no attachments.
- **Phase 3B impact:** mail signatures/orchestration; preserve old path and finally context restoration. **Next gate:** artifact/mail review and approved real evidence; unresolved hooks explicitly unsupported.

### 4E — UI, real integration and independent completion review

- **Scope/IDs:** C05-01–11, C06-05 plus C07-03–09, C08-03 and shared replay/permission behavior. Enable complete invoice option only after preceding gates. Clear customer creation consent, full confirmation and separate DB/invoice/PDF/mail results with authorized links.
- **Likely files:** `subscription.php`, preview/booking renderers, localization files; optional module JS only for owner-approved loader/BFCache increment; existing integration checklists, requirements coverage and phase evidence docs. No Core list rewrite or global notification change.
- **Dependencies:** approved default/notice/loader decisions O1/O7; 4B–4D tested contract; explicitly authorized isolated installation, current backups covering the actual pre-test state, permitted recipient and enabled-hook inventory. Symlink/module activation changes require their own authorized operational step, not incidental test setup.
- **Risks:** stale confirmation, browser replay, misleading counts, inaccessible links, long PDF/SMTP requests, remaining no-journal uncertainty.
- **Tests:** T1/T2 result totals and escaped/localized messages; T3 authenticated HTTP, no-JS and owner/manual Chrome/Firefox acceptance below. T4 invoice concurrency, version and hooks according to actual risk. Store sanitized evidence durably, not only in `/tmp`; record exact tested SHAs and provenance.
- **Acceptance:** each selected option matches executed behavior; known DB failure and post-commit warning visibly different; result GET/refresh/Back cannot book/generate/send; correct linked objects; no payment/bank creation; all remaining original requirements either proved or explicitly deferred/approved. Independent review and invoice-specific residual-risk acceptance required.
- **Phase 3B impact:** UI and shared controllers; demonstrate invoice-off remains usable when invoice/customer modules or rights are absent. **Next gate:** owner acceptance for bounded development use; production release is a separate decision.

## 3. Focused validation catalogue (planned, not executed)

These scenario labels are planning references, not a second requirement matrix. On implementation, map final test identifiers into existing CSV rows and preserve historical provenance. Prefer existing runners and fixtures; add tests for new branches/failures, not an artificial Cartesian-product target.

| Group | Minimal decisive cases | Level / requirement focus |
| --- | --- | --- |
| Options/access | Invoice off with no invoice rights; invoice on with read/create/advanced validate/send combinations; internal/external user; missing customer, allowed creation, inaccessible existing link; lost right after confirmation | T2 then targeted T3; C01-06/07, C05-01/03/04/08 |
| Price/description | Mixed fees, explicit zero, null/empty history fallback, invalid negative; gross/VAT/local-tax/rounding supported profiles; packaging changes quantity; configured product unavailable/foreign; UTF-8 and one-time escaping | T2 and real line/PDF sample T3; C02-01–03/11, C05-05/06/10, C08-02 |
| State/freshness | Change customer/address/tax/product/amount/options/config after confirmation; forged links and amounts; old state version; two tabs; consumed run replay | T2 plus authenticated T3; C03-06–11 |
| DB success | Existing customer and approved new customer/contact; exact contribution→invoice link; validated status/ref, one line/totals; zero fee; R16; no payment/bank | T3 with Core methods; C04-01/02/09, C05-02–08/10 |
| DB failure | Negative/exception at customer/create/link/line/validate, stored-value mismatch; fresh third-party/member link rollback; prior unrelated data unchanged; row A success/B rollback | T2 controlled adapters + T3 focused real failure; C04-03/04/07/08, C05-11 |
| Uncertainty/concurrency | Foreign outer transaction, leaked nesting, physical COMMIT acknowledgement loss, rollback failure; two module connections same member; separate Core writer | T2 then specifically authorized T4; R05/R06, C03-09, C04-05/06/08 |
| PDF | Positive generation plus canonical file; negative/throw/missing/wrong format; wrong entity/root, symlink escape, stale file, metadata error, configured model | T2/T3; C05-09/11, C06-05, R04 |
| Mail | Off, standard success/failure/disabled; invoice requires verified PDF per O5; no invoice unchanged; hook 0/+/-/throw/replacement; unknown result not resent | T2 then separately authorized delivery T3; C06-01–08 |
| UI/results | Correct separate counts/IDs/links; escaping; persistent warning beside successful booking; refresh/Back/POST replay; keyboard and no-JS; loader only if approved | T2/T3; C07-03–09 |
| Compatibility | V24 trailing subscription argument/schema; retained R16; customer alias; packaging/stock-managed difference; sponge model/common document hooks | T4 separate V24 installation; no runtime claim from signature match |

**T1 commands for later functional PRs:** lint changed PHP files, run existing `php test/unit/run.php`, `DOLIBARR_TEST_ROOT=<pinned V22 source> php test/unit/booking.php` (includes preview), and `php test/unit/maildelivery.php`; language parity and `git diff --check`. Follow existing CI PHP 7.4/8.2 contract where still supported. No duplicate standalone preview rerun when booking already includes it unless a failure justifies one. This documentation-only task requires formal/source checks, not rerunning those suites.

**T3 evidence contract:** exact code/Core SHAs, DB/document identity and approved backup, specific writable fixture scope and recipient, before/after relevant-row values/hashes, object/link/totals/end-date reads, trigger inventory, transaction outcome, document evidence and sanitized HTTP observations. Counts alone do not prove unchanged preexisting data. Normal login metadata changes must be handled explicitly, unlike the historical aggregate-user-hash limitation. Real fault injection remains distinct from adapters. Do not rerun a stopped write test without reconciliation and authorization.

**Operational guard:** existing read-only runner rights do not authorize invoice creation or new privileged programs. Current historic backups are not automatically the baseline for future writes; verify coverage of then-current state. Preserve existing records, isolate synthetic fixtures, log unique run IDs to the approved persistent result directory, and avoid raw credentials/tokens/documents in repository artifacts. No automatic cleanup, restore or extra “convenient” fixture writes without scope.

## 4. Manual browser acceptance to schedule

On approved V22 runtime and later V24 independently:

1. Invoice off: existing preview/confirmation/result flow, independent mail option, all old dates/amounts/R16 behavior unchanged.
2. Invoice on: default matches owner decision; autocreate depends on invoice and permission; missing/inaccessible customer messages differ; user sees creation intent, fee, tax/date policy and description before final confirmation.
3. Confirmed combined booking: invoice link opens the correct validated invoice; linked contribution/member/customer and totals agree with read-only DB evidence. Confirm invoice-side linked-elements UI and contribution-side navigation independently.
4. Known rollback: clear failed row without a misleading successful subscription/invoice count. Mixed rows retain successful results.
5. PDF/mail failure after commit: successful business IDs remain; document and mail warning are distinct; no “retry booking” button or silent attachment omission.
6. Refresh, repeated POST, Back/Forward and multiple tabs do not repeat effects. If loader implemented, final submit only, cancellation/validation errors do not stick, `pageshow` restores UI.
7. Keyboard navigation, focus/status announcements, narrow screen, German/English, escaped unusual text and no-JavaScript forms remain usable. No global toast/notification changes.
8. Removed rights prevent access to results' invoice/PDF links and later delivery without undoing a prior commit. Run real shared-entity cases only when an authorized Multicompany environment exists; otherwise explicitly retain that gap.

Browser acceptance is complementary to authenticated HTTP and DB reconciliation; owner observations must be labeled as such. A success screenshot or received email alone does not prove transaction or attachment safety.

## 5. V24 qualification prerequisites

Use official V24.0.0 source `769c7db907099643558e77d7002c109cfda919e5`, a separate compatible initialized schema, document root and authorized synthetic fixtures. Review actual PHP/database prerequisites, model configuration, rights and installed extensions at that time. No downgrade/upgrade of the existing V22 instance is implied.

Re-run affected API contracts and real combined success/rollback/R16, zero invoice, canonical PDF, permissions and replay cases. Specifically test changed packaging behavior, `sponge` fallback, template-path restriction, model warning/exception propagation and common-document moreparams context. Verify subscription schema fields through Core APIs, not V22 SQL assumptions. Broader V23/V25 and other drivers remain out of scope. Reuse tests where possible, but do not equate identical method signatures with runtime qualification.

## 6. Gates and unresolved decisions

| Gate | Required evidence/decision | Blocking effect |
| --- | --- | --- |
| G0 Design | Owner reviews O1–O7, including invoice default, known side effects, customer changes, tax/date/currency, attachment policy, persistence, UI deviations | No functional work authorized by this document alone. |
| G1 Read-only contracts | 4B targeted tests and state/rights review; unsupported profiles explicit | No financial option enabled from UI-only preparation. |
| G2 Transaction | 4C independent review, current baseline/authorized environment, actual invoice rollback/link/zero/R16 evidence | No general invoice use while DB integrity remains unverified. |
| G3 Document/delivery | 4D model/artifact/path/hook evidence; recipient/SMTP-specific authorization | No invoice standard mail before safe PDF policy is qualified. |
| G4 V22 acceptance | 4E exact-SHA CI, HTTP/browser/DB evidence, invoice-specific R04–R09/R16 and new-risk decisions | Bounded development acceptance only; not production release. |
| G5 V24 support | Separate runtime qualification above | V24 remains source-compared, NOT EXECUTED until satisfied. |

Open decisions are precisely O1–O7 in the architecture; no additional implied policy is introduced here. In particular, validation remains before database commit and PDF generation after it in the recommendation. If the owner instead requires an environment with absolutely no pre-commit filesystem/external callback effects, its Core/models/hooks must be proven to satisfy that constraint or invoice mode must stay blocked there.

## 7. Completion of this design task

Only documentation is to be committed locally with `docs: design Phase 4 invoice integration architecture`, after checking the exact two-file diff, whitespace, source links, matrix preservation and unchanged source/worktrees. No new tests have been executed and no invoice behavior has been implemented by this plan. No push, PR, main/tag change, module activation, symlink change, database action or mail is authorized here.

**PHASE 4 INVOICE ARCHITECTURE READY FOR OWNER REVIEW** — the design and staged plan are ready for a decision, not for automatic implementation or financial operations.
