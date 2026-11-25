# c2pa_reader – C2PA Content Credentials in the TYPO3 file list

TYPO3 14 extension (proof of concept). When a JPEG or PNG file is uploaded or replaced,
the extension reads and verifies its C2PA manifest, if there is one. The result is shown,
read-only, in the "Content Credentials (C2PA)" tab of the file metadata.

Verification is done by [`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier).
It is pure PHP: no binary, no PHP extension, no network access.

## Requirements

- PHP 8.3 or later
- TYPO3 14.x (tested with 14.3)
- `provemark/c2pa-verifier` ^0.11 (tested with 0.11.5)

## Installation

With Composer:

```bash
composer require dkd/c2pa-reader
vendor/bin/typo3 extension:setup -e c2pa_reader
```

For local development as a path repository, for example in a DDEV project:

```json
"repositories": [
    { "type": "path", "url": "packages/*", "options": { "symlink": true } }
]
```

```bash
ddev composer require "dkd/c2pa-reader:@dev"
ddev exec vendor/bin/typo3 extension:setup -e c2pa_reader
```

If the extension lives outside the project, mount it into the container with
`.ddev/docker-compose.*.yaml` (for example to `/var/www/packages/c2pa_reader`).

## Usage

### Automatically on upload

An event listener handles `AfterFileAddedEvent` and `AfterFileReplacedEvent`. This covers
uploads in the file list, replacing a file, and every other path through the FAL API
(`ResourceStorage::addFile()`, `replaceFile()`).

### Checking existing files

```bash
vendor/bin/typo3 c2pa:scan          # only files that have not been checked yet
vendor/bin/typo3 c2pa:scan --force  # check all JPEG/PNG files again
```

The command ends with a table of file counts per status. Files that cannot be read are
skipped with a warning.

### In the backend

File list → edit file (metadata) → tab **Content Credentials (C2PA)**.

The tab also has the switch **"AI-generated (set by editors)"**. Editors set it for AI
images that carry no Content Credentials, for example because a service stripped the
metadata.

### In the frontend: AI notice

An image gets a notice when **either** of these is true:

1. the manifest declares it AI-generated and the signature is valid (`tx_c2pareader_ai`):
   "AI-generated – according to Content Credentials (OpenAI Media Service API, ChatGPT gpt-image)";
2. an editor has set the switch (`tx_c2pareader_ai_manual`):
   "AI-generated – according to the editors".

The manifest takes precedence because it is signed. Labels exist in English and German
(`Resources/Private/Language/locallang.xlf`, `de.locallang.xlf`).

**Option A: automatically on all fluid_styled_content images.** Add the site set
`dkd/c2pa-reader-badge` to the site configuration:

```yaml
dependencies:
  - typo3/fluid-styled-content
  - dkd/c2pa-reader-badge
```

The set overrides the partial `Media/Type/Image` (path priority 1700) and puts the notice
into the `figcaption`, so it belongs to the image semantically. It also includes a plain
stylesheet (`Resources/Public/Css/badge.css`, classes `c2pa-badge*`). If your site package
overrides the same partial with a higher priority, add `<c2pa:badge file="{file}" />` there
yourself.

**Option B: in your own template.** The ViewHelper is registered globally as `c2pa`:

```html
<c2pa:badge file="{file}" />
<c2pa:badge file="{file}" id="ai-note-{file.uid}" />
<c2pa:badge file="{file}" template="EXT:my_site/Resources/Private/Templates/C2paBadge.fluid.html" />
```

`file` is a `File` or a `FileReference`. Without an AI flag the ViewHelper renders nothing.
The notice gets the id `c2pa-badge-<uid>`, which you can use for `aria-describedby` on the
`<img>`, for example. A custom template receives `{disclosure}` (`source`: `credentials` or
`editor`, `agents`, `fromCredentials`) and `{id}`.

If you only need the values: the metadata is also available directly in every template,
for example `{file.properties.tx_c2pareader_ai}`.

The notice does not replace a legal assessment of whether and how AI content must be
labelled (for example under Art. 50 of the EU AI Act).

## Stored fields (`sys_file_metadata`)

| Field | Values | Meaning |
|---|---|---|
| `tx_c2pareader_status` | `none`, `valid`, `invalid`, `unreadable` | result of the check (see below) |
| `tx_c2pareader_ai` | 0 / 1 | 1 = the manifest declares the file AI-generated. Only set when the signature is valid. |
| `tx_c2pareader_agents` | text, max. 255 characters | generating software (`claim_generator_info`) and the `softwareAgent` of the actions |
| `tx_c2pareader_checked` | Unix timestamp | last check |
| `tx_c2pareader_ai_manual` | 0 / 1 | 1 = an editor labels the file as AI-generated. The only editable field. |

### Status

| Status | Meaning |
|---|---|
| `none` | The file has no Content Credentials. |
| `valid` | Signature, hashes and manifest are consistent. The signer is **not** checked against a trust list. |
| `invalid` | Something failed: signature mismatch, file changed after signing, certificate expired … |
| `unreadable` | Not checked: unsupported format, file larger than 50 MB, or a read error (see the TYPO3 log). |

### When is a file considered AI-generated?

When an action in the active manifest has the IPTC `digitalSourceType`
`trainedAlgorithmicMedia` or `compositeWithTrainedAlgorithmicMedia`. This is the case for
images from ChatGPT or Adobe Firefly, for example. `algorithmicMedia` (computed, without a
trained model) does not count.

## Limitations

- **"valid" is not proof of trust.** Anyone can sign an image with their own self-issued
  certificate and claim anything in it, including "captured with a camera". Only a trust
  list check (status `Trusted` in the verifier) tells you who signed.
- **No manifest does not mean fake.** Many services strip metadata on upload. Conversely,
  anyone can remove an existing manifest. So `none` says nothing about whether a file is
  AI-generated.
- **JPEG and PNG only.** The verifier supports more (WebP, GIF, AVIF/HEIC, MP4, WAV, MP3 …).
  Add further MIME types to `CredentialReader::SUPPORTED_MIME_TYPES`.
- **Synchronous on upload.** The check runs in the upload request. Large files slow the
  upload down; the limit is 50 MB (`CredentialReader::MAX_BYTES`).
- **Changes outside TYPO3** (FTP, deployment) do not trigger a new check. Run
  `c2pa:scan --force` in that case.

## Security

- Only the extension itself writes the four check fields (through `MetaDataRepository`). A
  DataHandler hook (`Classes/Hooks/ProtectCredentialFields.php`) removes them from every
  incoming record, so no editor can forge the status through a form, an import or a
  workspace. `readOnly` in TCA is not enough on its own, because it only affects the
  display. The manual field `tx_c2pareader_ai_manual` is exempt; the frontend then says
  "according to the editors", not "according to Content Credentials".
- All values from the manifest (agents, texts) are **claims made by the file** and
  therefore untrusted. The backend and the badge template escape them. A custom badge
  template must do the same: no `f:format.raw` on `{disclosure.agents}`. (The site set's
  partial uses `f:format.raw` only for the ViewHelper's finished, already escaped output.)
- The verifier fetches nothing. A remote manifest declared by URL is not retrieved, and
  OCSP requests are off.

Details of the last review: [SECURITY.md](SECURITY.md) (in German).

## Structure

| File | Purpose |
|---|---|
| `Classes/Service/CredentialReader.php` | calls the verifier and maps the report to the four fields |
| `Classes/EventListener/ReadCredentialsOnUpload.php` | checks after upload and replace |
| `Classes/Command/ScanCommand.php` | `c2pa:scan` for existing files |
| `Classes/Hooks/ProtectCredentialFields.php` | locks the check fields against DataHandler |
| `Classes/Service/AiDisclosureResolver.php` | decides whether an AI notice is shown, and with which source |
| `Classes/ViewHelpers/BadgeViewHelper.php` | `<c2pa:badge>`, renders `Resources/Private/Templates/Badge.fluid.html` |
| `Configuration/Sets/Badge/` | site set `dkd/c2pa-reader-badge`: notice on fluid_styled_content images |
| `Configuration/TCA/Overrides/sys_file_metadata.php` | fields and tab in the backend |
| `ext_tables.sql` | database fields |

## Testing

### Automated tests

Unit and functional tests with `typo3/testing-framework` (PHPUnit 12). The functional
tests use SQLite and need no database server. Requirements: PHP 8.3+ with `pdo_sqlite`,
`intl` and `gd`.

```bash
composer install          # installs TYPO3 14 and the test tools into .Build/
composer test             # all tests
composer test:unit
composer test:functional
```

GitHub Actions (`.github/workflows/tests.yml`) runs the tests on every push to `main` and
on pull requests, with PHP 8.3 and 8.4.

| Test | Covers |
|---|---|
| `Tests/Unit/Service/CredentialReaderTest.php` | all test images against the real verifier; tampered AI image → `invalid` and `ai=0`; unsupported type, size limit, read error |
| `Tests/Unit/Service/AiDisclosureResolverTest.php` | source of the AI notice, precedence of Content Credentials |
| `Tests/Unit/Hooks/ProtectCredentialFieldsTest.php` | which fields the hook removes |
| `Tests/Functional/Hooks/ProtectCredentialFieldsTest.php` | real DataHandler as admin and as editor: check fields stay, `title` and the manual AI field change |
| `Tests/Functional/EventListener/ReadCredentialsOnUploadTest.php` | adding and replacing through the FAL API, through to `sys_file_metadata` |
| `Tests/Functional/ViewHelpers/BadgeViewHelperTest.php` | output of `<c2pa:badge>`, id, labels, escaping of manifest values |

### Manual test in an instance

Test images from
[`contentauth/c2pa-rs`](https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures)
(MIT) are in `Tests/Fixtures/c2pa-rs/`, a ChatGPT image is in `Tests/Fixtures/ai/`. This
script adds them all to `fileadmin/c2pa-test/` through the FAL API, so the same events run
as for an upload:

```bash
ddev exec php /var/www/packages/c2pa_reader/Build/Scripts/import-fixtures.php
```

`Tests/` and `Build/` are not part of the Composer dist package (see `.gitattributes`), so
the script needs a source checkout, for example the path repository setup above, or
`composer require dkd/c2pa-reader --prefer-source`.

The script takes the path to `vendor/autoload.php` as an optional argument; the default is
`/var/www/html/vendor/autoload.php`. Running it again replaces the files and checks them
again. Expected results:

| File | Expected status | AI |
|---|---|---|
| `C.jpg` | valid | 0 (`algorithmicMedia`) |
| `CA.jpg` | valid | 0 |
| `E-sig-CA.jpg` | invalid (`claimSignature.mismatch`) | 0 |
| `exp-test1.png` | invalid (certificate expired) | 0 |
| `libpng-test.png` | none | 0 |
| `no_manifest.jpg` | none | 0 |
| `chatgpt-gpt-image.png` | valid | 1 (`trainedAlgorithmicMedia`) |

To cross-check, use the verifier's CLI: `vendor/bin/c2pa-verify <file>`.

## Next steps

1. Trust list check (`TrustSettings`) and `trusted` as a separate status.
2. More formats (WebP, MP4) and a warning in the backend for `invalid`.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
