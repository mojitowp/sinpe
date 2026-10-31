#!/usr/bin/env bash
set -euo pipefail
# Only creates a disposable database named sinpe_qa_ci. Never use a live wp-config.
: "${WP_VERSION:?Set the WordPress fixture version}"
: "${WC_VERSION:?Set the WooCommerce fixture version}"
: "${SINPE_DB_HOST:=127.0.0.1:3306}"
: "${SINPE_DB_USER:=root}"
: "${SINPE_DB_PASSWORD:=root}"
repo=$(cd "$(dirname "$0")/.." && pwd)
site=$(mktemp -d /tmp/sinpe-ci-site-XXXXXX)
wp core download --path="$site" --version="$WP_VERSION" --quiet
wp config create --path="$site" --dbname=sinpe_qa_ci --dbuser="$SINPE_DB_USER" --dbpass="$SINPE_DB_PASSWORD" --dbhost="$SINPE_DB_HOST" --quiet
wp db create --path="$site" --quiet
wp core install --path="$site" --url=http://localhost:8080 --title='SINPE disposable CI' --admin_user=sinpe-qa --admin_password='Local-only-ci-2026' --admin_email=qa@example.test --skip-email --quiet
mkdir -p "$site/wp-content/mu-plugins"
cat > "$site/wp-content/mu-plugins/sinpe-ci.php" <<'PHP'
<?php
add_filter('pre_wp_mail','__return_true');
add_filter('pre_http_request',static function($pre,$args,$url){
    if(str_contains($url,'api.hacienda.go.cr/indicadores/tc')) return new WP_Error('offline','No external services in CI');
    return $pre;
},10,3);
PHP
if [[ -n "${WC_PACKAGE_URL:-}" ]]; then
    wp plugin install "$WC_PACKAGE_URL" --path="$site" --activate --quiet
else
    wp plugin install woocommerce --version="$WC_VERSION" --path="$site" --activate --quiet
fi
ln -s "$repo" "$site/wp-content/plugins/mojito-sinpe"
wp plugin activate mojito-sinpe --path="$site" --quiet
for hpos in no yes; do
    wp wc hpos sync --path="$site" --batch-size=100 || wp wc cot sync --path="$site" --batch-size=100
    wp option update woocommerce_custom_orders_table_data_sync_enabled no --path="$site" --quiet
    wp option update woocommerce_custom_orders_table_enabled "$hpos" --path="$site" --quiet
    SINPE_TEST_SITE=1 wp eval-file "$repo/tests/integration.php" --path="$site"
done
if [[ "${SINPE_PLUGIN_CHECK:-0}" == 1 ]]; then
    wp plugin install plugin-check --activate --path="$site" --quiet
    python3 "$repo/tools/build.py" /tmp/sinpe-ci-package.zip
    extracted=$(mktemp -d /tmp/sinpe-ci-package-XXXXXX)
    unzip -q /tmp/sinpe-ci-package.zip -d "$extracted"
    wp plugin check "$extracted/mojito-sinpe" --path="$site" --mode=update --format=table
fi
