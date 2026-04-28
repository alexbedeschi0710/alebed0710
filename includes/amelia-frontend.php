<?php
/**
 * PadelZero - Integrazione Frontend Amelia
 * Aggiunge metodo pagamento Wallet al form Amelia
 */

if (!defined('ABSPATH')) exit;

// Carica script solo sulle pagine con Amelia booking
add_action('wp_enqueue_scripts', 'pz_enqueue_amelia_wallet_script');

function pz_enqueue_amelia_wallet_script() {
    // Carica SEMPRE (su tutte le pagine frontend)
    if (!is_admin()) {
        wp_enqueue_script(
            'pz-amelia-wallet',
            PZ_PLUGIN_URL . 'assets/amelia-wallet.js',
            ['jquery'],
            PZ_VERSION . '-' . time(), // Cache-bust per debug
            true
        );
        
        wp_localize_script('pz-amelia-wallet', 'pzAmeliaWallet', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pz_amelia_wallet_payment'),
            'user_balance' => is_user_logged_in() ? pz_get_user_wallet_balance(get_current_user_id()) : 0,
            'is_logged_in' => is_user_logged_in(),
        ]);
    }
}


// AJAX: Paga con wallet da form Amelia
add_action('wp_ajax_pz_amelia_wallet_payment', 'pz_ajax_amelia_wallet_payment');

function pz_ajax_amelia_wallet_payment() {
    check_ajax_referer('pz_amelia_wallet_payment', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Login richiesto');
    }
    
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    
    if (!$appointment_id || !$service_id) {
        wp_send_json_error('Dati mancanti');
    }
    
    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    
    // Verifica utente Amelia
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id, type FROM {$prefix}users WHERE email = %s LIMIT 1",
        $email
    ));
    
    if (!$customer) {
        wp_send_json_error('Utente non trovato in Amelia');
    }
    
    // Prendi prezzo servizio
    $price = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT price FROM {$prefix}services WHERE id = %d",
        $service_id
    ));
    
    // Controlla saldo
    $balance = pz_get_user_wallet_balance(get_current_user_id());
    if ($balance < $price) {
        wp_send_json_error('Credito insufficiente (saldo: €' . number_format($balance, 2) . ')');
    }
    
    // Scala wallet
    pz_deduct_wallet_credit(get_current_user_id(), $price, 'Prenotazione Amelia #' . $appointment_id);
    
    // Crea booking in Amelia
    $booking_id = $wpdb->insert("{$prefix}customer_bookings", [
        'appointmentId' => $appointment_id,
        'customerId' => $customer->id,
        'status' => 'approved',
        'persons' => 1,
        'price' => $price,
        'created' => current_time('mysql'),
    ]);
    
    if (!$booking_id) {
        // Rimborsa in caso di errore
        pz_add_wallet_credit(get_current_user_id(), $price, 'Rimborso prenotazione fallita #' . $appointment_id);
        wp_send_json_error('Errore creazione prenotazione');
    }
    
    wp_send_json_success([
        'message' => 'Prenotazione confermata! Pagato con Wallet.',
        'new_balance' => pz_get_user_wallet_balance(get_current_user_id()),
    ]);
}

