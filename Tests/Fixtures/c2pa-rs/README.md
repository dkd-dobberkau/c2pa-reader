# Testbilder aus c2pa-rs

Quelle: <https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures>,
Lizenz MIT (siehe `LICENSE-MIT`) oder Apache-2.0, nach Wahl.

| Datei | erwarteter Status | KI | Inhalt |
|---|---|---|---|
| `C.jpg` | valid | 0 | erzeugt, `digitalSourceType` `algorithmicMedia` (kein trainiertes Modell) |
| `CA.jpg` | valid | 0 | geöffnet und bearbeitet (`c2pa.opened`, `c2pa.color_adjustments`) |
| `E-sig-CA.jpg` | invalid | 0 | Signatur passt nicht (`claimSignature.mismatch`) |
| `exp-test1.png` | invalid | 0 | Photoshop, Signaturzertifikat 2023 abgelaufen (`signingCredential.expired`) |
| `libpng-test.png` | none | 0 | kein Manifest |
| `no_manifest.jpg` | none | 0 | kein Manifest |

Ein echtes KI-Bild (Status `valid`, KI `1`) ist nicht dabei, weil die Rechte daran
unklar sind. Dafür ein Bild aus ChatGPT oder Adobe Firefly hochladen.

Einspielen in eine lokale Instanz: `Build/Scripts/import-fixtures.php`, siehe README im
Wurzelverzeichnis.
