# Browser evidence and focused follow-up

## Artifact inventory checked 2026-11-08

Phase-2 private archives still contained the original Chrome selection harness and
anonymized result files. `archive/phase2-browser-smoke.py` is a **sanitized copy** of
that existing harness: machine-specific URL/output locations were parameterized.
The adjacent phase2 JSON files retain the available technical result evidence.
These describe baseline `0531c9e` on 2026-11-07, not a fresh run on the financial preview.
The old harness expects the old ID table; do not run it against the current preview.
It needs an already authenticated, explicitly approved Chrome CDP session, Python
`websockets`, and MSM_EVIDENCE_DIR; optional MSM_BASE_URL/MSM_CDP_URL select endpoints.
No dependencies were installed and no old lifecycle tests were repeated for archiving.

The Phase-3A temporary browser harness/profile and raw results are **not available**.
The committed `docs/phase3a-validation.md` and historical completion report remain
narrative evidence of the successful final run. No Phase-3A artifact was restored or
fabricated. No suitable additional screenshots were found in the task evidence folders;
none are needed for this dependency fix. Unrelated files/profiles were not searched.

Historical Chrome 157.0.8103.38 cases (2026-11-07):

| ID | Observed case |
| --- | --- |
| BR-01 | Two selected members produce populated financial preview |
| BR-02 | Individual start recalculates automatic end |
| BR-03 | Manual end accepted |
| BR-04 | Manual end retained after start edit |
| BR-05 | Common input alone leaves dates unchanged |
| BR-06 | Explicit common-start application |
| BR-07 | Calendar action replaces manual ends |
| BR-08 | Reset changes only one row |
| BR-09a | Core blocks script input before module processing |
| BR-09b | Normal description placeholders resolve |
| BR-10 | Forged amount rejected |
| BR-11 | Reload remains read-only |
| BR-12 | Cancel returns to list |

The initial harness had expected script input to reach output escaping and compared
raw HTML rather than decoded localized text. The recorded final run corrected those
assertions. This follow-up does not repeat all 13 cases just to recreate artifacts.

## Current focused no-JavaScript HTTP check

`options-http.py` is a new small standard-library harness; the old technical-browser
harness does not cover the current financial form. It performs real HTTP login and
normal form POSTs with in-memory cookie jars, without executing JavaScript. It checks:

- OPT-HTTP-01: invoice on permits explicit third-party planning;
- OPT-HTTP-02: unchecking invoice submits the old checked box and safely resets it;
- OPT-HTTP-03: invoice off renders third-party checkbox disabled and unchecked;
- OPT-HTTP-04: reenabling invoice does not restore third-party consent;
- OPT-HTTP-05: malformed option rejected;
- OPT-HTTP-06: foreign business field rejected;
- OPT-HTTP-07: restricted users still have no assistant/action access;
- OPT-HTTP-08: invalid CSRF rejected; cancellation returns to list.

Use only on an explicitly approved local read-only installation. No fixture creation,
activation, rights changes or booking actions are included. Example:

```
python3 test/integration/options-http.py --approved-read-only \
  --url http://127.0.0.1:8088 --admin test \
  --member-reader test-member-read --restricted test-restricted \
  --output /private/evidence/options-http.json
```

Passwords are prompted without echo and never saved. The output contains only case
IDs, PASS/FAIL and runtime transport metadata, not names/IDs of members, tokens or
responses. Real login can update normal authentication/session metadata. Review any
artifact for secrets/private data before publication. Full browser rendering, keyboard
or cross-version coverage is not claimed by this no-JavaScript HTTP test.

## Focused result (2026-11-08)

All nine named HTTP assertions passed; see
[evidence/phase3a-ext1-options-http.json](evidence/phase3a-ext1-options-http.json).
The first harness attempt expected a generic CSRF error. V22 removes invalid POST
fields, so the controller instead rejects the missing preview identifier. The final
assertion checks that denial and an unchanged preview revision before cancellation.
This was a harness expectation correction, not a disabled security check. No full
historical browser rerun was performed.
