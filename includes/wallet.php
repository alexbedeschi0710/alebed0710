<?php
/**
 * PadelZero - Sistema Wallet
 */

if (!defined('ABSPATH')) exit;

function pz_get_user_wallet_balance($user_id) {
  $balance = get_user_meta($user_id, 'pz_wallet_balance', true);
  return $balance ? (float)$balance : 0.0;
}

function pz_add_wallet_credit($user_id, $amount, $note = '') {
  $current = pz_get_user_wallet_balance($user_id);
  $new_balance = $current + (float)$amount;
  update_user_meta($user_id, 'pz_wallet_balance', $new_balance);
  pz_log_wallet_transaction($user_id, $amount, 'credit', $note);
  return $new_balance;
}

function pz_deduct_wallet_credit($user_id, $amount, $note = '') {
  $current = pz_get_user_wallet_balance($user_id);
  if ($current < $amount) return false;
  
  $new_balance = $current - (float)$amount;
  update_user_meta($user_id, 'pz_wallet_balance', $new_balance);
  pz_log_wallet_transaction($user_id, -$amount, 'debit', $note);
  return $new_balance;
}

function pz_log_wallet_transaction($user_id, $amount, $type, $note) {
  global $wpdb;
  $table = $wpdb->prefix . 'pz_wallet_transactions';
  
  $wpdb->insert($table, [
    'user_id' => $user_id,
    'amount' => $amount,
    'balance_after' => pz_get_user_wallet_balance($user_id),
    'type' => $type,
    'note' => $note,
    'created_at' => current_time('mysql'),
    'created_by' => get_current_user_id(),
  ], ['%d', '%f', '%f', '%s', '%s', '%s', '%d']);
}

