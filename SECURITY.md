# Sicherheit

## Lücken melden

Bitte nicht als öffentliches Issue, sondern direkt an dkd Internet Service GmbH.

## Review vom 2026-11-25

Gezielter Quellcode-Review aller Dateien der Extension (Stand nach dem Umbau auf
`provemark/c2pa-verifier` 0.11.5), mit lokaler Prüfung in einer DDEV-Instanz
(TYPO3 14.3.12, PHP 8.4). Kein vollständiger Audit; das Paket `provemark/c2pa-verifier`
selbst wurde nicht geprüft.

### Behoben

**C2PA-Felder per DataHandler überschreibbar** (Schwere: mittel)

- *Wer:* jeder Backend-Benutzer mit Schreibrecht auf `sys_file_metadata`. Die Felder
  sind nicht `exclude`, also braucht es keine eigene Freigabe.
- *Was:* `readOnly` im TCA steuert nur die Anzeige. DataHandler hat die Felder trotzdem
  geschrieben, etwa über einen manipulierten Formular-POST, einen Import oder einen
  Workspace-Datensatz.
- *Folge:* Der Herkunftsnachweis ließ sich fälschen. Lokal nachgestellt: Status `invalid`
  → `valid`, Agent „Forged Camera 1.0", ohne Fehlermeldung.
- *Fix:* `Classes/Hooks/ProtectCredentialFields.php` entfernt die vier Felder in
  `processDatamap_preProcessFieldArray` aus jedem eingehenden Datensatz für
  `sys_file_metadata`. Die Extension schreibt selbst über `MetaDataRepository`, also nicht
  über DataHandler. Nach dem Fix: der Titel ändert sich, die C2PA-Felder bleiben; Upload
  und `c2pa:scan` schreiben weiter.

### Geprüft, kein Befund

- **Ausgabe von Manifest-Daten:** Agents stehen in einem `readOnly`-Input-Feld; FormEngine
  escaped den Wert.
- **Netzwerk / SSRF:** Der Verifier ruft keine URLs ab (Remote-Manifest wird nur gemeldet,
  OCSP-Quelle ist `NoRevocationSource`).
- **SQL:** `c2pa:scan` nutzt den QueryBuilder mit benannten Parametern.
- **Dateizugriff:** Gelesen wird über `getForLocalProcessing(false)` nur die Datei, die
  das Event oder der Scan liefert. Der Stream wird in `finally` geschlossen.
- **Speicher:** Dateien über 50 MB werden nicht geprüft. Der Verifier liest als Stream
  und begrenzt CBOR-Strukturen (`CborBudget`).

### Bekannte Grenzen (kein Fehler im Code)

- `valid` bedeutet nicht vertrauenswürdig. Ohne Trust List ist ein selbst signiertes
  Manifest ebenfalls `valid` und kann z. B. eine KI-Herkunft verschweigen.
- Die Prüfung läuft synchron im Upload-Request. Wie lange der Verifier für eine präparierte
  Datei knapp unter 50 MB braucht, wurde nicht gemessen.
- Werden Dateien außerhalb von TYPO3 geändert, ist der gespeicherte Status veraltet, bis
  `c2pa:scan --force` läuft.
