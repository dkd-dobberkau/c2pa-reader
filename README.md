# c2pa_reader – C2PA Content Credentials in the TYPO3 file list

TYPO3 14 extension (proof of concept). When an image, video or audio file is uploaded or
replaced, the extension reads and verifies its C2PA manifest, if there is one. The result is
shown, read-only, in the "Content Credentials (C2PA)" tab of the file metadata, and an AI
notice can be shown in the frontend.

Supported formats: JPEG, PNG, WebP, GIF, AVIF, HEIC/HEIF, MP4, MOV, WAV, MP3 and M4A.

Verification is done by [`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier).
It is pure PHP: no binary, no PHP extension, no network access during the check.

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
vendor/bin/typo3 c2pa:scan --force  # check all supported files again
```

The command ends with a table of file counts per status. Files that cannot be read are
skipped with a warning.

### Trust lists: who signed?

Without trust lists, the best status is `valid`: the file is intact, but anyone could have
signed it. With the trust lists of the C2PA conformance programme, files whose signer chains
to one of its anchors get the status `trusted` (for example images from ChatGPT, Gemini or
Google Pixel phones).

```bash
vendor/bin/typo3 c2pa:trust:update                  # signer list + timestamp authority list
vendor/bin/typo3 c2pa:trust:update --with-digicert  # also accept DigiCert timestamps
vendor/bin/typo3 c2pa:scan --force                  # check existing files against the lists
```

- The command downloads `C2PA-TRUST-LIST.json` and `C2PA-TSA-TRUST-LIST.pem` from
  [c2pa-org/conformance-public](https://github.com/c2pa-org/conformance-public/tree/main/trust-list)
  (CC BY 4.0) into the trust directory, `var/c2pa/` by default. `trust-lists.json` there
  records the date, the URLs and the SHA-256 of each file.
- The lists change. Keep them current with the Scheduler (below) or a cron job running
  `vendor/bin/typo3 c2pa:trust:update --rescan`; `--rescan` checks all files again after a
  successful update.
- `--with-digicert` also anchors **DigiCert Trusted Root G4** for timestamps; Adobe Firefly
  and Microsoft use it, but it is not on the C2PA list. Without it, such files are `invalid`
  once their signing certificate has expired. The command checks the root's published
  SHA-256 fingerprint. Whether you accept DigiCert's timestamps is your decision; see
  [the verifier's notes](https://github.com/provemark/c2pa-verifier/blob/main/docs/trust-settings.md).
- Only this command uses the network, and only when you run it. Uploads and `c2pa:scan`
  never do.
- Your own trust settings: put a `trust.settings.json` (the format `c2patool --settings`
  reads) into the trust directory. It takes precedence over the downloaded lists.
- A broken or unreadable trust file is logged and ignored; files are then checked without
  trust, never rejected.

### Keeping the trust lists current with the Scheduler

With `typo3/cms-scheduler` installed: Scheduler → add task → **Execute console commands** →
command `c2pa:trust:update`, enable the option `--rescan`, and run it, for example, once a
week. If a download fails, the task fails, the previous lists stay in place and no file is
checked again.

### Extension settings

Admin Tools → Settings → Extension Configuration → `c2pa_reader`:

| Setting | Default | Meaning |
|---|---|---|
| `trustDirectory` | `var/c2pa` | where the trust lists are stored, relative to the project root or absolute |
| `maxFileSizeMb` | `50` | larger files are not checked; the check runs during the upload |

### In the backend

File list → edit file (metadata) → tab **Content Credentials (C2PA)**.

When the Content Credentials are **invalid**, the editor is told right away and in words:

- after an upload or a replace in the backend, a warning notification names the file and the
  reason, for example *"E-sig-CA.jpg: The signature does not match the manifest, or the
  manifest was altered."* The upload itself has succeeded;
- in the metadata form, a red box above the status explains the reason and advises not to
  rely on the manifest. The field *Problems found* lists the C2PA status codes.

The reasons fall into four groups: the file was changed after signing; the signature does
not match or the manifest was altered; the signing certificate is not valid (expired,
revoked, unsuitable) and no trusted timestamp vouches for the signing time; the manifest is
damaged or incomplete. Labels exist in English and German.

The tab also has the switch **"AI-generated (set by editors)"**. Editors set it for AI
images that carry no Content Credentials, for example because a service stripped the
metadata.

### In the frontend: AI notice

An image, video or audio file gets a notice when **either** of these is true:

1. the manifest declares it AI-generated and the signature is valid (`tx_c2pareader_ai`):
   "AI-generated – according to Content Credentials (OpenAI Media Service API, ChatGPT gpt-image)";
2. an editor has set the switch (`tx_c2pareader_ai_manual`):
   "AI-generated – according to the editors".

When the status is `trusted`, the notice adds "signer on the C2PA trust list".

The manifest takes precedence because it is signed. Labels exist in English and German
(`Resources/Private/Language/locallang.xlf`, `de.locallang.xlf`).

**Option A: automatically on all fluid_styled_content images.** Add the site set
`dkd/c2pa-reader-badge` to the site configuration:

```yaml
dependencies:
  - typo3/fluid-styled-content
  - dkd/c2pa-reader-badge
```

The set overrides the partials `Media/Type/Image`, `Media/Type/Video` and `Media/Type/Audio`
(path priority 1700) and puts the notice into the `figcaption`, so it belongs to the media
semantically. It also includes a plain
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
`editor`, `agents`, `fromCredentials`, `trustedSigner`) and `{id}`.

If you only need the values: the metadata is also available directly in every template,
for example `{file.properties.tx_c2pareader_ai}`.

The notice does not replace a legal assessment of whether and how AI content must be
labelled (for example under Art. 50 of the EU AI Act).

## Stored fields (`sys_file_metadata`)

| Field | Values | Meaning |
|---|---|---|
| `tx_c2pareader_status` | `none`, `trusted`, `valid`, `invalid`, `unreadable` | result of the check (see below) |
| `tx_c2pareader_ai` | 0 / 1 | 1 = the manifest declares the file AI-generated. Only set when the signature is valid. |
| `tx_c2pareader_agents` | text, max. 255 characters | generating software (`claim_generator_info`) and the `softwareAgent` of the actions |
| `tx_c2pareader_checked` | Unix timestamp | last check |
| `tx_c2pareader_issues` | C2PA status codes, comma-separated | why the file is `invalid`, e.g. `claimSignature.mismatch`; empty otherwise. `signingCredential.untrusted` alone is not listed: it never makes a file invalid |
| `tx_c2pareader_ai_manual` | 0 / 1 | 1 = an editor labels the file as AI-generated. The only editable field. |

### Status

| Status | Meaning |
|---|---|
| `none` | The file has no Content Credentials. |
| `trusted` | Signature, hashes and manifest are consistent, and the signer's certificate chains to an anchor of the trust lists. |
| `valid` | Signature, hashes and manifest are consistent. The signer is unknown: not on the trust lists, or no trust lists installed. |
| `invalid` | Something failed: signature mismatch, file changed after signing, certificate expired … |
| `unreadable` | Not checked: unsupported format, file larger than `maxFileSizeMb`, or a read error (see the TYPO3 log). |

### When is a file considered AI-generated?

When an action in the active manifest has the IPTC `digitalSourceType`
`trainedAlgorithmicMedia` or `compositeWithTrainedAlgorithmicMedia`. This is the case for
images from ChatGPT or Adobe Firefly, for example. `algorithmicMedia` (computed, without a
trained model) does not count.

## Limitations

- **"valid" is not proof of trust.** Anyone can sign a file with their own self-issued
  certificate and claim anything in it, including "captured with a camera". Only `trusted`
  tells you that the signer is known to the C2PA conformance programme, and even then the
  manifest's statements are the signer's claims.
- **No manifest does not mean fake.** Many services strip metadata on upload. Conversely,
  anyone can remove an existing manifest. So `none` says nothing about whether a file is
  AI-generated.
- **Formats.** The verifier also reads FLAC, AVI and plain text; they are not enabled here.
  Add further MIME types to `CredentialReader::SUPPORTED_MIME_TYPES`. Microsoft Bing Image
  Creator files are not fully supported by the verifier yet (see its `docs/comparison.md`).
- **Synchronous on upload.** The check runs in the upload request. Measured with crafted
  files of 49 MB (large chunks, millions of tiny PNG chunks or JPEG segments, padded MP4 and
  WAV): at most 0.15 s and 6 MB of memory each. The limit is `maxFileSizeMb` (50 MB).
- **Changes outside TYPO3** (FTP, deployment) do not trigger a new check. Run
  `c2pa:scan --force` in that case.

## Security

- Only the extension itself writes the five check fields (through `MetaDataRepository`). A
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
  OCSP requests are off. Only `c2pa:trust:update` downloads, from fixed HTTPS URLs, without
  redirects, with a size limit, and it validates everything before replacing any file.

Details of the last review: [SECURITY.md](SECURITY.md).

## Structure

| File | Purpose |
|---|---|
| `Classes/Service/CredentialReader.php` | calls the verifier and maps the report to the five check fields |
| `Classes/Service/CredentialScanner.php` | checks existing files; used by `c2pa:scan` and `c2pa:trust:update --rescan` |
| `Classes/Service/IssueCatalog.php` | turns the status codes of an invalid file into sentences |
| `Classes/Form/CredentialIssuesInformation.php` | the red box in the metadata form |
| `Classes/EventListener/ReadCredentialsOnUpload.php` | checks after upload and replace; warns the backend user about invalid files |
| `Classes/Command/ScanCommand.php` | `c2pa:scan` for existing files |
| `Classes/Command/TrustUpdateCommand.php`, `Classes/Trust/TrustListUpdater.php` | `c2pa:trust:update`: downloads and validates the trust lists |
| `Classes/Trust/TrustSettingsProvider.php` | loads the trust settings for the verifier |
| `Classes/Configuration/ExtensionSettings.php`, `ext_conf_template.txt` | extension settings |
| `Classes/Hooks/ProtectCredentialFields.php` | locks the check fields against DataHandler |
| `Classes/Service/AiDisclosureResolver.php` | decides whether an AI notice is shown, and with which source |
| `Classes/ViewHelpers/BadgeViewHelper.php` | `<c2pa:badge>`, renders `Resources/Private/Templates/Badge.fluid.html` |
| `Configuration/Sets/Badge/` | site set `dkd/c2pa-reader-badge`: notice on fluid_styled_content images, videos and audio |
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
| `Tests/Unit/Service/CredentialReaderTest.php` | all test files against the real verifier: every format valid and AI, one changed byte → `invalid`; with the trust list snapshot the ChatGPT image is `trusted`; unsupported type, size limit, read error |
| `Tests/Unit/Trust/TrustSettingsProviderTest.php` | loading the lists, precedence of `trust.settings.json`, broken files |
| `Tests/Unit/Trust/TrustListUpdaterTest.php` | download (with a fake HTTP client), metadata, DigiCert fingerprint, nothing written when validation fails |
| `Tests/Unit/Service/IssueCatalogTest.php` | grouping and wording of the status codes |
| `Tests/Unit/Service/AiDisclosureResolverTest.php` | source of the AI notice, precedence of Content Credentials |
| `Tests/Unit/Hooks/ProtectCredentialFieldsTest.php` | which fields the hook removes |
| `Tests/Functional/Hooks/ProtectCredentialFieldsTest.php` | real DataHandler as admin and as editor: check fields stay, `title` and the manual AI field change |
| `Tests/Functional/EventListener/ReadCredentialsOnUploadTest.php` | adding (every format) and replacing through the FAL API, through to `sys_file_metadata` |
| `Tests/Functional/EventListener/InvalidUploadNoticeTest.php` | warning after a backend upload of an invalid file, none for valid files or outside the backend |
| `Tests/Functional/Form/CredentialIssuesInformationTest.php` | the red box in the form, English and German |
| `Tests/Functional/Command/TrustUpdateCommandTest.php` | `c2pa:trust:update` with and without `--rescan` |
| `Tests/Functional/Command/SchedulableCommandsTest.php` | both commands are offered by the Scheduler |
| `Tests/Functional/ViewHelpers/BadgeViewHelperTest.php` | output of `<c2pa:badge>`, id, labels, trusted signer, escaping of manifest values |

### Test files

| Directory | Content |
|---|---|
| `Tests/Fixtures/c2pa-rs/` | JPEG/PNG from [`contentauth/c2pa-rs`](https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures) (MIT) |
| `Tests/Fixtures/ai/` | an image made with ChatGPT; its signer is on the C2PA trust list |
| `Tests/Fixtures/formats/` | one tiny AI-declared file per format, signed with the c2pa-rs test certificate |
| `Tests/Fixtures/trust-list/` | snapshot of the C2PA trust lists (CC BY 4.0) for tests without network |

The test certificate behind `Tests/Fixtures/formats/` expires on **2030-08-26**; after that
these tests fail. `Build/Scripts/make-format-fixtures.sh` creates the files again (needs
ffmpeg, ImageMagick, libheif and c2patool).

### Manual test in an instance

This script adds all test files from `Tests/Fixtures/` to `fileadmin/c2pa-test/` through the FAL API, so the same events run
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
| `chatgpt-gpt-image.png` | valid, or trusted with trust lists | 1 (`trainedAlgorithmicMedia`) |
| `ai.webp`, `ai.gif`, `ai.avif`, `ai.heic`, `ai.mp4`, `ai.mov`, `ai.wav`, `ai.mp3`, `ai.m4a` | valid | 1 |

To cross-check, use the verifier's CLI: `vendor/bin/c2pa-verify <file>`.

### Development note

The ViewHelper uses constructor injection and is registered as a public service in
`Configuration/Services.yaml`. After changing that file, flush the caches
(`vendor/bin/typo3 cache:flush`). Otherwise a request may still use the old dependency
injection container and fail with `ArgumentCountError: Too few arguments to function
...BadgeViewHelper::__construct()`; with DDEV, `ddev restart` also clears PHP's opcode
cache.

## Next steps

1. Registration on Packagist.
2. A column or icon for the C2PA status in the file list.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
