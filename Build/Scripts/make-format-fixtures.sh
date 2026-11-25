#!/usr/bin/env bash
# Creates the small signed test files in Tests/Fixtures/formats/: one tiny
# media file per format, signed with the ES256 test certificate of c2pa-rs
# and declared AI-generated (digitalSourceType trainedAlgorithmicMedia).
#
# The test certificate expires on 2030-08-26. After that the files no longer
# verify as "valid"; run this script again to renew them.
#
# Requires: ffmpeg, magick (ImageMagick), heif-enc (libheif), c2patool, curl.
#
#   Build/Scripts/make-format-fixtures.sh
set -euo pipefail

cd "$(dirname "$0")/../.."
out=Tests/Fixtures/formats
work="$(mktemp -d -t c2pa-fixtures.XXXX)"
trap 'rm -rf "$work"' EXIT

for tool in ffmpeg magick heif-enc c2patool curl; do
    command -v "$tool" >/dev/null || { echo "ERROR: $tool not found" >&2; exit 1; }
done

# Test signing certificate and key of c2pa-rs (MIT), "FOR TESTING_ONLY".
certs=https://raw.githubusercontent.com/contentauth/c2pa-rs/main/sdk/tests/fixtures/certs
curl -sfL -o "$work/es256.pem" "$certs/es256.pem"
curl -sfL -o "$work/es256.pub" "$certs/es256.pub"

cat > "$work/manifest.json" <<EOF
{
    "alg": "es256",
    "private_key": "$work/es256.pem",
    "sign_cert": "$work/es256.pub",
    "claim_generator_info": [{ "name": "c2pa_reader test fixtures", "version": "1" }],
    "assertions": [{
        "label": "c2pa.actions",
        "data": { "actions": [{
            "action": "c2pa.created",
            "softwareAgent": "c2pa_reader fixture generator",
            "digitalSourceType": "http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia"
        }] }
    }]
}
EOF

# Tiny unsigned inputs: a 64x36 test pattern, one second of video or a sine tone.
q=(-hide_banner -loglevel error -y)
magick -size 64x36 gradient:'#1d3557-#f4a261' "$work/source.png"
magick "$work/source.png" "$work/raw.webp"
magick "$work/source.png" "$work/raw.gif"
magick "$work/source.png" "$work/raw.avif"
heif-enc -o "$work/raw.heic" "$work/source.png" >/dev/null
ffmpeg "${q[@]}" -f lavfi -i testsrc=duration=1:size=64x36:rate=5 -pix_fmt yuv420p "$work/raw.mp4"
ffmpeg "${q[@]}" -f lavfi -i testsrc=duration=1:size=64x36:rate=5 -pix_fmt yuv420p "$work/raw.mov"
ffmpeg "${q[@]}" -f lavfi -i sine=frequency=440:duration=1 -ar 8000 -ac 1 "$work/raw.wav"
ffmpeg "${q[@]}" -f lavfi -i sine=frequency=440:duration=1 -ar 22050 -ac 1 -b:a 32k "$work/raw.mp3"
ffmpeg "${q[@]}" -f lavfi -i sine=frequency=440:duration=1 -ar 22050 -ac 1 -b:a 32k "$work/raw.m4a"

mkdir -p "$out"
for ext in webp gif avif heic mp4 mov wav mp3 m4a; do
    c2patool "$work/raw.$ext" -m "$work/manifest.json" -o "$out/ai.$ext" -f >/dev/null
    printf '%-10s %6d bytes\n' "ai.$ext" "$(wc -c < "$out/ai.$ext")"
done
