<?php
/**
 * PadelZero - Shortcodes
 * v4 — design allineato al wizard
 */

if (!defined('ABSPATH')) exit;

/** =========================
 *  SHORTCODE: [pz_wallet_balance]
 *  ========================= */
add_shortcode('pz_wallet_balance', function() {
    if (!is_user_logged_in()) {
        return pz_render_login_wall(
            '💰',
            'Il tuo borsellino',
            'Accedi per visualizzare il tuo saldo e la cronologia dei movimenti.',
            '/inizio/login/'
        );
    }

    $user_id  = get_current_user_id();
    $balance  = pz_get_user_wallet_balance($user_id);

    global $wpdb;
    $table        = $wpdb->prefix . 'pz_wallet_transactions';
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
        $user_id
    ));

    ob_start(); ?>
    <style>
    #pzWalletWrap,#pzWalletWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzWalletWrap{max-width:480px !important;margin:0 auto !important;color:#161B2E !important;}
    .pz-wallet-hero{
        background:linear-gradient(135deg,#1FB856 0%,#0d7a38 100%) !important;
        color:#fff !important;padding:28px 24px !important;border-radius:20px !important;
        text-align:center !important;margin-bottom:20px !important;
        box-shadow:0 8px 30px -12px rgba(31,184,86,.35) !important;
    }
    .pz-wallet-hero-label{font-size:13px !important;font-weight:600 !important;letter-spacing:.06em !important;text-transform:uppercase !important;opacity:.8 !important;margin:0 0 6px !important;}
    .pz-wallet-hero-amount{font-size:48px !important;font-weight:800 !important;letter-spacing:-0.03em !important;margin:0 !important;line-height:1 !important;}
    .pz-wallet-card{
        background:#FFFFFF !important;border-radius:20px !important;padding:20px !important;
        box-shadow:0 8px 30px -12px rgba(22,27,46,.10) !important;
    }
    .pz-wallet-card-title{font-size:15px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 14px !important;}
    .pz-wallet-tx{display:flex !important;justify-content:space-between !important;align-items:center !important;padding:12px 0 !important;border-bottom:1px solid #F0F2F5 !important;}
    .pz-wallet-tx:last-child{border-bottom:none !important;}
    .pz-wallet-tx-date{font-size:12px !important;color:#8B92A5 !important;margin:0 0 2px !important;}
    .pz-wallet-tx-note{font-size:13px !important;color:#161B2E !important;font-weight:500 !important;margin:0 !important;}
    .pz-wallet-tx-amount{font-size:15px !important;font-weight:700 !important;white-space:nowrap !important;}
    </style>

    <div id="pzWalletWrap">
        <div class="pz-wallet-hero">
            <p class="pz-wallet-hero-label">Il tuo borsellino</p>
            <p class="pz-wallet-hero-amount">€<?php echo number_format($balance, 2, ',', '.'); ?></p>
        </div>
        <?php if ($transactions): ?>
        <div class="pz-wallet-card">
            <p class="pz-wallet-card-title">Ultimi movimenti</p>
            <?php foreach ($transactions as $t): ?>
            <div class="pz-wallet-tx">
                <div>
                    <p class="pz-wallet-tx-date"><?php echo date('d/m/Y H:i', strtotime($t->created_at)); ?></p>
                    <p class="pz-wallet-tx-note"><?php echo esc_html($t->note); ?></p>
                </div>
                <span class="pz-wallet-tx-amount" style="color:<?php echo $t->amount >= 0 ? '#1FB856' : '#E53E3E'; ?> !important">
                    <?php echo $t->amount > 0 ? '+' : ''; ?>€<?php echo number_format($t->amount, 2, ',', '.'); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});


/** =========================
 *  SHORTCODE: [pzlobby]
 *  ========================= */
add_shortcode('pzlobby', function($atts) {

    $atts  = shortcode_atts(['limit' => 50], (array)$atts);
    $limit = max(1, (int)$atts['limit']);

    $k     = pz_meta_keys();
    $level = isset($_GET['pzlevel']) ? sanitize_text_field($_GET['pzlevel']) : '';
    $when  = isset($_GET['pzwhen'])  ? sanitize_text_field($_GET['pzwhen'])  : '';

    // ── Query partite ────────────────────────────────────────────────────────
    $args = [
        'post_type'      => 'pz_match',
        'posts_per_page' => $limit,
        'post_status'    => 'publish',
        'orderby'        => 'meta_value',
        'meta_key'       => $k['starts_date'],
        'order'          => 'ASC',
    ];

    $q     = new WP_Query($args);
    $items = [];
    $mesi  = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $id = get_the_ID();

            $serviceId  = (int)get_post_meta($id, $k['service_id'],    true);
            $locationId = (int)get_post_meta($id, $k['location_id'],   true);
            $startsDate = (string)get_post_meta($id, $k['starts_date'], true);
            $startsTime = (string)get_post_meta($id, $k['starts_time'], true);
            $lvl        = (string)get_post_meta($id, $k['level'],       true);
            $max        = (int)get_post_meta($id,   $k['max_capacity'], true);
            $booked     = (int)get_post_meta($id,   $k['booked_count'], true);

            if ($serviceId && !in_array($serviceId, PZ_PUBLIC_SERVICE_IDS, true)) continue;
            if ($level !== '' && $lvl !== $level) continue;

            $dt   = pz_dt_from_meta($startsDate, $startsTime);
            if (!$dt) continue;

            $now0 = pz_start_of_today();
            $pass = true;

            if ($when === '' || $when === 'tutte') {
                $pass = ($dt >= $now0);
            } elseif ($when === 'oggi') {
                $pass = ($dt >= $now0 && $dt <= pz_end_of_day(clone $now0));
            } elseif ($when === 'domani') {
                $d1 = clone $now0; $d1->modify('+1 day');
                $pass = ($dt >= $d1 && $dt <= pz_end_of_day($d1));
            } elseif ($when === 'weekend') {
                $pass = pz_is_weekend($dt) && ($dt >= $now0);
            } elseif ($when === '7') {
                $d7 = clone $now0; $d7->modify('+7 day'); $d7 = pz_end_of_day($d7);
                $pass = ($dt >= $now0 && $dt <= $d7);
            } elseif ($when === '14') {
                $d14 = clone $now0; $d14->modify('+14 day'); $d14 = pz_end_of_day($d14);
                $pass = ($dt >= $now0 && $dt <= $d14);
            } elseif ($when === '30') {
                $d30 = clone $now0; $d30->modify('+30 day'); $d30 = pz_end_of_day($d30);
                $pass = ($dt >= $now0 && $dt <= $d30);
            }

            if (!$pass) continue;

            // Partecipanti live da Amelia
            global $wpdb;
            $prefix        = defined('PZ_DB_PREFIX') ? PZ_DB_PREFIX : $wpdb->prefix;
            $amelia_apt_id = (int)get_post_meta($id, $k['amelia_appointment_id'], true);
            $participants  = [];

            if ($amelia_apt_id) {
                $bookings = $wpdb->get_results($wpdb->prepare(
                    "SELECT cb.*, u.email, u.firstName, u.lastName
                     FROM {$prefix}customer_bookings cb
                     LEFT JOIN {$prefix}users u ON cb.customerId = u.id
                     WHERE cb.appointmentId = %d AND cb.status IN ('approved','paid')
                     ORDER BY cb.created ASC",
                    $amelia_apt_id
                ));
                if ($bookings) {
                    foreach ($bookings as $bk) {
                        if (empty($bk->email)) continue;
                        $user = get_user_by('email', $bk->email);
                        if ($user && user_can($user, 'manage_options')) continue;
                        $participants[] = [
                            'email'   => $bk->email,
                            'name'    => trim(($bk->firstName ?? '') . ' ' . ($bk->lastName ?? '')),
                            'wp_id'   => $user ? $user->ID : 0,
                        ];
                    }
                }
            }

            $level_info = pz_get_level_info($serviceId);

            $items[] = [
                'id'           => $id,
                'dt'           => $dt,
                'starts_date'  => $startsDate,
                'starts_time'  => $startsTime,
                'level'        => $lvl,
                'level_color'  => $level_info['color'],
                'service_id'   => $serviceId,
                'max'          => $max,
                'booked'       => $booked,
                'participants' => $participants,
            ];
        }
        wp_reset_postdata();
    }

    // ── Dati filtri ──────────────────────────────────────────────────────────
    $level_display = [
        'Principiante' => '1.0 – 2.5',
        'Intermedio'   => '3.0 – 3.5',
        'Avanzato'     => '4.0+',
        'Pro'          => '4.0+',
    ];

    $levels = [
        ''             => 'Tutti i livelli',
        'Principiante' => '1.0 – 2.5',
        'Intermedio'   => '3.0 – 3.5',
        'Avanzato'     => '4.0+',
    ];
    $whens = [
        ''       => 'Qualsiasi data',
        'tutte'  => 'Tutte',
        'oggi'   => 'Oggi',
        'domani' => 'Domani',
        'weekend'=> 'Weekend',
        '7'      => '7 giorni',
        '14'     => '14 giorni',
        '30'     => '30 giorni',
    ];
    $level_colors = [
        ''             => '#8B92A5',
        'Principiante' => '#00cc44',
        'Intermedio'   => '#ffcc00',
        'Avanzato'     => '#ff7900',
        'Pro'          => '#ff7900',
    ];

    $user_wallet_balance = is_user_logged_in() ? pz_get_user_wallet_balance(get_current_user_id()) : 0;

    // ── Prezzi servizi (cache locale) ────────────────────────────────────────
    global $wpdb;
    $prefix         = defined('PZ_DB_PREFIX') ? PZ_DB_PREFIX : $wpdb->prefix;
    $service_prices = [];

    ob_start();
    ?>

    <style>
    /* ===== PZ Lobby — CSS blindato v4 ===== */
    #pzLobbyWrap,#pzLobbyWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzLobbyWrap{max-width:640px !important;margin:0 auto !important;padding:0 0 80px !important;color:#161B2E !important;background:transparent !important;}

    /* Filtri */
    .pz-lb-filters{display:flex !important;gap:10px !important;flex-wrap:wrap !important;margin-bottom:24px !important;align-items:stretch !important;}
    .pz-lb-filters form{display:flex !important;gap:10px !important;flex-wrap:wrap !important;align-items:stretch !important;width:100% !important;}

    /* Custom select */
    .pz-lb-select{position:relative !important;flex:1 !important;min-width:0 !important;width:100% !important;}
    .pz-lb-placeholder{color:#8B92A5 !important;}
    .pz-lb-select-trigger{
        display:flex !important;align-items:center !important;gap:8px !important;
        padding:11px 32px 11px 14px !important;
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:12px !important;
        cursor:pointer !important;font-size:14px !important;font-weight:500 !important;color:#161B2E !important;
        user-select:none !important;position:relative !important;
        transition:border-color .15s ease !important;
        overflow:hidden !important;width:100% !important;
    }
    .pz-lb-sel-text{
        overflow:hidden !important;text-overflow:ellipsis !important;
        white-space:nowrap !important;min-width:0 !important;flex:1 !important;
    }
    .pz-lb-select-trigger::after{content:"▾" !important;position:absolute !important;right:13px !important;font-size:13px !important;color:#8B92A5 !important;line-height:1 !important;}
    .pz-lb-select-trigger:hover{border-color:#161B2E !important;}
    .pz-lb-select.open .pz-lb-select-trigger{border-color:#161B2E !important;}
    .pz-lb-select-options{
        position:absolute !important;top:calc(100% + 6px) !important;left:0 !important;right:0 !important;
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:12px !important;
        padding:6px !important;box-shadow:0 8px 24px -8px rgba(22,27,46,.18) !important;
        z-index:1000 !important;display:none !important;max-height:260px !important;overflow-y:auto !important;
    }
    .pz-lb-select.open .pz-lb-select-options{display:block !important;}
    .pz-lb-select-opt{
        display:flex !important;align-items:center !important;gap:10px !important;
        padding:10px 12px !important;border-radius:8px !important;
        font-size:14px !important;cursor:pointer !important;color:#161B2E !important;
        transition:background .12s ease !important;
    }
    .pz-lb-select-opt:hover{background:#F4F5F8 !important;}
    .pz-lb-select-opt.selected{background:#E8F8EE !important;font-weight:600 !important;}

    /* Bottone cerca */
    .pz-lb-search-btn{
        padding:0 20px !important;background:#161B2E !important;color:#FFFFFF !important;
        border:none !important;border-radius:12px !important;font-size:14px !important;font-weight:600 !important;
        cursor:pointer !important;display:flex !important;align-items:center !important;gap:8px !important;
        white-space:nowrap !important;transition:background .15s ease !important;flex-shrink:0 !important;
        align-self:stretch !important;
    }
    .pz-lb-search-btn:hover{background:#2d3748 !important;}
    .pz-lb-search-btn svg{stroke:#fff !important;fill:none !important;flex-shrink:0 !important;}

    /* Level dot */
    .pz-lb-dot{width:10px !important;height:10px !important;border-radius:50% !important;flex-shrink:0 !important;display:inline-block !important;}

    /* Card partita */
    .pz-lb-list{display:flex !important;flex-direction:column !important;gap:12px !important;}
    .pz-lb-card{
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:20px !important;
        padding:18px !important;display:flex !important;align-items:stretch !important;gap:16px !important;
        transition:border-color .18s ease,transform .18s ease !important;
    }
    .pz-lb-card:hover{border-color:#B0B6C3 !important;transform:translateY(-1px) !important;}

    /* Colonna sinistra: avatars uno sotto l'altro sovrapposti */
    .pz-lb-avatars-col{
        display:flex !important;flex-direction:column !important;
        flex-shrink:0 !important;justify-content:center !important;
    }
    .pz-lb-avatar-stack{
        width:44px !important;height:44px !important;border-radius:50% !important;
        object-fit:cover !important;border:2px solid #fff !important;
        box-shadow:0 0 0 1.5px #D9DCE3 !important;display:block !important;
        margin-top:-10px !important;background:#F4F5F8 !important;
    }
    .pz-lb-avatar-stack:first-child{margin-top:0 !important;}
    .pz-lb-avatar-empty{
        width:44px !important;height:44px !important;border-radius:50% !important;
        background:#F4F5F8 !important;border:1.5px dashed #D9DCE3 !important;
        display:block !important;flex-shrink:0 !important;
        margin-top:-10px !important;
    }
    .pz-lb-avatar-empty:first-child{margin-top:0 !important;}

    /* Colonna destra */
    .pz-lb-card-body{flex:1 !important;min-width:0 !important;display:flex !important;flex-direction:column !important;gap:5px !important;}
    .pz-lb-card-title{
        font-size:15px !important;font-weight:700 !important;color:#161B2E !important;
        margin:0 0 4px !important;letter-spacing:-0.01em !important;
        display:flex !important;align-items:center !important;gap:7px !important;
        text-transform:none !important;background:transparent !important;
    }
    .pz-lb-meta-item{
        display:flex !important;align-items:center !important;gap:6px !important;
        font-size:13px !important;color:#8B92A5 !important;font-weight:500 !important;
        margin:0 !important;
    }
    .pz-lb-meta-item svg{stroke:#8B92A5 !important;fill:none !important;flex-shrink:0 !important;}
    .pz-lb-price{
        font-size:13px !important;font-weight:700 !important;color:#1FB856 !important;
        background:#E8F8EE !important;padding:2px 8px !important;border-radius:6px !important;
        display:inline-block !important;align-self:flex-start !important;margin-top:2px !important;
    }

    /* Action dentro card-body */
    .pz-lb-action{margin-top:auto !important;padding-top:10px !important;}

    /* Bottoni azione */
    .pz-lb-btn-join{
        padding:11px 22px !important;background:#9FD731 !important;color:#fff !important;
        border:none !important;border-radius:12px !important;cursor:pointer !important;
        font-weight:700 !important;font-size:13px !important;letter-spacing:.04em !important;
        text-transform:uppercase !important;white-space:nowrap !important;
        transition:background .15s ease,transform .15s ease !important;
        box-shadow:0 4px 12px -4px rgba(159,215,49,.45) !important;
    }
    .pz-lb-btn-join:hover{background:#8BC41F !important;transform:translateY(-1px) !important;}
    .pz-lb-btn-login{
        padding:11px 22px !important;background:#9FD731 !important;color:#fff !important;
        border:none !important;border-radius:12px !important;cursor:pointer !important;
        font-weight:700 !important;font-size:13px !important;letter-spacing:.04em !important;
        text-transform:uppercase !important;white-space:nowrap !important;
        text-decoration:none !important;display:inline-block !important;
        transition:background .15s ease !important;
    }
    .pz-lb-btn-login:hover{background:#8BC41F !important;text-decoration:none !important;}
    .pz-lb-btn-full{
        padding:11px 22px !important;background:#F4F5F8 !important;color:#8B92A5 !important;
        border:none !important;border-radius:12px !important;
        font-weight:700 !important;font-size:13px !important;letter-spacing:.04em !important;
        text-transform:uppercase !important;white-space:nowrap !important;cursor:not-allowed !important;
    }

    /* Empty state */
    .pz-lb-empty{
        padding:40px 24px !important;text-align:center !important;
        background:#FFFFFF !important;border-radius:20px !important;
        border:1.5px dashed #D9DCE3 !important;color:#8B92A5 !important;font-size:15px !important;
    }

    /* Modal */
    #pzLbModal{
        display:none !important;position:fixed !important;inset:0 !important;
        background:rgba(22,27,46,.6) !important;z-index:999999 !important;
        align-items:flex-end !important;justify-content:center !important;
        backdrop-filter:blur(4px) !important;
    }
    #pzLbModal.is-open{display:flex !important;}
    .pz-lb-modal-inner{
        background:#FFFFFF !important;border-radius:24px 24px 0 0 !important;
        padding:28px 24px 40px !important;width:100% !important;max-width:520px !important;
        animation:pzLbSlideUp .3s ease both !important;
    }
    @keyframes pzLbSlideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .pz-lb-modal-handle{
        width:40px !important;height:4px !important;background:#D9DCE3 !important;
        border-radius:2px !important;margin:0 auto 20px !important;
    }
    .pz-lb-modal-title{font-size:18px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 6px !important;}
    .pz-lb-modal-sub{font-size:13px !important;color:#8B92A5 !important;margin:0 0 20px !important;}

    /* Opzioni pagamento nel modal */
    .pz-lb-pay-opt{
        display:flex !important;align-items:center !important;gap:14px !important;
        border:1.5px solid #D9DCE3 !important;border-radius:14px !important;
        padding:14px 16px !important;margin-bottom:10px !important;cursor:pointer !important;
        transition:border-color .15s ease,background .15s ease !important;
        background:#fff !important;
    }
    .pz-lb-pay-opt:hover{border-color:#9FD731 !important;background:#FAFFF4 !important;}
    .pz-lb-pay-opt.selected{border-color:#9FD731 !important;background:#FAFFF4 !important;}
    .pz-lb-pay-opt.disabled{opacity:.4 !important;cursor:not-allowed !important;pointer-events:none !important;}
    .pz-lb-pay-icon{
        width:44px !important;height:44px !important;border-radius:12px !important;
        background:#E8F8EE !important;display:flex !important;align-items:center !important;
        justify-content:center !important;flex-shrink:0 !important;
    }
    .pz-lb-pay-icon svg{stroke:#1FB856 !important;fill:none !important;width:22px !important;height:22px !important;}
    .pz-lb-pay-body{flex:1 !important;}
    .pz-lb-pay-label{font-size:14px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 2px !important;}
    .pz-lb-pay-desc{font-size:12px !important;color:#8B92A5 !important;margin:0 !important;}
    .pz-lb-pay-check{
        width:20px !important;height:20px !important;border-radius:50% !important;
        border:1.5px solid #D9DCE3 !important;flex-shrink:0 !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        transition:border-color .15s,background .15s !important;
    }
    .pz-lb-pay-opt.selected .pz-lb-pay-check{border-color:#9FD731 !important;background:#9FD731 !important;}
    .pz-lb-pay-check::after{content:"" !important;width:8px !important;height:8px !important;border-radius:50% !important;background:#fff !important;display:none !important;}
    .pz-lb-pay-opt.selected .pz-lb-pay-check::after{display:block !important;}

    /* Bottoni modal */
    .pz-lb-modal-actions{display:flex !important;gap:10px !important;margin-top:20px !important;}
    .pz-lb-modal-cancel{
        flex:1 !important;padding:14px !important;background:#F4F5F8 !important;color:#161B2E !important;
        border:none !important;border-radius:12px !important;font-weight:600 !important;font-size:14px !important;cursor:pointer !important;
        transition:background .15s !important;
    }
    .pz-lb-modal-cancel:hover{background:#E8E9EC !important;}
    .pz-lb-modal-confirm{
        flex:2 !important;padding:14px !important;background:#9FD731 !important;color:#fff !important;
        border:none !important;border-radius:12px !important;font-weight:700 !important;font-size:14px !important;cursor:pointer !important;
        box-shadow:0 4px 12px -4px rgba(159,215,49,.5) !important;
        transition:background .15s,opacity .15s !important;
    }
    .pz-lb-modal-confirm:hover{background:#8BC41F !important;}
    .pz-lb-modal-confirm:disabled{opacity:.5 !important;cursor:not-allowed !important;}

    /* Responsive */
    @media (max-width:600px){
        .pz-lb-btn-join,.pz-lb-btn-full,.pz-lb-btn-login{width:100% !important;text-align:center !important;}
        .pz-lb-select{min-width:0 !important;}
        .pz-lb-modal-inner{border-radius:24px 24px 0 0 !important;}
    }
    /* Header */
    .pz-lb-header{display:flex !important;align-items:center !important;position:relative !important;min-height:44px !important;margin-bottom:6px !important;}
    .pz-lb-back{
        width:44px !important;height:44px !important;background:#FFFFFF !important;
        border:1.5px solid #D9DCE3 !important;border-radius:50% !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        cursor:pointer !important;padding:0 !important;flex-shrink:0 !important;
        position:relative !important;z-index:1 !important;text-decoration:none !important;
        transition:background .15s ease,border-color .15s ease !important;
    }
    .pz-lb-back svg{stroke:#8B92A5 !important;fill:none !important;width:18px !important;height:18px !important;}
    .pz-lb-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-lb-title{
        position:absolute !important;left:0 !important;right:0 !important;
        font-size:19px !important;font-weight:700 !important;letter-spacing:-0.02em !important;
        text-align:center !important;pointer-events:none !important;margin:0 !important;
        color:#161B2E !important;background:transparent !important;text-transform:none !important;
    }
    .pz-lb-sub{
        font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;
        margin:0 0 22px !important;padding:0 !important;
        background:transparent !important;text-transform:none !important;
    }
    </style>

    <div id="pzLobbyWrap">

        <!-- HEADER -->
        <div class="pz-lb-header">
            <a href="javascript:history.back()" class="pz-lb-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pz-lb-title">Partite pubbliche</p>
        </div>
        <p class="pz-lb-sub">Trova una partita del tuo livello e unisciti.</p>

        <!-- FILTRI -->
        <div class="pz-lb-filters">
            <form method="get" id="pzLbForm">
                <?php foreach ($_GET as $key => $val): ?>
                    <?php if (in_array($key, ['pzlevel','pzwhen'], true) || is_array($val)) continue; ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>">
                <?php endforeach; ?>

                <!-- Select livello -->
                <?php
                $sel_level_label = ($level && isset($levels[$level])) ? $levels[$level] : 'Livello';
                $sel_level_color = isset($level_colors[$level]) ? $level_colors[$level] : '#8B92A5';
                $show_dot        = ($level !== '' && isset($level_colors[$level]) && $level_colors[$level] !== '#8B92A5');
                ?>
                <div class="pz-lb-select" data-name="pzlevel">
                    <div class="pz-lb-select-trigger">
                        <?php if ($show_dot): ?>
                            <span class="pz-lb-dot" style="background:<?php echo esc_attr($sel_level_color); ?>;flex-shrink:0"></span>
                        <?php endif; ?>
                        <span class="pz-lb-sel-text<?php echo !$level ? ' pz-lb-placeholder' : ''; ?>"><?php echo esc_html($sel_level_label); ?></span>
                    </div>
                    <div class="pz-lb-select-options">
                        <?php foreach ($levels as $v => $label):
                            $color = isset($level_colors[$v]) ? $level_colors[$v] : '#8B92A5';
                            $sel   = ($v === $level) ? ' selected' : '';
                        ?>
                        <div class="pz-lb-select-opt<?php echo $sel; ?>" data-value="<?php echo esc_attr($v); ?>">
                            <?php if ($v !== ''): ?>
                                <span class="pz-lb-dot" style="background:<?php echo esc_attr($color); ?>"></span>
                            <?php endif; ?>
                            <span><?php echo esc_html($label); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="pzlevel" id="pzLbLevelInput" value="<?php echo esc_attr($level); ?>">

                <!-- Select quando -->
                <?php
                $sel_when_label = ($when && isset($whens[$when])) ? $whens[$when] : 'Data';
                ?>
                <div class="pz-lb-select" data-name="pzwhen">
                    <div class="pz-lb-select-trigger">
                        <span class="pz-lb-sel-text<?php echo !$when ? ' pz-lb-placeholder' : ''; ?>"><?php echo esc_html($sel_when_label); ?></span>
                    </div>
                    <div class="pz-lb-select-options">
                        <?php foreach ($whens as $v => $label):
                            $sel = ($v === $when) ? ' selected' : '';
                        ?>
                        <div class="pz-lb-select-opt<?php echo $sel; ?>" data-value="<?php echo esc_attr($v); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="pzwhen" id="pzLbWhenInput" value="<?php echo esc_attr($when); ?>">

                <button type="submit" class="pz-lb-search-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Cerca
                </button>
            </form>
        </div>

        <!-- LISTA PARTITE -->
        <?php if (empty($items)): ?>
        <div class="pz-lb-empty">
            <p style="margin:0 0 6px;font-size:32px">🎾</p>
            <p style="margin:0;font-weight:600;color:#161B2E">Nessuna partita trovata</p>
            <p style="margin:6px 0 0">Prova a cambiare i filtri o torna più tardi.</p>
        </div>
        <?php else: ?>
        <div class="pz-lb-list">
            <?php foreach ($items as $it):
                $dt         = $it['dt'];
                $dateLabel  = $dt ? $dt->format('d') . ' ' . $mesi[(int)$dt->format('n')] : $it['starts_date'];
                $timeLabel  = $dt ? $dt->format('H:i') : substr($it['starts_time'], 0, 5);
                $levelColor = $it['level_color'];
                $levelKey   = $it['level'] ?: '';
                $levelLabel = isset($level_display[$levelKey]) ? $level_display[$levelKey] : ($levelKey ?: '–');
                $isFull     = ((int)$it['booked'] >= (int)$it['max']);
                $free       = max(0, (int)$it['max'] - (int)$it['booked']);

                // Prezzo
                $sid = $it['service_id'];
                if (!isset($service_prices[$sid])) {
                    $service_prices[$sid] = (float)$wpdb->get_var(
                        $wpdb->prepare("SELECT price FROM {$prefix}services WHERE id = %d", $sid)
                    );
                }
                $price = (float)$service_prices[$sid];

                $giorni_short = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
                $dayLabel = $dt ? $giorni_short[(int)$dt->format('w')] : '';
            ?>
            <div class="pz-lb-card">

                <!-- Colonna sinistra: avatars -->
                <div class="pz-lb-avatars-col">
                    <?php
                    $participants = $it['participants'];
                    $max_slots    = (int)$it['max'];
                    for ($s = 0; $s < $max_slots; $s++):
                        if (isset($participants[$s])) :
                            $p      = $participants[$s];
                            $email  = is_array($p) ? ($p['email'] ?? '') : $p;
                            $pname  = is_array($p) ? ($p['name']  ?? '') : $p;
                            $wp_id  = is_array($p) ? ($p['wp_id'] ?? 0)  : 0;

                            // Usa pz_get_user_avatar_url se abbiamo un wp_id, altrimenti SVG placeholder
                            if ($wp_id) {
                                $av = pz_get_user_avatar_url($wp_id, 56);
                            } else {
                                $av = '';
                            }

                            // Fallback: SVG con iniziale
                            if (!$av) {
                                $initial = strtoupper(substr(trim($pname) ?: $email, 0, 1)) ?: '?';
                                $av = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(
                                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56">'
                                    . '<rect fill="#E8F8EE" width="56" height="56" rx="28"/>'
                                    . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#1FB856" font-size="22" font-family="Arial">'
                                    . htmlspecialchars($initial)
                                    . '</text></svg>'
                                );
                            }
                    ?>
                            <img class="pz-lb-avatar-stack" src="<?php echo esc_url($av); ?>" title="<?php echo esc_attr($pname ?: $email); ?>" alt="" loading="lazy">
                    <?php else: ?>
                            <span class="pz-lb-avatar-empty" title="Posto libero"></span>
                    <?php endif; endfor; ?>
                </div>

                <!-- Colonna destra: dati + azione -->
                <div class="pz-lb-card-body">
                    <p class="pz-lb-card-title">
                        <span class="pz-lb-dot" style="background:<?php echo esc_attr($levelColor); ?>"></span>
                        <?php echo esc_html($levelLabel); ?>
                    </p>

                    <p class="pz-lb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_html($dayLabel . ' ' . $dateLabel); ?>
                    </p>
                    <p class="pz-lb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo esc_html($timeLabel); ?>
                    </p>
                    <p class="pz-lb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo $isFull ? 'Al completo' : $free . ' ' . ($free === 1 ? 'posto libero' : 'posti liberi'); ?>
                    </p>
                    <?php if ($price > 0): ?>
                    <p class="pz-lb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 9h1a2 2 0 0 1 0 4H9v2h3a2 2 0 0 1 0 4"/><line x1="12" y1="6" x2="12" y2="8"/><line x1="12" y1="18" x2="12" y2="20"/></svg>
                        €<?php echo number_format($price, 2, ',', '.'); ?>
                    </p>
                    <?php endif; ?>

                    <!-- Azione -->
                    <div class="pz-lb-action">
                        <?php if ($isFull): ?>
                            <button class="pz-lb-btn-full" disabled>Completo</button>
                        <?php elseif (is_user_logged_in()): ?>
                            <button class="pz-lb-btn-join"
                                data-post-id="<?php echo (int)$it['id']; ?>"
                                data-price="<?php echo esc_attr($price); ?>"
                                data-level="<?php echo esc_attr($levelLabel); ?>"
                                data-date="<?php echo esc_attr($dayLabel . ' ' . $dateLabel . ' ' . $timeLabel); ?>">
                                Partecipa
                            </button>
                        <?php else: ?>
                            <a href="<?php echo esc_url(home_url('/inizio/login/')); ?>" class="pz-lb-btn-login">Accedi</a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- MODAL PAGAMENTO (bottom sheet) -->
    <div id="pzLbModal">
        <div class="pz-lb-modal-inner">
            <div class="pz-lb-modal-handle"></div>
            <p class="pz-lb-modal-title" id="pzLbModalTitle">Conferma partecipazione</p>
            <p class="pz-lb-modal-sub" id="pzLbModalSub">Scegli come vuoi pagare per unirti alla partita.</p>

            <!-- Wallet -->
            <div class="pz-lb-pay-opt<?php echo $user_wallet_balance <= 0 ? ' disabled' : ''; ?>" data-method="wallet" id="pzLbOptWallet">
                <span class="pz-lb-pay-icon">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0"/><path d="M22 9H2"/></svg>
                </span>
                <span class="pz-lb-pay-body">
                    <p class="pz-lb-pay-label">Borsellino</p>
                    <p class="pz-lb-pay-desc">Saldo: €<?php echo number_format((float)$user_wallet_balance, 2, ',', '.'); ?></p>
                </span>
                <span class="pz-lb-pay-check"></span>
            </div>

            <!-- In loco (pre-selezionato) -->
            <div class="pz-lb-pay-opt selected" data-method="onsite" id="pzLbOptOnsite">
                <span class="pz-lb-pay-icon">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                <span class="pz-lb-pay-body">
                    <p class="pz-lb-pay-label">Paga in loco</p>
                    <p class="pz-lb-pay-desc">Paghi direttamente alla struttura</p>
                </span>
                <span class="pz-lb-pay-check"></span>
            </div>

            <!-- Online -->
            <div class="pz-lb-pay-opt" data-method="amelia" id="pzLbOptAmelia">
                <span class="pz-lb-pay-icon">
                    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </span>
                <span class="pz-lb-pay-body">
                    <p class="pz-lb-pay-label">Paga online</p>
                    <p class="pz-lb-pay-desc">Carta, PayPal e altri metodi</p>
                </span>
                <span class="pz-lb-pay-check"></span>
            </div>

            <div class="pz-lb-modal-actions">
                <button class="pz-lb-modal-cancel" id="pzLbModalCancel">Annulla</button>
                <button class="pz-lb-modal-confirm" id="pzLbModalConfirm">Conferma</button>
            </div>
        </div>
    </div>

    <script>
    (function($){
        var AJAX          = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var NONCE         = '<?php echo esc_js(wp_create_nonce('pz_join_match')); ?>';
        var walletBalance = <?php echo (float)$user_wallet_balance; ?>;

        var selectedPostId = 0;
        var selectedPrice  = 0;
        var selectedMethod = 'onsite';

        // ── Custom selects ───────────────────────────────────────────────────
        document.querySelectorAll('.pz-lb-select').forEach(function(sel) {
            var trigger = sel.querySelector('.pz-lb-select-trigger');
            var opts    = sel.querySelectorAll('.pz-lb-select-opt');
            var text    = sel.querySelector('.pz-lb-sel-text');
            var name    = sel.getAttribute('data-name');
            var hidden  = document.getElementById(name === 'pzlevel' ? 'pzLbLevelInput' : 'pzLbWhenInput');

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('.pz-lb-select').forEach(function(o){ if(o!==sel) o.classList.remove('open'); });
                sel.classList.toggle('open');
            });

            opts.forEach(function(opt) {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var val   = this.getAttribute('data-value');
                    var label = this.querySelector('span:last-child').textContent.trim();

                    text.textContent = label;
                    if (val === '') {
                        text.classList.add('pz-lb-placeholder');
                    } else {
                        text.classList.remove('pz-lb-placeholder');
                    }
                    if (hidden) hidden.value = val;

                    if (name === 'pzlevel') {
                        var existDot = trigger.querySelector('.pz-lb-dot');
                        var newDot   = this.querySelector('.pz-lb-dot');
                        if (newDot && existDot) {
                            existDot.style.background = newDot.style.background;
                        } else if (newDot && !existDot) {
                            var d = document.createElement('span');
                            d.className = 'pz-lb-dot';
                            d.style.background = newDot.style.background;
                            trigger.insertBefore(d, text);
                        } else if (!newDot && existDot) {
                            existDot.remove();
                        }
                    }

                    opts.forEach(function(o){ o.classList.remove('selected'); });
                    this.classList.add('selected');
                    sel.classList.remove('open');
                });
            });
        });
        document.addEventListener('click', function() {
            document.querySelectorAll('.pz-lb-select').forEach(function(s){ s.classList.remove('open'); });
        });

        // ── Apri modal ───────────────────────────────────────────────────────
        $(document).on('click', '.pz-lb-btn-join', function() {
            selectedPostId = parseInt($(this).data('post-id'), 10) || 0;
            selectedPrice  = parseFloat($(this).data('price')) || 0;
            var level      = $(this).data('level') || '';
            var date       = $(this).data('date')  || '';

            $('#pzLbModalTitle').text('Unisciti alla partita');
            $('#pzLbModalSub').text(level + ' · ' + date);

            if (walletBalance < selectedPrice || walletBalance <= 0) {
                $('#pzLbOptWallet').addClass('disabled');
            } else {
                $('#pzLbOptWallet').removeClass('disabled');
            }

            selectedMethod = 'onsite';
            $('.pz-lb-pay-opt').removeClass('selected');
            $('#pzLbOptOnsite').addClass('selected');

            $('#pzLbModal').addClass('is-open');
        });

        // ── Selezione metodo ─────────────────────────────────────────────────
        $(document).on('click', '.pz-lb-pay-opt:not(.disabled)', function() {
            selectedMethod = $(this).data('method');
            $('.pz-lb-pay-opt').removeClass('selected');
            $(this).addClass('selected');
        });

        // ── Chiudi modal ─────────────────────────────────────────────────────
        function closeModal() {
            $('#pzLbModal').removeClass('is-open');
        }
        $('#pzLbModalCancel').on('click', closeModal);
        $('#pzLbModal').on('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ── Conferma ─────────────────────────────────────────────────────────
        $('#pzLbModalConfirm').on('click', function() {
            if (!selectedPostId) return;
            var btn = $(this);
            btn.text('Elaborazione…').prop('disabled', true);

            $.post(AJAX, {
                action:         'pz_join_match',
                post_id:        selectedPostId,
                payment_method: selectedMethod,
                nonce:          NONCE
            }).done(function(res) {
                if (res && res.success) {
                    if (selectedMethod === 'wallet' || selectedMethod === 'onsite') {
                        location.reload();
                    } else if (res.data && res.data.payment_url) {
                        window.location.href = res.data.payment_url;
                    } else {
                        alert('URL di pagamento mancante.');
                        btn.text('Conferma').prop('disabled', false);
                    }
                } else {
                    var msg = (res && res.data) ? res.data : 'Impossibile completare.';
                    alert('Errore: ' + msg);
                    btn.text('Conferma').prop('disabled', false);
                }
            }).fail(function() {
                alert('Errore di connessione. Riprova.');
                btn.text('Conferma').prop('disabled', false);
            });
        });

    })(jQuery);
    </script>

    <?php
    return ob_get_clean();
});
