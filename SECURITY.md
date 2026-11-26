# Security

## Reporting a vulnerability

Please do not open a public issue. Report it privately through GitHub instead:
**Security → Report a vulnerability** in this repository
(<https://github.com/dkd-dobberkau/c2pa-reader/security/advisories/new>).

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

### Addendum: trust lists, more formats (2026-11-25)

- `c2pa:trust:update` is the only code that uses the network, and only when an
  administrator runs it. It downloads from fixed HTTPS URLs (GitHub raw for the C2PA lists,
  cacerts.digicert.com for the optional DigiCert root), follows no redirects, refuses
  bodies over 5 MB, checks that the lists load as trust settings and that the DigiCert root
  matches its published SHA-256 fingerprint, and only then replaces the files atomically.
  If anything fails, the previous lists stay in place.
- A broken trust file never blocks uploads: it is logged and files are checked without
  trust (best status `valid`).
- Runtime with crafted files just under the 50 MB limit (a 47 MB PNG chunk, 4 million tiny
  PNG chunks, 12 million JPEG segments, padded MP4 and WAV): at most 0.15 s and 6 MB of
  memory per file. The synchronous check in the upload request is therefore not a
  practical denial-of-service vector at this limit.
- One changed byte inside the C2PA box of an MP4 or MOV can leave the file `valid`: that
  part of the manifest store is covered neither by the hash nor by the signature.
  `c2patool` 0.29.0 gives the same verdict, so this is the format's behaviour, not a
  verifier defect; the media data itself is covered.

### Addendum: backend notices (0.3.0)

- The new field `tx_c2pareader_issues` is one of the protected check fields; the DataHandler
  hook removes it from incoming records like the others (covered by the functional test).
- The notification and the form box show only fixed sentences chosen by the extension and
  the file name; status codes come from the verifier's vocabulary, never from the file.
  The form box escapes its output; backend notifications render their text as text.
- The notice is a warning, not an error, so a failed check never turns a successful upload
  into a reported failure.

### Known limitations (not a code defect)

- `valid` does not mean trusted. Without a trust list, a self-signed manifest is also
  `valid` and can, for example, hide that an image is AI-generated.
- If files are changed outside TYPO3, the stored status is stale until
  `c2pa:scan --force` runs.
