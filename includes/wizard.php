<?php
/**
 * PadelZero - Wizard scelta prenotazione
 * Shortcode: [pz_wizard]
 */

if (!defined('ABSPATH')) exit;

function pz_wz_routes() {
    return [
        'lezione'       => '/prenota-lezione/',
        'privata'       => '/prenota-partita-privata/',
        'pubblica_crea' => '/prenota-partita-pubblica/',
        'pubblica_join' => '/partite-pubbliche/',
    ];
}

add_shortcode('pz_wizard', 'pz_wz_render');

function pz_wz_render($atts) {

    if (!is_user_logged_in()) {
        return pz_render_login_wall(
            '',
            'Accedi per prenotare',
            'Per prenotare una partita o una lezione devi prima effettuare il login.',
            '/inizio/login/'
        );
    }

    if (function_exists('pz_ensure_amelia_customer')) {
        pz_ensure_amelia_customer(get_current_user_id());
    }

    $routes = pz_wz_routes();
    $home   = home_url('/');

    ob_start();
    ?>

    <style>
    /* ===== Wizard — CSS blindato contro override tema ===== */
    #pzWzWrap,#pzWzWrap *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    #pzWzWrap{max-width:480px !important;margin:0 auto !important;padding:0 0 160px !important;color:#161B2E !important;background:transparent !important;}

    /* Header */
    .pz-wz-header{display:flex !important;align-items:center !important;position:relative !important;min-height:44px !important;margin-bottom:14px !important;}
    .pz-wz-back{
      width:44px !important;height:44px !important;background:#FFFFFF !important;
      border:1.5px solid #D9DCE3 !important;border-radius:50% !important;
      display:flex !important;align-items:center !important;justify-content:center !important;
      cursor:pointer !important;box-shadow:none !important;padding:0 !important;
      flex-shrink:0 !important;position:relative !important;z-index:1 !important;
      transition:background .15s ease,border-color .15s ease !important;
    }
    .pz-wz-back svg{stroke:#8B92A5 !important;fill:none !important;width:18px !important;height:18px !important;}
    .pz-wz-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-wz-back.is-hidden{visibility:hidden !important;pointer-events:none !important;}
    .pz-wz-title{
      position:absolute !important;left:0 !important;right:0 !important;
      font-size:19px !important;font-weight:700 !important;letter-spacing:-0.02em !important;
      text-align:center !important;pointer-events:none !important;margin:0 !important;
      color:#161B2E !important;background:transparent !important;text-transform:none !important;
    }

    /* Subtitle */
    .pz-wz-sub{
      font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;
      margin:0 0 22px !important;padding:0 !important;
      background:transparent !important;text-transform:none !important;
    }

    /* Card step */
    .pz-wz-card{
      background:#FFFFFF !important;border-radius:20px !important;padding:16px !important;
      box-shadow:0 8px 30px -12px rgba(22,27,46,.10) !important;
      animation:pzWzFadeUp .4s ease both !important;border:none !important;
    }
    @keyframes pzWzFadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

    /* Choices */
    .pz-wz-choices{display:flex !important;flex-direction:column !important;gap:10px !important;}

    /* Singola card scelta */
    .pz-wz-choice{
      display:flex !important;align-items:center !important;gap:14px !important;
      width:100% !important;text-align:left !important;
      background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;
      border-radius:14px !important;padding:16px !important;
      cursor:pointer !important;box-shadow:none !important;text-decoration:none !important;
      transition:border-color .18s ease,transform .18s ease !important;
    }
    .pz-wz-choice:hover{border-color:#161B2E !important;transform:translateY(-1px) !important;background:#FFFFFF !important;}

    /* Icona */
    .pz-wz-icon{
      flex-shrink:0 !important;width:48px !important;height:48px !important;
      border-radius:12px !important;background:#E8F8EE !important;
      display:flex !important;align-items:center !important;justify-content:center !important;
    }
    .pz-wz-icon svg{width:24px !important;height:24px !important;stroke:#1FB856 !important;stroke-width:2;fill:none !important;stroke-linecap:round !important;stroke-linejoin:round !important;}
    .pz-wz-icon--fill svg,
    .pz-wz-icon--fill svg *{fill:#1FB856 !important;stroke:none !important;}
    .pz-wz-icon--fill svg{width:26px !important;height:26px !important;}

    /* Testo */
    .pz-wz-body{flex:1 !important;min-width:0 !important;display:block !important;}
    .pz-wz-label{
      display:block !important;font-size:15px !important;font-weight:700 !important;
      color:#161B2E !important;letter-spacing:-0.01em !important;
      margin:0 0 3px !important;line-height:1.3 !important;
      text-transform:none !important;background:transparent !important;
    }
    .pz-wz-desc{
      display:block !important;font-size:13px !important;font-weight:400 !important;
      color:#8B92A5 !important;line-height:1.4 !important;margin:0 !important;
      text-transform:none !important;background:transparent !important;
    }

    /* Freccia */
    .pz-wz-arrow{flex-shrink:0 !important;}
    .pz-wz-arrow svg{stroke:#D9DCE3 !important;fill:none !important;width:18px !important;height:18px !important;transition:stroke .18s ease !important;}
    .pz-wz-choice:hover .pz-wz-arrow svg{stroke:#161B2E !important;}
    </style>

    <div id="pzWzWrap">

      <div class="pz-wz-header">
        <button class="pz-wz-back is-hidden" type="button" id="pzWzBack" aria-label="Indietro">
          <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="pz-wz-title" id="pzWzTitle">Cosa vuoi prenotare?</div>
      </div>
      <p class="pz-wz-sub" id="pzWzSubtitle">Scegli il tipo di prenotazione per iniziare.</p>

      <!-- STEP 1 -->
      <div class="pz-wz-card" id="pzWzStep1">
        <div class="pz-wz-choices">
          <button type="button" class="pz-wz-choice" data-go="step2">
            <span class="pz-wz-icon pz-wz-icon--fill">
              <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="m435.6 76.4c-48-48-111.8-74.4-179.7-74.4s-131.6 26.4-179.6 74.4c-99.1 99-99.1 260.2 0 359.2 48 48 111.8 74.4 179.6 74.4 67.9 0 131.7-26.4 179.6-74.4 48-48 74.4-111.8 74.4-179.6.1-67.8-26.3-131.6-74.3-179.6zm-20 20c35.2 35.2 57.4 80.1 64 128.6-30.7-12.9-62.6-22.3-95.2-27.7-106.8-17.9-167.1-72.1-179.9-161.1 16.7-3.9 33.9-5.9 51.4-5.9 60.4-.1 117.1 23.4 159.7 66.1zm-379.5 108.2c88.9 12.8 143.1 73.1 161 179.8 5.5 32.6 14.8 64.5 27.7 95.2-48.5-6.6-93.4-28.7-128.6-64-57.1-57.1-77.1-137.5-60.1-211zm379.5 211.1c-42.5 42.5-98.9 65.9-159 66.1-15.1-32.6-25.8-66.9-31.7-102-24.1-144.7-108-190.9-180.5-202.6 10.9-29.5 28.2-57.2 51.9-80.9 23.3-23.3 50.8-40.8 80.8-52 11.7 72.5 57.8 156.5 202.6 180.7 35.1 5.9 69.4 16.5 102 31.7-.1 60.1-23.6 116.5-66.1 159z"/></svg>
            </span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Partita</span>
              <span class="pz-wz-desc">Prenota un campo per giocare</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
          <button type="button" class="pz-wz-choice" data-go="lezione">
            <span class="pz-wz-icon"><svg viewBox="0 0 24 24"><path d="M22 10v6"/><path d="M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Lezione</span>
              <span class="pz-wz-desc">Prenota una lezione con un maestro</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="pz-wz-card" id="pzWzStep2" style="display:none">
        <div class="pz-wz-choices">
          <button type="button" class="pz-wz-choice" data-go="privata">
            <span class="pz-wz-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M10 20v-6h4v6"/></svg></span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Partita Privata</span>
              <span class="pz-wz-desc">Gioco coi miei amici, prenoto io</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
          <button type="button" class="pz-wz-choice" data-go="step3">
            <span class="pz-wz-icon pz-wz-icon--fill">
              <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><g><path d="m47.06201 43.50049c-.51514 0-1.0166-.26514-1.2959-.74219-.41846-.71484-.17822-1.63379.53662-2.05225 3.43408-2.01074 5.56738-5.72998 5.56738-9.70605 0-6.19775-5.04248-11.24023-11.24023-11.24023-6.20312 0-11.25 5.04248-11.25 11.24023 0 .76123.07715 1.52441.22998 2.26904.16602.81152-.35693 1.604-1.16895 1.77051-.80713.16699-1.604-.35693-1.77051-1.16895-.19287-.94189-.29053-1.90771-.29053-2.87061 0-7.85205 6.39258-14.24023 14.25-14.24023 7.85205 0 14.24023 6.38818 14.24023 14.24023 0 5.03711-2.70215 9.74805-7.05176 12.29492-.23828.13965-.49902.20557-.75635.20557z"></path><path d="m51.16016 39.66064c-.15771 0-.31787-.0249-.47559-.07764-4.23486-1.41553-7.35303-4.6665-8.3418-8.6958-1.04492-4.21436.33008-8.68311 3.67969-11.95117.5918-.5791 1.54199-.56738 2.12109.02637.57861.59277.56689 1.54248-.02637 2.12109-2.57861 2.51611-3.64844 5.91211-2.86133 9.08545.74268 3.02734 3.12695 5.48145 6.37988 6.56836.78564.2627 1.20947 1.11279.94727 1.89844-.20996.62793-.79492 1.0249-1.42285 1.0249z"></path><path d="m21.65918 31.87598c-.06689 0-.13379-.00439-.20166-.01318-7.02783-.94482-12.32764-7.01611-12.32764-14.12256 0-7.85205 6.38818-14.24023 14.24023-14.24023s14.23975 6.38818 14.23975 14.24023c0 .50244-.0293 1.0083-.08936 1.54639-.0918.82324-.83154 1.41895-1.65723 1.32422-.82324-.0918-1.41602-.83398-1.32422-1.65723.04785-.42676.0708-.82324.0708-1.21338 0-6.19775-5.04199-11.24023-11.23975-11.24023s-11.24023 5.04248-11.24023 11.24023c0 5.61035 4.18164 10.40381 9.72705 11.14893.82129.11035 1.39746.86572 1.28711 1.68652-.10156.75342-.74561 1.30029-1.48486 1.30029z"></path><path d="m16.05908 29.68164c-.33545 0-.67285-.11182-.95215-.3418-.63965-.52588-.73145-1.47119-.20557-2.11133 1.92285-2.3374 2.64502-5.20898 1.98047-7.87842-.65625-2.66357-2.63477-4.86377-5.42871-6.03906-.76367-.32129-1.12256-1.20117-.80127-1.96436.32227-.76465 1.20215-1.12158 1.96436-.80127 3.67725 1.54736 6.29346 4.49365 7.17773 8.0835.89502 3.59668-.04346 7.42725-2.57568 10.50586-.29639.36035-.72607.54688-1.15918.54688z"></path><path d="m31.80908 23.31006c-.33447 0-.6709-.11133-.94971-.33984-1.97119-1.61475-3.3252-3.72705-3.91553-6.10938-.89404-3.58643.04492-7.41504 2.57666-10.50195.52393-.64111 1.46924-.73486 2.11084-.2085.64062.5249.73389 1.47021.2085 2.11084-1.92383 2.34619-2.64746 5.2168-1.98438 7.87598.43359 1.75146 1.43848 3.31104 2.90527 4.5127.64062.5249.73486 1.46973.20996 2.11084-.29688.36182-.72754.54932-1.16162.54932z"></path><path d="m42.45605 49.1499c-.51318 0-1.02539-.02832-1.53564-.08496-3.5708-.39697-6.6001-2.14209-8.53027-4.91309-2.44324-3.50879-2.60779-7.99817-.86462-11.96014-.70483-.10773-1.4209-.18097-2.1554-.18097-6.87415 0-12.62653 4.89459-13.95538 11.38165 4.40112-.28436 8.51953 1.49835 10.93488 4.96698 1.93018 2.77051 2.51611 6.2168 1.65039 9.7041-.19208.77405-.45972 1.52606-.78088 2.25623.70343.1073 1.41803.1803 2.151.1803 6.87524 0 12.62811-4.89624 13.95587-11.38483-.28986.01788-.5799.03473-.86993.03473z"></path><path d="m34.85205 42.43701c1.42871 2.05127 3.70166 3.34619 6.3999 3.64648.78027.08667 1.56854.07874 2.35394-.00238-.07281-5.98016-3.84845-11.0733-9.13629-13.11053-1.54437 3.13721-1.53247 6.71698.38245 9.46643z"></path><path d="m25.08838 57.34033c.6543-2.63525.22803-5.21582-1.20068-7.2666-1.88269-2.70483-5.20624-4.02863-8.75378-3.65149.06982 5.98444 3.84802 11.08154 9.13959 13.1189.34686-.70654.62628-1.44104.81488-2.20081z"></path></g></svg>
            </span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Partita Pubblica</span>
              <span class="pz-wz-desc">Gioco con altri giocatori del mio livello</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
        </div>
      </div>

      <!-- STEP 3 — prima Crea, poi Unisciti -->
      <div class="pz-wz-card" id="pzWzStep3" style="display:none">
        <div class="pz-wz-choices">
          <button type="button" class="pz-wz-choice" data-go="pubblica_crea">
            <span class="pz-wz-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Crea una partita aperta</span>
              <span class="pz-wz-desc">Lancia un invito ad altri giocatori</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
          <button type="button" class="pz-wz-choice" data-go="pubblica_join">
            <span class="pz-wz-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3 3-3-3"/><path d="M19 14V5"/></svg></span>
            <span class="pz-wz-body">
              <span class="pz-wz-label">Unisciti a una partita</span>
              <span class="pz-wz-desc">Cerca tra le partite del tuo livello</span>
            </span>
            <span class="pz-wz-arrow"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
        </div>
      </div>

    </div>

    <script>
    (function(){
      var ROUTES = <?php echo wp_json_encode($routes); ?>;
      var HOME   = <?php echo wp_json_encode($home); ?>;
      var TITLES = {
        1: { title:'Cosa vuoi prenotare?',      sub:'Scegli il tipo di prenotazione per iniziare.' },
        2: { title:'Che partita vuoi giocare?', sub:'Una partita privata o pubblica aperta a tutti?' },
        3: { title:'Crea o partecipa?',          sub:'Unisciti a una partita esistente o lanciarne una tua.' },
      };
      var stack  = [1];
      var $back  = document.getElementById('pzWzBack');
      var $title = document.getElementById('pzWzTitle');
      var $sub   = document.getElementById('pzWzSubtitle');

      function stepEl(n){ return document.getElementById('pzWzStep'+n); }

      function show(n){
        [1,2,3].forEach(function(k){
          var el = stepEl(k);
          if(el) el.style.display = (k===n)?'':'none';
        });
        $title.textContent = TITLES[n].title;
        $sub.textContent   = TITLES[n].sub;
        $back.classList.toggle('is-hidden', stack.length < 2);
        var cur = stepEl(n);
        if(cur){ cur.style.animation='none'; void cur.offsetWidth; cur.style.animation=''; }
      }

      function go(target){
        if(target==='step2'){ stack.push(2); show(2); return; }
        if(target==='step3'){ stack.push(3); show(3); return; }
        var slug = ROUTES[target];
        if(!slug) return;
        window.location.href = /^https?:/i.test(slug) ? slug : HOME.replace(/\/$/,'')+slug;
      }

      document.querySelectorAll('.pz-wz-choice').forEach(function(btn){
        btn.addEventListener('click', function(){ go(btn.getAttribute('data-go')); });
      });
      $back.addEventListener('click', function(){
        if(stack.length<2) return;
        stack.pop(); show(stack[stack.length-1]);
      });
      show(1);
    })();
    </script>

    <?php
    return ob_get_clean();
}
