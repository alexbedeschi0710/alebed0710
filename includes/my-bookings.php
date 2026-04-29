<?php
/**
 * PadelZero - Le mie prenotazioni
 *
 * Shortcode [pz_my_bookings]
 * v4 — design allineato al wizard
 */

if (!defined('ABSPATH')) exit;

add_shortcode('pz_my_bookings', function() {

    if (!is_user_logged_in()) {
        return pz_render_login_wall(
            '📅',
            'Le tue prenotazioni',
            'Accedi per vedere e gestire le tue partite.',
            '/app/login/'
        );
    }

    $user_id   = get_current_user_id();
    $user      = wp_get_current_user();
    $user_email = $user->user_email;

    $k = pz_meta_keys();

    // ── Query partite dell'utente ────────────────────────────────────────────
    global $wpdb;
    $prefix = defined('PZ_DB_PREFIX') ? PZ_DB_PREFIX : $wpdb->prefix;

    // Recupera appointment IDs dell'utente
    $amelia_customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1",
        $user_email
    ));

    $appointment_ids = [];
    if ($amelia_customer) {
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT appointmentId FROM {$prefix}customer_bookings
             WHERE customerId = %d AND status IN ('approved','paid')",
            $amelia_customer->id
        ));
        if ($bookings) {
            $appointment_ids = array_column($bookings, 'appointmentId');
        }
    }

    // Recupera i post pz_match con amelia_appointment_id in quella lista
    $items = [];
    if (!empty($appointment_ids)) {
        $placeholders = implode(',', array_fill(0, count($appointment_ids), '%d'));
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_pz_amelia_appointment_id'
             AND CAST(meta_value AS UNSIGNED) IN ($placeholders)",
            ...$appointment_ids
        ));

        foreach ($post_ids as $pid) {
            $pid = (int)$pid;
            if (get_post_status($pid) !== 'publish') continue;

            $serviceId  = (int)get_post_meta($pid, $k['service_id'],    true);
            $startsDate = (string)get_post_meta($pid, $k['starts_date'], true);
            $startsTime = (string)get_post_meta($pid, $k['starts_time'], true);
            $lvl        = (string)get_post_meta($pid, $k['level'],       true);
            $max        = (int)get_post_meta($pid,   $k['max_capacity'], true);
            $booked     = (int)get_post_meta($pid,   $k['booked_count'], true);
            $ameliaId   = (int)get_post_meta($pid,   $k['amelia_appointment_id'], true);

            $dt = pz_dt_from_meta($startsDate, $startsTime);
            if (!$dt) continue;

            $level_info = pz_get_level_info($serviceId);

            // Prezzo
            $price = (float)$wpdb->get_var($wpdb->prepare(
                "SELECT price FROM {$prefix}services WHERE id = %d", $serviceId
            ));

            // Booking ID dell'utente per questo appuntamento
            $booking_id = 0;
            if ($amelia_customer && $ameliaId) {
                $bk = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$prefix}customer_bookings
                     WHERE customerId = %d AND appointmentId = %d
                     AND status IN ('approved','paid') LIMIT 1",
                    $amelia_customer->id, $ameliaId
                ));
                if ($bk) $booking_id = (int)$bk->id;
            }

            $items[] = [
                'post_id'    => $pid,
                'dt'         => $dt,
                'starts_date'=> $startsDate,
                'starts_time'=> $startsTime,
                'level'      => $lvl,
                'level_color'=> $level_info['color'],
                'service_id' => $serviceId,
                'max'        => $max,
                'booked'     => $booked,
                'price'      => $price,
                'booking_id' => $booking_id,
                'amelia_id'  => $ameliaId,
            ];
        }
    }

    // Ordina per data
    usort($items, fn($a,$b) => $a['dt'] <=> $b['dt']);

    $now  = new DateTime('now', new DateTimeZone(wp_timezone_string()));
    $mesi = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    $giorni_short = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];

    $level_display = [
        'Principiante' => '1.0 – 2.5',
        'Intermedio'   => '3.0 – 3.5',
        'Avanzato'     => '4.0+',
        'Pro'          => '4.0+',
    ];

    ob_start();
    ?>
    <style>
    /* ===== PZ My Bookings — CSS blindato v4 ===== */
    #pzMyBkWrap,#pzMyBkWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzMyBkWrap{max-width:640px !important;margin:0 auto !important;padding:30px 0 80px !important;color:#161B2E !important;background:transparent !important;}

    /* Tabs */
    .pz-mb-tabs{display:flex !important;gap:4px !important;background:#F4F5F8 !important;border-radius:14px !important;padding:4px !important;margin-bottom:24px !important;}
    .pz-mb-tab{
        flex:1 !important;padding:10px 0 !important;text-align:center !important;
        font-size:13px !important;font-weight:600 !important;border-radius:10px !important;
        cursor:pointer !important;background:transparent !important;border:none !important;
        color:#8B92A5 !important;transition:background .15s,color .15s !important;
    }
    .pz-mb-tab.active{background:#FFFFFF !important;color:#161B2E !important;box-shadow:0 1px 4px rgba(22,27,46,.08) !important;}

    /* Card prenotazione */
    .pz-mb-list{display:flex !important;flex-direction:column !important;gap:12px !important;}
    .pz-mb-card{
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:20px !important;
        padding:18px !important;transition:border-color .18s ease !important;
    }
    .pz-mb-card-head{
        display:flex !important;align-items:center !important;justify-content:space-between !important;
        margin-bottom:12px !important;
    }
    .pz-mb-card-title{
        font-size:15px !important;font-weight:700 !important;color:#161B2E !important;
        margin:0 !important;display:flex !important;align-items:center !important;gap:7px !important;
        text-transform:none !important;background:transparent !important;
    }
    .pz-mb-dot{width:10px !important;height:10px !important;border-radius:50% !important;flex-shrink:0 !important;display:inline-block !important;}
    .pz-mb-badge{
        font-size:11px !important;font-weight:700 !important;letter-spacing:.04em !important;
        text-transform:uppercase !important;padding:3px 8px !important;border-radius:6px !important;
    }
    .pz-mb-badge-future{background:#E8F8EE !important;color:#1FB856 !important;}
    .pz-mb-badge-past{background:#F4F5F8 !important;color:#8B92A5 !important;}
    .pz-mb-meta{display:flex !important;flex-direction:column !important;gap:5px !important;}
    .pz-mb-meta-item{
        display:flex !important;align-items:center !important;gap:6px !important;
        font-size:13px !important;color:#8B92A5 !important;font-weight:500 !important;margin:0 !important;
    }
    .pz-mb-meta-item svg{stroke:#8B92A5 !important;fill:none !important;flex-shrink:0 !important;}

    /* Azione cancel */
    .pz-mb-actions{margin-top:14px !important;padding-top:14px !important;border-top:1px solid #F0F2F5 !important;}
    .pz-mb-btn-cancel{
        padding:10px 18px !important;background:#FEF2F2 !important;color:#E53E3E !important;
        border:1.5px solid #FECACA !important;border-radius:10px !important;
        font-size:13px !important;font-weight:600 !important;cursor:pointer !important;
        transition:background .15s,border-color .15s !important;
    }
    .pz-mb-btn-cancel:hover{background:#FEE2E2 !important;border-color:#FCA5A5 !important;}

    /* Empty state */
    .pz-mb-empty{
        padding:40px 24px !important;text-align:center !important;
        background:#FFFFFF !important;border-radius:20px !important;
        border:1.5px dashed #D9DCE3 !important;color:#8B92A5 !important;font-size:15px !important;
    }

    /* Modal conferma cancellazione */
    #pzMbModal{
        display:none !important;position:fixed !important;inset:0 !important;
        background:rgba(22,27,46,.6) !important;z-index:999999 !important;
        align-items:flex-end !important;justify-content:center !important;
        backdrop-filter:blur(4px) !important;
    }
    #pzMbModal.is-open{display:flex !important;}
    .pz-mb-modal-inner{
        background:#FFFFFF !important;border-radius:24px 24px 0 0 !important;
        padding:28px 24px 40px !important;width:100% !important;max-width:520px !important;
        animation:pzMbSlideUp .3s ease both !important;
    }
    @keyframes pzMbSlideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .pz-mb-modal-handle{
        width:40px !important;height:4px !important;background:#D9DCE3 !important;
        border-radius:2px !important;margin:0 auto 20px !important;
    }
    .pz-mb-modal-title{font-size:18px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 8px !important;}
    .pz-mb-modal-body{font-size:14px !important;color:#8B92A5 !important;margin:0 0 24px !important;line-height:1.5 !important;}
    .pz-mb-modal-actions{display:flex !important;gap:10px !important;}
    .pz-mb-modal-cancel-btn{
        flex:1 !important;padding:14px !important;background:#F4F5F8 !important;color:#161B2E !important;
        border:none !important;border-radius:12px !important;font-weight:600 !important;font-size:14px !important;cursor:pointer !important;
        transition:background .15s !important;
    }
    .pz-mb-modal-cancel-btn:hover{background:#E8E9EC !important;}
    .pz-mb-modal-confirm-btn{
        flex:2 !important;padding:14px !important;background:#E53E3E !important;color:#fff !important;
        border:none !important;border-radius:12px !important;font-weight:700 !important;font-size:14px !important;cursor:pointer !important;
        transition:background .15s,opacity .15s !important;
    }
    .pz-mb-modal-confirm-btn:hover{background:#C53030 !important;}
    .pz-mb-modal-confirm-btn:disabled{opacity:.5 !important;cursor:not-allowed !important;}

    @media(max-width:600px){
        .pz-mb-btn-cancel{width:100% !important;text-align:center !important;}
    }
    /* → header, back, title, sub: vedi pz-global.php (.pz-g-*) */
    </style>

    <div id="pzMyBkWrap">

        <!-- HEADER -->
        <div class="pz-g-header">
            <a href="javascript:history.back()" class="pz-g-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pz-g-title">Le mie prenotazioni</p>
        </div>
        <p class="pz-g-sub">Tutte le partite a cui sei iscritto.</p>

        <!-- TABS -->
        <div class="pz-mb-tabs">
            <button class="pz-mb-tab active" data-tab="future">Prossime</button>
            <button class="pz-mb-tab" data-tab="past">Passate</button>
        </div>

        <!-- LISTA -->
        <?php
        $future = array_filter($items, fn($i) => $i['dt'] >= $now);
        $past   = array_filter($items, fn($i) => $i['dt'] <  $now);
        $past   = array_reverse(array_values($past));
        $future = array_values($future);
        ?>

        <!-- Prossime -->
        <div class="pz-mb-list" id="pzMbFuture">
        <?php if (empty($future)): ?>
            <div class="pz-mb-empty">
                <p style="margin:0 0 6px;font-size:32px">📅</p>
                <p style="margin:0;font-weight:600;color:#161B2E">Nessuna partita in programma</p>
                <p style="margin:6px 0 0">Vai alle partite pubbliche per iscriverti.</p>
            </div>
        <?php else: foreach ($future as $it):
            $dt         = $it['dt'];
            $dateLabel  = $dt ? $dt->format('d') . ' ' . $mesi[(int)$dt->format('n')] : $it['starts_date'];
            $timeLabel  = $dt ? $dt->format('H:i') : substr($it['starts_time'],0,5);
            $dayLabel   = $dt ? $giorni_short[(int)$dt->format('w')] : '';
            $levelKey   = $it['level'] ?: '';
            $levelLabel = isset($level_display[$levelKey]) ? $level_display[$levelKey] : ($levelKey ?: '–');
            $levelColor = $it['level_color'];
        ?>
            <div class="pz-mb-card">
                <div class="pz-mb-card-head">
                    <p class="pz-mb-card-title">
                        <span class="pz-mb-dot" style="background:<?php echo esc_attr($levelColor); ?>"></span>
                        <?php echo esc_html($levelLabel); ?>
                    </p>
                    <span class="pz-mb-badge pz-mb-badge-future">Confermata</span>
                </div>
                <div class="pz-mb-meta">
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_html($dayLabel . ' ' . $dateLabel); ?>
                    </p>
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo esc_html($timeLabel); ?>
                    </p>
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo (int)$it['booked']; ?>/<?php echo (int)$it['max']; ?> giocatori
                    </p>
                    <?php if ($it['price'] > 0): ?>
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 9h1a2 2 0 0 1 0 4H9v2h3a2 2 0 0 1 0 4"/><line x1="12" y1="6" x2="12" y2="8"/><line x1="12" y1="18" x2="12" y2="20"/></svg>
                        €<?php echo number_format($it['price'], 2, ',', '.'); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php if ($it['booking_id']): ?>
                <div class="pz-mb-actions">
                    <button class="pz-mb-btn-cancel"
                        data-booking-id="<?php echo (int)$it['booking_id']; ?>"
                        data-post-id="<?php echo (int)$it['post_id']; ?>"
                        data-label="<?php echo esc_attr($levelLabel . ' · ' . $dayLabel . ' ' . $dateLabel . ' ' . $timeLabel); ?>">
                        Annulla iscrizione
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
        </div>

        <!-- Passate -->
        <div class="pz-mb-list" id="pzMbPast" style="display:none">
        <?php if (empty($past)): ?>
            <div class="pz-mb-empty">
                <p style="margin:0 0 6px;font-size:32px">🏓</p>
                <p style="margin:0;font-weight:600;color:#161B2E">Nessuna partita passata</p>
                <p style="margin:6px 0 0">Qui troverai lo storico delle tue partite.</p>
            </div>
        <?php else: foreach ($past as $it):
            $dt         = $it['dt'];
            $dateLabel  = $dt ? $dt->format('d') . ' ' . $mesi[(int)$dt->format('n')] : $it['starts_date'];
            $timeLabel  = $dt ? $dt->format('H:i') : substr($it['starts_time'],0,5);
            $dayLabel   = $dt ? $giorni_short[(int)$dt->format('w')] : '';
            $levelKey   = $it['level'] ?: '';
            $levelLabel = isset($level_display[$levelKey]) ? $level_display[$levelKey] : ($levelKey ?: '–');
            $levelColor = $it['level_color'];
        ?>
            <div class="pz-mb-card" style="opacity:.7">
                <div class="pz-mb-card-head">
                    <p class="pz-mb-card-title">
                        <span class="pz-mb-dot" style="background:<?php echo esc_attr($levelColor); ?>"></span>
                        <?php echo esc_html($levelLabel); ?>
                    </p>
                    <span class="pz-mb-badge pz-mb-badge-past">Conclusa</span>
                </div>
                <div class="pz-mb-meta">
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_html($dayLabel . ' ' . $dateLabel); ?>
                    </p>
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo esc_html($timeLabel); ?>
                    </p>
                    <p class="pz-mb-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?php echo (int)$it['booked']; ?>/<?php echo (int)$it['max']; ?> giocatori
                    </p>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>

    </div>

    <!-- MODAL CANCELLAZIONE -->
    <div id="pzMbModal">
        <div class="pz-mb-modal-inner">
            <div class="pz-mb-modal-handle"></div>
            <p class="pz-mb-modal-title">Annulla iscrizione?</p>
            <p class="pz-mb-modal-body" id="pzMbModalBody">Sei sicuro di voler annullare la tua iscrizione a questa partita?</p>
            <div class="pz-mb-modal-actions">
                <button class="pz-mb-modal-cancel-btn" id="pzMbModalClose">Torna indietro</button>
                <button class="pz-mb-modal-confirm-btn" id="pzMbModalConfirm">Annulla iscrizione</button>
            </div>
        </div>
    </div>

    <script>
    (function($){
        var AJAX  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var NONCE = '<?php echo esc_js(wp_create_nonce('pz_cancel_booking')); ?>';
        var selectedBookingId = 0;
        var selectedPostId    = 0;

        // Tabs
        $('.pz-mb-tab').on('click', function() {
            $('.pz-mb-tab').removeClass('active');
            $(this).addClass('active');
            var tab = $(this).data('tab');
            if (tab === 'future') {
                $('#pzMbFuture').show();
                $('#pzMbPast').hide();
            } else {
                $('#pzMbFuture').hide();
                $('#pzMbPast').show();
            }
        });

        // Apri modal
        $(document).on('click', '.pz-mb-btn-cancel', function() {
            selectedBookingId = parseInt($(this).data('booking-id'), 10) || 0;
            selectedPostId    = parseInt($(this).data('post-id'),    10) || 0;
            var label         = $(this).data('label') || '';
            $('#pzMbModalBody').text('Stai per annullare la tua iscrizione a: ' + label);
            $('#pzMbModal').addClass('is-open');
        });

        // Chiudi modal
        function closeModal() { $('#pzMbModal').removeClass('is-open'); }
        $('#pzMbModalClose').on('click', closeModal);
        $('#pzMbModal').on('click', function(e){ if (e.target === this) closeModal(); });

        // Conferma cancellazione
        $('#pzMbModalConfirm').on('click', function() {
            if (!selectedBookingId) return;
            var btn = $(this);
            btn.text('Annullamento…').prop('disabled', true);

            $.post(AJAX, {
                action:     'pz_cancel_booking',
                booking_id: selectedBookingId,
                post_id:    selectedPostId,
                nonce:      NONCE
            }).done(function(res) {
                if (res && res.success) {
                    location.reload();
                } else {
                    var msg = (res && res.data) ? res.data : 'Impossibile annullare.';
                    alert('Errore: ' + msg);
                    btn.text('Annulla iscrizione').prop('disabled', false);
                }
            }).fail(function() {
                alert('Errore di connessione. Riprova.');
                btn.text('Annulla iscrizione').prop('disabled', false);
            });
        });

    })(jQuery);
    </script>

    <?php
    return ob_get_clean();
});
