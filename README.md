# c2pa_reader – C2PA Content Credentials in der TYPO3-Dateiliste

TYPO3-14-Extension (Proof of Concept). Beim Upload oder Ersetzen einer JPEG- oder
PNG-Datei liest die Extension ein eventuell vorhandenes C2PA-Manifest und prüft es.
Das Ergebnis steht im Tab „Content Credentials (C2PA)" der Dateimetadaten. Dort ist es
nur lesbar.

Die Prüfung macht [`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier).
Das ist reines PHP: kein Binary, keine PHP-Extension, kein Netzwerkzugriff.

## Voraussetzungen

- PHP 8.3 oder neuer
- TYPO3 14.x (getestet mit 14.3)
- `provemark/c2pa-verifier` ^0.11 (getestet mit 0.11.5)

## Installation

Über Composer:

```bash
composer require dkd/c2pa-reader
vendor/bin/typo3 extension:setup -e c2pa_reader
```

Für die lokale Entwicklung als Path-Repository, zum Beispiel in einem DDEV-Projekt:

```json
"repositories": [
    { "type": "path", "url": "packages/*", "options": { "symlink": true } }
]
```

```bash
ddev composer require "dkd/c2pa-reader:@dev"
ddev exec vendor/bin/typo3 extension:setup -e c2pa_reader
```

Liegt die Extension außerhalb des Projekts, über `.ddev/docker-compose.*.yaml` in den
Container einhängen (z. B. nach `/var/www/packages/c2pa_reader`).

## Benutzung

### Automatisch beim Upload

Ein Event-Listener reagiert auf `AfterFileAddedEvent` und `AfterFileReplacedEvent`. Das
gilt für Uploads in der Dateiliste, für Ersetzen und für jeden anderen Weg über die
FAL-API (`ResourceStorage::addFile()`, `replaceFile()`).

### Bestand prüfen

```bash
vendor/bin/typo3 c2pa:scan          # nur Dateien, die noch nicht geprüft wurden
vendor/bin/typo3 c2pa:scan --force  # alle JPEG/PNG erneut prüfen
```

Der Befehl gibt am Ende eine Tabelle mit der Anzahl der Dateien pro Status aus. Dateien,
die nicht gelesen werden können, werden mit Warnung übersprungen.

### Im Backend

Dateiliste → Datei bearbeiten (Metadaten) → Tab **Content Credentials (C2PA)**.

Dort gibt es auch den Schalter **„AI-generated (set by editors)"**. Den setzt die Redaktion
bei KI-Bildern, die keine Content Credentials (mehr) haben, etwa weil ein Dienst die
Metadaten entfernt hat.

### Im Frontend: KI-Hinweis

Ein Bild bekommt einen Hinweis, wenn **eine** der beiden Angaben zutrifft:

1. das Manifest erklärt es als KI-generiert, bei gültiger Signatur (`tx_c2pareader_ai`):
   „KI-generiert – laut Content Credentials (OpenAI Media Service API, ChatGPT gpt-image)";
2. die Redaktion hat den Schalter gesetzt (`tx_c2pareader_ai_manual`):
   „KI-generiert – laut Redaktion".

Die Angabe aus dem Manifest hat Vorrang, weil sie signiert ist. Texte gibt es auf Englisch
und Deutsch (`Resources/Private/Language/locallang.xlf`, `de.locallang.xlf`).

**Variante A: automatisch an allen Bildern von fluid_styled_content.** Das Site Set
`dkd/c2pa-reader-badge` in der Site-Konfiguration ergänzen:

```yaml
dependencies:
  - typo3/fluid-styled-content
  - dkd/c2pa-reader-badge
```

Das Set überschreibt das Partial `Media/Type/Image` (Pfad-Priorität 1700) und setzt den
Hinweis in die `figcaption`, so gehört er semantisch zum Bild. Es bindet außerdem ein
schlichtes Stylesheet ein (`Resources/Public/Css/badge.css`, Klassen `c2pa-badge*`).
Überschreibt das Sitepackage dasselbe Partial mit höherer Priorität, dort
`<c2pa:badge file="{file}" />` selbst einsetzen.

**Variante B: im eigenen Template.** Der ViewHelper ist global als `c2pa` registriert:

```html
<c2pa:badge file="{file}" />
<c2pa:badge file="{file}" id="ai-note-{file.uid}" />
<c2pa:badge file="{file}" template="EXT:my_site/Resources/Private/Templates/C2paBadge.fluid.html" />
```

`file` ist ein `File` oder eine `FileReference`. Ohne KI-Angabe gibt der ViewHelper nichts
aus. Der Hinweis bekommt die id `c2pa-badge-<uid>`, die sich z. B. für `aria-describedby`
am `<img>` nutzen lässt. Ein eigenes Template erhält `{disclosure}` (`source`:
`credentials` oder `editor`, `agents`, `fromCredentials`) und `{id}`.

Wer nur die Werte braucht: Die Metadaten stehen in jedem Template auch direkt zur Verfügung,
z. B. `{file.properties.tx_c2pareader_ai}`.

Der Hinweis ersetzt keine rechtliche Prüfung, ob und wie KI-Inhalte gekennzeichnet werden
müssen (z. B. Art. 50 AI Act).

## Gespeicherte Felder (`sys_file_metadata`)

| Feld | Werte | Bedeutung |
|---|---|---|
| `tx_c2pareader_status` | `none`, `valid`, `invalid`, `unreadable` | Ergebnis der Prüfung (siehe unten) |
| `tx_c2pareader_ai` | 0 / 1 | 1 = das Manifest erklärt die Datei als KI-generiert. Nur bei gültiger Signatur. |
| `tx_c2pareader_agents` | Text, max. 255 Zeichen | erzeugende Software (`claim_generator_info`) und `softwareAgent` der Actions |
| `tx_c2pareader_checked` | Unix-Zeitstempel | letzte Prüfung |
| `tx_c2pareader_ai_manual` | 0 / 1 | 1 = die Redaktion kennzeichnet die Datei als KI-generiert. Als einziges Feld bearbeitbar. |

### Status

| Status | Bedeutung |
|---|---|
| `none` | Die Datei enthält keine Content Credentials. |
| `valid` | Signatur, Hashes und Manifest sind in sich stimmig. Der Aussteller ist **nicht** gegen eine Trust List geprüft. |
| `invalid` | Etwas ist fehlgeschlagen: Signatur passt nicht, Datei nach dem Signieren verändert, Zertifikat abgelaufen … |
| `unreadable` | Nicht geprüft: Format nicht unterstützt, Datei größer als 50 MB oder Lesefehler (steht im TYPO3-Log). |

### Wann gilt eine Datei als KI-generiert?

Eine Action im aktiven Manifest hat den IPTC-`digitalSourceType`
`trainedAlgorithmicMedia` oder `compositeWithTrainedAlgorithmicMedia`. Das ist zum
Beispiel bei Bildern aus ChatGPT oder Adobe Firefly der Fall. `algorithmicMedia`
(rein rechnerisch erzeugt, ohne trainiertes Modell) zählt nicht.

## Wichtige Grenzen

- **„valid" ist kein Vertrauensnachweis.** Jeder kann ein Bild mit einem eigenen,
  selbst ausgestellten Zertifikat signieren und darin beliebige Angaben machen, auch
  „mit Kamera aufgenommen". Erst eine Trust-List-Prüfung (Status `Trusted` im Verifier)
  sagt, wer signiert hat.
- **Kein Manifest heißt nicht Fälschung.** Viele Dienste entfernen Metadaten beim Upload.
  Umgekehrt kann jeder ein vorhandenes Manifest entfernen. `none` sagt also nichts darüber
  aus, ob eine Datei KI-generiert ist.
- **Nur JPEG und PNG.** Der Verifier kann mehr (WebP, GIF, AVIF/HEIC, MP4, WAV, MP3 …).
  Weitere MIME-Typen in `CredentialReader::SUPPORTED_MIME_TYPES` ergänzen.
- **Synchron beim Upload.** Die Prüfung läuft im Upload-Request. Große Dateien machen den
  Upload langsamer; die Obergrenze ist 50 MB (`CredentialReader::MAX_BYTES`).
- **Änderungen außerhalb von TYPO3** (FTP, Deployment) lösen keine neue Prüfung aus. Dann
  `c2pa:scan --force` ausführen.

## Sicherheit

- Die vier Prüf-Felder schreibt nur die Extension selbst (über `MetaDataRepository`). Ein
  DataHandler-Hook (`Classes/Hooks/ProtectCredentialFields.php`) entfernt sie aus jedem
  eingehenden Datensatz. So kann kein Redakteur per Formular, Import oder Workspace den
  Status fälschen. `readOnly` im TCA allein reicht dafür nicht, denn es wirkt nur in der
  Anzeige. Das manuelle Feld `tx_c2pareader_ai_manual` ist davon ausgenommen; im
  Frontend steht dann „laut Redaktion", nicht „laut Content Credentials".
- Alle Werte aus dem Manifest (Agents, Texte) sind **Angaben aus der Datei** und damit
  nicht vertrauenswürdig. Backend und Badge-Template escapen sie. Ein eigenes Badge-Template
  muss das ebenso tun: kein `f:format.raw` auf `{disclosure.agents}`. (Das Partial des Site
  Sets nutzt `f:format.raw` nur für die fertige, bereits escapte Ausgabe des ViewHelpers.)
- Der Verifier lädt nichts nach. Ein per URL deklariertes Remote-Manifest wird nicht
  abgerufen, OCSP-Abfragen sind aus.

Details zum letzten Review: [SECURITY.md](SECURITY.md).

## Aufbau

| Datei | Aufgabe |
|---|---|
| `Classes/Service/CredentialReader.php` | ruft den Verifier auf und übersetzt den Report in die vier Felder |
| `Classes/EventListener/ReadCredentialsOnUpload.php` | prüft nach Upload und Ersetzen |
| `Classes/Command/ScanCommand.php` | `c2pa:scan` für den Bestand |
| `Classes/Hooks/ProtectCredentialFields.php` | sperrt die Prüf-Felder für DataHandler |
| `Classes/Service/AiDisclosureResolver.php` | entscheidet, ob und mit welcher Quelle ein KI-Hinweis erscheint |
| `Classes/ViewHelpers/BadgeViewHelper.php` | `<c2pa:badge>`, rendert `Resources/Private/Templates/Badge.fluid.html` |
| `Configuration/Sets/Badge/` | Site Set `dkd/c2pa-reader-badge`: Hinweis an fluid_styled_content-Bildern |
| `Configuration/TCA/Overrides/sys_file_metadata.php` | Felder und Tab im Backend |
| `ext_tables.sql` | Datenbankfelder |

## Testen

### Automatisierte Tests

Unit- und Functional-Tests mit `typo3/testing-framework` (PHPUnit 12). Die Functional-Tests
nutzen SQLite und brauchen keinen Datenbankserver. Voraussetzung: PHP 8.3+ mit `pdo_sqlite`,
`intl` und `gd`.

```bash
composer install          # installiert TYPO3 14 und die Testwerkzeuge nach .Build/
composer test             # alle Tests
composer test:unit
composer test:functional
```

GitHub Actions (`.github/workflows/tests.yml`) führt die Tests bei jedem Push auf `main` und
bei Pull Requests mit PHP 8.3 und 8.4 aus.

| Test | prüft |
|---|---|
| `Tests/Unit/Service/CredentialReaderTest.php` | alle Testbilder gegen den echten Verifier; manipuliertes KI-Bild → `invalid` und `ai=0`; nicht unterstützter Typ, Größenlimit, Lesefehler |
| `Tests/Unit/Service/AiDisclosureResolverTest.php` | Quelle des KI-Hinweises, Vorrang der Content Credentials |
| `Tests/Unit/Hooks/ProtectCredentialFieldsTest.php` | welche Felder der Hook entfernt |
| `Tests/Functional/Hooks/ProtectCredentialFieldsTest.php` | echter DataHandler als Admin und als Redakteur: Prüf-Felder bleiben, `title` und manuelles KI-Feld ändern sich |
| `Tests/Functional/EventListener/ReadCredentialsOnUploadTest.php` | Hinzufügen und Ersetzen über die FAL-API bis in `sys_file_metadata` |
| `Tests/Functional/ViewHelpers/BadgeViewHelperTest.php` | Ausgabe von `<c2pa:badge>`, id, Texte, Escaping von Manifest-Angaben |

### Handtest in einer Instanz

Testbilder aus
[`contentauth/c2pa-rs`](https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures)
(MIT) liegen in `Tests/Fixtures/c2pa-rs/`, ein ChatGPT-Bild in `Tests/Fixtures/ai/`. Dieses Skript
spielt alle über die FAL-API nach `fileadmin/c2pa-test/` ein. Dabei laufen dieselben Events
wie bei einem Upload:

```bash
ddev exec php /var/www/packages/c2pa_reader/Build/Scripts/import-fixtures.php
```

Das Skript nimmt als optionales Argument den Pfad zu `vendor/autoload.php`; der Standard
ist `/var/www/html/vendor/autoload.php`. Bei erneutem Aufruf werden die Dateien ersetzt
und neu geprüft. Erwartete Ergebnisse:

| Datei | erwarteter Status | KI |
|---|---|---|
| `C.jpg` | valid | 0 (`algorithmicMedia`) |
| `CA.jpg` | valid | 0 |
| `E-sig-CA.jpg` | invalid (`claimSignature.mismatch`) | 0 |
| `exp-test1.png` | invalid (Zertifikat abgelaufen) | 0 |
| `no_manifest.jpg` | none | 0 |
| `chatgpt-gpt-image.png` | valid | 1 (`trainedAlgorithmicMedia`) |

Zum Gegencheck die CLI des Verifiers: `vendor/bin/c2pa-verify <datei>`.

## Nächste Schritte

1. Trust-List-Prüfung (`TrustSettings`) und Status `trusted` als eigener Wert.
2. Weitere Formate (WebP, MP4) und ein Warnhinweis im Backend bei `invalid`.

## Lizenz

GPL-2.0-or-later, siehe [LICENSE](LICENSE).
