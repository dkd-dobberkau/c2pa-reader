# Changelog

All notable changes to this extension. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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

### Changed
- README, SECURITY.md, CHANGELOG, fixture notes, code comments and the composer
  description are in English.

### Fixed
- `CredentialReader`, `BadgeViewHelper` and the upload listener called `getUid()` on
  `FileInterface`, which has no such method. They now use `getProperty('uid')` or check for
  `File`; the listener ignores files without metadata.
- `CredentialReader` uses the actual API of `provemark/c2pa-verifier` (it previously used
  classes of the sister package `provemark/content-credentials`).
- `c2pa:scan`: `ConnectionPool::getQueryBuilderForTable()` instead of a method that does
  not exist.

### Security
- The C2PA fields could be overwritten through DataHandler despite `readOnly`. See
  SECURITY.md.
