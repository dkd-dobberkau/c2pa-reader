# Test images from c2pa-rs

Source: <https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures>,
licensed MIT (see `LICENSE-MIT`) or Apache-2.0, at your option.

| File | Expected status | AI | Content |
|---|---|---|---|
| `C.jpg` | valid | 0 | created, `digitalSourceType` `algorithmicMedia` (no trained model) |
| `CA.jpg` | valid | 0 | opened and edited (`c2pa.opened`, `c2pa.color_adjustments`) |
| `E-sig-CA.jpg` | invalid | 0 | signature mismatch (`claimSignature.mismatch`) |
| `exp-test1.png` | invalid | 0 | Photoshop, signing certificate expired in 2023 (`signingCredential.expired`) |
| `libpng-test.png` | none | 0 | no manifest |
| `no_manifest.jpg` | none | 0 | no manifest |

A real AI image (status `valid`, AI `1`) is in `../ai/`.

To add them to a local instance: `Build/Scripts/import-fixtures.php`, see the README in the
root directory.
