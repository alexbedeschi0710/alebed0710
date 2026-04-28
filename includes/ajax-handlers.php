<?php
/**
 * PadelZero - AJAX Handlers
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_pz_join_match', 'pz_ajax_join_match');

function pz_ajax_join_match() {
  check_ajax_referer('pz_join_match', 'nonce');
  
  if (!is_user_logged_in()) wp_send_json_error('Login richiesto');
  
  $post_id = (int)$_POST['post_id'];
  $payment_method = sanitize_text_field($_POST['payment_method']);
  
  $k = pz_meta_keys();
  $amelia_apt_id = (int)get_post_meta($post_id, $k['amelia_appointment_id'], true);
  
  if (!$amelia_apt_id) wp_send_json_error('Partita non trovata');
  
  $current_user = wp_get_current_user();
  $email = $current_user->user_email;
  
// Leggi partecipanti LIVE da Amelia (come nello shortcode)
global $wpdb;
$prefix = PZ_DB_PREFIX;
$participants = [];

$bookings = $wpdb->get_results($wpdb->prepare(
  "SELECT cb.*, u.email, u.firstName, u.lastName
  FROM {$prefix}customer_bookings cb
  LEFT JOIN {$prefix}users u ON cb.customerId = u.id
  WHERE cb.appointmentId = %d AND cb.status IN ('approved', 'paid')
  ORDER BY cb.created ASC",
  $amelia_apt_id
));

if ($bookings) {
  foreach ($bookings as $bk) {
    if (empty($bk->email)) continue;
    
    // Salta admin
    $user = get_user_by('email', $bk->email);
    if ($user && user_can($user, 'manage_options')) continue;
    
    $participants[] = [
      'email' => $bk->email,
      'name' => trim(($bk->firstName ?? '') . ' ' . ($bk->lastName ?? '')),
    ];
  }
}

// Controlla se già iscritto
foreach ($participants as $p) {
  if (isset($p['email']) && $p['email'] === $email) {
    wp_send_json_error('Già iscritto');
  }
}
  
  $max = (int)get_post_meta($post_id, $k['max_capacity'], true);
  if (count($participants) >= $max) {
    wp_send_json_error('Partita al completo');
  }
  
global $wpdb;
$prefix = PZ_DB_PREFIX;

// Prima cerca come customer
$customer = $wpdb->get_row($wpdb->prepare(
    "SELECT id, type FROM {$prefix}users WHERE email = %s AND type = 'customer' LIMIT 1",
    $email
));

// Se non trovato E sei admin, cerca comunque un record Amelia
if (!$customer && current_user_can('manage_options')) {
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id, type FROM {$prefix}users WHERE email = %s LIMIT 1",
        $email
    ));
}

if (!$customer) {
    wp_send_json_error('Email non trovata in Amelia. Crea prima un cliente con questa email.');
}
  
  $is_first = empty($participants);
  
  if ($is_first) {
    $service_id = get_post_meta($post_id, $k['service_id'], true);
    $price = (float)$wpdb->get_var($wpdb->prepare("SELECT price FROM {$prefix}services WHERE id = %d", $service_id));
    
    if ($payment_method === 'wallet') {
      if (pz_get_user_wallet_balance(get_current_user_id()) < $price) {
        wp_send_json_error('Credito insufficiente');
      }
      
      pz_deduct_wallet_credit(get_current_user_id(), $price, 'Partita #'.$post_id);
      
      $wpdb->insert("{$prefix}customer_bookings", [
        'appointmentId' => $amelia_apt_id,
        'customerId' => $customer->id,
        'status' => 'approved',
        'persons' => 1,
        'price' => $price,
        'created' => current_time('mysql'),
      ]);
      
      $participants[] = ['email' => $email, 'name' => trim($current_user->first_name . ' ' . $current_user->last_name)];
      update_post_meta($post_id, $k['participants'], $participants);
      update_post_meta($post_id, $k['booked_count'], count($participants));
      update_post_meta($post_id, $k['payment_method'], 'wallet');
      update_post_meta($post_id, $k['payment_status'], 'paid');
      
      wp_send_json_success(['message' => 'Pagato con wallet']);
      
    } elseif ($payment_method === 'onsite') {
      $wpdb->insert("{$prefix}customer_bookings", [
        'appointmentId' => $amelia_apt_id,
        'customerId' => $customer->id,
        'status' => 'approved',
        'persons' => 1,
        'price' => $price,
        'created' => current_time('mysql'),
      ]);
      
      $participants[] = ['email' => $email, 'name' => trim($current_user->first_name . ' ' . $current_user->last_name)];
      update_post_meta($post_id, $k['participants'], $participants);
      update_post_meta($post_id, $k['booked_count'], count($participants));
      update_post_meta($post_id, $k['payment_method'], 'onsite');
      update_post_meta($post_id, $k['payment_status'], 'pending');
      
      wp_send_json_success(['message' => 'Paga in loco']);
      
    } else {
      update_post_meta($post_id, 'pz_block_auto_add', '1');
      update_post_meta($post_id, 'pz_pending_payment_user', $email);
      update_post_meta($post_id, 'pz_pending_payment_time', current_time('mysql'));
      
      $amelia_url = home_url('/#ameliabooking?ameliaAppointmentBooking='.$amelia_apt_id);
      
      wp_send_json_success(['payment_url' => $amelia_url]);
    }
    
  } else {
    $wpdb->insert("{$prefix}customer_bookings", [
      'appointmentId' => $amelia_apt_id,
      'customerId' => $customer->id,
      'status' => 'approved',
      'persons' => 1,
      'price' => 0,
      'created' => current_time('mysql'),
    ]);
    
    $participants[] = ['email' => $email, 'name' => trim($current_user->first_name . ' ' . $current_user->last_name)];
    update_post_meta($post_id, $k['participants'], $participants);
    update_post_meta($post_id, $k['booked_count'], count($participants));
    
    wp_send_json_success(['message' => 'Aggregato']);
  }
}

