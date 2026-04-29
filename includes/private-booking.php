<?php
/**
 * PadelZero - Booking Partita Privata
 *
 * Shortcode:  [pz_book_private]
 *
 * Crea direttamente un appointment in Amelia con status='approved'
 * e un customer_booking collegato all'utente WordPress corrente.
 *
 * Pagamento: SOLO IN LOCO (per ora). La prenotazione resta a debito
 * e l'amministratore la riconcilia in Amelia.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 *  CONFIG
 * ============================================================ */

function pz_pb_services() {
    return [
        9  => 60,   // Partita da 1 ora
        10 => 90,   // Partita da 1 ora e mezza
    ];
}

function pz_pb_courts() {
    return [
        2 => 'Campo 1',
        3 => 'Campo 2',
        4 => 'Campo 3',
        5 => 'Campo 4',
    ];
}

define('PZ_PB_OPEN_HOUR',   8);
define('PZ_PB_CLOSE_HOUR', 23);
define('PZ_PB_DAYS_AHEAD', 14);


/* ============================================================
 *  SHORTCODE
 * ============================================================ */

add_shortcode('pz_book_private', 'pz_pb_render');

function pz_pb_render($atts) {

    if (!is_user_logged_in()) {
        return pz_render_login_wall('', 'Accedi per prenotare', 'Per prenotare una partita privata devi prima effettuare il login.', 'login/');
    }

    $courts        = pz_pb_courts();
    $services      = pz_pb_services();
    $service_prices = [];

    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    foreach (array_keys($services) as $sid) {
        $service_prices[$sid] = (float)$wpdb->get_var(
            $wpdb->prepare("SELECT price FROM {$prefix}services WHERE id = %d", $sid)
        );
    }

    $config = [
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('pz_private_booking'),
        'courts'    => $courts,
        'services'  => $services,
        'prices'    => $service_prices,
        'daysAhead' => PZ_PB_DAYS_AHEAD,
    ];

    ob_start();
    ?>

    <style>
    .pz-pb-wrap{
      --pz-green:        #1FB856;
      --pz-green-dark:   #15994A;
      --pz-green-soft:   #E8F8EE;
      --pz-ink:          #161B2E;
      --pz-muted:        #8B92A5;
      --pz-line:         #ECEEF2;
      --pz-line-strong:  #D9DCE3;
      --pz-bg:           #F4F5F8;
      --pz-white:        #FFFFFF;
      --pz-radius-lg:    20px;
      --pz-radius-md:    14px;
      --pz-radius-sm:    10px;

      max-width:480px;
      margin:0 auto;
      padding:0 0 160px !important;
      font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
      color:var(--pz-ink);
      -webkit-font-smoothing:antialiased;
      position:relative;
      box-sizing:border-box;
    }
    .pz-pb-wrap *,
    .pz-pb-wrap *::before,
    .pz-pb-wrap *::after{box-sizing:border-box}

    .pz-pb-login-wall{
      max-width:480px;margin:30px auto;padding:24px;
      background:#fff3cd;border:1px solid #ffc107;border-radius:14px;text-align:center;
      font-family:'DM Sans',sans-serif;
    }

    /* Header */
    .pz-pb-header{
      display:flex;align-items:center;
      position:relative;min-height:44px;margin-bottom:14px;
    }
    .pz-pb-back{
      width:44px !important;height:44px !important;
      background:#FFFFFF !important;
      border:1.5px solid #D9DCE3 !important;border-radius:50% !important;
      display:flex !important;align-items:center !important;justify-content:center !important;
      cursor:pointer !important;
      transition:background .15s ease,border-color .15s ease !important;
      box-shadow:none !important;padding:0 !important;flex-shrink:0 !important;
      position:relative;z-index:1;
    }
    .pz-pb-back svg{stroke:#8B92A5 !important;width:18px !important;height:18px !important;}
    .pz-pb-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-pb-title{
      position:absolute;left:0;right:0;
      font-size:19px;font-weight:700;letter-spacing:-0.02em;
      text-align:center;pointer-events:none;margin:0;
    }
    .pz-pb-subtitle{font-size:14px;color:var(--pz-muted);line-height:1.5;margin:0 0 22px;padding:0 4px}

    /* Card */
    .pz-pb-card{
      background:var(--pz-white);border-radius:var(--pz-radius-lg);
      padding:22px 18px 14px;box-shadow:0 8px 30px -12px rgba(22,27,46,.10);
      animation:pz-pb-fade-up .4s ease both;
    }
    @keyframes pz-pb-fade-up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

    /* Sezioni */
    .pz-pb-section{margin-bottom:24px}
    .pz-pb-section:last-child{margin-bottom:8px}
    .pz-pb-section-head{display:flex;align-items:center;gap:10px;margin-bottom:14px}
    .pz-pb-section-head svg{width:18px;height:18px;stroke:var(--pz-ink);stroke-width:2;fill:none;flex-shrink:0}
    .pz-pb-section-label{font-size:15px;font-weight:700;letter-spacing:-0.01em}

    /* Date picker */
    .pz-pb-dates{display:flex;gap:10px;overflow-x:auto;padding:2px 2px 8px;margin:0 -2px;scrollbar-width:none}
    .pz-pb-dates::-webkit-scrollbar{display:none}
    .pz-pb-date{
      flex:0 0 auto;width:78px;
      background:var(--pz-white) !important;color:var(--pz-muted) !important;
      border:1.5px solid var(--pz-line-strong) !important;border-radius:14px !important;
      padding:14px 10px 12px;text-align:center;cursor:pointer;
      transition:transform .15s ease,background .2s ease,color .2s ease,border-color .2s ease;
      user-select:none;font-family:inherit;box-shadow:none !important;
    }
    .pz-pb-date:hover:not(.is-active){transform:translateY(-1px);border-color:var(--pz-ink) !important}
    .pz-pb-date-dow,.pz-pb-date-mo{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
    .pz-pb-date-day{font-size:24px;font-weight:700;line-height:1.1;margin:6px 0 4px;color:var(--pz-ink) !important;letter-spacing:-0.02em}
    .pz-pb-date.is-active{background:var(--pz-green) !important;border-color:var(--pz-green) !important;color:#D6F5E0 !important;}
    .pz-pb-date.is-active .pz-pb-date-day{color:var(--pz-white) !important}

    /* Toggle durata */
    .pz-pb-duration{
      display:flex;background:var(--pz-bg) !important;
      border-radius:14px !important;padding:4px;gap:4px;
    }
    .pz-pb-duration-opt{
      flex:1;border:none !important;background:transparent !important;padding:11px 12px;
      font-size:14px;font-weight:600;font-family:inherit;
      color:var(--pz-muted) !important;border-radius:10px !important;cursor:pointer;
      transition:background .2s,color .2s,box-shadow .2s;box-shadow:none !important;
    }
    .pz-pb-duration-opt.is-active{
      background:var(--pz-white) !important;color:var(--pz-ink) !important;
      box-shadow:0 2px 8px -2px rgba(22,27,46,.12) !important;
    }

    /* Orari */
    .pz-pb-times{
      display:flex;gap:8px;overflow-x:auto;
      padding:2px 2px 8px;margin:0 -2px;min-height:60px;scrollbar-width:none;
    }
    .pz-pb-times::-webkit-scrollbar{display:none}
    .pz-pb-time{
      flex:0 0 auto;min-width:68px;
      border:1.5px solid var(--pz-line-strong) !important;
      background:var(--pz-white) !important;color:var(--pz-ink) !important;
      font-size:13px;font-weight:600;font-family:inherit;
      padding:11px 14px;border-radius:10px !important;cursor:pointer;
      transition:all .15s ease;letter-spacing:-0.01em;box-shadow:none !important;
    }
    .pz-pb-time:hover:not(.is-disabled):not(.is-active){border-color:var(--pz-ink) !important}
    .pz-pb-time.is-active{background:var(--pz-green) !important;border-color:var(--pz-green) !important;color:var(--pz-white) !important;}
    .pz-pb-time.is-disabled{background:var(--pz-bg) !important;color:#C5C9D2 !important;border-color:var(--pz-line) !important;cursor:not-allowed;text-decoration:line-through;}

    /* Loader */
    .pz-pb-loading{flex:1 1 auto;text-align:center;color:var(--pz-muted);padding:18px 0;font-size:13px;font-weight:500;}
    .pz-pb-loading::before{content:"";display:inline-block;width:14px;height:14px;border:2px solid var(--pz-line-strong);border-top-color:var(--pz-green);border-radius:50%;animation:pz-pb-spin .7s linear infinite;vertical-align:middle;margin-right:8px;}
    @keyframes pz-pb-spin{to{transform:rotate(360deg)}}

    /* Campi */
    .pz-pb-courts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .pz-pb-court{
      border:1.5px solid var(--pz-line-strong) !important;background:var(--pz-white) !important;
      border-radius:14px !important;padding:22px 12px;
      font-size:13.5px;font-weight:600;font-family:inherit;color:var(--pz-ink) !important;
      cursor:pointer;transition:all .15s ease;text-align:center;line-height:1.35;box-shadow:none !important;
    }
    .pz-pb-court:hover:not(.is-disabled):not(.is-active){border-color:var(--pz-ink) !important}
    .pz-pb-court.is-active{background:var(--pz-green) !important;border-color:var(--pz-green) !important;color:var(--pz-white) !important;}
    .pz-pb-court.is-disabled{background:var(--pz-bg) !important;color:#C5C9D2 !important;border-color:var(--pz-line) !important;cursor:not-allowed;}

    /* Riepilogo */
    .pz-pb-summary{
      display:flex;align-items:center;justify-content:space-between;
      padding:0;background:var(--pz-green-soft);border-radius:var(--pz-radius-md);
      font-size:13px;color:var(--pz-ink);font-weight:500;opacity:0;max-height:0;
      overflow:hidden;margin-top:0;
      transition:opacity .25s ease,max-height .25s ease,margin-top .25s ease,padding .25s ease;
    }
    .pz-pb-summary.is-visible{opacity:1;max-height:80px;margin-top:14px;padding:14px 16px}
    .pz-pb-summary-price{font-weight:700;color:var(--pz-green-dark);font-size:15px}

    /* CTA fisso */
    .pz-pb-cta-wrap{
      position:fixed;bottom:64px;left:0;right:0;width:100%;box-sizing:border-box;
      background:var(--pz-white);border-top:1px solid var(--pz-line);
      padding:16px 18px;z-index:99;
    }
    .pz-pb-cta-wrap *,.pz-pb-cta-wrap *::before,.pz-pb-cta-wrap *::after{box-sizing:border-box}
    .pz-pb-cta-inner{max-width:480px;margin:0 auto;width:100%}
    .pz-pb-cta{
      width:100%;display:block;box-sizing:border-box;
      border:none !important;background:var(--pz-green) !important;color:var(--pz-white) !important;
      font-family:inherit;font-size:15px;font-weight:700;letter-spacing:.08em;
      text-transform:uppercase;padding:17px;border-radius:14px !important;
      cursor:pointer;transition:all .2s;
      box-shadow:0 10px 24px -8px rgba(31,184,86,.55) !important;
    }
    .pz-pb-cta:hover:not(:disabled){background:var(--pz-green-dark) !important;transform:translateY(-1px)}
    .pz-pb-cta:disabled{background:#D5D8DE !important;color:#9097A5 !important;cursor:not-allowed;box-shadow:none !important;}

    /* Overlay successo */
    .pz-pb-success{position:fixed;inset:0;background:rgba(22,27,46,.55);z-index:999;display:none;align-items:center;justify-content:center;padding:24px;}
    .pz-pb-success.is-open{display:flex}
    .pz-pb-success-card{background:#fff;border-radius:var(--pz-radius-lg);max-width:380px;width:100%;padding:34px 26px 26px;text-align:center;animation:pz-pb-fade-up .3s ease both;}
    .pz-pb-success-icon{width:64px;height:64px;border-radius:50%;background:var(--pz-green-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;}
    .pz-pb-success-icon svg{width:32px;height:32px;stroke:var(--pz-green);stroke-width:3;fill:none}
    .pz-pb-success-title{font-size:20px;font-weight:700;margin:0 0 8px}
    .pz-pb-success-msg{font-size:14px;color:var(--pz-muted);line-height:1.5;margin:0 0 18px}
    .pz-pb-success-detail{background:var(--pz-bg);border-radius:var(--pz-radius-md);padding:14px;font-size:13.5px;font-weight:600;margin-bottom:18px;}
    .pz-pb-success-tag{display:inline-block;background:#FEF3C7;color:#92400E;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:4px 10px;border-radius:6px;margin-bottom:12px;}
    .pz-pb-success-btn{width:100%;background:var(--pz-green);color:#fff;border:none;font-family:inherit;font-size:14px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:14px;border-radius:var(--pz-radius-md);cursor:pointer;transition:background .2s;}
    .pz-pb-success-btn:hover{background:var(--pz-green-dark)}

    /* Errore */
    .pz-pb-error{background:#FEE2E2;color:#991B1B;border-radius:var(--pz-radius-sm);padding:10px 14px;font-size:13px;font-weight:500;margin-top:12px;display:none;}
    .pz-pb-error.is-visible{display:block}
    </style>

    <div class="pz-pb-wrap" id="pzPbWrap">

      <!-- HEADER -->
      <div class="pz-pb-header">
        <button class="pz-pb-back" type="button" aria-label="Indietro" onclick="history.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="pz-pb-title">Prenota Partita</div>
      </div>
      <p class="pz-pb-subtitle">Scegli data, durata, ora e campo per la tua partita privata.</p>

      <!-- CARD -->
      <div class="pz-pb-card">

        <!-- Data -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-head">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="pz-pb-section-label">Seleziona data</span>
          </div>
          <div class="pz-pb-dates" id="pzPbDates"></div>
        </div>

        <!-- Durata -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-head">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="pz-pb-section-label">Durata</span>
          </div>
          <div class="pz-pb-duration" id="pzPbDuration">
            <button type="button" class="pz-pb-duration-opt is-active" data-min="60">1 ora</button>
            <button type="button" class="pz-pb-duration-opt" data-min="90">1 ora e mezza</button>
          </div>
        </div>

        <!-- Ora -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-head">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="pz-pb-section-label">Seleziona orario</span>
          </div>
          <div class="pz-pb-times" id="pzPbTimes"></div>
        </div>

        <!-- Campo -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-head">
            <svg viewBox="0 0 24 24"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>
            <span class="pz-pb-section-label">Scegli il campo</span>
          </div>
          <div class="pz-pb-courts" id="pzPbCourts">
            <?php foreach ($courts as $cid => $cname): ?>
              <button type="button" class="pz-pb-court" data-id="<?php echo (int)$cid; ?>"><?php echo esc_html($cname); ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Riepilogo + errori -->
        <div class="pz-pb-summary" id="pzPbSummary">
          <div id="pzPbSummaryText">—</div>
          <div class="pz-pb-summary-price" id="pzPbSummaryPrice">€0</div>
        </div>
        <div class="pz-pb-error" id="pzPbError"></div>
      </div>
    </div>

    <!-- CTA -->
    <div class="pz-pb-cta-wrap">
      <div class="pz-pb-cta-inner">
        <button class="pz-pb-cta" id="pzPbCta" type="button" disabled>Prenota</button>
      </div>
    </div>

    <!-- Overlay successo -->
    <div class="pz-pb-success" id="pzPbSuccess">
      <div class="pz-pb-success-card">
        <div class="pz-pb-success-icon">
          <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3 class="pz-pb-success-title">Prenotazione confermata!</h3>
        <div class="pz-pb-success-tag">Pagamento in loco</div>
        <p class="pz-pb-success-msg">Ti aspettiamo al centro. Ricordati di pagare alla cassa al tuo arrivo.</p>
        <div class="pz-pb-success-detail" id="pzPbSuccessDetail">—</div>
        <button class="pz-pb-success-btn" type="button" id="pzPbSuccessBtn">Ok, fatto</button>
      </div>
    </div>

    <script>
    (function(){
      var PZ = <?php echo wp_json_encode($config); ?>;

      var DOW      = ['DOM','LUN','MAR','MER','GIO','VEN','SAB'];
      var MONTHS   = ['GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC'];
      var MONTHS_L = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];

      var state = {
        dateIso:  null,
        duration: 60,
        time:     null,
        courtId:  null,
        slots:    null,
      };

      // ── Date ──────────────────────────────────────────────────────────
      function buildDates(){
        var arr = [], now = new Date();
        for (var i = 0; i < PZ.daysAhead; i++){
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

      function renderDates(){
        var root = document.getElementById('pzPbDates');
        root.innerHTML = '';
        DATES.forEach(function(d){
          var el = document.createElement('button');
          el.type = 'button';
          el.className = 'pz-pb-date' + (d.iso === state.dateIso ? ' is-active' : '');
          el.innerHTML =
            '<div class="pz-pb-date-dow">' + d.dow + '</div>' +
            '<div class="pz-pb-date-day">' + d.day + '</div>' +
            '<div class="pz-pb-date-mo">' + d.month + '</div>';
          el.addEventListener('click', function(){
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

      // ── Durata ────────────────────────────────────────────────────────
      // Regola: 60 min solo lun-ven prima delle 17:00
      function is60MinAllowed(){
        if (!state.dateIso) return true;
        var d = new Date(state.dateIso + 'T12:00:00');
        var dow = d.getDay(); // 0=dom, 6=sab
        if (dow === 0 || dow === 6) return false;
        if (state.time){
          var h = parseInt(state.time.split(':')[0], 10);
          if (h >= 17) return false;
        }
        return true;
      }

      function updateDurationToggle(){
        var allowed = is60MinAllowed();
        var btn60 = document.querySelector('.pz-pb-duration-opt[data-min="60"]');
        var btn90 = document.querySelector('.pz-pb-duration-opt[data-min="90"]');
        if (!allowed){
          if (state.duration === 60){
            state.duration = 90;
            state.time     = null;
            state.courtId  = null;
          }
          btn60.disabled = true;
          btn60.style.opacity = '0.35';
          btn60.title = 'Non disponibile nel weekend e dopo le 17:00';
        } else {
          btn60.disabled = false;
          btn60.style.opacity = '';
          btn60.title = '';
        }
        document.querySelectorAll('.pz-pb-duration-opt').forEach(function(b){
          b.classList.toggle('is-active', parseInt(b.getAttribute('data-min'), 10) === state.duration);
        });
      }

      function renderDuration(){
        document.querySelectorAll('.pz-pb-duration-opt').forEach(function(b){
          b.addEventListener('click', function(){
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
      }

      // ── Orari ─────────────────────────────────────────────────────────
      function renderTimes(loading){
        var root = document.getElementById('pzPbTimes');
        if (loading){ root.innerHTML = '<div class="pz-pb-loading">Carico disponibilità…</div>'; return; }
        if (!state.slots){ root.innerHTML = '<div class="pz-pb-loading" style="color:#C5C9D2">Seleziona prima la data</div>'; return; }
        root.innerHTML = '';
        Object.keys(state.slots).forEach(function(t){
          var info = state.slots[t];
          var el = document.createElement('button');
          el.type = 'button';
          el.className = 'pz-pb-time'
            + (!info.available ? ' is-disabled' : '')
            + (state.time === t && info.available ? ' is-active' : '');
          el.disabled = !info.available;
          el.setAttribute('data-time', t);
          el.textContent = t;
          root.appendChild(el);
        });
      }

      // ── Campi ─────────────────────────────────────────────────────────
      function renderCourts(){
        var btns = document.querySelectorAll('.pz-pb-court');
        var availableForSlot = null;
        if (state.time && state.slots && state.slots[state.time]){
          availableForSlot = state.slots[state.time].courts;
        }
        btns.forEach(function(b){
          var id = parseInt(b.getAttribute('data-id'), 10);
          b.classList.remove('is-active','is-disabled');
          if (availableForSlot && availableForSlot.indexOf(id) === -1) b.classList.add('is-disabled');
          if (state.courtId === id) b.classList.add('is-active');
          b.onclick = function(){
            if (b.classList.contains('is-disabled')) return;
            if (!state.time){ flashError('Scegli prima un orario'); return; }
            state.courtId = id;
            renderCourts();
            updateCta();
          };
        });
      }

      // ── Riepilogo + CTA ───────────────────────────────────────────────
      function updateCta(){
        var ready = state.dateIso && state.time && state.courtId;
        document.getElementById('pzPbCta').disabled = !ready;
        var sum = document.getElementById('pzPbSummary');
        if (!ready){ sum.classList.remove('is-visible'); return; }

        var d = DATES.find(function(x){ return x.iso === state.dateIso; });
        var sid = -1;
        Object.keys(PZ.services).forEach(function(k){
          if (PZ.services[k] === state.duration) sid = parseInt(k,10);
        });
        var price = PZ.prices[sid] || 0;
        var hh = parseInt(state.time.split(':')[0], 10);
        var mm = parseInt(state.time.split(':')[1], 10);
        var endMin = hh*60 + mm + state.duration;
        var endStr = String(Math.floor(endMin/60)).padStart(2,'0') + ':' + String(endMin%60).padStart(2,'0');

        document.getElementById('pzPbSummaryText').textContent =
          (d ? d.dow + ' ' + d.day + ' ' + d.monthL : '') + ' · ' + state.time + '–' + endStr + ' · ' + (PZ.courts[state.courtId] || '');
        document.getElementById('pzPbSummaryPrice').textContent =
          '€' + price.toFixed(2).replace('.', ',');
        sum.classList.add('is-visible');
      }

      // ── Disponibilità AJAX ────────────────────────────────────────────
      var availXhr;
      function loadAvailability(){
        if (!state.dateIso){ state.slots = null; renderTimes(false); return; }
        renderTimes(true);
        if (availXhr) availXhr.abort();
        availXhr = new XMLHttpRequest();
        availXhr.open('POST', PZ.ajaxUrl, true);
        availXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        availXhr.onload = function(){
          try {
            var res = JSON.parse(availXhr.responseText);
            if (res && res.success){ state.slots = res.data.slots; renderTimes(false); renderCourts(); }
            else { flashError(res && res.data ? res.data : 'Errore disponibilità'); state.slots = null; renderTimes(false); }
          } catch(e){ flashError('Errore di connessione'); state.slots = null; renderTimes(false); }
        };
        availXhr.send('action=pz_private_availability&nonce=' + encodeURIComponent(PZ.nonce)
          + '&date=' + encodeURIComponent(state.dateIso)
          + '&duration=' + state.duration);
      }

      // ── Submit ────────────────────────────────────────────────────────
      document.getElementById('pzPbCta').addEventListener('click', function(){
        var btn = this;
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Prenotazione in corso…';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', PZ.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function(){
          try {
            var res = JSON.parse(xhr.responseText);
            if (res && res.success){ showSuccess(res.data); }
            else { flashError((res && res.data) ? res.data : 'Errore prenotazione'); btn.disabled = false; btn.textContent = 'Prenota'; }
          } catch(e){ flashError('Errore di connessione'); btn.disabled = false; btn.textContent = 'Prenota'; }
        };
        xhr.onerror = function(){ flashError('Errore di rete'); btn.disabled = false; btn.textContent = 'Prenota'; };
        xhr.send('action=pz_private_book'
          + '&nonce='    + encodeURIComponent(PZ.nonce)
          + '&date='     + encodeURIComponent(state.dateIso)
          + '&time='     + encodeURIComponent(state.time)
          + '&duration=' + state.duration
          + '&court_id=' + state.courtId);
      });

      // ── Successo ──────────────────────────────────────────────────────
      function showSuccess(data){
        var d = DATES.find(function(x){ return x.iso === state.dateIso; });
        var hh = parseInt(state.time.split(':')[0], 10);
        var mm = parseInt(state.time.split(':')[1], 10);
        var endMin = hh*60 + mm + state.duration;
        var endStr = String(Math.floor(endMin/60)).padStart(2,'0') + ':' + String(endMin%60).padStart(2,'0');

        document.getElementById('pzPbSuccessDetail').innerHTML =
          (d ? d.dow + ' ' + d.day + ' ' + d.monthL : '') +
          '<br>' + state.time + '–' + endStr +
          '<br>' + (PZ.courts[state.courtId] || '') +
          '<br><strong>€' + (data.price || 0).toFixed(2).replace('.', ',') + '</strong>';
        document.getElementById('pzPbSuccess').classList.add('is-open');
      }
      document.getElementById('pzPbSuccessBtn').addEventListener('click', function(){ location.reload(); });

      // ── Errori ────────────────────────────────────────────────────────
      var errTimer;
      function flashError(msg){
        var el = document.getElementById('pzPbError');
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(errTimer);
        errTimer = setTimeout(function(){ el.classList.remove('is-visible'); }, 4500);
      }

      // ── Event delegation orari ────────────────────────────────────────
      document.getElementById('pzPbTimes').addEventListener('click', function(e){
        var btn = e.target.closest('.pz-pb-time');
        if (!btn) return;
        if (btn.disabled || btn.classList.contains('is-disabled')) return;
        var t = btn.getAttribute('data-time');
        if (!t || !state.slots || !state.slots[t]) return;
        state.time = t;
        var info = state.slots[t];
        if (state.courtId && info.courts.indexOf(state.courtId) === -1) state.courtId = null;
        updateDurationToggle();
        renderTimes(false);
        renderCourts();
        updateCta();
      });

      // ── Init ──────────────────────────────────────────────────────────
      state.dateIso = DATES[0].iso;
      renderDates();
      renderDuration();
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
 *  AJAX 1 — Disponibilità slot
 * ============================================================ */
add_action('wp_ajax_pz_private_availability', 'pz_pb_ajax_availability');

function pz_pb_ajax_availability() {
    check_ajax_referer('pz_private_booking', 'nonce');

    $date     = isset($_POST['date'])     ? sanitize_text_field($_POST['date']) : '';
    $duration = isset($_POST['duration']) ? (int)$_POST['duration']             : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wp_send_json_error('Data non valida');
    if (!in_array($duration, [60, 90], true))         wp_send_json_error('Durata non valida');
    if (strtotime($date) < strtotime(date('Y-m-d')))  wp_send_json_error('Data nel passato');

    global $wpdb;
    $prefix    = PZ_DB_PREFIX;
    $courts    = pz_pb_courts();
    $court_ids = array_keys($courts);
    $ph        = implode(',', array_fill(0, count($court_ids), '%d'));

    // Amelia salva in timezone locale — nessuna conversione UTC
    $tz_local = wp_timezone();

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

    $weekday   = (int)date('N', strtotime($date));
    $is_wday   = ($weekday >= 1 && $weekday <= 5);
    $is_weekend = ($weekday >= 6);
    $is_today  = ($date === (new DateTime('now', $tz_local))->format('Y-m-d'));
    $now_ts    = time();
    $slots     = [];

    for ($h = PZ_PB_OPEN_HOUR; $h < PZ_PB_CLOSE_HOUR; $h++) {
        foreach ([0, 30] as $m) {
            $ts  = sprintf('%02d:%02d', $h, $m);
            $sm  = $h * 60 + $m;
            $em  = $sm + $duration;
            if ($em > PZ_PB_CLOSE_HOUR * 60) continue;

            // 60 min: non disponibile nel weekend o feriale >= 17:00
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
 *  AJAX 2 — Crea la prenotazione
 * ============================================================ */
add_action('wp_ajax_pz_private_book', 'pz_pb_ajax_book');

function pz_pb_ajax_book() {
    check_ajax_referer('pz_private_booking', 'nonce');

    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $date     = isset($_POST['date'])     ? sanitize_text_field($_POST['date']) : '';
    $time     = isset($_POST['time'])     ? sanitize_text_field($_POST['time']) : '';
    $duration = isset($_POST['duration']) ? (int)$_POST['duration']             : 0;
    $court_id = isset($_POST['court_id']) ? (int)$_POST['court_id']             : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wp_send_json_error('Data non valida');
    if (!preg_match('/^\d{2}:\d{2}$/', $time))       wp_send_json_error('Ora non valida');
    if (!in_array($duration, [60, 90], true))        wp_send_json_error('Durata non valida');

    $courts = pz_pb_courts();
    if (!isset($courts[$court_id]))                  wp_send_json_error('Campo non valido');

    $services   = pz_pb_services();
    $service_id = (int)array_search($duration, $services, true);
    if (!$service_id)                                wp_send_json_error('Servizio non trovato');

    global $wpdb;
    $prefix = PZ_DB_PREFIX;

    $current_user = wp_get_current_user();
    $email        = $current_user->user_email;

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$prefix}users WHERE email = %s LIMIT 1", $email
    ));
    if (!$customer) {
        $first = $current_user->first_name ?: $current_user->display_name;
        $last  = $current_user->last_name ?: '';
        $wpdb->insert("{$prefix}users", [
            'type'       => 'customer',
            'firstName'  => $first,
            'lastName'   => $last,
            'email'      => $email,
            'externalId' => $current_user->ID,
        ]);
        $customer_id = $wpdb->insert_id;
        if (!$customer_id) wp_send_json_error('Impossibile creare profilo cliente. Contatta lo staff.');
    } else {
        $customer_id = (int)$customer->id;
    }

    // Amelia salva in timezone locale — nessuna conversione UTC
    $tz_local = wp_timezone();

    try {
        $start_local = new DateTime($date . ' ' . $time . ':00', $tz_local);
    } catch (Exception $e) {
        wp_send_json_error('Orario non valido');
    }
    if ($start_local->getTimestamp() < time()) wp_send_json_error('Slot già passato');

    $end_local     = (clone $start_local)->modify('+' . $duration . ' minutes');
    $booking_start = $start_local->format('Y-m-d H:i:s');  // locale, come Amelia
    $booking_end   = $end_local->format('Y-m-d H:i:s');    // locale, come Amelia

    // Concurrency check
    $conflict = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}appointments
         WHERE locationId = %d AND status NOT IN ('canceled','rejected')
         AND bookingStart < %s AND bookingEnd > %s",
        $court_id, $booking_end, $booking_start
    ));
    if ($conflict > 0) wp_send_json_error('Lo slot non è più disponibile, ricarica la pagina.');

    $provider_id = (int)$wpdb->get_var(
        "SELECT id FROM {$prefix}users WHERE type = 'provider' ORDER BY id ASC LIMIT 1"
    );
    if (!$provider_id) wp_send_json_error('Nessun provider configurato in Amelia.');

    $price = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT price FROM {$prefix}services WHERE id = %d", $service_id
    ));

    $apt_ok = $wpdb->insert("{$prefix}appointments", [
        'bookingStart'       => $booking_start,
        'bookingEnd'         => $booking_end,
        'notifyParticipants' => 0,
        'serviceId'          => $service_id,
        'providerId'         => $provider_id,
        'locationId'         => $court_id,
        'status'             => 'approved',
        'internalNotes'      => 'Prenotazione privata via PadelZero (WP user #' . get_current_user_id() . ', pagamento in loco)',
    ]);

    if (!$apt_ok) wp_send_json_error('Errore creazione appointment: ' . $wpdb->last_error);

    $apt_id = $wpdb->insert_id;

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

    error_log('PZ Private Booking: created apt #' . $apt_id . ' for user ' . $email);

    wp_send_json_success([
        'message'        => 'Prenotazione confermata!',
        'appointment_id' => $apt_id,
        'booking_start'  => $booking_start,
        'court_name'     => $courts[$court_id],
        'price'          => $price,
        'payment'        => 'onsite',
    ]);
}
