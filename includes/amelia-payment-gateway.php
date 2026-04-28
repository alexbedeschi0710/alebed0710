<?php
/**
 * PadelZero - Custom Payment Gateway per Amelia
 * Registra "Wallet" come metodo di pagamento
 */

if (!defined('ABSPATH')) exit;

// Registra il gateway custom
add_filter('amelia_before_payment_gateway_settings_saved', 'pz_register_wallet_gateway', 10, 1);
add_filter('amelia_get_custom_payment_gateways', 'pz_add_wallet_to_gateways', 10, 1);

function pz_register_wallet_gateway($settings) {
    // Aggiungi Wallet ai gateway disponibili
    if (!isset($settings['payments']['walletEnabled'])) {
        $settings['payments']['walletEnabled'] = true;
    }
    
    return $settings;
}

function pz_add_wallet_to_gateways($gateways) {
    $gateways['wallet'] = [
        'name' => 'Borsellino',
        'enabled' => true,
        'description' => 'Paga con il tuo borsellino prepagato'
    ];
    
    return $gateways;
}

// Hook per processare il pagamento wallet da Amelia
add_action('amelia_before_booking_added', 'pz_process_wallet_payment_from_amelia', 10, 1);

function pz_process_wallet_payment_from_amelia($args) {
    // Verifica se il metodo di pagamento è wallet
    if (empty($args['payment']) || $args['payment']['gateway'] !== 'wallet') {
        return $args;
    }
    
    $userId = get_current_user_id();
    if (!$userId) {
        throw new Exception('Utente non autenticato');
    }
    
    $amount = floatval($args['payment']['amount']);
    $balance = pz_get_user_wallet_balance($userId);
    
    if ($balance < $amount) {
        throw new Exception('Credito insufficiente. Saldo: €' . number_format($balance, 2));
    }
    
    // Scala il wallet
    pz_deduct_wallet_credit($userId, $amount, 'Prenotazione Amelia');
    
    // Marca come pagato
    $args['payment']['status'] = 'paid';
    $args['booking']['status'] = 'approved';
    
    error_log('PZ: Pagamento wallet processato - €' . $amount);
    
    return $args;
}

// Aggiungi info wallet nel frontend Amelia
add_filter('amelia_before_booking_form_data', 'pz_add_wallet_info_to_form', 10, 1);

function pz_add_wallet_info_to_form($data) {
    if (is_user_logged_in()) {
        $balance = pz_get_user_wallet_balance(get_current_user_id());
        $data['walletBalance'] = $balance;
    }
    
    return $data;
}

