<?php
/**
 * PadelZero - Admin Sincronizzazione & Pulizia Partite
 */

if (!defined('ABSPATH')) exit;

// Aggiunge la voce nel menu admin
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=pz_match',
        'Sincronizzazione Partite',
        '🔄 Sincronizzazione',
        'manage_options',
        'pz-cleanup',
        'pz_cleanup_page'
    );
});

// ============================================================
// FUNZIONE CORE: sincronizzazione completa (specchio Amelia)
// Aggiunge, aggiorna, elimina post pz_match per rispecchiare
// esattamente gli appointment attivi futuri in Amelia.
// ============================================================

function pz_full_sync() {
    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    $k      = pz_meta_keys();
    $today  = date('Y-m-d');

    // 1. Leggi tutti gli appointment futuri attivi da Amelia
    $placeholders = implode(',', array_fill(0, count(PZ_PUBLIC_SERVICE_IDS), '%d'));
    $query = $wpdb->prepare(
        "SELECT * FROM {$prefix}appointments
         WHERE serviceId IN ($placeholders)
         AND DATE(bookingStart) >= %s
         AND status NOT IN ('canceled', 'rejected')
         ORDER BY bookingStart ASC",
        array_merge(PZ_PUBLIC_SERVICE_IDS, [$today])
    );
    $amelia_apts = $wpdb->get_results($query);

    // Mappa apt_id => appointment per lookup veloce
    $amelia_apt_ids = [];
    foreach ($amelia_apts as $apt) {
        $amelia_apt_ids[(int)$apt->id] = $apt;
    }

    // 2. Leggi tutti i post pz_match esistenti
    $existing_posts = get_posts([
        'post_type'      => 'pz_match',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'private'],
    ]);

    $created = 0;
    $updated = 0;
    $deleted = 0;

    // 3. Elimina post che non hanno più un appointment attivo in Amelia
    foreach ($existing_posts as $post) {
        $apt_id = (int)get_post_meta($post->ID, $k['amelia_appointment_id'], true);
        if (!$apt_id || !isset($amelia_apt_ids[$apt_id])) {
            wp_delete_post($post->ID, true);
            $deleted++;
        }
    }

    // 4. Crea o aggiorna post per ogni appointment attivo in Amelia
    foreach ($amelia_apts as $apt) {
        $apt_id     = (int)$apt->id;
        $service_id = (int)$apt->serviceId;

        // Leggi partecipanti live da Amelia
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT cb.*, u.email, u.firstName, u.lastName
             FROM {$prefix}customer_bookings cb
             LEFT JOIN {$prefix}users u ON cb.customerId = u.id
             WHERE cb.appointmentId = %d AND cb.status IN ('approved', 'paid')
             ORDER BY cb.created ASC",
            $apt_id
        ));

        $participants = [];
        if ($bookings) {
            foreach ($bookings as $bk) {
                if (empty($bk->email)) continue;
                $wp_user = get_user_by('email', $bk->email);
                if ($wp_user && user_can($wp_user, 'manage_options')) continue;
                $participants[] = [
                    'email' => $bk->email,
                    'name'  => trim(($bk->firstName ?? '') . ' ' . ($bk->lastName ?? '')),
                ];
            }
        }

        $level_info  = pz_get_level_info($service_id);
        $starts_date = substr($apt->bookingStart, 0, 10);
        $starts_time = substr($apt->bookingStart, 11, 8);

        // Cerca post esistente collegato a questo appointment
        $existing = get_posts([
            'post_type'      => 'pz_match',
            'meta_key'       => $k['amelia_appointment_id'],
            'meta_value'     => $apt_id,
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);

        $post_data = [
            'post_type'   => 'pz_match',
            'post_title'  => 'Partita ' . $level_info['level'] . ' - ' . $starts_date,
            'post_status' => 'publish',
        ];

        if ($existing) {
            $post_data['ID'] = $existing[0]->ID;
            wp_update_post($post_data);
            $post_id = $existing[0]->ID;
            $updated++;
        } else {
            $post_id = wp_insert_post($post_data);
            $created++;
        }

        if (!$post_id || is_wp_error($post_id)) continue;

        update_post_meta($post_id, $k['amelia_appointment_id'], $apt_id);
        update_post_meta($post_id, $k['service_id'],            $service_id);
        update_post_meta($post_id, $k['location_id'],           (int)$apt->locationId);
        update_post_meta($post_id, $k['starts_date'],           $starts_date);
        update_post_meta($post_id, $k['starts_time'],           $starts_time);
        update_post_meta($post_id, $k['level'],                 $level_info['level']);
        update_post_meta($post_id, $k['max_capacity'],          4);
        update_post_meta($post_id, $k['participants'],          $participants);
        update_post_meta($post_id, $k['booked_count'],          count($participants));
    }

    error_log("PZ Sync: creati=$created, aggiornati=$updated, eliminati=$deleted");

    return compact('created', 'updated', 'deleted');
}

