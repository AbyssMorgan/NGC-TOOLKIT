#!/bin/bash
SUPPORTED_PHP_VERSIONS=("php8.5")
find_php_bin() {
    for version in "${SUPPORTED_PHP_VERSIONS[@]}"; do
        if [[ -x "/usr/bin/$version" ]]; then
            echo "/usr/bin/$version"
            return
        elif [[ -x "/opt/homebrew/bin/$version" ]]; then
            echo "/opt/homebrew/bin/$version"
            return
        elif [[ -x "/usr/local/bin/$version" ]]; then
            echo "/usr/local/bin/$version"
            return
        fi
    done
    echo ""
}
PHP_BIN=$(find_php_bin)
if [[ -z "$PHP_BIN" ]]; then
    echo "No supported PHP version (8.5) found. Please check your installation."
    exit 1
fi
cd "$(dirname "$0")"
"$PHP_BIN" "$PWD/../includes/toolkit.php" --interactive