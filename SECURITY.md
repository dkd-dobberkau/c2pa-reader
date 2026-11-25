# Security

## Reporting a vulnerability

Please do not open a public issue. Contact dkd Internet Service GmbH directly.

## Review of 2026-11-25

Focused source review of all files of the extension (after the switch to
`provemark/c2pa-verifier` 0.11.5), with local checks in a DDEV instance
(TYPO3 14.3.12, PHP 8.4). This was not a full audit; the package `provemark/c2pa-verifier`
itself was not reviewed.

### Fixed

**C2PA fields could be overwritten through DataHandler** (severity: medium)

- *Who:* any backend user with write access to `sys_file_metadata`. The fields are not
  `exclude`, so no separate permission was needed.
- *What:* `readOnly` in TCA only controls the display. DataHandler still wrote the fields,
  for example from a manipulated form POST, an import or a workspace record.
- *Impact:* the provenance record could be forged. Reproduced locally: status `invalid`
  → `valid`, agent "Forged Camera 1.0", with no error.
- *Fix:* `Classes/Hooks/ProtectCredentialFields.php` removes the four fields from every
  incoming `sys_file_metadata` record in `processDatamap_preProcessFieldArray`. The
  extension writes them itself through `MetaDataRepository`, not through DataHandler.
  After the fix the title changes, the C2PA fields stay; upload and `c2pa:scan` still
  write them. `Tests/Functional/Hooks/ProtectCredentialFieldsTest.php` covers this for an
  admin and for an editor, and fails when the hook is disabled.

### Checked, no finding

- **Output of manifest data:** agents are shown in a `readOnly` input field; FormEngine
  escapes the value.
- **Network / SSRF:** the verifier fetches no URLs (a remote manifest is only reported,
  the OCSP source is `NoRevocationSource`).
- **SQL:** `c2pa:scan` uses the QueryBuilder with named parameters.
- **File access:** only the file passed by the event or the scan is read, through
  `getForLocalProcessing(false)`. The stream is closed in `finally`.
- **Memory:** files over 50 MB are not checked. The verifier reads from a stream and
  limits CBOR structures (`CborBudget`).

### Addendum: AI notice in the frontend

- Agents from the manifest are escaped in the badge. Checked locally with
  `<script>alert(1)</script>" onmouseover=x` as the agent: output as `&lt;script&gt;…&quot;`.
  Covered by `Tests/Functional/ViewHelpers/BadgeViewHelperTest.php`.
- `tx_c2pareader_ai_manual` is editable on purpose and not locked by the DataHandler hook.
  The frontend shows it separately ("according to the editors"); it can neither fake nor
  override a signed claim.

### Known limitations (not a code defect)

- `valid` does not mean trusted. Without a trust list, a self-signed manifest is also
  `valid` and can, for example, hide that an image is AI-generated.
- The check runs synchronously in the upload request. How long the verifier takes for a
  crafted file just under 50 MB has not been measured.
- If files are changed outside TYPO3, the stored status is stale until
  `c2pa:scan --force` runs.
