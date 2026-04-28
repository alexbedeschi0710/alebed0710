<?php
/**
 * PadelZero - PWA (Progressive Web App)
 *
 * Registra manifest.json e service worker per permettere
 * l'installazione dell'app sulla home screen del telefono.
 *
 * File da caricare:
 *   • manifest.json  → nella root del sito (public_html/)
 *   • pz-sw.js       → nella root del sito (public_html/)
 *   • questo file    → includes/pwa.php
 *
 * Aggiunge al bottom nav l'icona "App" al posto di Wallet.
 * Al click mostra un bottom sheet con istruzioni per
 * iOS (Safari) e Android (Chrome prompt nativo).
 */

if (!defined('ABSPATH')) exit;


/* ============================================================
 *  HEAD — manifest + meta PWA
 * ============================================================ */
add_action('wp_head', function() {
    $icon = 'https://padelzero.it/wp-content/uploads/2026/04/Icona-Padel-zero.png';
    ?>
    <!-- PWA PadelZero -->
    <link rel="manifest" href="<?php echo esc_url(home_url('/manifest.json')); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PadelZero">
    <meta name="theme-color" content="#1FB856">
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon); ?>">
    <?php
}, 5);


/* ============================================================
 *  FOOTER — registra service worker + bottom sheet installazione
 * ============================================================ */
