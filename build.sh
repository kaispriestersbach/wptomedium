#!/usr/bin/env bash
set -euo pipefail

VERSION=$(sed -n 's/^.*Version:[[:space:]]*\([0-9.]*\).*/\1/p' wptomedium/wptomedium.php)
ZIPNAME="wptomedium-${VERSION}.zip"

echo "Building ${ZIPNAME}..."

# CVE-Check (bricht Build bei bekannten Security-Advisories ab)
bash ./security-check.sh

# i18n aktualisieren
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-pot \
  /app /app/languages/wptomedium.pot --domain=wptomedium --package-name="WPtoMedium"
docker run --rm -v "$(pwd)/wptomedium:/app" wordpress:cli i18n make-mo /app/languages/

# ZIP erstellen (nur wptomedium/ Ordner, ohne Dev-Dateien)
rm -f "$ZIPNAME"
zip -r "$ZIPNAME" wptomedium/ \
  -x "wptomedium/composer.json" \
  -x "wptomedium/composer.lock" \
  -x "*/.DS_Store" \
  -x "__MACOSX/*"

echo "Created ${ZIPNAME} ($(du -h "$ZIPNAME" | cut -f1))"
