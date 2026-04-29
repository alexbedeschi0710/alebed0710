<?php
/**
 * PadelZero - Creazione Partita Pubblica
 *
 * Shortcode: [pz_create_public]
 *
 * Flusso:
 *   1. Scelta livello  (1.0–2.5 / 3.0–3.5 / 4.0+)
 *   2. Scelta data     (scroll orizzontale N giorni avanti)
 *   3. Scelta durata   (60 / 90 min)
 *   4. Scelta ora      (slot da 30 min, disponibilità live)
 *   5. Scelta campo    (griglia 2×2, campi liberi evidenziati)
 *   6. Conferma        → crea appointment Amelia + customer_booking
 *                        → pz_sync_appointment() crea il post pz_match
 *                        → pagamento in loco
 *
 * Mappa serviceId:
 *   1  → Principiante  (1.0 – 2.5)
 *   6  → Intermedio    (3.0 – 3.5)
 *   7  → Avanzato/Pro  (4.0+)
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 *  CONFIG
 * ============================================================ */

function pz_cpb_services() {
    return [
        1 => ['label' => '1.0 – 2.5', 'color' => '#00cc44', 'desc' => 'Nuovi giocatori, movimenti base.'],
        6 => ['label' => '3.0 – 3.5', 'color' => '#ffcc00', 'desc' => 'Buon controllo, uso delle pareti.'],
        7 => ['label' => '4.0+',      'color' => '#ff7900', 'desc' => 'Gioco solido, tattica avanzata.'],
    ];
}

function pz_cpb_durations() {
    return [
        60 => '1 ora',
        90 => '1 ora e mezza',
    ];
}

function pz_cpb_courts() {
    return [
        2 => 'Campo 1',
        3 => 'Campo 2',
        4 => 'Campo 3',
        5 => 'Campo 4',
    ];
}

define('PZ_CPB_OPEN_HOUR',  8);
define('PZ_CPB_CLOSE_HOUR', 23);
define('PZ_CPB_DAYS_AHEAD', 14);


/* ============================================================
 *  SHORTCODE
 * ============================================================ */

add_shortcode('pz_create_public', 'pz_cpb_render');

