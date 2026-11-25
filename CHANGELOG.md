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
- Testbilder aus `contentauth/c2pa-rs` in `Tests/Fixtures/c2pa-rs/`, ein ChatGPT-Bild
  in `Tests/Fixtures/ai/` sowie `Build/Scripts/import-fixtures.php`
  zum Einspielen in eine lokale Instanz.
- KI-Hinweis im Frontend: ViewHelper `<c2pa:badge>` mit überschreibbarem Template und
  Site Set `dkd/c2pa-reader-badge`, das den Hinweis in die `figcaption` der Bilder von
  fluid_styled_content setzt (Texte en/de, schlichtes CSS).
- Feld `tx_c2pareader_ai_manual`: KI-Kennzeichnung durch die Redaktion, z. B. für Bilder
  ohne Content Credentials. Im Frontend als „laut Redaktion" ausgewiesen.
- Unit- und Functional-Tests (typo3/testing-framework, PHPUnit 12, SQLite):
  `composer test`.

### Fixed
- `CredentialReader`, `BadgeViewHelper` und der Upload-Listener riefen `getUid()` auf
  `FileInterface` auf, das diese Methode nicht hat. Jetzt `getProperty('uid')` bzw. Prüfung
  auf `File`; der Listener ignoriert Dateien ohne Metadaten.
- `CredentialReader` nutzt die echte API von `provemark/c2pa-verifier`
  (vorher Klassen des Schwesterpakets `provemark/content-credentials`).
- `c2pa:scan`: `ConnectionPool::getQueryBuilderForTable()` statt einer nicht
  vorhandenen Methode.

### Security
- C2PA-Felder ließen sich trotz `readOnly` per DataHandler überschreiben. Siehe SECURITY.md.
