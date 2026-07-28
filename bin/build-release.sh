#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BUILD_DIR="${PROJECT_DIR}/build"
PACKAGE_DIR="${BUILD_DIR}/od-update-history"
ARCHIVE_PATH="${BUILD_DIR}/od-update-history.zip"

rm -rf "${BUILD_DIR}"
mkdir -p "${PACKAGE_DIR}"

cp "${PROJECT_DIR}/od-update-history.php" "${PACKAGE_DIR}/"
cp "${PROJECT_DIR}/README.md" "${PACKAGE_DIR}/"
cp "${PROJECT_DIR}/composer.json" "${PACKAGE_DIR}/"
cp "${PROJECT_DIR}/composer.lock" "${PACKAGE_DIR}/"
cp -R "${PROJECT_DIR}/includes" "${PACKAGE_DIR}/"

composer install \
	--working-dir="${PACKAGE_DIR}" \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader

rm "${PACKAGE_DIR}/composer.json"
rm "${PACKAGE_DIR}/composer.lock"

(
	cd "${BUILD_DIR}"
	zip -qr "${ARCHIVE_PATH}" od-update-history
)

echo "Created ${ARCHIVE_PATH}"