function pz_cpb_render($atts) {

    if (!is_user_logged_in()) {
        return pz_render_login_wall('', 'Accedi per prenotare', 'Per creare una partita pubblica devi prima effettuare il login.', 'login/');
    }

    $services = pz_cpb_services();
    $courts   = pz_cpb_courts();

    // Prezzi da Amelia
    global $wpdb;
    $prefix  = PZ_DB_PREFIX;
    $prices  = [];
    foreach (array_keys($services) as $sid) {
        $prices[$sid] = (float)$wpdb->get_var(
            $wpdb->prepare("SELECT price FROM {$prefix}services WHERE id = %d", $sid)
        );
    }

    $config = [
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('pz_public_booking'),
        'services'  => $services,
        'durations' => pz_cpb_durations(),
        'courts'    => $courts,
        'prices'    => $prices,
        'daysAhead' => PZ_CPB_DAYS_AHEAD,
    ];

    ob_start();
    ?>
    <style>
    /* ===== PZ Create Public — CSS blindato v4 ===== */
    #pzCpbWrap,#pzCpbWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzCpbWrap{max-width:480px !important;margin:0 auto !important;padding:0 0 160px !important;color:#161B2E !important;background:transparent !important;}

    .pz-cpb-login-wall{max-width:480px;margin:30px auto;padding:24px;background:#fff3cd;border:1px solid #ffc107;border-radius:14px;text-align:center;font-family:'DM Sans',sans-serif;}

    /* Header */
    .pz-cpb-header{display:flex !important;align-items:center !important;position:relative !important;min-height:44px !important;margin-bottom:6px !important;}
    .pz-cpb-back{
        width:44px !important;height:44px !important;background:#FFFFFF !important;
        border:1.5px solid #D9DCE3 !important;border-radius:50% !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        cursor:pointer !important;padding:0 !important;flex-shrink:0 !important;
        position:relative !important;z-index:1 !important;
        transition:background .15s ease,border-color .15s ease !important;
    }
    .pz-cpb-back svg{stroke:#8B92A5 !important;fill:none !important;width:18px !important;height:18px !important;}
    .pz-cpb-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-cpb-title{
        position:absolute !important;left:0 !important;right:0 !important;
        font-size:19px !important;font-weight:700 !important;letter-spacing:-0.02em !important;
        text-align:center !important;pointer-events:none !important;margin:0 !important;
        color:#161B2E !important;background:transparent !important;text-transform:none !important;
    }
    .pz-cpb-sub{font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;margin:0 0 22px !important;}

    /* Card */
    .pz-cpb-card{
        background:#FFFFFF !important;border-radius:20px !important;
        padding:22px 18px 14px !important;
        box-shadow:0 8px 30px -12px rgba(22,27,46,.10) !important;
        animation:pzCpbFadeUp .4s ease both !important;
    }
    @keyframes pzCpbFadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

    /* Sezione */
    .pz-cpb-section{margin-bottom:24px !important;}
    .pz-cpb-section:last-child{margin-bottom:8px !important;}
    .pz-cpb-section-head{display:flex !important;align-items:center !important;gap:10px !important;margin-bottom:14px !important;}
    .pz-cpb-section-head svg{width:18px !important;height:18px !important;stroke:#161B2E !important;stroke-width:2 !important;fill:none !important;flex-shrink:0 !important;}
    .pz-cpb-section-label{font-size:15px !important;font-weight:700 !important;letter-spacing:-0.01em !important;color:#161B2E !important;}

    /* Card livello */
    .pz-cpb-levels{display:flex !important;flex-direction:column !important;gap:10px !important;}
    .pz-cpb-level{
        display:flex !important;align-items:center !important;gap:14px !important;
        width:100% !important;text-align:left !important;
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;
        border-radius:14px !important;padding:14px 16px !important;
        cursor:pointer !important;
        transition:border-color .18s ease,transform .18s ease,background .18s ease !important;
    }
    .pz-cpb-level:hover{border-color:#161B2E !important;transform:translateY(-1px) !important;}
    .pz-cpb-level.is-active{border-color:#1FB856 !important;background:#FAFFF4 !important;}
    .pz-cpb-level-dot{flex-shrink:0 !important;width:12px !important;height:12px !important;border-radius:50% !important;}
    .pz-cpb-level-info{flex:1 !important;min-width:0 !important;}
    .pz-cpb-level-name{display:block !important;font-size:15px !important;font-weight:700 !important;color:#161B2E !important;text-transform:none !important;margin-bottom:3px !important;}
    .pz-cpb-level-desc{font-size:12.5px !important;color:#8B92A5 !important;line-height:1.4 !important;margin:0 !important;display:block !important;}
    .pz-cpb-level-check{
        flex-shrink:0 !important;width:20px !important;height:20px !important;border-radius:50% !important;
        border:1.5px solid #D9DCE3 !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        transition:border-color .15s,background .15s !important;
    }
    .pz-cpb-level.is-active .pz-cpb-level-check{border-color:#1FB856 !important;background:#1FB856 !important;}
    .pz-cpb-level-check::after{content:"" !important;width:8px !important;height:8px !important;border-radius:50% !important;background:#fff !important;display:none !important;}
    .pz-cpb-level.is-active .pz-cpb-level-check::after{display:block !important;}

    /* Date picker */
    .pz-cpb-dates{display:flex !important;gap:10px !important;overflow-x:auto !important;padding:2px 2px 8px !important;margin:0 -2px !important;scrollbar-width:none !important;}
    .pz-cpb-dates::-webkit-scrollbar{display:none !important;}
    .pz-cpb-date{
        flex:0 0 auto !important;width:72px !important;
        background:#FFFFFF !important;color:#8B92A5 !important;
        border:1.5px solid #D9DCE3 !important;border-radius:14px !important;
        padding:12px 8px 10px !important;text-align:center !important;cursor:pointer !important;
        transition:transform .15s ease,background .2s ease,border-color .2s ease !important;
        user-select:none !important;
    }
    .pz-cpb-date:hover:not(.is-active){transform:translateY(-1px) !important;border-color:#161B2E !important;}
    .pz-cpb-date-dow,.pz-cpb-date-mo{font-size:10px !important;font-weight:700 !important;letter-spacing:.08em !important;text-transform:uppercase !important;}
    .pz-cpb-date-day{font-size:22px !important;font-weight:700 !important;line-height:1.1 !important;margin:5px 0 3px !important;color:#161B2E !important;letter-spacing:-0.02em !important;}
    .pz-cpb-date.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#D6F5E0 !important;}
    .pz-cpb-date.is-active .pz-cpb-date-day{color:#FFFFFF !important;}

    /* Toggle durata */
    .pz-cpb-duration{display:flex !important;background:#F4F5F8 !important;border-radius:14px !important;padding:4px !important;gap:4px !important;}
    .pz-cpb-duration-opt{
        flex:1 !important;border:none !important;background:transparent !important;
        padding:11px 12px !important;font-size:14px !important;font-weight:600 !important;
        color:#8B92A5 !important;border-radius:10px !important;cursor:pointer !important;
        transition:background .2s,color .2s,box-shadow .2s !important;
    }
    .pz-cpb-duration-opt.is-active{background:#FFFFFF !important;color:#161B2E !important;box-shadow:0 2px 8px -2px rgba(22,27,46,.12) !important;}

    /* Orari */
    .pz-cpb-times{display:flex !important;gap:8px !important;overflow-x:auto !important;padding:2px 2px 8px !important;margin:0 -2px !important;min-height:60px !important;scrollbar-width:none !important;}
    .pz-cpb-times::-webkit-scrollbar{display:none !important;}
    .pz-cpb-time{
        flex:0 0 auto !important;min-width:68px !important;
        border:1.5px solid #D9DCE3 !important;background:#FFFFFF !important;color:#161B2E !important;
        font-size:13px !important;font-weight:600 !important;
        padding:11px 14px !important;border-radius:10px !important;cursor:pointer !important;
        transition:all .15s ease !important;
    }
    .pz-cpb-time:hover:not(.is-disabled):not(.is-active){border-color:#161B2E !important;}
    .pz-cpb-time.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#FFFFFF !important;}
    .pz-cpb-time.is-disabled{background:#F4F5F8 !important;color:#C5C9D2 !important;border-color:#ECEEF2 !important;cursor:not-allowed !important;text-decoration:line-through !important;}

    /* Loader */
    .pz-cpb-loading{flex:1 1 auto !important;text-align:center !important;color:#8B92A5 !important;padding:18px 0 !important;font-size:13px !important;font-weight:500 !important;}
    .pz-cpb-loading::before{content:"" !important;display:inline-block !important;width:14px !important;height:14px !important;border:2px solid #D9DCE3 !important;border-top-color:#1FB856 !important;border-radius:50% !important;animation:pzCpbSpin .7s linear infinite !important;vertical-align:middle !important;margin-right:8px !important;}
    @keyframes pzCpbSpin{to{transform:rotate(360deg)}}

    /* Campi */
    .pz-cpb-courts{display:grid !important;grid-template-columns:1fr 1fr !important;gap:10px !important;}
    .pz-cpb-court{
        border:1.5px solid #D9DCE3 !important;background:#FFFFFF !important;
        border-radius:14px !important;padding:22px 12px !important;
        font-size:13.5px !important;font-weight:600 !important;color:#161B2E !important;
        cursor:pointer !important;transition:all .15s ease !important;text-align:center !important;
    }
    .pz-cpb-court:hover:not(.is-disabled):not(.is-active){border-color:#161B2E !important;}
    .pz-cpb-court.is-active{background:#1FB856 !important;border-color:#1FB856 !important;color:#FFFFFF !important;}
    .pz-cpb-court.is-disabled{background:#F4F5F8 !important;color:#C5C9D2 !important;border-color:#ECEEF2 !important;cursor:not-allowed !important;}

    /* Riepilogo */
    .pz-cpb-summary{
        display:flex !important;align-items:center !important;justify-content:space-between !important;
        background:#E8F8EE !important;border-radius:14px !important;
        font-size:13px !important;color:#161B2E !important;font-weight:500 !important;
        opacity:0 !important;max-height:0 !important;overflow:hidden !important;margin-top:0 !important;
        transition:opacity .25s ease,max-height .25s ease,margin-top .25s ease,padding .25s ease !important;
    }
    .pz-cpb-summary.is-visible{opacity:1 !important;max-height:80px !important;margin-top:14px !important;padding:14px 16px !important;}
    .pz-cpb-summary-price{font-weight:700 !important;color:#1FB856 !important;font-size:15px !important;}

    /* Errore */
    .pz-cpb-error{background:#FEE2E2 !important;color:#991B1B !important;border-radius:10px !important;padding:10px 14px !important;font-size:13px !important;font-weight:500 !important;margin-top:12px !important;display:none !important;}
    .pz-cpb-error.is-visible{display:block !important;}

    /* CTA fisso */
    .pz-cpb-cta-wrap{
        position:fixed !important;bottom:64px !important;left:0 !important;right:0 !important;
        background:#FFFFFF !important;border-top:1px solid #ECEEF2 !important;
        padding:16px 18px !important;z-index:99 !important;
    }
    .pz-cpb-cta-inner{max-width:480px !important;margin:0 auto !important;width:100% !important;}
    .pz-cpb-cta{
        width:100% !important;display:block !important;
        border:none !important;background:#9FD731 !important;color:#FFFFFF !important;
        font-size:15px !important;font-weight:700 !important;letter-spacing:.06em !important;
        text-transform:uppercase !important;padding:17px !important;
        border-radius:14px !important;cursor:pointer !important;
        box-shadow:0 8px 20px -6px rgba(159,215,49,.5) !important;
        transition:background .2s,transform .2s !important;
    }
    .pz-cpb-cta:hover:not(:disabled){background:#8BC41F !important;transform:translateY(-1px) !important;}
    .pz-cpb-cta:disabled{background:#D9DCE3 !important;color:#8B92A5 !important;cursor:not-allowed !important;box-shadow:none !important;}

    /* Overlay successo */
    .pz-cpb-success{position:fixed !important;inset:0 !important;background:rgba(22,27,46,.6) !important;z-index:9999 !important;display:none !important;align-items:center !important;justify-content:center !important;padding:24px !important;backdrop-filter:blur(4px) !important;}
    .pz-cpb-success.is-open{display:flex !important;}
    .pz-cpb-success-card{background:#FFFFFF !important;border-radius:24px !important;max-width:380px !important;width:100% !important;padding:34px 26px 26px !important;text-align:center !important;animation:pzCpbFadeUp .3s ease both !important;}
    .pz-cpb-success-icon{width:64px !important;height:64px !important;border-radius:50% !important;background:#E8F8EE !important;display:flex !important;align-items:center !important;justify-content:center !important;margin:0 auto 18px !important;}
    .pz-cpb-success-icon svg{width:32px !important;height:32px !important;stroke:#1FB856 !important;stroke-width:3 !important;fill:none !important;}
    .pz-cpb-success-title{font-size:20px !important;font-weight:700 !important;margin:0 0 8px !important;color:#161B2E !important;}
    .pz-cpb-success-msg{font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;margin:0 0 16px !important;}
    .pz-cpb-success-detail{background:#F4F5F8 !important;border-radius:14px !important;padding:14px !important;font-size:13.5px !important;font-weight:600 !important;margin-bottom:18px !important;color:#161B2E !important;line-height:1.6 !important;}
    .pz-cpb-success-tag{display:inline-block !important;background:#FEF3C7 !important;color:#92400E !important;font-size:11px !important;font-weight:700 !important;letter-spacing:.05em !important;text-transform:uppercase !important;padding:4px 10px !important;border-radius:6px !important;margin-bottom:12px !important;}
    .pz-cpb-success-btn{width:100% !important;background:#9FD731 !important;color:#fff !important;border:none !important;font-size:14px !important;font-weight:700 !important;letter-spacing:.05em !important;text-transform:uppercase !important;padding:14px !important;border-radius:14px !important;cursor:pointer !important;transition:background .2s !important;}
    .pz-cpb-success-btn:hover{background:#8BC41F !important;}
    </style>

    <div id="pzCpbWrap">

        <!-- HEADER -->
        <div class="pz-cpb-header">
            <button class="pz-cpb-back" type="button" aria-label="Indietro" onclick="history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <p class="pz-cpb-title">Crea Partita Pubblica</p>
        </div>
        <p class="pz-cpb-sub">Scegli livello, data, ora e campo. Gli altri giocatori potranno aggregarsi.</p>

        <!-- CARD -->
        <div class="pz-cpb-card">

            <!-- Livello -->
            <div class="pz-cpb-section">
                <div class="pz-cpb-section-head">
                    <svg viewBox="0 0 24 24"><path d="M12 2 15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <span class="pz-cpb-section-label">Livello della partita</span>
                </div>
                <div class="pz-cpb-levels" id="pzCpbLevels">
                    <?php foreach ($services as $sid => $svc): ?>
                    <button type="button" class="pz-cpb-level" data-service-id="<?php echo (int)$sid; ?>">
                        <span class="pz-cpb-level-dot" style="background:<?php echo esc_attr($svc['color']); ?>"></span>
                        <span class="pz-cpb-level-info">
                            <span class="pz-cpb-level-name"><?php echo esc_html($svc['label']); ?></span>
                            <span class="pz-cpb-level-desc"><?php echo esc_html($svc['desc']); ?></span>
                        </span>
                        <span class="pz-cpb-level-check"></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Data -->
            <div class="pz-cpb-section">
                <div class="pz-cpb-section-head">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span class="pz-cpb-section-label">Seleziona data</span>
                </div>
                <div class="pz-cpb-dates" id="pzCpbDates"></div>
            </div>

            <!-- Durata -->
            <div class="pz-cpb-section">
                <div class="pz-cpb-section-head">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span class="pz-cpb-section-label">Durata</span>
                </div>
                <div class="pz-cpb-duration" id="pzCpbDuration">
                    <button type="button" class="pz-cpb-duration-opt is-active" data-min="60">1 ora</button>
                    <button type="button" class="pz-cpb-duration-opt" data-min="90">1 ora e mezza</button>
                </div>
            </div>

            <!-- Ora -->
            <div class="pz-cpb-section">
                <div class="pz-cpb-section-head">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span class="pz-cpb-section-label">Seleziona orario</span>
                </div>
                <div class="pz-cpb-times" id="pzCpbTimes">
                    <div class="pz-cpb-loading" style="color:#C5C9D2">Seleziona prima la data</div>
                </div>
            </div>

            <!-- Campo -->
            <div class="pz-cpb-section">
                <div class="pz-cpb-section-head">
                    <svg viewBox="0 0 24 24"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>
                    <span class="pz-cpb-section-label">Scegli il campo</span>
                </div>
                <div class="pz-cpb-courts" id="pzCpbCourts">
                    <?php foreach ($courts as $cid => $cname): ?>
                    <button type="button" class="pz-cpb-court" data-id="<?php echo (int)$cid; ?>"><?php echo esc_html($cname); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Riepilogo + errore -->
            <div class="pz-cpb-summary" id="pzCpbSummary">
                <div id="pzCpbSummaryText">—</div>
                <div class="pz-cpb-summary-price" id="pzCpbSummaryPrice">€0</div>
            </div>
            <div class="pz-cpb-error" id="pzCpbError"></div>

        </div>
    </div>

    <!-- CTA -->
    <div class="pz-cpb-cta-wrap">
        <div class="pz-cpb-cta-inner">
            <button class="pz-cpb-cta" id="pzCpbCta" type="button" disabled>Crea partita</button>
        </div>
    </div>

    <!-- Overlay successo -->
    <div class="pz-cpb-success" id="pzCpbSuccess">
        <div class="pz-cpb-success-card">
            <div class="pz-cpb-success-icon">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="pz-cpb-success-title">Partita creata!</h3>
            <div class="pz-cpb-success-tag">Pagamento in loco</div>
            <p class="pz-cpb-success-msg">La tua partita è visibile nella lobby. Gli altri giocatori potranno aggregarsi.</p>
            <div class="pz-cpb-success-detail" id="pzCpbSuccessDetail">—</div>
            <button class="pz-cpb-success-btn" type="button" id="pzCpbSuccessBtn">Vedi le partite</button>
        </div>
    </div>

    <script>
    (function(){
        var PZ  = <?php echo wp_json_encode($config); ?>;
        var DOW     = ['DOM','LUN','MAR','MER','GIO','VEN','SAB'];
        var MONTHS  = ['GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC'];
        var MONTHS_L= ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];

        var state = {
            serviceId: null,
            dateIso:   null,
            duration:  60,
            time:      null,
            courtId:   null,
            slots:     null,
        };

        // ── Date ──────────────────────────────────────────────────────────
        function buildDates() {
            var arr = [], now = new Date();
            for (var i = 0; i < PZ.daysAhead; i++) {
                var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
                arr.push({
                    iso:    d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'),
                    dow:    DOW[d.getDay()],
                    day:    d.getDate(),
                    month:  MONTHS[d.getMonth()],
                    monthL: MONTHS_L[d.getMonth()],
                });
            }
            return arr;
        }
        var DATES = buildDates();

        function renderDates() {
            var root = document.getElementById('pzCpbDates');
            root.innerHTML = '';
            DATES.forEach(function(d) {
                var el = document.createElement('button');
                el.type = 'button';
                el.className = 'pz-cpb-date' + (d.iso === state.dateIso ? ' is-active' : '');
                el.innerHTML =
                    '<div class="pz-cpb-date-dow">' + d.dow + '</div>' +
                    '<div class="pz-cpb-date-day">' + d.day + '</div>' +
                    '<div class="pz-cpb-date-mo">'  + d.month + '</div>';
                el.addEventListener('click', function() {
                    state.dateIso = d.iso;
                    state.time    = null;
                    state.courtId = null;
                    renderDates();
                    updateDurationToggle();
                    renderCourts();
                    loadAvailability();
                    updateCta();
                });
                root.appendChild(el);
            });
        }

        // ── Livelli ───────────────────────────────────────────────────────
        document.querySelectorAll('.pz-cpb-level').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.pz-cpb-level').forEach(function(b){ b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                state.serviceId = parseInt(btn.getAttribute('data-service-id'), 10);
                updateCta();
            });
        });

        // ── Durata ────────────────────────────────────────────────────────
        // Regola: 60 min disponibile solo lun-ven prima delle 17:00
        function is60MinAllowed() {
            if (!state.dateIso) return true; // non ancora scelto, mostra entrambi
            var d = new Date(state.dateIso + 'T12:00:00');
            var dow = d.getDay(); // 0=dom, 6=sab
            if (dow === 0 || dow === 6) return false; // weekend
            if (state.time) {
                var h = parseInt(state.time.split(':')[0], 10);
                if (h >= 17) return false; // feriale ma >= 17:00
            }
            return true;
        }

        function updateDurationToggle() {
            var allowed = is60MinAllowed();
            var btn60 = document.querySelector('.pz-cpb-duration-opt[data-min="60"]');
            var btn90 = document.querySelector('.pz-cpb-duration-opt[data-min="90"]');
            if (!allowed) {
                // Forza 90 min
                if (state.duration === 60) {
                    state.duration = 90;
                    state.time     = null;
                    state.courtId  = null;
                }
                btn60.disabled = true;
                btn60.style.opacity = '0.35';
                btn60.title = 'Non disponibile nel weekend e dopo le 17:00';
                btn90.classList.add('is-active');
                btn60.classList.remove('is-active');
            } else {
                btn60.disabled = false;
                btn60.style.opacity = '';
                btn60.title = '';
            }
            // Aggiorna classe is-active
            document.querySelectorAll('.pz-cpb-duration-opt').forEach(function(b) {
                b.classList.toggle('is-active', parseInt(b.getAttribute('data-min'), 10) === state.duration);
            });
        }

        document.querySelectorAll('.pz-cpb-duration-opt').forEach(function(b) {
            b.addEventListener('click', function() {
                if (b.disabled) return;
                var min = parseInt(b.getAttribute('data-min'), 10);
                if (min === state.duration) return;
                state.duration = min;
                state.time     = null;
                state.courtId  = null;
                updateDurationToggle();
                renderCourts();
                loadAvailability();
                updateCta();
            });
        });

        // ── Orari ─────────────────────────────────────────────────────────
        function renderTimes(loading) {
            var root = document.getElementById('pzCpbTimes');
            if (loading) {
                root.innerHTML = '<div class="pz-cpb-loading">Carico disponibilità…</div>';
                return;
            }
            if (!state.slots) {
                root.innerHTML = '<div class="pz-cpb-loading" style="color:#C5C9D2">Seleziona prima la data</div>';
                return;
            }
            root.innerHTML = '';
            Object.keys(state.slots).forEach(function(t) {
                var info = state.slots[t];
                var el   = document.createElement('button');
                el.type  = 'button';
                el.className = 'pz-cpb-time'
                    + (!info.available       ? ' is-disabled' : '')
                    + (state.time === t && info.available ? ' is-active' : '');
                el.disabled = !info.available;
                el.setAttribute('data-time', t);
                el.textContent = t;
                root.appendChild(el);
            });
        }

        document.getElementById('pzCpbTimes').addEventListener('click', function(e) {
            var btn = e.target.closest('.pz-cpb-time');
            if (!btn || btn.disabled || btn.classList.contains('is-disabled')) return;
            var t = btn.getAttribute('data-time');
            if (!t || !state.slots || !state.slots[t]) return;
            state.time = t;
            if (state.courtId && state.slots[t].courts.indexOf(state.courtId) === -1) {
                state.courtId = null;
            }
            updateDurationToggle();
            renderTimes(false);
            renderCourts();
            updateCta();
        });

        // ── Campi ─────────────────────────────────────────────────────────
        function renderCourts() {
            var btns = document.querySelectorAll('.pz-cpb-court');
            var availableForSlot = null;
            if (state.time && state.slots && state.slots[state.time]) {
                availableForSlot = state.slots[state.time].courts;
            }
            btns.forEach(function(b) {
                var id = parseInt(b.getAttribute('data-id'), 10);
                b.classList.remove('is-active','is-disabled');
                if (availableForSlot && availableForSlot.indexOf(id) === -1) b.classList.add('is-disabled');
                if (state.courtId === id) b.classList.add('is-active');
                b.onclick = function() {
                    if (b.classList.contains('is-disabled')) return;
                    if (!state.time) { flashError('Scegli prima un orario'); return; }
                    state.courtId = id;
                    renderCourts();
                    updateCta();
                };
            });
        }

        // ── Disponibilità AJAX ────────────────────────────────────────────
        var availXhr;
        function loadAvailability() {
            if (!state.dateIso) { state.slots = null; renderTimes(false); return; }
            renderTimes(true);
            if (availXhr) availXhr.abort();
            availXhr = new XMLHttpRequest();
            availXhr.open('POST', PZ.ajaxUrl, true);
            availXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            availXhr.onload = function() {
                try {
                    var res = JSON.parse(availXhr.responseText);
                    if (res && res.success) {
                        state.slots = res.data.slots;
                        renderTimes(false);
                        renderCourts();
                    } else {
                        flashError(res && res.data ? res.data : 'Errore disponibilità');
                        state.slots = null; renderTimes(false);
                    }
                } catch(e) { flashError('Errore di connessione'); state.slots = null; renderTimes(false); }
            };
            availXhr.send('action=pz_public_availability&nonce=' + encodeURIComponent(PZ.nonce)
                + '&date=' + encodeURIComponent(state.dateIso)
                + '&duration=' + state.duration);
        }

        // ── Riepilogo + CTA ───────────────────────────────────────────────
        function updateCta() {
            var ready = state.serviceId && state.dateIso && state.time && state.courtId;
            document.getElementById('pzCpbCta').disabled = !ready;

            var sum = document.getElementById('pzCpbSummary');
            if (!ready) { sum.classList.remove('is-visible'); return; }

            var d     = DATES.find(function(x){ return x.iso === state.dateIso; });
            var price = PZ.prices[state.serviceId] || 0;
            var hh    = parseInt(state.time.split(':')[0], 10);
            var mm    = parseInt(state.time.split(':')[1], 10);
            var endM  = hh*60 + mm + state.duration;
            var endS  = String(Math.floor(endM/60)).padStart(2,'0') + ':' + String(endM%60).padStart(2,'0');
            var lv    = PZ.services[state.serviceId] ? PZ.services[state.serviceId].label : '';

            document.getElementById('pzCpbSummaryText').textContent =
                lv + ' · ' + (d ? d.dow + ' ' + d.day + ' ' + d.monthL : '') + ' · ' + state.time + '–' + endS;
            document.getElementById('pzCpbSummaryPrice').textContent =
                '€' + price.toFixed(2).replace('.', ',');
            sum.classList.add('is-visible');
        }

        // ── Submit ────────────────────────────────────────────────────────
        document.getElementById('pzCpbCta').addEventListener('click', function() {
            var btn = this;
            if (btn.disabled) return;
            btn.disabled    = true;
            btn.textContent = 'Creazione in corso…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', PZ.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && res.success) {
                        showSuccess(res.data);
                    } else {
                        flashError((res && res.data) ? res.data : 'Errore creazione partita');
                        btn.disabled    = false;
                        btn.textContent = 'Crea partita';
                    }
                } catch(e) {
                    flashError('Errore di connessione');
                    btn.disabled    = false;
                    btn.textContent = 'Crea partita';
                }
            };
            xhr.onerror = function() {
                flashError('Errore di rete');
                btn.disabled    = false;
                btn.textContent = 'Crea partita';
            };
            xhr.send(
                'action=pz_public_book'
                + '&nonce='      + encodeURIComponent(PZ.nonce)
                + '&service_id=' + state.serviceId
                + '&date='       + encodeURIComponent(state.dateIso)
                + '&time='       + encodeURIComponent(state.time)
                + '&duration='   + state.duration
                + '&court_id='   + state.courtId
            );
        });

        // ── Successo ──────────────────────────────────────────────────────
        function showSuccess(data) {
            var d    = DATES.find(function(x){ return x.iso === state.dateIso; });
            var hh   = parseInt(state.time.split(':')[0], 10);
            var mm   = parseInt(state.time.split(':')[1], 10);
            var endM = hh*60 + mm + state.duration;
            var endS = String(Math.floor(endM/60)).padStart(2,'0') + ':' + String(endM%60).padStart(2,'0');
            var lv   = PZ.services[state.serviceId] ? PZ.services[state.serviceId].label : '';

            document.getElementById('pzCpbSuccessDetail').innerHTML =
                '<strong>' + lv + '</strong><br>'
                + (d ? d.dow + ' ' + d.day + ' ' + d.monthL : '') + '<br>'
                + state.time + '–' + endS + '<br>'
                + (data.court_name || '') + '<br>'
                + '<strong>€' + (data.price || 0).toFixed(2).replace('.', ',') + ' · Paga in loco</strong>';

            document.getElementById('pzCpbSuccess').classList.add('is-open');
        }

        document.getElementById('pzCpbSuccessBtn').addEventListener('click', function() {
            window.location.href = '<?php echo esc_js( pz_app_url("partite-pubbliche/") ); ?>';
        });

        // ── Errori ────────────────────────────────────────────────────────
        var errTimer;
        function flashError(msg) {
            var el = document.getElementById('pzCpbError');
            el.textContent = msg;
            el.classList.add('is-visible');
            clearTimeout(errTimer);
            errTimer = setTimeout(function(){ el.classList.remove('is-visible'); }, 4500);
        }

        // ── Init ──────────────────────────────────────────────────────────
        state.dateIso = DATES[0].iso;
        renderDates();
        updateDurationToggle();
        renderCourts();
        loadAvailability();
        updateCta();
    })();
    </script>

    <?php
    return ob_get_clean();
}


/* ============================================================
 *  AJAX 1 — Disponibilità slot (riusa la logica di private-booking)
 * ============================================================ */
add_action('wp_ajax_pz_public_availability', 'pz_cpb_ajax_availability');

function pz_cpb_ajax_availability() {
    check_ajax_referer('pz_public_booking', 'nonce');

    $date     = isset($_POST['date'])     ? sanitize_text_field($_POST['date'])  : '';
    $duration = isset($_POST['duration']) ? (int)$_POST['duration']              : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wp_send_json_error('Data non valida');
    if (!in_array($duration, [60, 90], true))         wp_send_json_error('Durata non valida');
    if (strtotime($date) < strtotime(date('Y-m-d')))  wp_send_json_error('Data nel passato');

    global $wpdb;
    $prefix      = PZ_DB_PREFIX;
    $courts      = pz_cpb_courts();
    $court_ids   = array_keys($courts);
    $ph          = implode(',', array_fill(0, count($court_ids), '%d'));

    $tz_local = wp_timezone();

    // Amelia salva in locale — il range giornaliero è in locale
    $day_start = (new DateTime($date . ' 00:00:00', $tz_local))->format('Y-m-d H:i:s');
    $day_end   = (new DateTime($date . ' 23:59:59', $tz_local))->format('Y-m-d H:i:s');

    $params = array_merge($court_ids, [$day_start, $day_end]);
    $apts   = $wpdb->get_results($wpdb->prepare(
        "SELECT locationId, bookingStart, bookingEnd
         FROM {$prefix}appointments
         WHERE locationId IN ($ph)
         AND bookingStart >= %s AND bookingStart <= %s
         AND status NOT IN ('canceled','rejected')",
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

    $weekday   = (int)date('N', strtotime($date)); // 1=lun … 7=dom
    $is_wday   = ($weekday >= 1 && $weekday <= 5);
    $is_weekend = ($weekday >= 6);
    $is_today  = ($date === (new DateTime('now', $tz_local))->format('Y-m-d'));
    $now_ts    = time();
    $slots     = [];

    for ($h = PZ_CPB_OPEN_HOUR; $h < PZ_CPB_CLOSE_HOUR; $h++) {
        foreach ([0, 30] as $m) {
            $ts  = sprintf('%02d:%02d', $h, $m);
            $sm  = $h * 60 + $m;
            $em  = $sm + $duration;
            if ($em > PZ_CPB_CLOSE_HOUR * 60) continue;

            // 60 min non disponibile: weekend oppure feriale dalle 17:00 in poi
            if ($duration === 60 && ($is_weekend || ($is_wday && $h >= 17))) {
                $slots[$ts] = ['available' => false, 'courts' => []];
                continue;
            }

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
}


/* ============================================================
 *  AJAX 2 — Crea appointment Amelia + customer_booking + pz_match
 * ============================================================ */
add_action('wp_ajax_pz_public_book', 'pz_cpb_ajax_book');

function pz_cpb_ajax_book() {
    check_ajax_referer('pz_public_booking', 'nonce');

    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id']          : 0;
    $date       = isset($_POST['date'])       ? sanitize_text_field($_POST['date']) : '';
    $time       = isset($_POST['time'])       ? sanitize_text_field($_POST['time']) : '';
    $duration   = isset($_POST['duration'])   ? (int)$_POST['duration']             : 0;
    $court_id   = isset($_POST['court_id'])   ? (int)$_POST['court_id']             : 0;

    $services = pz_cpb_services();
    $courts   = pz_cpb_courts();

    if (!isset($services[$service_id]))                     wp_send_json_error('Livello non valido');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))       wp_send_json_error('Data non valida');
    if (!preg_match('/^\d{2}:\d{2}$/', $time))             wp_send_json_error('Ora non valida');
    if (!in_array($duration, [60, 90], true))              wp_send_json_error('Durata non valida');
    if (!isset($courts[$court_id]))                        wp_send_json_error('Campo non valido');

    global $wpdb;
    $prefix = PZ_DB_PREFIX;

    // Customer Amelia
    $user  = wp_get_current_user();
    $email = $user->user_email;

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1", $email
    ));
    if (!$customer) {
        $first = $user->first_name ?: $user->display_name;
        $wpdb->insert("{$prefix}users", [
            'type'       => 'customer',
            'firstName'  => $first,
            'lastName'   => $user->last_name ?: '',
            'email'      => $email,
            'externalId' => $user->ID,
        ]);
        $customer_id = $wpdb->insert_id;
        if (!$customer_id) wp_send_json_error('Impossibile creare profilo cliente.');
    } else {
        $customer_id = (int)$customer->id;
    }

    // Amelia salva bookingStart/bookingEnd in timezone locale (Europe/Rome), non UTC
    $tz_local = wp_timezone();

    try {
        $start_local = new DateTime($date . ' ' . $time . ':00', $tz_local);
    } catch (Exception $e) {
        wp_send_json_error('Orario non valido');
    }
    if ($start_local->getTimestamp() < time()) wp_send_json_error('Slot già passato');

    $end_local     = (clone $start_local)->modify('+' . $duration . ' minutes');
    $booking_start = $start_local->format('Y-m-d H:i:s');   // locale, come Amelia
    $booking_end   = $end_local->format('Y-m-d H:i:s');     // locale, come Amelia

    // Concurrency check — confronto in locale come il DB
    $conflict = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}appointments
         WHERE locationId = %d AND status NOT IN ('canceled','rejected')
         AND bookingStart < %s AND bookingEnd > %s",
        $court_id, $booking_end, $booking_start
    ));
    if ($conflict > 0) wp_send_json_error('Slot non più disponibile, ricarica la pagina.');

    // Provider
    $provider_id = (int)$wpdb->get_var(
        "SELECT id FROM {$prefix}users WHERE type = 'provider' ORDER BY id ASC LIMIT 1"
    );
    if (!$provider_id) wp_send_json_error('Nessun provider configurato in Amelia.');

    // Prezzo
    $price = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT price FROM {$prefix}services WHERE id = %d", $service_id
    ));

    // Crea appointment
    $ok = $wpdb->insert("{$prefix}appointments", [
        'bookingStart'       => $booking_start,
        'bookingEnd'         => $booking_end,
        'notifyParticipants' => 0,
        'serviceId'          => $service_id,
        'providerId'         => $provider_id,
        'locationId'         => $court_id,
        'status'             => 'approved',
        'internalNotes'      => 'Partita pubblica creata da WP user #' . $user->ID . ' (paga in loco)',
    ]);
    if (!$ok) wp_send_json_error('Errore creazione appointment: ' . $wpdb->last_error);

    $apt_id = $wpdb->insert_id;

    // Crea customer_booking (creatore = primo partecipante, paga in loco)
    $bk_ok = $wpdb->insert("{$prefix}customer_bookings", [
        'appointmentId' => $apt_id,
        'customerId'    => $customer_id,
        'status'        => 'approved',
        'persons'       => 1,
        'price'         => $price,
        'created'       => current_time('mysql'),
    ]);
    if (!$bk_ok) {
        $wpdb->delete("{$prefix}appointments", ['id' => $apt_id]);
        wp_send_json_error('Errore creazione booking: ' . $wpdb->last_error);
    }

    // Sync → crea post pz_match (serviceId è in PZ_PUBLIC_SERVICE_IDS)
    if (function_exists('pz_sync_appointment')) {
        pz_sync_appointment($apt_id, $service_id);
    }

    error_log('PZ Public Booking: created apt #' . $apt_id . ' service #' . $service_id . ' by ' . $email);

    wp_send_json_success([
        'message'        => 'Partita creata!',
        'appointment_id' => $apt_id,
        'court_name'     => $courts[$court_id],
        'price'          => $price,
    ]);
}
