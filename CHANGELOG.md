# Changelog

All notable changes to this extension. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/dkd-dobberkau/c2pa-reader/compare/0.1.0...HEAD
[0.1.0]: https://github.com/dkd-dobberkau/c2pa-reader/releases/tag/0.1.0
