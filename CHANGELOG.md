# Changelog

All notable changes to this extension. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.3.0] - 2026-11-26

### Added
- Invalid Content Credentials are explained: the new field `tx_c2pareader_issues` stores the
  C2PA status codes, the metadata form shows the reason in words, and a backend upload or
  replace of an invalid file shows a warning notification. Labels in English and German.
- `c2pa:trust:update --rescan` checks all files again after updating the trust lists; meant
  for the Scheduler task "Execute console commands".
- German translation of all backend labels.

### Changed
- `c2pa:scan` and `--rescan` share the new `CredentialScanner` service.
- Functional tests run with a memory limit of 512 MB.

### Upgrading
- Run `vendor/bin/typo3 extension:setup -e c2pa_reader` (new database field), then
  `c2pa:scan --force` to fill it for existing files.

## [0.2.0] - 2026-11-25

### Added
- Trust lists: `c2pa:trust:update` downloads the signer and TSA lists of the C2PA
  conformance programme (optionally DigiCert Trusted Root G4 with `--with-digicert`); files
  whose signer chains to an anchor get the new status `trusted`, and the AI notice adds
  "signer on the C2PA trust list". A hand-made `trust.settings.json` is also supported.
- Formats: WebP, GIF, AVIF, HEIC/HEIF, MP4, MOV, WAV, MP3 and M4A, with signed test files
  for each (`Build/Scripts/make-format-fixtures.sh`).
- The site set also shows the AI notice under fluid_styled_content videos and audio.
- Extension settings `trustDirectory` and `maxFileSizeMb`.

### Changed
- `valid` now means "signer unknown"; with trust lists installed, known signers are
  `trusted`. Run `c2pa:scan --force` after installing the lists.

## [0.1.1] - 2026-11-25

### Changed
- The Composer dist package no longer contains `Tests/`, `Build/` and the CI configuration
  (about 100 KB instead of about 8.5 MB).
- SECURITY.md: vulnerabilities are reported through GitHub private vulnerability reporting.

## [0.1.0] - 2026-11-25

First release (proof of concept).

### Added
- Verification of C2PA Content Credentials when JPEG/PNG files are uploaded or replaced
  (FAL events).
- Command `c2pa:scan` (with `--force`) for existing files.
- Tab "Content Credentials (C2PA)" in the file metadata: status, AI flag, software agents,
  time of the check.
- DataHandler hook that locks the C2PA fields against writes by users.
- Test images from `contentauth/c2pa-rs` in `Tests/Fixtures/c2pa-rs/`, a ChatGPT image in
  `Tests/Fixtures/ai/`, and `Build/Scripts/import-fixtures.php` to add them to a local
  instance.
- AI notice in the frontend: ViewHelper `<c2pa:badge>` with an overridable template, and the
  site set `dkd/c2pa-reader-badge`, which adds the notice to the `figcaption` of
  fluid_styled_content images (labels en/de, plain CSS).
- Field `tx_c2pareader_ai_manual`: AI label set by editors, for example for images without
  Content Credentials. Shown in the frontend as "according to the editors".
- Unit and functional tests (typo3/testing-framework, PHPUnit 12, SQLite): `composer test`,
  run in GitHub Actions with PHP 8.3 and 8.4.
- Security review before the release; see SECURITY.md.

[Unreleased]: https://github.com/dkd-dobberkau/c2pa-reader/compare/0.3.0...HEAD
[0.3.0]: https://github.com/dkd-dobberkau/c2pa-reader/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/dkd-dobberkau/c2pa-reader/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dkd-dobberkau/c2pa-reader/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/dkd-dobberkau/c2pa-reader/releases/tag/0.1.0
