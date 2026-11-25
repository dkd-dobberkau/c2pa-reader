# Changelog

Alle wichtigen Änderungen an dieser Extension. Format nach
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added
- Prüfung von C2PA Content Credentials beim Upload und Ersetzen von JPEG/PNG (FAL-Events).
- Befehl `c2pa:scan` (mit `--force`) für den Dateibestand.
- Tab „Content Credentials (C2PA)" in den Dateimetadaten: Status, KI-Markierung,
  Software-Agents, Zeitpunkt der Prüfung.
- DataHandler-Hook, der die C2PA-Felder gegen Schreiben durch Benutzer sperrt.
- Testbilder aus `contentauth/c2pa-rs` in `Tests/Fixtures/c2pa-rs/` und
  `Build/Scripts/import-fixtures.php` zum Einspielen in eine lokale Instanz.

### Fixed
- `CredentialReader` nutzt die echte API von `provemark/c2pa-verifier`
  (vorher Klassen des Schwesterpakets `provemark/content-credentials`).
- `c2pa:scan`: `ConnectionPool::getQueryBuilderForTable()` statt einer nicht
  vorhandenen Methode.

### Security
- C2PA-Felder ließen sich trotz `readOnly` per DataHandler überschreiben. Siehe SECURITY.md.
