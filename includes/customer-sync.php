<?php
/**
 * PadelZero - Sincronizzazione automatica WP user → Amelia customer
 *
 * Crea automaticamente un record in wp_amelia_users (type='customer')
 * ogni volta che un utente WordPress viene registrato — incluso il
 * caso del login Google tramite addon Elementor.
 *
 * Usa una doppia salvaguardia:
 *   1) hook  user_register   (per registrazioni standard)
 *   2) hook  wp_login        (per primo login, se l'addon ha bypassato il #1)
 *
 * Espone anche  pz_ensure_amelia_customer($user_id)  che gli shortcode
 * possono chiamare difensivamente prima di operare.
 */

if (!defined('ABSPATH')) exit;


/* ============================================================
 *  HOOK 1 — registrazione standard
 * ============================================================ */
add_action('user_register', 'pz_cs_on_user_register', 20, 1);

function pz_cs_on_user_register($user_id) {
    pz_ensure_amelia_customer((int)$user_id);
}


/* ============================================================
 *  HOOK 2 — primo login (catch-all per registrazioni atipiche)
 * ============================================================ */
add_action('wp_login', 'pz_cs_on_wp_login', 20, 2);

function pz_cs_on_wp_login($user_login, $user) {
    if (!$user || !($user instanceof WP_User)) return;
    pz_ensure_amelia_customer((int)$user->ID);
}


/* ============================================================
 *  CORE — assicura che esista un customer Amelia per l'utente WP
 * ============================================================ */
function pz_ensure_amelia_customer($user_id) {
    if (!$user_id) return false;

    $user = get_user_by('id', $user_id);
    if (!$user) return false;

    // Salta amministratori e provider (non sono clienti)
    if (user_can($user, 'manage_options')) return false;

    $email = $user->user_email;
    if (!$email || !is_email($email)) return false;

    global $wpdb;
    $prefix = PZ_DB_PREFIX;

    // Esiste già?
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1",
        $email
    ));
    if ($existing) {
        // Già presente: aggiorna externalId se mancante
        $external = $wpdb->get_var($wpdb->prepare(
            "SELECT externalId FROM {$prefix}users WHERE id = %d",
            (int)$existing
        ));
        if (!$external) {
            $wpdb->update(
                "{$prefix}users",
                ['externalId' => $user_id],
                ['id'         => (int)$existing]
            );
        }
        return (int)$existing;
    }

    // Estrai nome / cognome con fallback
    $first = trim($user->first_name);
    $last  = trim($user->last_name);

    if (!$first && !$last) {
        // Prova dal display_name (es. "Mario Rossi")
        $parts = preg_split('/\s+/', trim($user->display_name), 2);
        $first = $parts[0] ?? $user->user_login;
        $last  = $parts[1] ?? '';
    }
    if (!$first) $first = $user->user_login;

    // Crea customer Amelia
    $ok = $wpdb->insert("{$prefix}users", [
        'type'       => 'customer',
        'firstName'  => $first,
        'lastName'   => $last,
        'email'      => $email,
        'externalId' => $user_id,
    ]);

    if (!$ok) {
        error_log('PZ Customer Sync: errore inserimento Amelia user per WP #' . $user_id . ' — ' . $wpdb->last_error);
        return false;
    }

    $customer_id = $wpdb->insert_id;
    error_log('PZ Customer Sync: creato Amelia customer #' . $customer_id . ' per WP user #' . $user_id . ' (' . $email . ')');

    return $customer_id;
}


/* ============================================================
 *  ADMIN UTILITY — bottone "Sincronizza tutti gli utenti WP esistenti"
 *  (utile una tantum, dopo l'installazione)
 * ============================================================ */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=pz_match',
        'Sincronizza Utenti',
        '👥 Sincronizza Utenti',
        'manage_options',
        'pz-sync-customers',
        'pz_cs_admin_page'
    );
});

add_action('admin_post_pz_sync_all_customers', function() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato');
    check_admin_referer('pz_sync_all_customers');

    $users   = get_users(['fields' => ['ID']]);
    $created = 0;
    $skipped = 0;

    foreach ($users as $u) {
        $r = pz_ensure_amelia_customer((int)$u->ID);
        if ($r === false) $skipped++;
        else $created++;  // sia esistente sia creato
    }

    wp_redirect(admin_url('edit.php?post_type=pz_match&page=pz-sync-customers&done=' . (int)$created . '&skip=' . (int)$skipped));
    exit;
});

function pz_cs_admin_page() {
    if (!current_user_can('manage_options')) return;
    $done = isset($_GET['done']) ? (int)$_GET['done'] : -1;
    $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
    ?>
    <div class="wrap">
      <h1>👥 Sincronizza Utenti WP → Amelia</h1>
      <?php if ($done >= 0): ?>
      <div class="notice notice-success is-dismissible">
        <p>✅ Sincronizzazione completata: <strong><?php echo $done; ?></strong> utenti processati,
           <strong><?php echo $skip; ?></strong> saltati (admin/email mancante).</p>
      </div>
      <?php endif; ?>
      <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #ddd;max-width:900px">
        <p>Questa azione scorre tutti gli utenti WordPress e si assicura che ognuno abbia un record cliente in Amelia.
           Eseguila <strong>una volta</strong> dopo aver installato il plugin, poi i nuovi utenti saranno sincronizzati automaticamente alla registrazione.</p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
          <?php wp_nonce_field('pz_sync_all_customers'); ?>
          <input type="hidden" name="action" value="pz_sync_all_customers">
          <button type="submit" class="button button-primary button-large">🔄 Sincronizza ora</button>
        </form>
      </div>
    </div>
    <?php
}
