# Trust list snapshot

Copies of the trust lists of the C2PA conformance programme, for tests without
network access. Snapshot of 2026-11-25 (upstream commit 285d98e).

| File | Source | License |
|---|---|---|
| `C2PA-TRUST-LIST.json` | <https://github.com/c2pa-org/conformance-public/tree/main/trust-list> | CC BY 4.0, Coalition for Content Provenance and Authenticity |
| `C2PA-TSA-TRUST-LIST.pem` | same | CC BY 4.0, Coalition for Content Provenance and Authenticity |
| `DigiCertTrustedRootG4.crt.pem` | <https://cacerts.digicert.com/DigiCertTrustedRootG4.crt.pem> | public root certificate |

Do not use these copies in production: run `vendor/bin/typo3 c2pa:trust:update`
to get the current lists.
