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

## Gespeicherte Felder (`sys_file_metadata`)

| Feld | Werte | Bedeutung |
|---|---|---|
| `tx_c2pareader_status` | `none`, `valid`, `invalid`, `unreadable` | Ergebnis der Prüfung (siehe unten) |
| `tx_c2pareader_ai` | 0 / 1 | 1 = das Manifest erklärt die Datei als KI-generiert. Nur bei gültiger Signatur. |
| `tx_c2pareader_agents` | Text, max. 255 Zeichen | erzeugende Software (`claim_generator_info`) und `softwareAgent` der Actions |
| `tx_c2pareader_checked` | Unix-Zeitstempel | letzte Prüfung |

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

- Die vier Felder schreibt nur die Extension selbst (über `MetaDataRepository`). Ein
  DataHandler-Hook (`Classes/Hooks/ProtectCredentialFields.php`) entfernt sie aus jedem
  eingehenden Datensatz. So kann kein Redakteur per Formular, Import oder Workspace den
  Status fälschen. `readOnly` im TCA allein reicht dafür nicht, denn es wirkt nur in der
  Anzeige.
- Alle Werte aus dem Manifest (Agents, Texte) sind **Angaben aus der Datei** und damit
  nicht vertrauenswürdig. Das Backend escaped sie. Ein künftiges Frontend-Badge muss sie
  ebenfalls escapen (Fluid tut das standardmäßig, kein `f:format.raw`).
- Der Verifier lädt nichts nach. Ein per URL deklariertes Remote-Manifest wird nicht
  abgerufen, OCSP-Abfragen sind aus.

Details zum letzten Review: [SECURITY.md](SECURITY.md).

## Aufbau

| Datei | Aufgabe |
|---|---|
| `Classes/Service/CredentialReader.php` | ruft den Verifier auf und übersetzt den Report in die vier Felder |
| `Classes/EventListener/ReadCredentialsOnUpload.php` | prüft nach Upload und Ersetzen |
| `Classes/Command/ScanCommand.php` | `c2pa:scan` für den Bestand |
| `Classes/Hooks/ProtectCredentialFields.php` | sperrt die Felder für DataHandler |
| `Configuration/TCA/Overrides/sys_file_metadata.php` | Felder und Tab im Backend |
| `ext_tables.sql` | Datenbankfelder |

## Testen

Es gibt noch keine automatisierten Tests. Für einen Handtest eignen sich die Testbilder
aus [`contentauth/c2pa-rs`](https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures):

| Datei | erwarteter Status | KI |
|---|---|---|
| `C.jpg` | valid | 0 (`algorithmicMedia`) |
| `CA.jpg` | valid | 0 |
| `E-sig-CA.jpg` | invalid (`claimSignature.mismatch`) | 0 |
| `exp-test1.png` | invalid (Zertifikat abgelaufen) | 0 |
| `no_manifest.jpg` | none | 0 |
| ein Bild aus ChatGPT | valid | 1 |

Zum Gegencheck die CLI des Verifiers: `vendor/bin/c2pa-verify <datei>`.

## Nächste Schritte

1. Trust-List-Prüfung (`TrustSettings`) und Status `trusted` als eigener Wert.
2. Frontend-Badge (Fluid-ViewHelper) aus den gespeicherten Feldern.
3. Weitere Formate (WebP, MP4) und ein Warnhinweis im Backend bei `invalid`.
4. Functional Tests für Reader, Listener und DataHandler-Hook.

## Lizenz

GPL-2.0-or-later, siehe [LICENSE](LICENSE).