add_action('wp_footer', function() {
    if (!is_user_logged_in()) return;
    ?>
    <style>
    /* ===== PWA Install Sheet ===== */
    #pzPwaSheet{
        display:none !important;position:fixed !important;inset:0 !important;
        background:rgba(22,27,46,.6) !important;z-index:999999 !important;
        align-items:flex-end !important;justify-content:center !important;
        backdrop-filter:blur(4px) !important;
        font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;
    }
    #pzPwaSheet.is-open{display:flex !important;}
    .pz-pwa-inner{
        background:#FFFFFF !important;border-radius:24px 24px 0 0 !important;
        padding:28px 24px 48px !important;width:100% !important;max-width:520px !important;
        animation:pzPwaUp .3s ease both !important;
        box-sizing:border-box !important;
    }
    .pz-pwa-inner *{box-sizing:border-box !important;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;}
    @keyframes pzPwaUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .pz-pwa-handle{width:40px !important;height:4px !important;background:#D9DCE3 !important;border-radius:2px !important;margin:0 auto 22px !important;}

    .pz-pwa-icon{
        width:72px !important;height:72px !important;border-radius:18px !important;
        overflow:hidden !important;margin:0 auto 16px !important;display:block !important;
        box-shadow:0 4px 16px -4px rgba(31,184,86,.35) !important;
    }
    .pz-pwa-icon img{width:100% !important;height:100% !important;object-fit:cover !important;display:block !important;}

    .pz-pwa-title{font-size:20px !important;font-weight:700 !important;color:#161B2E !important;text-align:center !important;margin:0 0 6px !important;}
    .pz-pwa-sub{font-size:14px !important;color:#8B92A5 !important;text-align:center !important;line-height:1.5 !important;margin:0 0 24px !important;}

    /* Steps iOS */
    .pz-pwa-steps{display:flex !important;flex-direction:column !important;gap:12px !important;margin-bottom:24px !important;}
    .pz-pwa-step{display:flex !important;align-items:center !important;gap:14px !important;}
    .pz-pwa-step-num{
        width:32px !important;height:32px !important;border-radius:50% !important;
        background:#E8F8EE !important;color:#1FB856 !important;
        font-size:14px !important;font-weight:700 !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        flex-shrink:0 !important;
    }
    .pz-pwa-step-text{font-size:14px !important;color:#161B2E !important;line-height:1.4 !important;}
    .pz-pwa-step-text strong{font-weight:700 !important;}

    /* Bottone Android */
    .pz-pwa-btn{
        width:100% !important;padding:16px !important;
        background:#9FD731 !important;color:#fff !important;
        border:none !important;border-radius:14px !important;
        font-size:15px !important;font-weight:700 !important;
        letter-spacing:.04em !important;text-transform:uppercase !important;
        cursor:pointer !important;
        box-shadow:0 8px 20px -6px rgba(159,215,49,.5) !important;
        transition:background .15s !important;
        margin-bottom:12px !important;
    }
    .pz-pwa-btn:hover{background:#8BC41F !important;}

    .pz-pwa-dismiss{
        width:100% !important;padding:14px !important;
        background:#F4F5F8 !important;color:#161B2E !important;
        border:none !important;border-radius:14px !important;
        font-size:14px !important;font-weight:600 !important;
        cursor:pointer !important;transition:background .15s !important;
    }
    .pz-pwa-dismiss:hover{background:#E8E9EC !important;}

    /* Badge "già installata" */
    .pz-pwa-installed{
        background:#E8F8EE !important;border-radius:12px !important;
        padding:14px 16px !important;text-align:center !important;
        font-size:14px !important;color:#1FB856 !important;font-weight:600 !important;
        margin-bottom:16px !important;
    }

    /* Sezione condizionale */
    .pz-pwa-android{display:none}
    .pz-pwa-ios{display:none}
    </style>

    <!-- Bottom sheet installazione PWA -->
    <div id="pzPwaSheet">
        <div class="pz-pwa-inner">
            <div class="pz-pwa-handle"></div>
            <div class="pz-pwa-icon">
                <img src="https://padelzero.it/wp-content/uploads/2026/04/Icona-Padel-zero.png" alt="PadelZero">
            </div>
            <p class="pz-pwa-title">Aggiungi alla Home</p>
            <p class="pz-pwa-sub">Usa PadelZero come un'app vera — niente browser, accesso diretto.</p>

            <!-- Android: prompt nativo -->
            <div class="pz-pwa-android" id="pzPwaAndroid">
                <button class="pz-pwa-btn" id="pzPwaInstallBtn">
                    📲 Installa l'app
                </button>
            </div>

            <!-- iOS: istruzioni manuali -->
            <div class="pz-pwa-ios" id="pzPwaIos">
                <div class="pz-pwa-steps">
                    <div class="pz-pwa-step">
                        <span class="pz-pwa-step-num">1</span>
                        <span class="pz-pwa-step-text">Tocca l'icona <strong>Condividi</strong> <span style="font-size:16px">⎋</span> in basso nel browser Safari</span>
                    </div>
                    <div class="pz-pwa-step">
                        <span class="pz-pwa-step-num">2</span>
                        <span class="pz-pwa-step-text">Scorri e seleziona <strong>"Aggiungi a schermata Home"</strong></span>
                    </div>
                    <div class="pz-pwa-step">
                        <span class="pz-pwa-step-num">3</span>
                        <span class="pz-pwa-step-text">Tocca <strong>"Aggiungi"</strong> in alto a destra</span>
                    </div>
                </div>
            </div>

            <!-- Già installata -->
            <div id="pzPwaAlready" style="display:none">
                <div class="pz-pwa-installed">✅ App già installata sulla tua home screen!</div>
            </div>

            <button class="pz-pwa-dismiss" id="pzPwaDismiss">Chiudi</button>
        </div>
    </div>

    <script>
    (function(){
        // ── Registra Service Worker ───────────────────────────────────────
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo esc_js(home_url('/pz-sw.js')); ?>')
                .then(function(reg) { console.log('PZ SW registered'); })
                .catch(function(err) { console.warn('PZ SW error:', err); });
        }

        // ── Rileva piattaforma ────────────────────────────────────────────
        var isIos     = /iphone|ipad|ipod/i.test(navigator.userAgent);
        var isAndroid = /android/i.test(navigator.userAgent);
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                        || window.navigator.standalone === true;

        var deferredPrompt = null;

        // Intercetta prompt Android
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
        });

        // ── Apri sheet ────────────────────────────────────────────────────
        window.pzOpenPwaSheet = function() {
            var sheet = document.getElementById('pzPwaSheet');

            if (isStandalone) {
                // Già installata
                document.getElementById('pzPwaAlready').style.display = 'block';
                document.getElementById('pzPwaAndroid').style.display = 'none';
                document.getElementById('pzPwaIos').style.display     = 'none';
            } else if (isIos) {
                document.getElementById('pzPwaIos').style.display     = 'block';
                document.getElementById('pzPwaAndroid').style.display = 'none';
            } else {
                // Android / Desktop Chrome
                document.getElementById('pzPwaAndroid').style.display = 'block';
                document.getElementById('pzPwaIos').style.display     = 'none';
            }

            sheet.classList.add('is-open');
        };

        // ── Bottone installa Android ──────────────────────────────────────
        var installBtn = document.getElementById('pzPwaInstallBtn');
        if (installBtn) {
            installBtn.addEventListener('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function(result) {
                        deferredPrompt = null;
                        if (result.outcome === 'accepted') {
                            document.getElementById('pzPwaSheet').classList.remove('is-open');
                        }
                    });
                } else {
                    // Prompt non disponibile (già installata o browser non supporta)
                    document.getElementById('pzPwaAndroid').style.display = 'none';
                    document.getElementById('pzPwaAlready').style.display = 'block';
                }
            });
        }

        // ── Chiudi ────────────────────────────────────────────────────────
        document.getElementById('pzPwaDismiss').addEventListener('click', function() {
            document.getElementById('pzPwaSheet').classList.remove('is-open');
        });
        document.getElementById('pzPwaSheet').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('is-open');
        });
    })();
    </script>
    <?php
}, 20);
