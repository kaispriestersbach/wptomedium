#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${ROOT_DIR}/wptomedium"

echo "Running Composer CVE audit (locked dependencies)..."
docker run --rm -v "${PLUGIN_DIR}:/app" -w /app composer:2 audit --locked --no-interaction
echo "Composer CVE audit passed."
