<?php
/**
 * PadelZero - Prenotazione Partita Privata
 * Shortcode: [pz_private_booking]
 */

if (!defined('ABSPATH')) exit;

add_shortcode('pz_private_booking', 'pz_pb_render');

/* ============================================================
 *  Costanti
 * ============================================================ */
if (!defined('PZ_PB_OPEN_HOUR'))  define('PZ_PB_OPEN_HOUR',  8);
if (!defined('PZ_PB_CLOSE_HOUR')) define('PZ_PB_CLOSE_HOUR', 22);
if (!defined('PZ_PB_DAYS_AHEAD')) define('PZ_PB_DAYS_AHEAD', 14);

/* ============================================================
 *  Helper — elenco campi e servizi
 * ============================================================ */
function pz_pb_courts() {
    return [
        2 => 'Campo 1',
        3 => 'Campo 2',
        4 => 'Campo 3',
        5 => 'Campo 4',
    ];
}

function pz_pb_services() {
    // service_id => durata in minuti
    return [
        2 => 60,
        3 => 90,
    ];
}

function pz_pb_service_prices() {
    global $wpdb;
    $prefix = PZ_DB_PREFIX;
    $ids    = array_keys(pz_pb_services());
    $ph     = implode(',', array_fill(0, count($ids), '%d'));
    $rows   = $wpdb->get_results(
        $wpdb->prepare("SELECT id, price FROM {$prefix}services WHERE id IN ($ph)", $ids)
    );
    $out = [];
    foreach ($rows as $r) $out[(int)$r->id] = (float)$r->price;
    return $out;
}

/* ============================================================
 *  Shortcode principale
 * ============================================================ */