// ============================================================
// AZIONE MANUALE: Sincronizza da admin
// ============================================================
add_action('admin_post_pz_do_sync', function() {
    if (!current_user_can('manage_options')) wp_die('Non autorizzato');
    check_admin_referer('pz_sync_action');

    $result = pz_full_sync();

    wp_redirect(admin_url(
        'edit.php?post_type=pz_match&page=pz-cleanup'
        . '&synced_created=' . $result['created']
        . '&synced_updated=' . $result['updated']
        . '&synced_deleted=' . $result['deleted']
    ));
    exit;
});

// ============================================================
// CRON JOB WORDPRESS: hook per esecuzione automatica
// Endpoint: /wp-cron.php oppure WP-CLI
// ============================================================
add_action('pz_cron_sync', function() {
    pz_full_sync();
});

// ============================================================
// ENDPOINT URL per cron esterno (Hestia)
// Chiamata: GET /wp-json/padelzero/v1/sync?secret=CHIAVE
// ============================================================
add_action('rest_api_init', function() {
    register_rest_route('padelzero/v1', '/sync', [
        'methods'             => 'GET',
        'callback'            => function(WP_REST_Request $request) {
            $secret = defined('PZ_CRON_SECRET') ? PZ_CRON_SECRET : '';
            if (!$secret || $request->get_param('secret') !== $secret) {
                return new WP_REST_Response(['error' => 'Non autorizzato'], 403);
            }
            $result = pz_full_sync();
            return new WP_REST_Response([
                'success' => true,
                'result'  => $result,
                'time'    => date('Y-m-d H:i:s'),
            ], 200);
        },
        'permission_callback' => '__return_true',
    ]);
});

