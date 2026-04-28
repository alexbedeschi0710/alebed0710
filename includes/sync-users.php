<?php
/**
 * PadelZero - Sincronizzazione utenti Amelia
 */

if (!defined('ABSPATH')) exit;

function pz_sync_amelia_customer_to_wp($email, $firstName = '', $lastName = '') {
  if (!$email || !is_email($email)) return false;
  
  $wp_user = get_user_by('email', $email);
  if ($wp_user) return $wp_user->ID;
  
  $username = sanitize_user($email);
  $base_username = $username;
  $counter = 1;
  while (username_exists($username)) {
    $username = $base_username . $counter;
    $counter++;
  }
  
  $password = wp_generate_password(12, true, true);
  $user_data = [
    'user_login' => $username,
    'user_email' => $email,
    'user_pass' => $password,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'role' => 'wpamelia-customer',
  ];
  
  $user_id = wp_insert_user($user_data);
  if (is_wp_error($user_id)) {
    error_log('PZ: Errore creazione utente: ' . $user_id->get_error_message());
    return false;
  }
  
  wp_new_user_notification($user_id, null, 'both');
  return $user_id;
}