function pz_pb_render($atts) {

    if (!is_user_logged_in()) {
        return pz_render_login_wall('', 'Prenota Partita', 'Accedi per prenotare un campo.', 'login/');
    }

    $courts         = pz_pb_courts();
    $services       = pz_pb_services();
    $service_prices = pz_pb_service_prices();

    $config = [
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('pz_private_booking'),
        'courts'    => $courts,
        'services'  => $services,
        'prices'    => $service_prices,
        'daysAhead' => PZ_PB_DAYS_AHEAD,
    ];

    pz_global_styles();
    ob_start();
    ?>

    <style>
    .pz-pb-wrap{
      --pz-green:        #1FB856;
      --pz-green-dark:   #15994A;
      --pz-green-soft:   #E8F8EE;
      --pz-ink:          #161B2E;
      --pz-muted:        #8B92A5;
      --pz-border:       #E2E5EC;
      --pz-white:        #FFFFFF;
      --pz-radius-lg:    14px;
      --pz-radius-md:    10px;
      --pz-radius-sm:    8px;
      --pz-font:         'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    }

    /* Login wall */
    .pz-pb-login-wall{
      max-width:480px;margin:30px auto;padding:24px;
      background:#fff3cd;border:1px solid #ffc107;border-radius:14px;text-align:center;
      font-family:'DM Sans',sans-serif;
    }

    /* → header/back/title/sub: vedi pz-global.php (.pz-g-*) */

    /* Card */
    .pz-pb-card{
      background:var(--pz-white);border-radius:var(--pz-radius-lg);
      padding:22px 18px 14px;box-shadow:0 8px 30px -12px rgba(22,27,46,.10);
      animation:pz-pb-fade-up .4s ease both;
    }
    @keyframes pz-pb-fade-up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

    /* Section header */
    .pz-pb-section{margin-bottom:22px;}
    .pz-pb-section-title{
      display:flex;align-items:center;gap:10px;
      font-size:16px;font-weight:700;color:var(--pz-ink);
      margin-bottom:14px;
    }
    .pz-pb-section-title svg{flex-shrink:0;stroke:var(--pz-ink);}

    /* Date strip */
    .pz-pb-dates{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;}
    .pz-pb-dates::-webkit-scrollbar{display:none;}
    .pz-pb-day{
      flex-shrink:0;width:72px;padding:10px 6px;
      border:1.5px solid var(--pz-border);border-radius:var(--pz-radius-md);
      background:var(--pz-white);cursor:pointer;text-align:center;
      transition:all .15s ease;
    }
    .pz-pb-day.active{background:var(--pz-ink);border-color:var(--pz-ink);}
    .pz-pb-day-name{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--pz-muted);}
    .pz-pb-day.active .pz-pb-day-name{color:rgba(255,255,255,.7);}
    .pz-pb-day-num{font-size:26px;font-weight:800;color:var(--pz-ink);line-height:1.1;}
    .pz-pb-day.active .pz-pb-day-num{color:#fff;}
    .pz-pb-day-month{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--pz-muted);}
    .pz-pb-day.active .pz-pb-day-month{color:rgba(255,255,255,.7);}

    /* Duration toggle */
    .pz-pb-duration{display:flex;gap:10px;}
    .pz-pb-dur{
      flex:1;padding:13px 10px;border:1.5px solid var(--pz-border);
      border-radius:var(--pz-radius-md);background:var(--pz-white);
      font-size:14px;font-weight:600;color:var(--pz-muted);
      cursor:pointer;text-align:center;transition:all .15s ease;
    }
    .pz-pb-dur.active{border-color:var(--pz-ink);background:var(--pz-ink);color:#fff;}
    .pz-pb-dur.disabled-slot{opacity:.4;cursor:not-allowed;}

    /* Time slots */
    .pz-pb-slots{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .pz-pb-slot{
      padding:11px 6px;border:1.5px solid var(--pz-border);
      border-radius:var(--pz-radius-md);background:var(--pz-white);
      font-size:14px;font-weight:600;color:var(--pz-ink);
      cursor:pointer;text-align:center;transition:all .15s ease;
    }
    .pz-pb-slot.active{border-color:var(--pz-green);background:var(--pz-green-soft);color:var(--pz-green-dark);}
    .pz-pb-slot.disabled-slot{
      background:#F6F7FA;color:var(--pz-muted);
      text-decoration:line-through;cursor:not-allowed;border-color:transparent;
    }
    .pz-pb-slots-loading{text-align:center;padding:20px;color:var(--pz-muted);font-size:14px;}

    /* Courts */
    .pz-pb-courts{display:flex;flex-direction:column;gap:8px;}
    .pz-pb-court{
      padding:14px 16px;border:1.5px solid var(--pz-border);
      border-radius:var(--pz-radius-md);background:var(--pz-white);
      font-size:15px;font-weight:600;color:var(--pz-ink);
      cursor:pointer;text-align:left;transition:all .15s ease;
      display:flex;align-items:center;justify-content:space-between;
    }
    .pz-pb-court.active{border-color:var(--pz-green);background:var(--pz-green-soft);}
    .pz-pb-court.disabled-slot{opacity:.4;cursor:not-allowed;background:#F6F7FA;}
    .pz-pb-court-check{
      width:20px;height:20px;border-radius:50%;
      border:1.5px solid var(--pz-border);
      display:flex;align-items:center;justify-content:center;
      transition:all .15s ease;flex-shrink:0;
    }
    .pz-pb-court.active .pz-pb-court-check{
      background:var(--pz-green);border-color:var(--pz-green);
    }
    .pz-pb-court.active .pz-pb-court-check::after{
      content:'';display:block;width:6px;height:6px;
      border-radius:50%;background:#fff;
    }

    /* Riepilogo */
    .pz-pb-summary{
      background:var(--pz-green-soft);border-radius:var(--pz-radius-md);
      padding:14px 16px;margin-bottom:20px;
      font-size:14px;color:var(--pz-ink);line-height:1.7;
    }
    .pz-pb-summary strong{font-weight:700;}

    /* Toast */
    .pz-pb-toast{
      position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);
      background:#1c1f26;color:#fff;padding:12px 20px;border-radius:10px;
      font-size:14px;font-weight:500;white-space:nowrap;
      opacity:0;transition:opacity .25s,transform .25s;pointer-events:none;z-index:9999;
    }
    .pz-pb-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
    </style>

    <div class="pz-g-wrap pz-pb-wrap" id="pzPbWrap">

      <!-- HEADER -->
      <div class="pz-g-header">
        <button class="pz-g-back" type="button" aria-label="Indietro" onclick="history.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="pz-g-title">Prenota Partita</div>
      </div>
      <p class="pz-g-sub">Scegli data, durata, ora e campo per la tua partita privata.</p>

      <!-- CARD -->
      <div class="pz-pb-card">

        <!-- Data -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Seleziona data
          </div>
          <div class="pz-pb-dates" id="pzPbDates"></div>
        </div>

        <!-- Durata -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Durata
          </div>
          <div class="pz-pb-duration" id="pzPbDuration">
            <?php foreach ($services as $sid => $mins): ?>
            <button type="button" class="pz-pb-dur" data-dur="<?php echo (int)$mins; ?>">
              <?php echo $mins === 60 ? '1 ORA' : '1 ORA E MEZZA'; ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Orario -->
        <div class="pz-pb-section">
          <div class="pz-pb-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Seleziona orario
          </div>
          <div class="pz-pb-slots" id="pzPbSlots">
            <div class="pz-pb-slots-loading">Seleziona data e durata</div>
          </div>
        </div>

        <!-- Campo -->
        <div class="pz-pb-section" id="pzPbCourtsSection" style="display:none">
          <div class="pz-pb-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="12" y1="3" x2="12" y2="21"/></svg>
            Seleziona campo
          </div>
          <div class="pz-pb-courts" id="pzPbCourts">
            <?php foreach ($courts as $cid => $cname): ?>
            <button type="button" class="pz-pb-court" data-id="<?php echo (int)$cid; ?>"><?php echo esc_html($cname); ?>
              <span class="pz-pb-court-check"></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /card -->

      <!-- CTA -->
      <div class="pz-g-cta-wrap">
        <div class="pz-g-cta-inner">
          <button type="button" class="pz-g-cta" id="pzPbCta" disabled>PRENOTA</button>
        </div>
      </div>

      <!-- Toast -->
      <div class="pz-pb-toast" id="pzPbToast"></div>

    </div><!-- /wrap -->

    <script>
    var PZ = <?php echo wp_json_encode($config); ?>;
    (function(){
      var wrap       = document.getElementById('pzPbWrap');
      var datesEl    = document.getElementById('pzPbDates');
      var slotsEl    = document.getElementById('pzPbSlots');
      var courtsWrap = document.getElementById('pzPbCourtsSection');
      var cta        = document.getElementById('pzPbCta');
      var toast      = document.getElementById('pzPbToast');

      var state = { date:null, dur:null, time:null, courtId:null, loadingSlots:false };

      /* ---- Dates ---- */
      (function buildDates(){
        var today = new Date(); today.setHours(0,0,0,0);
        var days  = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
        var months= ['GEN','FEB','MAR','APR','MAG','GIU','LUG','AGO','SET','OTT','NOV','DIC'];
        for(var i=0;i<PZ.daysAhead;i++){
          var d = new Date(today); d.setDate(today.getDate()+i);
          var btn = document.createElement('button');
          btn.type='button'; btn.className='pz-pb-day';
          btn.dataset.date = d.toISOString().slice(0,10);
          btn.innerHTML =
            '<div class="pz-pb-day-name">'+days[d.getDay()]+'</div>'+
            '<div class="pz-pb-day-num">'+d.getDate()+'</div>'+
            '<div class="pz-pb-day-month">'+months[d.getMonth()]+'</div>';
          btn.addEventListener('click', function(){
            document.querySelectorAll('.pz-pb-day').forEach(function(b){b.classList.remove('active');});
            this.classList.add('active');
            state.date = this.dataset.date;
            state.time = null; state.courtId = null;
            courtsWrap.style.display='none';
            updateCta();
            if(state.dur) loadAvailability();
            else slotsEl.innerHTML='<div class="pz-pb-slots-loading">Seleziona una durata</div>';
          });
          datesEl.appendChild(btn);
        }
        // Seleziona oggi
        var first = datesEl.querySelector('.pz-pb-day');
        if(first){ first.click(); }
      })();

      /* ---- Duration ---- */
      function updateDurationToggle(){
        var btns = document.querySelectorAll('.pz-pb-dur');
        btns.forEach(function(b){ b.classList.remove('disabled-slot'); });
        if(!state.date) return;
        var d    = new Date(state.date+'T00:00:00');
        var dow  = d.getDay(); // 0=Dom,6=Sab
        var isWe = (dow===0||dow===6);
        btns.forEach(function(b){
          var dur = parseInt(b.dataset.dur);
          if(dur===60 && isWe) b.classList.add('disabled-slot');
        });
      }

      document.querySelectorAll('.pz-pb-dur').forEach(function(b){
        b.addEventListener('click', function(){
          if(this.classList.contains('disabled-slot')) return;
          document.querySelectorAll('.pz-pb-dur').forEach(function(x){x.classList.remove('active');});
          this.classList.add('active');
          state.dur  = parseInt(this.dataset.dur);
          state.time = null; state.courtId = null;
          courtsWrap.style.display='none';
          updateCta();
          if(state.date) loadAvailability();
        });
      });

      /* ---- Slots ---- */
      function loadAvailability(){
        if(!state.date||!state.dur) return;
        if(state.loadingSlots) return;
        state.loadingSlots = true;
        slotsEl.innerHTML='<div class="pz-pb-slots-loading">Caricamento orari…</div>';
        var fd = new FormData();
        fd.append('action','pz_private_availability');
        fd.append('nonce', PZ.nonce);
        fd.append('date',  state.date);
        fd.append('duration', state.dur);
        fetch(PZ.ajaxUrl,{method:'POST',body:fd})
          .then(function(r){return r.json();})
          .then(function(res){
            state.loadingSlots=false;
            if(!res.success){slotsEl.innerHTML='<div class="pz-pb-slots-loading">Errore caricamento</div>';return;}
            renderSlots(res.data.slots);
          })
          .catch(function(){
            state.loadingSlots=false;
            slotsEl.innerHTML='<div class="pz-pb-slots-loading">Errore di rete</div>';
          });
      }

      function renderSlots(slots){
        slotsEl.innerHTML='';
        var keys = Object.keys(slots).sort();
        if(!keys.length){slotsEl.innerHTML='<div class="pz-pb-slots-loading">Nessuno slot disponibile</div>';return;}
        keys.forEach(function(ts){
          var s   = slots[ts];
          var btn = document.createElement('button');
          btn.type='button';
          btn.className='pz-pb-slot'+(s.available?'':' disabled-slot');
          btn.textContent=ts;
          btn.dataset.time=ts;
          btn.dataset.courts=JSON.stringify(s.courts);
          if(s.available){
            btn.addEventListener('click',function(){
              document.querySelectorAll('.pz-pb-slot').forEach(function(x){x.classList.remove('active');});
              this.classList.add('active');
              state.time    = this.dataset.time;
              state.courtId = null;
              renderCourts(JSON.parse(this.dataset.courts));
              courtsWrap.style.display='block';
              updateCta();
            });
          }
          slotsEl.appendChild(btn);
        });
      }

      /* ---- Courts ---- */
      function renderCourts(freeCourts){
        document.querySelectorAll('.pz-pb-court').forEach(function(b){
          b.classList.remove('active','disabled-slot');
          var cid = parseInt(b.dataset.id);
          if(!freeCourts||freeCourts.indexOf(cid)===-1) b.classList.add('disabled-slot');
        });
      }

      document.querySelectorAll('.pz-pb-court').forEach(function(b){
        b.addEventListener('click',function(){
          if(this.classList.contains('disabled-slot')) return;
          document.querySelectorAll('.pz-pb-court').forEach(function(x){x.classList.remove('active');});
          this.classList.add('active');
          state.courtId = parseInt(this.dataset.id);
          updateCta();
        });
      });

      /* ---- CTA ---- */
      function updateCta(){
        cta.disabled = !(state.date && state.dur && state.time && state.courtId);
      }

      cta.addEventListener('click', function(){
        if(cta.disabled) return;
        cta.disabled=true; cta.textContent='Prenotazione in corso…';
        var fd = new FormData();
        fd.append('action','pz_private_book');
        fd.append('nonce',   PZ.nonce);
        fd.append('date',    state.date);
        fd.append('time',    state.time);
        fd.append('duration',state.dur);
        fd.append('court_id',state.courtId);
        fetch(PZ.ajaxUrl,{method:'POST',body:fd})
          .then(function(r){return r.json();})
          .then(function(res){
            if(res.success){
              showToast('✅ Prenotazione confermata!');
              setTimeout(function(){window.location.href=res.data.redirect||'/app/prenotazioni/';},1500);
            } else {
              showToast('❌ '+(res.data||'Errore'));
              cta.disabled=false; cta.textContent='PRENOTA';
            }
          })
          .catch(function(){
            showToast('❌ Errore di rete');
            cta.disabled=false; cta.textContent='PRENOTA';
          });
      });

      /* ---- Toast ---- */
      function showToast(msg){
        toast.textContent=msg; toast.classList.add('show');
        setTimeout(function(){toast.classList.remove('show');},3000);
      }

      /* ---- Init ---- */
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

    $tz_local = wp_timezone();

    try {
        $start_local = new DateTime($date . ' ' . $time . ':00', $tz_local);
    } catch (Exception $e) {
        wp_send_json_error('Orario non valido');
    }
    if ($start_local->getTimestamp() < time()) wp_send_json_error('Slot già passato');

    $end_local     = (clone $start_local)->modify('+' . $duration . ' minutes');
    $booking_start = $start_local->format('Y-m-d H:i:s');
    $booking_end   = $end_local->format('Y-m-d H:i:s');

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
        'message'  => 'Prenotazione confermata',
        'apt_id'   => $apt_id,
        'redirect' => home_url('/app/prenotazioni/'),
    ]);
}
