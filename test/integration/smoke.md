# Approved-environment smoke protocol

Never run this protocol without explicit approval for activation/deactivation and
read-only member selection. Do not create members, subscriptions, invoices, payments
or emails. Use an authenticated browser; do not publish session IDs or screenshots
containing real member data. Record core/PHP version, role, initial state, action,
expected/observed result, PASS/FAIL/NOT EXECUTED and evidence for every row.

| ID | Action | Expected result |
| --- | --- | --- |
| ST-01 | Open Setup → Modules | Descriptor visible |
| ST-02 | Enable module (Members already enabled) | Module metadata registered only |
| ST-03 | Open member list | No PHP/hook error |
| ST-04 | Open massaction selector | Own action present |
| ST-05 | Compare original actions | Original actions still available |
| ST-06 | Check two accessible members | Two IDs selected |
| ST-07 | Choose own action and press Confirm | Own technical assistant opens |
| ST-08 | Compare displayed count/IDs | Exactly the selected two IDs |
| ST-09 | Submit empty/invalid/duplicate selection | Rejected, no run/business write |
| ST-10 | Open assistant without a run | Denied |
| ST-11 | Use a user without required core rights | Option absent/access denied |
| ST-12 | Alter run ID or switch user/entity | No foreign selection disclosed |
| ST-13 | Cancel/Back | Member list, no new massaction |
| ST-14 | Reload assistant | Same read-only selection, no business processing |
| ST-15 | Deactivate module | Own action disappears |
| ST-16 | Reactivate module | Own action returns |
| ST-17 | Compare core git HEAD/status | No tracked core changes |
| ST-18 | Compare authorized aggregate business counts | No new subscriptions/invoices/payments |

ST-04 through ST-08 require a real browser flow; CLI hook checks alone are insufficient.
When browser access is unavailable, record NOT EXECUTED, never PASS by inference.
No test should require executing another core business massaction: compare its menu
presence without confirming delete, invoice, subscription or email actions.
