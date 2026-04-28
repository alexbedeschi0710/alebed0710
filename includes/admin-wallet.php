<?php
/**
 * PadelZero - Admin Menu Wallet
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
  add_menu_page('Wallet Clienti', 'Wallet Clienti', 'manage_options', 'pz-wallet-manager', 'pz_wallet_manager_page', 'dashicons-money-alt', 30);
});

function pz_wallet_manager_page() {
  if (!current_user_can('manage_options')) wp_die('Accesso negato');
  
  if (isset($_POST['pz_quick_wallet_update'])) {
    check_admin_referer('pz_wallet_quick_update');
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $note = sanitize_text_field($_POST['note']);
    
    if ($user_id && $note) {
      if ($amount > 0) {
        pz_add_wallet_credit($user_id, $amount, $note);
        echo '<div class="notice notice-success"><p>✅ Aggiunto €'.number_format($amount, 2).'</p></div>';
      } elseif ($amount < 0) {
        pz_deduct_wallet_credit($user_id, abs($amount), $note);
        echo '<div class="notice notice-success"><p>✅ Scalato €'.number_format(abs($amount), 2).'</p></div>';
      }
    }
  }
  
  $users = get_users(['role__in' => ['customer', 'subscriber', 'wpamelia-customer'], 'orderby' => 'display_name']);
  $total_credit = 0;
  $users_with_credit = 0;
  
  foreach ($users as $u) {
    $bal = pz_get_user_wallet_balance($u->ID);
    if ($bal > 0) {
      $total_credit += $bal;
      $users_with_credit++;
    }
  }
  ?>
  <div class="wrap">
    <h1>💰 Gestione Wallet Clienti</h1>
    
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0">
      <div style="background:#fff;padding:20px;border-left:4px solid #28a745;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
        <div style="font-size:14px;color:#666">Credito Totale</div>
        <div style="font-size:28px;font-weight:700;color:#28a745">€<?php echo number_format($total_credit, 2, ',', '.'); ?></div>
      </div>
      <div style="background:#fff;padding:20px;border-left:4px solid #2196F3;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
        <div style="font-size:14px;color:#666">Clienti con Credito</div>
        <div style="font-size:28px;font-weight:700;color:#2196F3"><?php echo $users_with_credit; ?></div>
      </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
      <thead><tr><th>Cliente</th><th>Email</th><th>Saldo</th><th>Azioni</th></tr></thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <?php $balance = pz_get_user_wallet_balance($user->ID); ?>
          <tr>
            <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
            <td><?php echo esc_html($user->user_email); ?></td>
            <td><strong style="color:#28a745">€<?php echo number_format($balance, 2); ?></strong></td>
            <td>
              <button class="button button-primary pz-wallet-btn" data-user-id="<?php echo $user->ID; ?>" data-name="<?php echo esc_attr($user->display_name); ?>" data-balance="<?php echo $balance; ?>">💰 Ricarica</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <div id="pz-wallet-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:999999;align-items:center;justify-content:center">
    <div style="background:#fff;padding:30px;border-radius:12px;width:500px;max-width:90%">
      <h2 id="pz-modal-title">Ricarica Wallet</h2>
      <form method="post">
        <?php wp_nonce_field('pz_wallet_quick_update'); ?>
        <input type="hidden" name="user_id" id="pz-modal-user-id">
        <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px">
          <div style="font-size:14px;color:#666">Saldo:</div>
          <div style="font-size:28px;font-weight:700;color:#28a745" id="pz-modal-balance">€0</div>
        </div>
        <div style="margin-bottom:20px">
          <button type="button" class="button pz-quick-amount" data-amount="20">+€20</button>
          <button type="button" class="button pz-quick-amount" data-amount="50">+€50</button>
          <button type="button" class="button pz-quick-amount" data-amount="100">+€100</button>
          <button type="button" class="button pz-quick-amount" data-amount="200">+€200</button>
        </div>
        <p><input type="number" name="amount" id="pz-modal-amount" step="0.01" style="width:100%;padding:10px" placeholder="+100 o -50" required></p>
        <p><input type="text" name="note" id="pz-modal-note" style="width:100%;padding:10px" placeholder="Nota" required></p>
        <div style="display:flex;gap:10px">
          <button type="button" id="pz-modal-close" class="button" style="flex:1">Annulla</button>
          <button type="submit" name="pz_quick_wallet_update" class="button button-primary" style="flex:1">Conferma</button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
  jQuery(document).ready(function($) {
    $('.pz-wallet-btn').on('click', function() {
      $('#pz-modal-user-id').val($(this).data('user-id'));
      $('#pz-modal-title').text('Ricarica: ' + $(this).data('name'));
      $('#pz-modal-balance').text('€' + parseFloat($(this).data('balance')).toFixed(2));
      $('#pz-wallet-modal').css('display', 'flex');
    });
    $('#pz-modal-close, #pz-wallet-modal').on('click', function(e) {
      if (e.target === this) $('#pz-wallet-modal').hide();
    });
    $('.pz-quick-amount').on('click', function() {
      $('#pz-modal-amount').val('+' + $(this).data('amount'));
    });
  });
  </script>
  <?php
}
