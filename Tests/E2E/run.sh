#!/usr/bin/env bash
set -euo pipefail

EXTENSION_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.."
E2E_DIR="${EXTENSION_ROOT}/Tests/E2E"
APP_DIR="${E2E_DIR}/.app"
CORE_CONSTRAINT="${TYPO3_CORE_CONSTRAINT:-^13.4}"

for pidFile in "${E2E_DIR}/.php-server.pid" "${E2E_DIR}/.php-server-staging.pid" "${E2E_DIR}/.vite.pid"; do
    if [ -f "${pidFile}" ]; then
        pkill -P "$(cat "${pidFile}")" 2>/dev/null || true
        kill "$(cat "${pidFile}")" 2>/dev/null || true
        rm -f "${pidFile}"
    fi
done

rm -rf "${APP_DIR}"
mkdir -p "${APP_DIR}"
cp "${E2E_DIR}/fixture/composer.json" "${APP_DIR}/"
cp "${E2E_DIR}/fixture/router.php" "${APP_DIR}/"
cp "${E2E_DIR}/fixture/vite.config.ts" "${APP_DIR}/"
cp "${E2E_DIR}/fixture/package.json" "${APP_DIR}/"
cp -R "${E2E_DIR}/fixture/packages" "${APP_DIR}/packages"

(cd "${EXTENSION_ROOT}/Resources/Private/Vite" && npm install --no-audit --no-fund && npm run build)
(cd "${APP_DIR}" && npm install --no-audit --no-fund)

cd "${APP_DIR}"
composer require --no-interaction --no-progress \
    "typo3/cms-core:${CORE_CONSTRAINT}" \
    "typo3/cms-frontend:${CORE_CONSTRAINT}" \
    "typo3/cms-backend:${CORE_CONSTRAINT}" \
    "typo3/cms-install:${CORE_CONSTRAINT}" \
    "typo3/cms-fluid-styled-content:${CORE_CONSTRAINT}"

TYPO3_DB_DRIVER=sqlite \
TYPO3_SETUP_ADMIN_EMAIL=admin@example.com \
TYPO3_SETUP_ADMIN_USERNAME=admin \
TYPO3_SETUP_ADMIN_PASSWORD='Password1!' \
TYPO3_PROJECT_NAME=E2E \
TYPO3_SERVER_TYPE=other \
TYPO3_SETUP_CREATE_SITE='http://127.0.0.1:8080/' \
php -d opcache.enable_cli=0 vendor/bin/typo3 setup --force --no-interaction

cp "${E2E_DIR}/fixture/additional.php" config/system/additional.php
cp "${E2E_DIR}/fixture/sites-main-config.yaml" config/sites/main/config.yaml
vendor/bin/typo3 cache:flush
vendor/bin/typo3 e2e:seed > "${E2E_DIR}/.seed.json"
vendor/bin/typo3 cache:flush
php -r '$database = glob("var/sqlite/cms-*.sqlite")[0]; (new PDO("sqlite:" . $database))->exec("PRAGMA journal_mode=WAL");'

unset VITE_SERVER_URI VITE_PRIMARY_PORT
TYPO3_CONTEXT=Development VITE_PRIMARY_PORT=5273 php -S 127.0.0.1:8080 -t public router.php >"${E2E_DIR}/.php-server.log" 2>&1 &
echo $! > "${E2E_DIR}/.php-server.pid"
TYPO3_CONTEXT='Production/Staging' php -S 127.0.0.1:8081 -t public router.php >"${E2E_DIR}/.php-server-staging.log" 2>&1 &
echo $! > "${E2E_DIR}/.php-server-staging.pid"
npx vite --config vite.config.ts >"${E2E_DIR}/.vite.log" 2>&1 &
echo $! > "${E2E_DIR}/.vite.pid"

for _ in $(seq 1 30); do
    curl -fs http://127.0.0.1:8080/ >/dev/null && break
    sleep 1
done
curl -fs http://127.0.0.1:8080/ >/dev/null
for _ in $(seq 1 30); do
    curl -fs http://127.0.0.1:8081/ >/dev/null && break
    sleep 1
done
curl -fs http://127.0.0.1:8081/ >/dev/null
for _ in $(seq 1 15); do
    curl -fs http://127.0.0.1:5273/@id/virtual:live-reload >/dev/null && break
    sleep 1
done
curl -fs http://127.0.0.1:5273/@id/virtual:live-reload >/dev/null

echo "E2E environment ready"
