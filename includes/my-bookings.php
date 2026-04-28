<?php
/**
 * PadelZero - Le mie prenotazioni
 *
 * Shortcode: [pz_my_bookings]
 *
 * Mostra all'utente loggato:
 *   • Prenotazioni future  (private + pubbliche create + pubbliche aggregate)
 *   • Storico prenotazioni passate
 *
 * Azioni:
 *   • Cancella / Rimuovi partecipazione — se > 24h: esegue; se < 24h: popup "chiama"
 *   • Modifica (solo partite private, se > 24h) — bottom sheet con nuova data/ora/campo
 */

if (!defined('ABSPATH')) exit;

add_shortcode('pz_my_bookings', 'pz_mb_render');

function pz_mb_render($atts) {

    if (!is_user_logged_in()) {
        return '<div style="padding:20px;background:#f8d7da;border-radius:12px;font-family:\'DM Sans\',-apple-system,sans-serif">'
             . '❌ Effettua il <a href="' . esc_url(wp_login_url(get_permalink())) . '">login</a> per vedere le tue prenotazioni.'
             . '</div>';
    }

    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    $user   = wp_get_current_user();
    $email  = $user->user_email;
    $phone  = '328 303 8491';

    // Trova customer Amelia
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1", $email
    ));
    $customer_id = $customer ? (int)$customer->id : 0;

    $bookings = [];

    if ($customer_id) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                cb.id            AS booking_id,
                cb.appointmentId AS apt_id,
                cb.status        AS booking_status,
                cb.price         AS price,
                cb.created       AS created,
                a.bookingStart,
                a.bookingEnd,
                a.serviceId,
                a.locationId,
                a.status         AS apt_status,
                s.name           AS service_name,
                l.name           AS location_name
             FROM {$prefix}customer_bookings cb
             JOIN {$prefix}appointments a ON a.id = cb.appointmentId
             LEFT JOIN {$prefix}services  s ON s.id = a.serviceId
             LEFT JOIN {$prefix}locations l ON l.id = a.locationId
             WHERE cb.customerId = %d
             AND cb.status NOT IN ('rejected')
             AND a.status NOT IN ('canceled','rejected')
             ORDER BY a.bookingStart DESC",
            $customer_id
        ));

        $tz      = wp_timezone();
        $now     = new DateTime('now', $tz);

        foreach ($rows as $r) {
            $start   = new DateTime($r->bookingStart, $tz);
            $end     = new DateTime($r->bookingEnd,   $tz);
            $is_past = ($start < $now);
            $diff_h  = ($start->getTimestamp() - $now->getTimestamp()) / 3600;
            $can_act = ($diff_h > 24); // può cancellare/modificare

            // Tipo partita
            $sid = (int)$r->serviceId;
            if (in_array($sid, [1, 6, 7], true)) {
                $type = 'public';
            } else {
                $type = 'private';
            }

            // Controlla se è il creatore (primo booking approvato per questo appointment)
            $first_booking = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}customer_bookings
                 WHERE appointmentId = %d AND status IN ('approved','paid')
                 ORDER BY created ASC LIMIT 1",
                (int)$r->apt_id
            ));
            $is_creator = ($first_booking === (int)$r->booking_id);

            // Info livello
            $level_info = pz_get_level_info($sid);
            $level_display = [
                'Principiante' => '1.0 – 2.5',
                'Intermedio'   => '3.0 – 3.5',
                'Avanzato'     => '4.0+',
                'Pro'          => '4.0+',
            ];
            $level_label = isset($level_display[$level_info['level']]) ? $level_display[$level_info['level']] : $r->service_name;
            $level_color = $level_info['color'] ?: '#8B92A5';

            // Conta partecipanti
            $participants_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}customer_bookings
                 WHERE appointmentId = %d AND status IN ('approved','paid')",
                (int)$r->apt_id
            ));

            $bookings[] = [
                'booking_id'         => (int)$r->booking_id,
                'apt_id'             => (int)$r->apt_id,
                'type'               => $type,
                'is_creator'         => $is_creator,
                'is_past'            => $is_past,
                'can_act'            => $can_act,
                'start'              => $start,
                'end'                => $end,
                'price'              => (float)$r->price,
                'service_id'         => $sid,
                'service_name'       => $r->service_name,
                'location_name'      => $r->location_name ?: '—',
                'location_id'        => (int)$r->locationId,
                'level_label'        => $level_label,
                'level_color'        => $level_color,
                'participants_count' => $participants_count,
            ];
        }
    }

    $future   = array_filter($bookings, fn($b) => !$b['is_past']);
    $past     = array_filter($bookings, fn($b) =>  $b['is_past']);
    // Ordina future ASC, past DESC
    usort($future, fn($a, $b) => $a['start']->getTimestamp() - $b['start']->getTimestamp());
    usort($past,   fn($a, $b) => $b['start']->getTimestamp() - $a['start']->getTimestamp());

    $mesi       = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    $giorni_s   = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];

    // Courts per il form modifica
    $courts = [2 => 'Campo 1', 3 => 'Campo 2', 4 => 'Campo 3', 5 => 'Campo 4'];

    $config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pz_my_bookings'),
        'phone'   => $phone,
        'courts'  => $courts,
        'daysAhead' => 14,
    ];

    ob_start();
    ?>
    <style>
    /* ===== PZ My Bookings — CSS blindato v4 ===== */
    #pzMbWrap,#pzMbWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzMbWrap{max-width:640px !important;margin:0 auto !important;padding:0 0 100px !important;color:#161B2E !important;background:transparent !important;}

    /* Header */
    .pz-mb-header{display:flex !important;align-items:center !important;position:relative !important;min-height:44px !important;margin-bottom:6px !important;}
    .pz-mb-back{width:44px !important;height:44px !important;background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:50% !important;display:flex !important;align-items:center !important;justify-content:center !important;cursor:pointer !important;padding:0 !important;flex-shrink:0 !important;position:relative !important;z-index:1 !important;text-decoration:none !important;transition:background .15s ease,border-color .15s ease !important;}
    .pz-mb-back svg{stroke:#8B92A5 !important;fill:none !important;width:18px !important;height:18px !important;}
    .pz-mb-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-mb-title{position:absolute !important;left:0 !important;right:0 !important;font-size:19px !important;font-weight:700 !important;letter-spacing:-0.02em !important;text-align:center !important;pointer-events:none !important;margin:0 !important;color:#161B2E !important;background:transparent !important;text-transform:none !important;}
    .pz-mb-sub{font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;margin:0 0 24px !important;padding:0 !important;background:transparent !important;}

    /* Sezione */
    .pz-mb-section-title{font-size:13px !important;font-weight:700 !important;letter-spacing:.06em !important;text-transform:uppercase !important;color:#8B92A5 !important;margin:0 0 12px !important;padding:0 !important;}

    /* Card prenotazione */
    .pz-mb-list{display:flex !important;flex-direction:column !important;gap:12px !important;margin-bottom:32px !important;}
    .pz-mb-card{background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;border-radius:20px !important;padding:18px !important;transition:border-color .18s ease !important;}
    .pz-mb-card.is-past{opacity:.65 !important;}
    .pz-mb-card-top{display:flex !important;align-items:flex-start !important;justify-content:space-between !important;gap:12px !important;margin-bottom:12px !important;}
    .pz-mb-card-left{flex:1 !important;min-width:0 !important;}
    .pz-mb-card-title{font-size:15px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 6px !important;display:flex !important;align-items:center !important;gap:7px !important;text-transform:none !important;background:transparent !important;}
    .pz-mb-dot{width:10px !important;height:10px !important;border-radius:50% !important;flex-shrink:0 !important;display:inline-block !important;}
    .pz-mb-meta{display:flex !important;flex-direction:column !important;gap:4px !important;}
    .pz-mb-meta-item{display:flex !important;align-items:center !important;gap:6px !important;font-size:13px !important;color:#8B92A5 !important;font-weight:500 !important;margin:0 !important;}
    .pz-mb-meta-item svg{stroke:#8B92A5 !important;fill:none !important;flex-shrink:0 !important;}

    /* Badge */
    .pz-mb-badge{display:inline-flex !important;align-items:center !important;gap:5px !important;font-size:11px !important;font-weight:700 !important;letter-spacing:.04em !important;text-transform:uppercase !important;padding:4px 10px !important;border-radius:20px !important;white-space:nowrap !important;flex-shrink:0 !important;}
    .pz-mb-badge-creator{background:#E8F8EE !important;color:#1FB856 !important;}
    .pz-mb-badge-guest{background:#F4F5F8 !important;color:#8B92A5 !important;}
    .pz-mb-badge-past{background:#F4F5F8 !important;color:#8B92A5 !important;}
    .pz-mb-badge-private{background:#EEF2FF !important;color:#4F46E5 !important;}

    /* Azioni */
    .pz-mb-actions{display:flex !important;gap:8px !important;margin-top:14px !important;padding-top:14px !important;border-top:1px solid #F0F2F5 !important;}
    .pz-mb-btn-cancel{flex:1 !important;padding:10px !important;background:#FEE2E2 !important;color:#DC2626 !important;border:none !important;border-radius:10px !important;font-size:13px !important;font-weight:600 !important;cursor:pointer !important;transition:background .15s !important;}
    .pz-mb-btn-cancel:hover{background:#FECACA !important;}
    .pz-mb-btn-edit{flex:1 !important;padding:10px !important;background:#F4F5F8 !important;color:#161B2E !important;border:none !important;border-radius:10px !important;font-size:13px !important;font-weight:600 !important;cursor:pointer !important;transition:background .15s !important;}
    .pz-mb-btn-edit:hover{background:#E8E9EC !important;}

    /* Empty state */
    .pz-mb-empty{padding:32px 24px !important;text-align:center !important;background:#FFFFFF !important;border-radius:20px !important;border:1.5px dashed #D9DCE3 !important;color:#8B92A5 !important;font-size:14px !important;margin-bottom:32px !important;}

    /* Modal conferma cancellazione */
    #pzMbModalCancel{display:none !important;position:fixed !important;inset:0 !important;background:rgba(22,27,46,.6) !important;z-index:99999 !important;align-items:flex-end !important;justify-content:center !important;backdrop-filter:blur(4px) !important;}
    #pzMbModalCancel.is-open{display:flex !important;}
    .pz-mb-sheet{background:#FFFFFF !important;border-radius:24px 24px 0 0 !important;padding:28px 24px 40px !important;width:100% !important;max-width:520px !important;animation:pzMbSlideUp .3s ease both !important;}
    @keyframes pzMbSlideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .pz-mb-sheet-handle{width:40px !important;height:4px !important;background:#D9DCE3 !important;border-radius:2px !important;margin:0 auto 20px !important;}
    .pz-mb-sheet-title{font-size:18px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 8px !important;}
    .pz-mb-sheet-sub{font-size:14px !important;color:#8B92A5 !important;margin:0 0 20px !important;line-height:1.5 !important;}
    .pz-mb-sheet-actions{display:flex !important;gap:10px !important;}
    .pz-mb-sheet-dismiss{flex:1 !important;padding:14px !important;background:#F4F5F8 !important;color:#161B2E !important;border:none !important;border-radius:12px !important;font-weight:600 !important;font-size:14px !important;cursor:pointer !important;}
    .pz-mb-sheet-confirm{flex:2 !important;padding:14px !important;background:#DC2626 !important;color:#fff !important;border:none !important;border-radius:12px !important;font-weight:700 !important;font-size:14px !important;cursor:pointer !important;transition:background .15s !important;}
    .pz-mb-sheet-confirm:hover{background:#B91C1C !important;}
    .pz-mb-sheet-call{flex:2 !important;padding:14px !important;background:#9FD731 !important;color:#fff !important;border:none !important;border-radius:12px !important;font-weight:700 !important;font-size:14px !important;cursor:pointer !important;text-decoration:none !important;display:flex !important;align-items:center !important;justify-content:center !important;gap:8px !important;}

    /* Modal modifica */
    #pzMbModalEdit{display:none !important;position:fixed !important;inset:0 !important;background:rgba(22,27,46,.6) !important;z-index:99999 !important;align-items:flex-end !important;justify-content:center !important;backdrop-filter:blur(4px) !important;}
    #pzMbModalEdit.is-open{display:flex !important;}
    .pz-mb-edit-sheet{background:#FFFFFF !important;border-radius:24px 24px 0 0 !important;padding:28px 24px 40px !important;width:100% !important;max-width:520px !important;max-height:85vh !important;overflow-y:auto !important;animation:pzMbSlideUp .3s ease both !important;}

    /* Date picker nel modal edit */
    .pz-mb-dates{display:flex !important;gap:8px !important;overflow-x:auto !important;padding:2px 2px 8px !important;scrollbar-width:none !important;}
    .pz-mb-dates::-webkit-scrollbar{display:none !important;}
    .pz-mb-date{flex:0 0 auto !important;width:64px !important;background:#FFFFFF !important;color:#8B92A5 !important;border:1.5px solid #D9DCE3 !important;border-radius:12px !important;padding:10px 6px !important;text-align:center !important;cursor:pointer !important;transition:all .15s ease !important;}
    .pz-mb-date:hover:not(.is-active){border-color:#161B2E !important;}
    .pz-mb-date-dow{font-size:9px !important;font-weight:700 !important;letter-spacing:.08em !important;text-transform:uppercase !important;}
    .pz-mb-date-day{font-size:18px !important;font-weight:700 !important;color:#161B2E !important;line-height:1.2 !important;margin:3px 0 2px !important;}
    .pz-mb-date-mo{font-size:9px !important;font-weight:600 !important;text-transform:uppercase !important;}
    .pz-mb-date.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#D6F5E0 !important;}
    .pz-mb-date.is-active .pz-mb-date-day{color:#FFFFFF !important;}

    /* Orari nel modal edit */
    .pz-mb-times{display:flex !important;gap:6px !important;flex-wrap:wrap !important;min-height:44px !important;}
    .pz-mb-time{min-width:60px !important;border:1.5px solid #D9DCE3 !important;background:#FFFFFF !important;color:#161B2E !important;font-size:12px !important;font-weight:600 !important;padding:9px 10px !important;border-radius:8px !important;cursor:pointer !important;transition:all .15s ease !important;}
    .pz-mb-time:hover:not(.is-disabled):not(.is-active){border-color:#161B2E !important;}
    .pz-mb-time.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#FFFFFF !important;}
    .pz-mb-time.is-disabled{background:#F4F5F8 !important;color:#C5C9D2 !important;border-color:#ECEEF2 !important;cursor:not-allowed !important;text-decoration:line-through !important;}

    /* Campi nel modal edit */
    .pz-mb-courts{display:grid !important;grid-template-columns:1fr 1fr !important;gap:8px !important;}
    .pz-mb-court{border:1.5px solid #D9DCE3 !important;background:#FFFFFF !important;border-radius:12px !important;padding:16px 10px !important;font-size:13px !important;font-weight:600 !important;color:#161B2E !important;cursor:pointer !important;transition:all .15s ease !important;text-align:center !important;}
    .pz-mb-court:hover:not(.is-disabled):not(.is-active){border-color:#161B2E !important;}
    .pz-mb-court.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#FFFFFF !important;}
    .pz-mb-court.is-disabled{background:#F4F5F8 !important;color:#C5C9D2 !important;border-color:#ECEEF2 !important;cursor:not-allowed !important;}

    /* Edit section label */
    .pz-mb-edit-label{font-size:13px !important;font-weight:700 !important;color:#161B2E !important;margin:0 0 10px !important;padding:0 !important;}
    .pz-mb-edit-section{margin-bottom:20px !important;}

    /* Loading */
    .pz-mb-loading{text-align:center !important;color:#8B92A5 !important;padding:12px 0 !important;font-size:13px !important;}
    .pz-mb-loading::before{content:"" !important;display:inline-block !important;width:12px !important;height:12px !important;border:2px solid #D9DCE3 !important;border-top-color:#1FB856 !important;border-radius:50% !important;animation:pzMbSpin .7s linear infinite !important;vertical-align:middle !important;margin-right:6px !important;}
    @keyframes pzMbSpin{to{transform:rotate(360deg)}}

    /* Responsive */
    @media(max-width:600px){
        .pz-mb-actions{flex-direction:column !important;}
    }
    </style>

    <div id="pzMbWrap">

        <!-- HEADER -->
        <div class="pz-mb-header">
            <a href="javascript:history.back()" class="pz-mb-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pz-mb-title">Le mie prenotazioni</p>
        </div>
        <p class="pz-mb-sub">Le tue partite passate e future.</p>

        <!-- FUTURE -->
        <p class="pz-mb-section-title">In programma</p>
        <?php if (empty($future)): ?>
        <div class="pz-mb-empty">
            <p style="font-size:28px;margin:0 0 8px">🎾</p>
            <p style="margin:0;font-weight:600;color:#161B2E">Nessuna prenotazione futura</p>
            <p style="margin:6px 0 0">Prenota una partita dalla home.</p>
        </div>
        <?php else: ?>
        <div class="pz-mb-list">
            <?php foreach ($future as $b):
                $day_label  = $giorni_s[(int)$b['start']->format('w')] . ' ' . $b['start']->format('d') . ' ' . $mesi[(int)$b['start']->format('n')];
                $time_label = $b['start']->format('H:i') . ' – ' . $b['end']->format('H:i');
            ?>
            <div class="pz-mb-card" data-booking-id="<?php echo $b['booking_id']; ?>" data-apt-id="<?php echo $b['apt_id']; ?>">
                <div class="pz-mb-card-top">
                    <div class="pz-mb-card-left">
                        <p class="pz-mb-card-title">
                            <span class="pz-mb-dot" style="background:<?php echo esc_attr($b['level_color']); ?>"></span>
                            <?php echo $b['type'] === 'public' ? esc_html($b['level_label']) : 'Partita privata'; ?>
                        </p>
                        <div class="pz-mb-meta">
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html($day_label); ?>
                            </p>
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo esc_html($time_label); ?>
                            </p>
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>
                                <?php echo esc_html($b['location_name']); ?>
                            </p>
                            <?php if ($b['type'] === 'public'): ?>
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php echo (int)$b['participants_count']; ?> / 4 partecipanti
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($b['type'] === 'public' && $b['is_creator']): ?>
                            <span class="pz-mb-badge pz-mb-badge-creator">Organizzatore</span>
                        <?php elseif ($b['type'] === 'public'): ?>
                            <span class="pz-mb-badge pz-mb-badge-guest">Aggregato</span>
                        <?php else: ?>
                            <span class="pz-mb-badge pz-mb-badge-private">Privata</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pz-mb-actions">
                    <?php if ($b['type'] === 'private' && $b['can_act']): ?>
                        <button class="pz-mb-btn-edit"
                            data-booking-id="<?php echo $b['booking_id']; ?>"
                            data-apt-id="<?php echo $b['apt_id']; ?>"
                            data-location-id="<?php echo $b['location_id']; ?>"
                            data-service-id="<?php echo $b['service_id']; ?>"
                            data-duration="<?php echo (int)(($b['end']->getTimestamp() - $b['start']->getTimestamp()) / 60); ?>"
                            onclick="pzMbOpenEdit(this)">
                            ✏️ Modifica
                        </button>
                    <?php elseif ($b['type'] === 'private' && !$b['can_act']): ?>
                        <button class="pz-mb-btn-edit" onclick="pzMbOpenCallPopup()">✏️ Modifica</button>
                    <?php endif; ?>

                    <button class="pz-mb-btn-cancel"
                        data-booking-id="<?php echo $b['booking_id']; ?>"
                        data-apt-id="<?php echo $b['apt_id']; ?>"
                        data-type="<?php echo esc_attr($b['type']); ?>"
                        data-is-creator="<?php echo $b['is_creator'] ? '1' : '0'; ?>"
                        data-can-act="<?php echo $b['can_act'] ? '1' : '0'; ?>"
                        onclick="pzMbOpenCancel(this)">
                        <?php echo $b['type'] === 'public' && !$b['is_creator'] ? '🚪 Esci' : '🗑 Cancella'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- STORICO -->
        <?php if (!empty($past)): ?>
        <p class="pz-mb-section-title" style="margin-top:8px">Storico</p>
        <div class="pz-mb-list">
            <?php foreach ($past as $b):
                $day_label  = $giorni_s[(int)$b['start']->format('w')] . ' ' . $b['start']->format('d') . ' ' . $mesi[(int)$b['start']->format('n')];
                $time_label = $b['start']->format('H:i') . ' – ' . $b['end']->format('H:i');
            ?>
            <div class="pz-mb-card is-past">
                <div class="pz-mb-card-top">
                    <div class="pz-mb-card-left">
                        <p class="pz-mb-card-title">
                            <span class="pz-mb-dot" style="background:<?php echo esc_attr($b['level_color']); ?>"></span>
                            <?php echo $b['type'] === 'public' ? esc_html($b['level_label']) : 'Partita privata'; ?>
                        </p>
                        <div class="pz-mb-meta">
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html($day_label); ?>
                            </p>
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo esc_html($time_label); ?>
                            </p>
                            <p class="pz-mb-meta-item">
                                <svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>
                                <?php echo esc_html($b['location_name']); ?>
                            </p>
                        </div>
                    </div>
                    <span class="pz-mb-badge pz-mb-badge-past">Conclusa</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- MODAL CANCELLAZIONE -->
    <div id="pzMbModalCancel">
        <div class="pz-mb-sheet">
            <div class="pz-mb-sheet-handle"></div>
            <p class="pz-mb-sheet-title" id="pzMbCancelTitle">Conferma cancellazione</p>
            <p class="pz-mb-sheet-sub" id="pzMbCancelSub">Sei sicuro di voler cancellare questa prenotazione?</p>
            <div class="pz-mb-sheet-actions" id="pzMbCancelActions">
                <button class="pz-mb-sheet-dismiss" onclick="pzMbCloseCancel()">Annulla</button>
                <button class="pz-mb-sheet-confirm" id="pzMbCancelConfirm">Sì, cancella</button>
            </div>
        </div>
    </div>

    <!-- MODAL MODIFICA -->
    <div id="pzMbModalEdit">
        <div class="pz-mb-edit-sheet">
            <div class="pz-mb-sheet-handle"></div>
            <p class="pz-mb-sheet-title">Modifica prenotazione</p>
            <p class="pz-mb-sheet-sub">Scegli la nuova data, ora e campo.</p>

            <div class="pz-mb-edit-section">
                <p class="pz-mb-edit-label">Data</p>
                <div class="pz-mb-dates" id="pzMbEditDates"></div>
            </div>

            <div class="pz-mb-edit-section">
                <p class="pz-mb-edit-label">Orario</p>
                <div class="pz-mb-times" id="pzMbEditTimes">
                    <div class="pz-mb-loading">Seleziona prima la data</div>
                </div>
            </div>

            <div class="pz-mb-edit-section">
                <p class="pz-mb-edit-label">Campo</p>
                <div class="pz-mb-courts" id="pzMbEditCourts">
                    <?php foreach ($courts as $cid => $cname): ?>
                    <button type="button" class="pz-mb-court" data-id="<?php echo (int)$cid; ?>"><?php echo esc_html($cname); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pz-mb-sheet-actions">
                <button class="pz-mb-sheet-dismiss" onclick="pzMbCloseEdit()">Annulla</button>
                <button class="pz-mb-sheet-confirm" id="pzMbEditConfirm" style="background:#9FD731 !important" disabled>Conferma modifica</button>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var PZ      = <?php echo wp_json_encode($config); ?>;
        var AJAX    = PZ.ajaxUrl;
        var NONCE   = PZ.nonce;
        var DOW     = ['DOM','LUN','MAR','MER','GIO','VEN','SAB'];
        var MONTHS  = ['GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC'];
        var MONTHS_L= ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];

        // ── Stato modifica ────────────────────────────────────────────────
        var edit = { bookingId: null, aptId: null, duration: 60, dateIso: null, time: null, courtId: null, slots: null };

        // ── Cancellazione ─────────────────────────────────────────────────
        var cancelState = { bookingId: null, aptId: null, type: null, isCreator: false };

        window.pzMbOpenCancel = function(btn) {
            var canAct    = btn.getAttribute('data-can-act') === '1';
            var type      = btn.getAttribute('data-type');
            var isCreator = btn.getAttribute('data-is-creator') === '1';
            cancelState   = {
                bookingId: btn.getAttribute('data-booking-id'),
                aptId:     btn.getAttribute('data-apt-id'),
                type:      type,
                isCreator: isCreator,
            };

            if (!canAct) {
                // Meno di 24h — mostra popup chiama
                document.getElementById('pzMbCancelTitle').textContent = 'Termini scaduti';
                document.getElementById('pzMbCancelSub').textContent =
                    'Non è più possibile cancellare online. Siamo oltre le 24 ore. Contattaci per telefono.';
                document.getElementById('pzMbCancelActions').innerHTML =
                    '<button class="pz-mb-sheet-dismiss" onclick="pzMbCloseCancel()">Chiudi</button>' +
                    '<a href="tel:' + PZ.phone.replace(/\s/g,'') + '" class="pz-mb-sheet-call">📞 ' + PZ.phone + '</a>';
            } else {
                var label = (type === 'public' && !isCreator) ? 'la tua partecipazione' : 'questa prenotazione';
                document.getElementById('pzMbCancelTitle').textContent =
                    (type === 'public' && !isCreator) ? 'Esci dalla partita' : 'Cancella prenotazione';
                document.getElementById('pzMbCancelSub').textContent =
                    'Sei sicuro di voler cancellare ' + label + '? L\'operazione non è reversibile.';
                document.getElementById('pzMbCancelActions').innerHTML =
                    '<button class="pz-mb-sheet-dismiss" onclick="pzMbCloseCancel()">Annulla</button>' +
                    '<button class="pz-mb-sheet-confirm" id="pzMbCancelConfirm">Sì, cancella</button>';
                document.getElementById('pzMbCancelConfirm').addEventListener('click', doCancel);
            }
            document.getElementById('pzMbModalCancel').classList.add('is-open');
        };

        window.pzMbCloseCancel = function() {
            document.getElementById('pzMbModalCancel').classList.remove('is-open');
        };

        function doCancel() {
            var btn = document.getElementById('pzMbCancelConfirm');
            btn.disabled = true; btn.textContent = 'Cancellazione…';
            post({ action: 'pz_mb_cancel', nonce: NONCE, booking_id: cancelState.bookingId, apt_id: cancelState.aptId, type: cancelState.type, is_creator: cancelState.isCreator ? '1' : '0' }, function(res) {
                if (res.success) { location.reload(); }
                else { alert('Errore: ' + (res.data || 'impossibile cancellare')); btn.disabled = false; btn.textContent = 'Sì, cancella'; }
            });
        }

        // ── Modifica ──────────────────────────────────────────────────────
        window.pzMbOpenEdit = function(btn) {
            edit.bookingId = btn.getAttribute('data-booking-id');
            edit.aptId     = btn.getAttribute('data-apt-id');
            edit.duration  = parseInt(btn.getAttribute('data-duration'), 10) || 60;
            edit.dateIso   = null; edit.time = null; edit.courtId = null; edit.slots = null;

            buildEditDates();
            renderEditCourts();
            document.getElementById('pzMbEditTimes').innerHTML = '<div class="pz-mb-loading" style="color:#C5C9D2">Seleziona prima la data</div>';
            document.getElementById('pzMbEditConfirm').disabled = true;
            document.getElementById('pzMbModalEdit').classList.add('is-open');
        };

        window.pzMbCloseEdit = function() {
            document.getElementById('pzMbModalEdit').classList.remove('is-open');
        };

        window.pzMbOpenCallPopup = function() {
            document.getElementById('pzMbCancelTitle').textContent = 'Modifica non disponibile';
            document.getElementById('pzMbCancelSub').textContent =
                'Non è più possibile modificare online. Siamo oltre le 24 ore. Contattaci per telefono.';
            document.getElementById('pzMbCancelActions').innerHTML =
                '<button class="pz-mb-sheet-dismiss" onclick="pzMbCloseCancel()">Chiudi</button>' +
                '<a href="tel:' + PZ.phone.replace(/\s/g,'') + '" class="pz-mb-sheet-call">📞 ' + PZ.phone + '</a>';
            document.getElementById('pzMbModalCancel').classList.add('is-open');
        };

        // ── Date picker modifica ──────────────────────────────────────────
        function buildEditDates() {
            var root = document.getElementById('pzMbEditDates');
            root.innerHTML = '';
            var now = new Date();
            for (var i = 1; i <= PZ.daysAhead; i++) { // parte da domani
                var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
                var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                var el = document.createElement('button');
                el.type = 'button';
                el.className = 'pz-mb-date';
                el.innerHTML = '<div class="pz-mb-date-dow">' + DOW[d.getDay()] + '</div><div class="pz-mb-date-day">' + d.getDate() + '</div><div class="pz-mb-date-mo">' + MONTHS[d.getMonth()] + '</div>';
                (function(isoVal){ el.addEventListener('click', function(){
                    edit.dateIso = isoVal; edit.time = null; edit.courtId = null;
                    document.querySelectorAll('.pz-mb-date').forEach(function(x){ x.classList.remove('is-active'); });
                    el.classList.add('is-active');
                    renderEditCourts();
                    loadEditSlots();
                    updateEditConfirm();
                }); })(iso);
                root.appendChild(el);
            }
        }

        function loadEditSlots() {
            if (!edit.dateIso) return;
            document.getElementById('pzMbEditTimes').innerHTML = '<div class="pz-mb-loading">Carico disponibilità…</div>';
            post({ action: 'pz_mb_availability', nonce: NONCE, date: edit.dateIso, duration: edit.duration, apt_id: edit.aptId }, function(res) {
                if (res.success) { edit.slots = res.data.slots; renderEditTimes(); renderEditCourts(); }
                else { document.getElementById('pzMbEditTimes').innerHTML = '<div style="color:#DC2626;font-size:13px">Errore caricamento disponibilità</div>'; }
            });
        }

        function renderEditTimes() {
            var root = document.getElementById('pzMbEditTimes');
            root.innerHTML = '';
            if (!edit.slots) return;
            Object.keys(edit.slots).forEach(function(t) {
                var info = edit.slots[t];
                var el = document.createElement('button');
                el.type = 'button';
                el.className = 'pz-mb-time' + (!info.available ? ' is-disabled' : '') + (edit.time === t ? ' is-active' : '');
                el.disabled = !info.available;
                el.setAttribute('data-time', t);
                el.textContent = t;
                el.addEventListener('click', function(){
                    if (el.disabled) return;
                    edit.time = t;
                    if (edit.courtId && info.courts.indexOf(edit.courtId) === -1) edit.courtId = null;
                    renderEditTimes();
                    renderEditCourts();
                    updateEditConfirm();
                });
                root.appendChild(el);
            });
        }

        function renderEditCourts() {
            var avail = (edit.time && edit.slots && edit.slots[edit.time]) ? edit.slots[edit.time].courts : null;
            document.querySelectorAll('.pz-mb-court').forEach(function(b) {
                var id = parseInt(b.getAttribute('data-id'), 10);
                b.classList.remove('is-active','is-disabled');
                if (avail && avail.indexOf(id) === -1) b.classList.add('is-disabled');
                if (edit.courtId === id) b.classList.add('is-active');
                b.onclick = function(){
                    if (b.classList.contains('is-disabled')) return;
                    if (!edit.time) return;
                    edit.courtId = id;
                    renderEditCourts();
                    updateEditConfirm();
                };
            });
        }

        function updateEditConfirm() {
            document.getElementById('pzMbEditConfirm').disabled = !(edit.dateIso && edit.time && edit.courtId);
        }

        document.getElementById('pzMbEditConfirm').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true; btn.textContent = 'Salvataggio…';
            post({ action: 'pz_mb_edit', nonce: NONCE, booking_id: edit.bookingId, apt_id: edit.aptId, date: edit.dateIso, time: edit.time, duration: edit.duration, court_id: edit.courtId }, function(res) {
                if (res.success) { location.reload(); }
                else { alert('Errore: ' + (res.data || 'impossibile modificare')); btn.disabled = false; btn.textContent = 'Conferma modifica'; }
            });
        });

        // ── Chiudi cliccando fuori ─────────────────────────────────────
        document.getElementById('pzMbModalCancel').addEventListener('click', function(e){ if(e.target===this) pzMbCloseCancel(); });
        document.getElementById('pzMbModalEdit').addEventListener('click',   function(e){ if(e.target===this) pzMbCloseEdit(); });

        // ── Helper XHR ────────────────────────────────────────────────────
        function post(data, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', AJAX, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function(){ try { cb(JSON.parse(xhr.responseText)); } catch(e){ cb({success:false,data:'Errore connessione'}); } };
            xhr.onerror = function(){ cb({success:false,data:'Errore di rete'}); };
            var body = Object.keys(data).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(data[k]); }).join('&');
            xhr.send(body);
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}


/* ============================================================
 *  AJAX — Disponibilità per modifica
 * ============================================================ */
add_action('wp_ajax_pz_mb_availability', function() {
    check_ajax_referer('pz_my_bookings', 'nonce');

    $date     = isset($_POST['date'])     ? sanitize_text_field($_POST['date']) : '';
    $duration = isset($_POST['duration']) ? (int)$_POST['duration']             : 60;
    $apt_id   = isset($_POST['apt_id'])   ? (int)$_POST['apt_id']               : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wp_send_json_error('Data non valida');

    global $wpdb;
    $prefix    = PZ_DB_PREFIX;
    $tz_local  = wp_timezone();
    $courts    = [2 => 'Campo 1', 3 => 'Campo 2', 4 => 'Campo 3', 5 => 'Campo 4'];
    $court_ids = array_keys($courts);
    $ph        = implode(',', array_fill(0, count($court_ids), '%d'));

    $day_start = (new DateTime($date . ' 00:00:00', $tz_local))->format('Y-m-d H:i:s');
    $day_end   = (new DateTime($date . ' 23:59:59', $tz_local))->format('Y-m-d H:i:s');

    // Escludi l'appointment corrente dal check (stiamo modificando lui)
    $params = array_merge($court_ids, [$day_start, $day_end]);
    $apts   = $wpdb->get_results($wpdb->prepare(
        "SELECT locationId, bookingStart, bookingEnd FROM {$prefix}appointments
         WHERE locationId IN ($ph)
         AND bookingStart >= %s AND bookingStart <= %s
         AND status NOT IN ('canceled','rejected')
         AND id != " . (int)$apt_id,
        $params
    ));

    $intervals = [];
    foreach ($apts as $a) {
        $intervals[] = [
            'cid'   => (int)$a->locationId,
            'start' => (new DateTime($a->bookingStart, $tz_local))->getTimestamp(),
            'end'   => (new DateTime($a->bookingEnd,   $tz_local))->getTimestamp(),
        ];
    }

    $weekday    = (int)date('N', strtotime($date));
    $is_wday    = ($weekday >= 1 && $weekday <= 5);
    $is_weekend = ($weekday >= 6);
    $now_ts     = time();
    $is_today   = ($date === (new DateTime('now', $tz_local))->format('Y-m-d'));
    $slots      = [];
    $open = 8; $close = 23;

    for ($h = $open; $h < $close; $h++) {
        foreach ([0, 30] as $m) {
            $ts  = sprintf('%02d:%02d', $h, $m);
            $sm  = $h * 60 + $m;
            $em  = $sm + $duration;
            if ($em > $close * 60) continue;
            if ($duration === 60 && ($is_weekend || ($is_wday && $h >= 17))) { $slots[$ts] = ['available' => false, 'courts' => []]; continue; }

            $sl_ts  = (new DateTime($date . ' ' . $ts . ':00', $tz_local))->getTimestamp();
            $sl_end = $sl_ts + $duration * 60;
            if ($is_today && $sl_ts <= $now_ts) { $slots[$ts] = ['available' => false, 'courts' => []]; continue; }

            $free = [];
            foreach ($court_ids as $cid) {
                $busy = false;
                foreach ($intervals as $iv) {
                    if ($iv['cid'] !== $cid) continue;
                    if ($sl_ts < $iv['end'] && $sl_end > $iv['start']) { $busy = true; break; }
                }
                if (!$busy) $free[] = $cid;
            }
            $slots[$ts] = ['available' => !empty($free), 'courts' => $free];
        }
    }

    wp_send_json_success(['slots' => $slots]);
});


/* ============================================================
 *  AJAX — Cancellazione
 * ============================================================ */
add_action('wp_ajax_pz_mb_cancel', function() {
    check_ajax_referer('pz_my_bookings', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $apt_id     = isset($_POST['apt_id'])     ? (int)$_POST['apt_id']     : 0;
    $type       = isset($_POST['type'])       ? sanitize_text_field($_POST['type']) : '';
    $is_creator = isset($_POST['is_creator']) && $_POST['is_creator'] === '1';

    if (!$booking_id || !$apt_id) wp_send_json_error('Dati mancanti');

    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    $email  = wp_get_current_user()->user_email;

    // Verifica che il booking appartenga all'utente
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1", $email
    ));
    if (!$customer) wp_send_json_error('Utente non trovato');

    $owns = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}customer_bookings WHERE id = %d AND customerId = %d",
        $booking_id, (int)$customer->id
    ));
    if (!$owns) wp_send_json_error('Non autorizzato');

    // Verifica che siamo ancora > 24h prima
    $tz_local = wp_timezone();
    $start    = $wpdb->get_var($wpdb->prepare(
        "SELECT bookingStart FROM {$prefix}appointments WHERE id = %d", $apt_id
    ));
    $start_dt = new DateTime($start, $tz_local);
    $diff_h   = ($start_dt->getTimestamp() - time()) / 3600;
    if ($diff_h <= 24) wp_send_json_error('Termini scaduti — chiama il centro');

    if ($type === 'public' && !$is_creator) {
        // Aggregato: rimuove solo il suo booking
        $wpdb->update("{$prefix}customer_bookings", ['status' => 'canceled'], ['id' => $booking_id]);
    } else {
        // Creatore partita pubblica o partita privata: cancella l'intero appointment
        $wpdb->update("{$prefix}appointments", ['status' => 'canceled'], ['id' => $apt_id]);
        $wpdb->update("{$prefix}customer_bookings", ['status' => 'canceled'], ['appointmentId' => $apt_id]);

        // Sync pz_match se partita pubblica
        if (function_exists('pz_sync_appointment') && in_array($type, ['public'], true)) {
            pz_sync_appointment($apt_id);
        }
    }

    wp_send_json_success(['message' => 'Cancellato']);
});


/* ============================================================
 *  AJAX — Modifica prenotazione privata
 * ============================================================ */
add_action('wp_ajax_pz_mb_edit', function() {
    check_ajax_referer('pz_my_bookings', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id']          : 0;
    $apt_id     = isset($_POST['apt_id'])     ? (int)$_POST['apt_id']               : 0;
    $date       = isset($_POST['date'])       ? sanitize_text_field($_POST['date'])  : '';
    $time       = isset($_POST['time'])       ? sanitize_text_field($_POST['time'])  : '';
    $duration   = isset($_POST['duration'])   ? (int)$_POST['duration']              : 0;
    $court_id   = isset($_POST['court_id'])   ? (int)$_POST['court_id']              : 0;

    if (!$booking_id || !$apt_id)                             wp_send_json_error('Dati mancanti');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))         wp_send_json_error('Data non valida');
    if (!preg_match('/^\d{2}:\d{2}$/', $time))               wp_send_json_error('Ora non valida');
    if (!in_array($duration, [60, 90], true))                wp_send_json_error('Durata non valida');

    $courts = [2 => 'Campo 1', 3 => 'Campo 2', 4 => 'Campo 3', 5 => 'Campo 4'];
    if (!isset($courts[$court_id]))                          wp_send_json_error('Campo non valido');

    global $wpdb;
    $prefix   = PZ_DB_PREFIX;
    $email    = wp_get_current_user()->user_email;
    $tz_local = wp_timezone();

    // Verifica ownership
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1", $email
    ));
    if (!$customer) wp_send_json_error('Utente non trovato');

    $owns = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}customer_bookings WHERE id = %d AND customerId = %d",
        $booking_id, (int)$customer->id
    ));
    if (!$owns) wp_send_json_error('Non autorizzato');

    // Verifica > 24h
    $old_start = $wpdb->get_var($wpdb->prepare(
        "SELECT bookingStart FROM {$prefix}appointments WHERE id = %d", $apt_id
    ));
    $old_dt  = new DateTime($old_start, $tz_local);
    $diff_h  = ($old_dt->getTimestamp() - time()) / 3600;
    if ($diff_h <= 24) wp_send_json_error('Termini scaduti — chiama il centro');

    // Nuovo slot
    try {
        $new_start = new DateTime($date . ' ' . $time . ':00', $tz_local);
    } catch (Exception $e) { wp_send_json_error('Orario non valido'); }

    if ($new_start->getTimestamp() < time()) wp_send_json_error('Slot già passato');

    $new_end       = (clone $new_start)->modify('+' . $duration . ' minutes');
    $booking_start = $new_start->format('Y-m-d H:i:s');
    $booking_end   = $new_end->format('Y-m-d H:i:s');

    // Concurrency check (escludi appointment corrente)
    $conflict = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}appointments
         WHERE locationId = %d AND status NOT IN ('canceled','rejected')
         AND id != %d
         AND bookingStart < %s AND bookingEnd > %s",
        $court_id, $apt_id, $booking_end, $booking_start
    ));
    if ($conflict > 0) wp_send_json_error('Slot non più disponibile, riprova.');

    // Aggiorna appointment
    $wpdb->update("{$prefix}appointments", [
        'bookingStart' => $booking_start,
        'bookingEnd'   => $booking_end,
        'locationId'   => $court_id,
    ], ['id' => $apt_id]);

    wp_send_json_success(['message' => 'Prenotazione modificata']);
});
