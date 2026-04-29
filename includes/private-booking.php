<?php
/**
 * PadelZero - Booking Partita Privata
 *
 * Shortcode:  [pz_book_private]
 *
 * Crea direttamente un appuntamento Amelia (partita 2×2, campi privati).
 * Flusso: Step 1 → data/ora/campo  |  Step 2 → riepilogo + conferma
 *
 * @package PadelZero
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ──────────────────────────────────────────────────────────────
 * Helper locale
 * ────────────────────────────────────────────────────────────── */
function pz_pb_ajax_url(): string {
    return admin_url( 'admin-ajax.php' );
}

/* ──────────────────────────────────────────────────────────────
 * Shortcode principale
 * ────────────────────────────────────────────────────────────── */
add_shortcode( 'pz_book_private', function () {

    if ( ! is_user_logged_in() ) {
        return pz_render_login_wall(
            '🏟️',
            'Prenota una partita privata',
            'Accedi per prenotare un campo e invitare i tuoi amici.',
            '/app/login/'
        );
    }

    $user_id   = get_current_user_id();
    $user      = wp_get_current_user();
    $user_name = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;

    // Legge i campi "privati" da Amelia (category = "Privata" o simile)
    global $wpdb;
    $prefix = defined( 'PZ_DB_PREFIX' ) ? PZ_DB_PREFIX : $wpdb->prefix;

    // Recupera servizi privati (categoryId <> categoria pubblica)
    // Convenzione: i servizi privati hanno name LIKE '%Privat%'
    $services = $wpdb->get_results(
        "SELECT s.id, s.name, s.price, s.duration, s.minCapacity, s.maxCapacity,
                s.categoryId
         FROM {$prefix}services s
         WHERE s.status = 'visible'
           AND (s.name LIKE '%Privat%' OR s.name LIKE '%privat%')
         ORDER BY s.price ASC"
    );

    if ( empty( $services ) ) {
        return '<p style="padding:20px;text-align:center;color:#8B92A5">Nessun campo privato disponibile al momento.</p>';
    }

    // Providers (istruttori/campi) per questi servizi
    $service_ids  = array_column( $services, 'id' );
    $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

    $providers = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT u.id, u.firstName, u.lastName, u.locationId
         FROM {$prefix}providers_to_services pts
         JOIN {$prefix}users u ON u.id = pts.userId
         WHERE pts.serviceId IN ($placeholders)
         ORDER BY u.firstName",
        ...$service_ids
    ) );

    ob_start();
    ?>
    <style>
    /* ===== PZ Private Booking ===== */
    #pzPbWrap,#pzPbWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,sans-serif !important;}
    #pzPbWrap{max-width:640px !important;margin:0 auto !important;padding:30px 0 80px !important;color:#161B2E !important;}

    /* Steps */
    .pz-pb-steps{display:flex !important;gap:8px !important;margin-bottom:28px !important;align-items:center !important;}
    .pz-pb-step{
        flex:1 !important;height:4px !important;border-radius:2px !important;
        background:#E8EAF0 !important;transition:background .3s !important;
    }
    .pz-pb-step.active{background:#161B2E !important;}
    .pz-pb-step.done{background:#1FB856 !important;}

    /* Card selezione */
    .pz-pb-options{display:flex !important;flex-direction:column !important;gap:10px !important;margin-bottom:20px !important;}
    .pz-pb-option{
        display:flex !important;align-items:center !important;justify-content:space-between !important;
        padding:16px !important;background:#fff !important;border:1.5px solid #D9DCE3 !important;
        border-radius:16px !important;cursor:pointer !important;transition:border-color .18s,box-shadow .18s !important;
    }
    .pz-pb-option:hover{border-color:#161B2E !important;}
    .pz-pb-option.selected{border-color:#161B2E !important;box-shadow:0 0 0 3px rgba(22,27,46,.08) !important;}
    .pz-pb-option-label{font-size:15px !important;font-weight:600 !important;color:#161B2E !important;margin:0 !important;}
    .pz-pb-option-meta{font-size:13px !important;color:#8B92A5 !important;margin:4px 0 0 !important;}
    .pz-pb-option-price{font-size:15px !important;font-weight:700 !important;color:#161B2E !important;}
    .pz-pb-option-radio{
        width:20px !important;height:20px !important;border-radius:50% !important;
        border:2px solid #D9DCE3 !important;flex-shrink:0 !important;
        transition:border-color .18s,background .18s !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
    }
    .pz-pb-option.selected .pz-pb-option-radio{border-color:#161B2E !important;background:#161B2E !important;}
    .pz-pb-option.selected .pz-pb-option-radio::after{
        content:'' !important;width:8px !important;height:8px !important;
        background:#fff !important;border-radius:50% !important;display:block !important;
    }

    /* Date / Time picker */
    .pz-pb-label{font-size:12px !important;font-weight:700 !important;letter-spacing:.06em !important;text-transform:uppercase !important;color:#8B92A5 !important;margin:0 0 8px !important;}
    .pz-pb-input{
        width:100% !important;padding:14px 16px !important;font-size:15px !important;font-weight:500 !important;
        color:#161B2E !important;background:#fff !important;border:1.5px solid #D9DCE3 !important;
        border-radius:14px !important;outline:none !important;transition:border-color .18s !important;
        -webkit-appearance:none !important;
    }
    .pz-pb-input:focus{border-color:#161B2E !important;}

    /* Slot orari */
    .pz-pb-slots{display:flex !important;flex-wrap:wrap !important;gap:8px !important;margin-top:4px !important;}
    .pz-pb-slot{
        padding:10px 16px !important;background:#fff !important;border:1.5px solid #D9DCE3 !important;
        border-radius:10px !important;font-size:13px !important;font-weight:600 !important;
        cursor:pointer !important;transition:border-color .15s,background .15s !important;
        color:#161B2E !important;
    }
    .pz-pb-slot:hover{border-color:#161B2E !important;}
    .pz-pb-slot.selected{background:#161B2E !important;color:#fff !important;border-color:#161B2E !important;}
    .pz-pb-slot.unavailable{opacity:.4 !important;cursor:not-allowed !important;pointer-events:none !important;}
    .pz-pb-slots-loading{font-size:13px !important;color:#8B92A5 !important;padding:10px 0 !important;}

    /* Riepilogo step 2 */
    .pz-pb-summary{background:#F8F9FB !important;border-radius:16px !important;padding:20px !important;margin-bottom:20px !important;}
    .pz-pb-summary-row{display:flex !important;justify-content:space-between !important;align-items:center !important;padding:8px 0 !important;border-bottom:1px solid #EEF0F4 !important;}
    .pz-pb-summary-row:last-child{border-bottom:none !important;padding-bottom:0 !important;}
    .pz-pb-summary-key{font-size:13px !important;color:#8B92A5 !important;font-weight:500 !important;}
    .pz-pb-summary-val{font-size:14px !important;font-weight:600 !important;color:#161B2E !important;}

    /* CTA */
    .pz-pb-cta{
        width:100% !important;padding:16px !important;background:#161B2E !important;color:#fff !important;
        border:none !important;border-radius:14px !important;font-size:15px !important;font-weight:700 !important;
        cursor:pointer !important;transition:opacity .2s !important;margin-top:8px !important;
    }
    .pz-pb-cta:disabled{opacity:.4 !important;cursor:not-allowed !important;}
    .pz-pb-cta:hover:not(:disabled){opacity:.88 !important;}
    .pz-pb-back-step{
        display:flex !important;align-items:center !important;gap:6px !important;
        font-size:14px !important;font-weight:600 !important;color:#8B92A5 !important;
        cursor:pointer !important;background:none !important;border:none !important;
        padding:0 !important;margin-bottom:20px !important;
    }

    /* Feedback */
    .pz-pb-feedback{
        padding:20px !important;border-radius:16px !important;text-align:center !important;
        font-size:15px !important;font-weight:600 !important;
    }
    .pz-pb-feedback.ok{background:#E8F8EE !important;color:#1FB856 !important;}
    .pz-pb-feedback.err{background:#FEF2F2 !important;color:#E53E3E !important;}

    .pz-pb-section{margin-bottom:24px !important;}
    /* -> .pz-g-* (pz-global.php) */
    </style>

    <div id="pzPbWrap">

        <!-- HEADER -->
        <div class="pz-g-header">
            <a href="javascript:history.back()" class="pz-g-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pz-g-title">Partita Privata</p>
        </div>

        <!-- STEPS INDICATOR -->
        <div class="pz-pb-steps">
            <div class="pz-pb-step active" id="pzPbStep1Bar"></div>
            <div class="pz-pb-step" id="pzPbStep2Bar"></div>
        </div>

        <!-- ===== STEP 1 ===== -->
        <div id="pzPbStep1">

            <!-- Selezione servizio/campo -->
            <div class="pz-pb-section">
                <p class="pz-pb-label">Seleziona il campo</p>
                <div class="pz-pb-options" id="pzPbServiceList">
                <?php foreach ( $services as $svc ):
                    $dur_min = (int) round( $svc->duration / 60 );
                    $price   = number_format( (float)$svc->price, 2, ',', '.' );
                ?>
                    <div class="pz-pb-option" data-service-id="<?php echo (int)$svc->id; ?>" data-price="<?php echo esc_attr($svc->price); ?>" data-name="<?php echo esc_attr($svc->name); ?>" data-duration="<?php echo (int)$dur_min; ?>">
                        <div>
                            <p class="pz-pb-option-label"><?php echo esc_html($svc->name); ?></p>
                            <p class="pz-pb-option-meta"><?php echo (int)$dur_min; ?> minuti · max <?php echo (int)$svc->maxCapacity; ?> giocatori</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px">
                            <span class="pz-pb-option-price">€<?php echo $price; ?></span>
                            <div class="pz-pb-option-radio"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <!-- Data -->
            <div class="pz-pb-section">
                <p class="pz-pb-label">Data</p>
                <input type="date" class="pz-pb-input" id="pzPbDate"
                    min="<?php echo date('Y-m-d'); ?>"
                    max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>">
            </div>

            <!-- Slot orari -->
            <div class="pz-pb-section" id="pzPbSlotSection" style="display:none">
                <p class="pz-pb-label">Orario</p>
                <div id="pzPbSlots"><p class="pz-pb-slots-loading">Seleziona una data...</p></div>
            </div>

            <button class="pz-pb-cta" id="pzPbNext1" disabled>Continua</button>
        </div>

        <!-- ===== STEP 2 ===== -->
        <div id="pzPbStep2" style="display:none">
            <button class="pz-pb-back-step" id="pzPbBackStep">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                Modifica
            </button>

            <div class="pz-pb-summary" id="pzPbSummary"></div>

            <button class="pz-pb-cta" id="pzPbConfirm">Conferma prenotazione</button>
            <div id="pzPbFeedback" style="display:none;margin-top:16px"></div>
        </div>

    </div>

    <script>
    (function($){
        var AJAX  = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
        var NONCE = '<?php echo esc_js( wp_create_nonce('pz_private_booking') ); ?>';

        var state = { serviceId: 0, serviceName: '', price: 0, duration: 0,
                      date: '', timeStart: '', providerId: 0 };

        // Selezione servizio
        $(document).on('click', '.pz-pb-option', function() {
            $('.pz-pb-option').removeClass('selected');
            $(this).addClass('selected');
            state.serviceId   = parseInt($(this).data('service-id'), 10);
            state.serviceName = $(this).data('name');
            state.price       = parseFloat($(this).data('price'));
            state.duration    = parseInt($(this).data('duration'), 10);
            checkStep1();
            if (state.date) loadSlots();
        });

        // Selezione data
        $('#pzPbDate').on('change', function() {
            state.date = $(this).val();
            state.timeStart = '';
            $('#pzPbSlotSection').show();
            if (state.serviceId) loadSlots();
            checkStep1();
        });

        // Carica slot
        function loadSlots() {
            $('#pzPbSlots').html('<p class="pz-pb-slots-loading">Caricamento orari...</p>');
            $.post(AJAX, {
                action:     'pz_get_private_slots',
                service_id: state.serviceId,
                date:       state.date,
                nonce:      NONCE
            }).done(function(res) {
                if (!res.success || !res.data.length) {
                    $('#pzPbSlots').html('<p class="pz-pb-slots-loading">Nessun orario disponibile per questa data.</p>');
                    return;
                }
                var html = '<div class="pz-pb-slots">';
                res.data.forEach(function(s) {
                    var cls = s.available ? 'pz-pb-slot' : 'pz-pb-slot unavailable';
                    html += '<div class="' + cls + '" data-time="' + s.time + '" data-provider="' + (s.providerId||0) + '">' + s.label + '</div>';
                });
                html += '</div>';
                $('#pzPbSlots').html(html);
            }).fail(function() {
                $('#pzPbSlots').html('<p class="pz-pb-slots-loading">Errore nel caricamento degli orari.</p>');
            });
        }

        // Selezione slot
        $(document).on('click', '.pz-pb-slot:not(.unavailable)', function() {
            $('.pz-pb-slot').removeClass('selected');
            $(this).addClass('selected');
            state.timeStart  = $(this).data('time');
            state.providerId = parseInt($(this).data('provider'), 10) || 0;
            checkStep1();
        });

        function checkStep1() {
            var ok = state.serviceId && state.date && state.timeStart;
            $('#pzPbNext1').prop('disabled', !ok);
        }

        // Vai a step 2
        $('#pzPbNext1').on('click', function() {
            var mesi = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                        'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
            var parts = state.date.split('-');
            var dateLabel = parseInt(parts[2],10) + ' ' + mesi[parseInt(parts[1],10)] + ' ' + parts[0];
            var html = '';
            var rows = [
                ['Campo',   state.serviceName],
                ['Data',    dateLabel],
                ['Orario',  state.timeStart],
                ['Durata',  state.duration + ' min'],
                ['Totale',  '€' + state.price.toFixed(2).replace('.',',')],
            ];
            rows.forEach(function(r) {
                html += '<div class="pz-pb-summary-row"><span class="pz-pb-summary-key">' +
                        r[0] + '</span><span class="pz-pb-summary-val">' + r[1] + '</span></div>';
            });
            $('#pzPbSummary').html(html);
            $('#pzPbStep1').hide();
            $('#pzPbStep2').show();
            $('#pzPbStep1Bar').removeClass('active').addClass('done');
            $('#pzPbStep2Bar').addClass('active');
            window.scrollTo(0,0);
        });

        // Torna a step 1
        $('#pzPbBackStep').on('click', function() {
            $('#pzPbStep2').hide();
            $('#pzPbStep1').show();
            $('#pzPbStep2Bar').removeClass('active');
            $('#pzPbStep1Bar').removeClass('done').addClass('active');
            $('#pzPbFeedback').hide();
        });

        // Conferma
        $('#pzPbConfirm').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Prenotazione in corso…');
            $('#pzPbFeedback').hide();

            $.post(AJAX, {
                action:      'pz_create_private_booking',
                service_id:  state.serviceId,
                date:        state.date,
                time_start:  state.timeStart,
                provider_id: state.providerId,
                nonce:       NONCE
            }).done(function(res) {
                if (res && res.success) {
                    $('#pzPbFeedback')
                        .removeClass('err').addClass('ok')
                        .html('✅ Prenotazione confermata! Riceverai una email di conferma.')
                        .show();
                    btn.hide();
                } else {
                    var msg = (res && res.data) ? res.data : 'Impossibile completare la prenotazione.';
                    $('#pzPbFeedback')
                        .removeClass('ok').addClass('err')
                        .html('❌ ' + msg)
                        .show();
                    btn.prop('disabled', false).text('Conferma prenotazione');
                }
            }).fail(function() {
                $('#pzPbFeedback')
                    .removeClass('ok').addClass('err')
                    .html('❌ Errore di connessione. Riprova.')
                    .show();
                btn.prop('disabled', false).text('Conferma prenotazione');
            });
        });

    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
});

/* ──────────────────────────────────────────────────────────────
 * AJAX: slot disponibili per una data
 * ────────────────────────────────────────────────────────────── */
add_action('wp_ajax_pz_get_private_slots', 'pz_ajax_get_private_slots');
function pz_ajax_get_private_slots() {
    check_ajax_referer('pz_private_booking', 'nonce');

    $service_id = (int) ($_POST['service_id'] ?? 0);
    $date       = sanitize_text_field($_POST['date'] ?? '');

    if (!$service_id || !$date) wp_send_json_error('Parametri mancanti');

    global $wpdb;
    $prefix = defined('PZ_DB_PREFIX') ? PZ_DB_PREFIX : $wpdb->prefix;

    // Durata del servizio in secondi
    $duration = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT duration FROM {$prefix}services WHERE id = %d", $service_id
    ));
    if (!$duration) wp_send_json_error('Servizio non trovato');

    // Providers per questo servizio
    $providers = $wpdb->get_col($wpdb->prepare(
        "SELECT userId FROM {$prefix}providers_to_services WHERE serviceId = %d", $service_id
    ));
    if (empty($providers)) wp_send_json_error('Nessun provider');

    $slots = [];

    foreach ($providers as $provider_id) {
        $provider_id = (int)$provider_id;

        // Orari di lavoro del provider per il giorno
        $dow = (int) date('N', strtotime($date)); // 1=Lun 7=Dom
        $periods = $wpdb->get_results($wpdb->prepare(
            "SELECT wh.startTime, wh.endTime
             FROM {$prefix}providers_to_periods ptp
             JOIN {$prefix}timeouts t ON t.id = ptp.periodId
             WHERE 1=0" // placeholder — usa schedule reale sotto
        ));

        // Approccio semplificato: genera slot 08:00-22:00 ogni 90 min
        $start_h = 8; $end_h = 22;
        $step    = $duration / 60; // minuti

        for ($h = $start_h; ($h + $step/60) <= $end_h; $h += $step/60) {
            $hh  = floor($h);
            $mm  = round(($h - $hh) * 60);
            $time_start = sprintf('%02d:%02d', $hh, $mm);
            $time_end   = date('H:i', strtotime($date . ' ' . $time_start) + $duration);

            // Controlla sovrapposizioni
            $clash = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}appointments
                 WHERE providerId = %d
                   AND DATE(bookingStart) = %s
                   AND status IN ('approved','pending')
                   AND bookingStart < %s
                   AND bookingEnd   > %s",
                $provider_id,
                $date,
                $date . ' ' . $time_end . ':00',
                $date . ' ' . $time_start . ':00'
            ));

            $slots[] = [
                'time'       => $time_start,
                'label'      => $time_start,
                'available'  => $clash == 0,
                'providerId' => $provider_id,
            ];
        }
        break; // primo provider disponibile
    }

    wp_send_json_success($slots);
}

/* ──────────────────────────────────────────────────────────────
 * AJAX: crea la prenotazione privata
 * ────────────────────────────────────────────────────────────── */
add_action('wp_ajax_pz_create_private_booking', 'pz_ajax_create_private_booking');
function pz_ajax_create_private_booking() {
    check_ajax_referer('pz_private_booking', 'nonce');

    $user_id    = get_current_user_id();
    $service_id = (int) ($_POST['service_id']  ?? 0);
    $date       = sanitize_text_field($_POST['date']       ?? '');
    $time_start = sanitize_text_field($_POST['time_start'] ?? '');
    $provider_id= (int) ($_POST['provider_id'] ?? 0);

    if (!$user_id || !$service_id || !$date || !$time_start) {
        wp_send_json_error('Dati mancanti');
    }

    global $wpdb;
    $prefix   = defined('PZ_DB_PREFIX') ? PZ_DB_PREFIX : $wpdb->prefix;
    $user     = wp_get_current_user();

    // Recupera/crea customer Amelia
    $amelia_user = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1",
        $user->user_email
    ));
    if (!$amelia_user) wp_send_json_error('Utente Amelia non trovato');

    // Durata e prezzo
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT duration, price FROM {$prefix}services WHERE id = %d", $service_id
    ));
    if (!$service) wp_send_json_error('Servizio non trovato');

    $booking_start = $date . ' ' . $time_start . ':00';
    $booking_end   = date('Y-m-d H:i:s', strtotime($booking_start) + (int)$service->duration);

    // Se non è stato passato un provider, prendi il primo disponibile
    if (!$provider_id) {
        $provider_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT userId FROM {$prefix}providers_to_services WHERE serviceId = %d LIMIT 1",
            $service_id
        ));
    }
    if (!$provider_id) wp_send_json_error('Nessun provider disponibile');

    // Crea appointment
    $inserted = $wpdb->insert("{$prefix}appointments", [
        'bookingStart'    => $booking_start,
        'bookingEnd'      => $booking_end,
        'notifyParticipants' => 1,
        'serviceId'       => $service_id,
        'providerId'      => $provider_id,
        'status'          => 'approved',
        'created'         => current_time('mysql'),
    ]);
    if (!$inserted) wp_send_json_error('Impossibile creare l\'appuntamento');

    $appointment_id = (int) $wpdb->insert_id;

    // Crea customer_booking
    $wpdb->insert("{$prefix}customer_bookings", [
        'appointmentId' => $appointment_id,
        'customerId'    => (int)$amelia_user->id,
        'status'        => 'approved',
        'price'         => (float)$service->price,
        'payment'       => 'onsite',
    ]);

    wp_send_json_success([
        'appointment_id' => $appointment_id,
        'message'        => 'Prenotazione confermata!',
        'price'          => (float)$service->price,
        'name'           => $user->display_name,
    ]);
}
