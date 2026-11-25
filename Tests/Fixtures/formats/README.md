# One signed test file per format

Tiny files (a 64×36 test pattern, one second of video, a one-second sine tone), each with a
C2PA manifest that declares it AI-generated (`digitalSourceType`
`trainedAlgorithmicMedia`). Created by `Build/Scripts/make-format-fixtures.sh` with
`c2patool` 0.29.0.

They are signed with the ES256 test certificate of
[c2pa-rs](https://github.com/contentauth/c2pa-rs/tree/main/sdk/tests/fixtures/certs) (MIT),
issued "FOR TESTING_ONLY" and on no trust list, so the expected status is `valid`, never
`trusted`. The certificate expires on **2030-08-26**; run the script again after that.

| File | MIME type | Expected status | AI |
|---|---|---|---|
| `ai.webp` | image/webp | valid | 1 |
| `ai.gif` | image/gif | valid | 1 |
| `ai.avif` | image/avif | valid | 1 |
| `ai.heic` | image/heic | valid | 1 |
| `ai.mp4` | video/mp4 | valid | 1 |
| `ai.mov` | video/quicktime | valid | 1 |
| `ai.wav` | audio/x-wav | valid | 1 |
| `ai.mp3` | audio/mpeg | valid | 1 |
| `ai.m4a` | audio/x-m4a | valid | 1 |
