#!/usr/bin/env bash
# setup.sh
# Installs all project dependencies for local development.
# Run once after cloning: bash setup.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
MATHJS_VERSION="13.2.0"
MATHJS_URL="https://cdnjs.cloudflare.com/ajax/libs/mathjs/${MATHJS_VERSION}/math.min.js"
MATHJS_DEST="${ROOT}/assets/js/vendor/math.min.js"

echo "XcaliburMoon - Setup"
echo "---"

# ----- PHP version check -----
if ! command -v php &>/dev/null; then
    echo "ERROR: PHP not found. Install PHP 8.3+ and try again."
    exit 1
fi

PHP_MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
PHP_MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"
PHP_VERSION="${PHP_MAJOR}.${PHP_MINOR}"

if [[ "$PHP_MAJOR" -lt 8 ]] || { [[ "$PHP_MAJOR" -eq 8 ]] && [[ "$PHP_MINOR" -lt 3 ]]; }; then
    echo "ERROR: PHP 8.3+ required. Found: ${PHP_VERSION}"
    exit 1
fi

echo "  PHP ${PHP_VERSION} found"

# ----- Composer / PHPMailer -----
if [[ ! -d "${ROOT}/vendor" ]]; then
    if command -v composer &>/dev/null; then
        COMPOSER_CMD="composer"
    elif [[ -f "${ROOT}/composer.phar" ]]; then
        COMPOSER_CMD="php ${ROOT}/composer.phar"
    else
        echo "  Composer not found. Downloading composer.phar..."
        curl -sSL https://getcomposer.org/installer \
            | php -- --install-dir="${ROOT}" --filename=composer.phar --quiet
        COMPOSER_CMD="php ${ROOT}/composer.phar"
        echo "  composer.phar downloaded"
    fi

    echo "  Installing PHP dependencies (PHPMailer)..."
    cd "${ROOT}" && ${COMPOSER_CMD} install --no-interaction --prefer-dist --quiet
    echo "  vendor/ ready"
else
    echo "  vendor/ already present"
fi

# ----- Math.js (self-hosted) -----
if [[ ! -f "${MATHJS_DEST}" ]]; then
    mkdir -p "$(dirname "${MATHJS_DEST}")"
    echo "  Downloading Math.js ${MATHJS_VERSION}..."

    if command -v curl &>/dev/null; then
        curl -sSL -o "${MATHJS_DEST}" "${MATHJS_URL}"
    elif command -v wget &>/dev/null; then
        wget -q -O "${MATHJS_DEST}" "${MATHJS_URL}"
    else
        echo "ERROR: Neither curl nor wget is available."
        echo "  Download manually: ${MATHJS_URL}"
        echo "  Save to: ${MATHJS_DEST}"
        exit 1
    fi

    echo "  Math.js ${MATHJS_VERSION} saved to assets/js/vendor/"
else
    echo "  Math.js already present"
fi

# ----- Data files -----
if [[ ! -f "${ROOT}/data/quotes.json" ]]; then
    echo "[]" > "${ROOT}/data/quotes.json"
    echo "  Created data/quotes.json"
fi

if [[ ! -f "${ROOT}/data/clean_data.json" ]]; then
    echo "[]" > "${ROOT}/data/clean_data.json"
    echo "  Created data/clean_data.json"
fi

echo "---"
echo "Setup complete."