// ============================================================
// PAGINA ADMIN
// ============================================================
function pz_cleanup_page() {
    if (!current_user_can('manage_options')) return;

    $k     = pz_meta_keys();
    $today = date('Y-m-d');

    global $wpdb;
    $prefix       = PZ_DB_PREFIX;
    $placeholders = implode(',', array_fill(0, count(PZ_PUBLIC_SERVICE_IDS), '%d'));

    // Appointment attivi futuri in Amelia
    $query = $wpdb->prepare(
        "SELECT a.*, COUNT(cb.id) as booked_count
         FROM {$prefix}appointments a
         LEFT JOIN {$prefix}customer_bookings cb
             ON cb.appointmentId = a.id AND cb.status IN ('approved','paid')
         WHERE a.serviceId IN ($placeholders)
         AND DATE(a.bookingStart) >= %s
         AND a.status NOT IN ('canceled','rejected')
         GROUP BY a.id
         ORDER BY a.bookingStart ASC",
        array_merge(PZ_PUBLIC_SERVICE_IDS, [$today])
    );
    $amelia_apts    = $wpdb->get_results($query);
    $amelia_apt_ids = array_map(fn($a) => (int)$a->id, $amelia_apts);

    // Post pz_match esistenti
    $all_posts = get_posts([
        'post_type'      => 'pz_match',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'private'],
    ]);

    // Post orfani (appointment non più in Amelia)
    $orphan_posts = array_filter($all_posts, function($post) use ($k, $amelia_apt_ids) {
        $apt_id = (int)get_post_meta($post->ID, $k['amelia_appointment_id'], true);
        return !$apt_id || !in_array($apt_id, $amelia_apt_ids);
    });

    $level_colors = [
        'Principiante' => '#00cc44',
        'Intermedio'   => '#ffcc00',
        'Avanzato'     => '#ff7900',
        'Pro'          => '#ff0000',
    ];

    $synced_created = isset($_GET['synced_created']) ? (int)$_GET['synced_created'] : -1;
    $synced_updated = isset($_GET['synced_updated']) ? (int)$_GET['synced_updated'] : -1;
    $synced_deleted = isset($_GET['synced_deleted']) ? (int)$_GET['synced_deleted'] : -1;

    $secret_url = defined('PZ_CRON_SECRET')
        ? rest_url('padelzero/v1/sync') . '?secret=' . PZ_CRON_SECRET
        : rest_url('padelzero/v1/sync') . '?secret=IMPOSTA_PZ_CRON_SECRET_IN_WP_CONFIG';
    ?>
    <div class="wrap">
        <h1>🔄 Sincronizzazione PadelZero ↔ Amelia</h1>

        <?php if ($synced_created >= 0): ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ <strong>Sincronizzazione completata:</strong>
                <?php echo $synced_created; ?> create,
                <?php echo $synced_updated; ?> aggiornate,
                <?php echo $synced_deleted; ?> eliminate.
            </p>
        </div>
        <?php endif; ?>

        <!-- STATO ATTUALE -->
        <div style="display:flex;gap:16px;margin:20px 0;max-width:900px">
            <div style="flex:1;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;text-align:center">
                <div style="font-size:36px;font-weight:700;color:#2196F3"><?php echo count($amelia_apts); ?></div>
                <div style="color:#555">Appointment attivi in Amelia</div>
            </div>
            <div style="flex:1;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;text-align:center">
                <div style="font-size:36px;font-weight:700;color:#4caf50"><?php echo count($all_posts); ?></div>
                <div style="color:#555">Post pz_match nel plugin</div>
            </div>
            <div style="flex:1;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;text-align:center">
                <div style="font-size:36px;font-weight:700;color:<?php echo count($orphan_posts) > 0 ? '#dc3545' : '#4caf50'; ?>"><?php echo count($orphan_posts); ?></div>
                <div style="color:#555">Post orfani da eliminare</div>
            </div>
        </div>

        <!-- PULSANTE SYNC -->
        <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #ddd;max-width:900px;margin-bottom:24px">
            <h2 style="margin-top:0">Sincronizza ora</h2>
            <p style="color:#555">
                Questa operazione <strong>rispecchia esattamente Amelia</strong>: aggiunge i post mancanti,
                aggiorna quelli esistenti ed elimina quelli orfani o delle partite cancellate.
            </p>

            <?php if (!empty($orphan_posts)): ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px;margin-bottom:16px">
                ⚠️ <strong><?php echo count($orphan_posts); ?> post orfani</strong> verranno eliminati:
                <?php foreach ($orphan_posts as $p): ?>
                    <br>→ #<?php echo $p->ID; ?> — <?php echo esc_html($p->post_title); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('pz_sync_action'); ?>
                <input type="hidden" name="action" value="pz_do_sync">
                <button type="submit" class="button button-primary button-large">
                    🔄 Sincronizza adesso
                </button>
            </form>
        </div>

        <!-- TABELLA APPOINTMENT AMELIA -->
        <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #ddd;max-width:900px;margin-bottom:24px">
            <h2 style="margin-top:0">Appointment attivi in Amelia</h2>
            <?php if (empty($amelia_apts)): ?>
                <p style="color:#888">Nessun appointment futuro trovato.</p>
            <?php else: ?>
            <table class="widefat striped" style="font-size:13px">
                <thead>
                    <tr>
                        <th>Apt ID</th>
                        <th>Livello</th>
                        <th>Data & Ora</th>
                        <th>Prenotati</th>
                        <th>Post plugin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($amelia_apts as $apt):
                        $level_info = pz_get_level_info((int)$apt->serviceId);
                        $color      = $level_colors[$level_info['level']] ?? '#ccc';
                        $date_fmt   = date('d/m/Y H:i', strtotime($apt->bookingStart));
                        $existing   = get_posts([
                            'post_type'      => 'pz_match',
                            'meta_key'       => $k['amelia_appointment_id'],
                            'meta_value'     => $apt->id,
                            'posts_per_page' => 1,
                            'post_status'    => 'any',
                            'fields'         => 'ids',
                        ]);
                        $status = $existing
                            ? '<span style="color:#2196F3">● Post #' . $existing[0] . '</span>'
                            : '<span style="color:#4caf50">● Da creare</span>';
                    ?>
                    <tr>
                        <td>#<?php echo $apt->id; ?></td>
                        <td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo $color; ?>;margin-right:4px"></span><?php echo esc_html($level_info['level']); ?></td>
                        <td><?php echo $date_fmt; ?></td>
                        <td><?php echo (int)$apt->booked_count; ?></td>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- SEZIONE CRON -->
        <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #ddd;max-width:900px">
            <h2 style="margin-top:0">⏰ Cron automatico (Hestia)</h2>
            <p style="color:#555">
                Aggiungi questo URL al cron di Hestia per sincronizzare automaticamente ogni ora.
                Prima definisci una chiave segreta in <code>wp-config.php</code>:
            </p>
            <pre style="background:#f4f4f4;padding:12px;border-radius:6px;font-size:13px">define('PZ_CRON_SECRET', 'cambia-questa-chiave-segreta-lunga');</pre>
            <p style="color:#555">L'URL da inserire in Hestia:</p>
            <pre style="background:#f4f4f4;padding:12px;border-radius:6px;font-size:13px;word-break:break-all"><?php echo esc_html($secret_url); ?></pre>
        </div>
    </div>
    <?php
}

