<?php
/**
 * Plugin Name: PadelZero Lobby System
 * Description: Sistema partite pubbliche + Wallet + Gestione pagamenti
 * Version: 4.0.0
 * Author: PadelZero
 */

if (!defined('ABSPATH')) exit;

// Blocca solo il caricamento degli hooks, non tutto il plugin
if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'wpamelia') !== false) {
    define('PZ_AMELIA_ADMIN_MODE', true);
}

define('PZ_VERSION', '4.0.0');
define('PZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PZ_DB_PREFIX', $GLOBALS['wpdb']->prefix . 'amelia_');
define('PZ_PUBLIC_SERVICE_IDS', [1, 6, 7, 8]);

// ─── 0. Stili globali condivisi — deve essere il primo ─────────────────────
require_once PZ_PLUGIN_DIR . 'includes/pz-global.php';

// ─── 1. Core helpers — devono essere primi, tutto dipende da loro ─────────
require_once PZ_PLUGIN_DIR . 'includes/helpers.php';
require_once PZ_PLUGIN_DIR . 'includes/db.php';
require_once PZ_PLUGIN_DIR . 'includes/wallet.php';
require_once PZ_PLUGIN_DIR . 'includes/pwa.php';

// ─── 2. Rating utente — deve venire prima di shortcodes e wizard ────────
require_once PZ_PLUGIN_DIR . 'includes/user-rating.php';

// ─── 3. Struttura dati e sincronizzazione ─────────────────────────────
require_once PZ_PLUGIN_DIR . 'includes/post-type.php';
require_once PZ_PLUGIN_DIR . 'includes/sync-users.php';
require_once PZ_PLUGIN_DIR . 'includes/amelia-hooks.php';
require_once PZ_PLUGIN_DIR . 'includes/admin-cleanup.php';

// ─── 4. Shortcode e UI — dipendono da helpers + user-rating ───────────
require_once PZ_PLUGIN_DIR . 'includes/shortcodes.php';
require_once PZ_PLUGIN_DIR . 'includes/wizard.php';
require_once PZ_PLUGIN_DIR . 'includes/private-booking.php';
require_once PZ_PLUGIN_DIR . 'includes/public-booking.php';
require_once PZ_PLUGIN_DIR . 'includes/my-bookings.php';
require_once PZ_PLUGIN_DIR . 'includes/bottom-nav.php';
require_once PZ_PLUGIN_DIR . 'includes/top-bar.php';
require_once PZ_PLUGIN_DIR . 'includes/account.php';

// ─── 5. Pagamenti e admin ───────────────────────────────────────
require_once PZ_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once PZ_PLUGIN_DIR . 'includes/amelia-frontend.php';
require_once PZ_PLUGIN_DIR . 'includes/amelia-payment-gateway.php';
require_once PZ_PLUGIN_DIR . 'includes/admin-wallet.php';

// ─── 6. Moduli opzionali (se esistono) ────────────────────────────
$customer_sync = PZ_PLUGIN_DIR . 'includes/customer-sync.php';
if (file_exists($customer_sync)) require_once $customer_sync;

register_activation_hook(__FILE__, 'pz_activate_plugin');

function pz_activate_plugin() {
    pz_create_wallet_table();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'pz_deactivate_plugin');

function pz_deactivate_plugin() {
    flush_rewrite_rules();
}
