<?php
/**
 * PadelZero - Rating utente
 * v4 — design allineato a wizard e lobby
 *
 * Espone:
 *   • pz_ur_request_modal()       attiva l'iniezione del modal in footer
 *   • pz_get_user_rating($uid)    float|null  (pz_get_all_levels() è in helpers.php)
 *   • pz_user_has_rating($uid)    bool
 *   • [pz_rating_setup]           pagina dedicata impostazione/modifica livello
 */

if (!defined('ABSPATH')) exit;


/* ============================================================
 *  HELPER — tutte in helpers.php:
 *  pz_get_all_levels(), pz_get_user_rating(), pz_user_has_rating()
 * ============================================================ */


/* ============================================================
 *  MODAL AUTO-INJECT in footer
 * ============================================================ */
function pz_ur_request_modal() {
    $GLOBALS['pz_ur_modal_needed'] = true;
}

add_action('wp_footer', 'pz_ur_inject_modal', 99);

function pz_ur_inject_modal() {
    if (empty($GLOBALS['pz_ur_modal_needed'])) return;
    if (!is_user_logged_in())                  return;
    if (pz_user_has_rating())                  return;

    $snooze = (int)get_user_meta(get_current_user_id(), 'pz_rating_snooze', true);
    if ($snooze && $snooze > time())           return;

    echo pz_ur_render(true);
}


/* ============================================================
 *  SHORTCODE [pz_rating_setup]
 * ============================================================ */
add_shortcode('pz_rating_setup', function() {
    if (!is_user_logged_in()) {
        return '<div style="padding:20px;background:#f8d7da;border-radius:12px;font-family:\'DM Sans\',-apple-system,sans-serif">'
             . '❌ Per impostare il livello devi <a href="' . esc_url(wp_login_url(get_permalink())) . '">accedere</a>.'
             . '</div>';
    }
    return pz_ur_render(false);
});


/* ============================================================
 *  RENDER — usato sia dal modal overlay che dalla pagina embed
 * ============================================================ */
function pz_ur_render($is_overlay = true) {
    $levels  = pz_get_all_levels();
    $current = pz_get_user_rating();
    $config  = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pz_user_rating'),
    ];

    ob_start();
    ?>

    <style>
    /* ===== PZ User Rating — CSS blindato v4 ===== */
    #pzUrWrap,#pzUrWrap *,
    #pzUrOverlay,#pzUrOverlay *{
        box-sizing:border-box !important;
        font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;
    }

    /* Overlay */
    #pzUrOverlay{
        position:fixed !important;inset:0 !important;
        background:rgba(22,27,46,.6) !important;
        z-index:99998 !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        padding:20px !important;
        backdrop-filter:blur(4px) !important;-webkit-backdrop-filter:blur(4px) !important;
    }

    /* Card modale / wrap pagina */
    #pzUrCard{
        background:#FFFFFF !important;border-radius:24px !important;
        max-width:440px !important;width:100% !important;
        padding:28px 24px 24px !important;
        max-height:90vh !important;overflow-y:auto !important;
        box-shadow:0 20px 60px -12px rgba(22,27,46,.28) !important;
        animation:pzUrFadeUp .3s ease both !important;
        color:#161B2E !important;
    }
    /* Wrap pagina (non overlay) */
    #pzUrWrap{
        max-width:480px !important;margin:0 auto !important;
        padding:0 0 80px !important;color:#161B2E !important;
        background:transparent !important;
    }

    @keyframes pzUrFadeUp{
        from{opacity:0;transform:translateY(12px)}
        to{opacity:1;transform:translateY(0)}
    }

    /* Header pagina (solo embed) */
    .pz-ur-header{
        display:flex !important;align-items:center !important;
        position:relative !important;min-height:44px !important;margin-bottom:6px !important;
    }
    .pz-ur-back{
        width:44px !important;height:44px !important;background:#FFFFFF !important;
        border:1.5px solid #D9DCE3 !important;border-radius:50% !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        cursor:pointer !important;padding:0 !important;flex-shrink:0 !important;
        position:relative !important;z-index:1 !important;text-decoration:none !important;
        transition:background .15s ease,border-color .15s ease !important;
    }
    .pz-ur-back svg{stroke:#8B92A5 !important;fill:none !important;width:18px !important;height:18px !important;}
    .pz-ur-back:hover{background:#F4F5F8 !important;border-color:#8B92A5 !important;}
    .pz-ur-page-title{
        position:absolute !important;left:0 !important;right:0 !important;
        font-size:19px !important;font-weight:700 !important;letter-spacing:-0.02em !important;
        text-align:center !important;pointer-events:none !important;margin:0 !important;
        color:#161B2E !important;background:transparent !important;text-transform:none !important;
    }
    .pz-ur-page-sub{
        font-size:14px !important;color:#8B92A5 !important;line-height:1.5 !important;
        margin:0 0 24px !important;padding:0 !important;
        background:transparent !important;text-transform:none !important;
    }

    /* Icona modale */
    .pz-ur-icon{
        width:52px !important;height:52px !important;border-radius:50% !important;
        background:#E8F8EE !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        margin:0 auto 14px !important;
    }
    .pz-ur-icon svg{
        width:26px !important;height:26px !important;
        stroke:#1FB856 !important;stroke-width:2.2 !important;fill:none !important;
        stroke-linecap:round !important;stroke-linejoin:round !important;
    }
    .pz-ur-modal-title{
        font-size:20px !important;font-weight:700 !important;
        text-align:center !important;margin:0 0 6px !important;
        color:#161B2E !important;letter-spacing:-0.02em !important;
        text-transform:none !important;background:transparent !important;
    }
    .pz-ur-modal-sub{
        font-size:14px !important;color:#8B92A5 !important;
        text-align:center !important;line-height:1.5 !important;
        margin:0 0 22px !important;background:transparent !important;
        text-transform:none !important;
    }

    /* Liste livelli */
    .pz-ur-levels{display:flex !important;flex-direction:column !important;gap:10px !important;}

    /* Card livello */
    .pz-ur-level{
        display:flex !important;align-items:center !important;gap:14px !important;
        width:100% !important;text-align:left !important;
        background:#FFFFFF !important;border:1.5px solid #D9DCE3 !important;
        border-radius:14px !important;padding:14px 16px !important;
        cursor:pointer !important;
        transition:border-color .18s ease,transform .18s ease,background .18s ease !important;
    }
    .pz-ur-level:hover{border-color:#161B2E !important;transform:translateY(-1px) !important;}
    .pz-ur-level.is-active{
        border-color:#1FB856 !important;background:#FAFFF4 !important;
    }

    /* Dot livello */
    .pz-ur-dot{
        flex-shrink:0 !important;width:12px !important;height:12px !important;
        border-radius:50% !important;
    }

    /* Testo */
    .pz-ur-info{flex:1 !important;min-width:0 !important;}
    .pz-ur-info-top{
        display:flex !important;align-items:baseline !important;
        justify-content:space-between !important;gap:10px !important;margin-bottom:3px !important;
    }
    .pz-ur-name{
        font-size:15px !important;font-weight:500 !important;color:#161B2E !important;
        text-transform:none !important;background:transparent !important;
    }
    .pz-ur-range{
        font-size:11px !important;font-weight:600 !important;color:#8B92A5 !important;
        background:#F4F5F8 !important;padding:2px 8px !important;border-radius:6px !important;
        white-space:nowrap !important;
    }
    .pz-ur-level.is-active .pz-ur-range{
        background:#D9F5E3 !important;color:#1FB856 !important;
    }
    .pz-ur-desc{
        font-size:12.5px !important;font-weight:400 !important;color:#8B92A5 !important;
        line-height:1.4 !important;margin:0 !important;
        text-transform:none !important;background:transparent !important;
    }

    /* Check attivo */
    .pz-ur-check{
        flex-shrink:0 !important;width:20px !important;height:20px !important;
        border-radius:50% !important;border:1.5px solid #D9DCE3 !important;
        display:flex !important;align-items:center !important;justify-content:center !important;
        transition:border-color .15s,background .15s !important;
    }
    .pz-ur-level.is-active .pz-ur-check{
        border-color:#1FB856 !important;background:#1FB856 !important;
    }
    .pz-ur-check::after{
        content:"" !important;width:8px !important;height:8px !important;
        border-radius:50% !important;background:#fff !important;display:none !important;
    }
    .pz-ur-level.is-active .pz-ur-check::after{display:block !important;}

    /* Footer azioni */
    .pz-ur-footer{
        display:flex !important;gap:10px !important;margin-top:20px !important;align-items:center !important;
    }
    .pz-ur-skip{
        flex-shrink:0 !important;background:none !important;border:none !important;
        color:#8B92A5 !important;font-size:13px !important;font-weight:500 !important;
        cursor:pointer !important;padding:10px 4px !important;
        text-decoration:underline !important;
        transition:color .15s !important;
    }
    .pz-ur-skip:hover{color:#161B2E !important;}
    .pz-ur-save{
        flex:1 !important;background:#9FD731 !important;color:#FFFFFF !important;
        border:none !important;border-radius:12px !important;
        font-size:14px !important;font-weight:700 !important;
        letter-spacing:.04em !important;text-transform:uppercase !important;
        padding:14px !important;cursor:pointer !important;
        box-shadow:0 4px 12px -4px rgba(159,215,49,.5) !important;
        transition:background .15s,opacity .15s !important;
    }
    .pz-ur-save:hover:not(:disabled){background:#8BC41F !important;}
    .pz-ur-save:disabled{
        background:#D9DCE3 !important;color:#8B92A5 !important;
        cursor:not-allowed !important;box-shadow:none !important;
    }

    /* Badge livello attuale (pagina embed) */
    .pz-ur-current-badge{
        display:flex !important;align-items:center !important;gap:10px !important;
        background:#F4F5F8 !important;border-radius:12px !important;
        padding:12px 16px !important;margin-bottom:20px !important;
    }
    .pz-ur-current-badge-dot{
        width:12px !important;height:12px !important;border-radius:50% !important;flex-shrink:0 !important;
    }
    .pz-ur-current-badge-text{font-size:13px !important;color:#8B92A5 !important;font-weight:500 !important;}
    .pz-ur-current-badge-val{font-size:14px !important;font-weight:700 !important;color:#161B2E !important;margin-left:auto !important;}
    </style>

    <?php if ($is_overlay): ?>

    <div id="pzUrOverlay">
        <div id="pzUrCard">
            <div class="pz-ur-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2 15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <p class="pz-ur-modal-title">Qual è il tuo livello?</p>
            <p class="pz-ur-modal-sub">Aiutaci a trovare le partite giuste per te.<br>Puoi cambiarlo in qualsiasi momento.</p>

            <?php echo pz_ur_levels_html($levels, $current); ?>

            <div class="pz-ur-footer">
                <button type="button" class="pz-ur-skip" id="pzUrSkip">Imposta più tardi</button>
                <button type="button" class="pz-ur-save" id="pzUrSave" disabled>Salva</button>
            </div>
        </div>
    </div>

    <?php else: ?>

    <div id="pzUrWrap">

        <!-- Header -->
        <div class="pz-ur-header">
            <a href="javascript:history.back()" class="pz-ur-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pz-ur-page-title">Il mio livello</p>
        </div>
        <p class="pz-ur-page-sub">Scegli la fascia che ti rappresenta meglio.<br>Serve per trovare le partite giuste per te.</p>

        <?php
        // Badge livello attuale se già impostato
        if ($current) {
            $badge_color = '#8B92A5';
            $badge_label = $current;
            foreach ($levels as $lvl) {
                if ($current >= $lvl['rating_min'] && $current <= $lvl['rating_max']) {
                    $badge_color = $lvl['color'];
                    $badge_label = $lvl['label'];
                    break;
                }
            }
        ?>
        <div class="pz-ur-current-badge">
            <span class="pz-ur-current-badge-dot" style="background:<?php echo esc_attr($badge_color); ?>"></span>
            <span class="pz-ur-current-badge-text">Livello attuale</span>
            <span class="pz-ur-current-badge-val"><?php echo esc_html($badge_label); ?></span>
        </div>
        <?php } ?>

        <?php echo pz_ur_levels_html($levels, $current); ?>

        <div class="pz-ur-footer">
            <button type="button" class="pz-ur-save" id="pzUrSave" <?php echo !$current ? 'disabled' : ''; ?>>
                <?php echo $current ? 'Aggiorna livello' : 'Salva'; ?>
            </button>
        </div>

    </div>

    <?php endif; ?>

    <script>
    (function(){
        var CFG     = <?php echo wp_json_encode($config); ?>;
        var picked  = <?php echo $current ? (float)$current : 'null'; ?>;
        var btns    = document.querySelectorAll('.pz-ur-level');
        var saveBtn = document.getElementById('pzUrSave');
        var overlay = document.getElementById('pzUrOverlay');
        var skipBtn = document.getElementById('pzUrSkip');

        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                btns.forEach(function(b){ b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                picked = parseFloat(btn.getAttribute('data-rating'));
                saveBtn.disabled = false;
            });
        });

        saveBtn.addEventListener('click', function() {
            if (!picked) return;
            saveBtn.disabled    = true;
            saveBtn.textContent = 'Salvataggio…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        if (overlay) { overlay.style.display = 'none'; }
                        location.reload();
                    } else {
                        alert(res.data || 'Errore salvataggio');
                        saveBtn.disabled    = false;
                        saveBtn.textContent = 'Salva';
                    }
                } catch(e) {
                    alert('Errore di connessione');
                    saveBtn.disabled    = false;
                    saveBtn.textContent = 'Salva';
                }
            };
            xhr.send('action=pz_save_rating&rating=' + picked + '&nonce=' + encodeURIComponent(CFG.nonce));
        });

        if (skipBtn) {
            skipBtn.addEventListener('click', function() {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', CFG.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (overlay) overlay.style.display = 'none';
                };
                xhr.send('action=pz_snooze_rating&nonce=' + encodeURIComponent(CFG.nonce));
            });
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}


/* ============================================================
 *  HELPER — HTML lista livelli (condiviso tra overlay e embed)
 * ============================================================ */
function pz_ur_levels_html($levels, $current) {
    ob_start();
    ?>
    <div class="pz-ur-levels">
        <?php foreach ($levels as $lvl):
            $is_active = $current && ($current >= $lvl['rating_min'] && $current <= $lvl['rating_max']);
        ?>
        <button type="button"
                class="pz-ur-level<?php echo $is_active ? ' is-active' : ''; ?>"
                data-rating="<?php echo (float)$lvl['rating_default']; ?>">
            <span class="pz-ur-dot" style="background:<?php echo esc_attr($lvl['color']); ?>"></span>
            <span class="pz-ur-info">
                <span class="pz-ur-info-top">
                    <span class="pz-ur-name"><?php echo esc_html($lvl['label']); ?></span>
                    <span class="pz-ur-range"><?php echo esc_html($lvl['label']); ?></span>
                </span>
                <span class="pz-ur-desc"><?php echo esc_html($lvl['description']); ?></span>
            </span>
            <span class="pz-ur-check"></span>
        </button>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}


/* ============================================================
 *  AJAX — salva rating
 * ============================================================ */
add_action('wp_ajax_pz_save_rating', function() {
    check_ajax_referer('pz_user_rating', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $rating = isset($_POST['rating']) ? (float)$_POST['rating'] : 0;
    if ($rating < 1.0 || $rating > 7.0) wp_send_json_error('Rating non valido');

    update_user_meta(get_current_user_id(), 'pz_user_rating', $rating);
    delete_user_meta(get_current_user_id(), 'pz_rating_snooze');

    wp_send_json_success(['rating' => $rating]);
});


/* ============================================================
 *  AJAX — snooze 7 giorni
 * ============================================================ */
add_action('wp_ajax_pz_snooze_rating', function() {
    check_ajax_referer('pz_user_rating', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    update_user_meta(get_current_user_id(), 'pz_rating_snooze', time() + 7 * DAY_IN_SECONDS);
    wp_send_json_success();
});


/* ============================================================
 *  ADMIN — campo rating nel profilo utente
 * ============================================================ */
add_action('show_user_profile', 'pz_ur_admin_field');
add_action('edit_user_profile', 'pz_ur_admin_field');

function pz_ur_admin_field($user) {
    $rating = pz_get_user_rating($user->ID);
    $levels = pz_get_all_levels();
    $label  = '—';
    if ($rating) {
        foreach ($levels as $lvl) {
            if ($rating >= $lvl['rating_min'] && $rating <= $lvl['rating_max']) {
                $label = $lvl['label'];
                break;
            }
        }
    }
    ?>
    <h2>PadelZero</h2>
    <table class="form-table">
        <tr>
            <th><label for="pz_user_rating">Livello Padel</label></th>
            <td>
                <input type="number" min="1" max="7" step="0.25"
                       name="pz_user_rating" id="pz_user_rating"
                       value="<?php echo esc_attr($rating ?: ''); ?>" class="small-text">
                <p class="description">
                    Fascia: <strong><?php echo esc_html($label); ?></strong> —
                    Auto-dichiarato dall'utente (1.0 – 7.0). Modifica per correggere manualmente.
                </p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update',  'pz_ur_admin_save');
add_action('edit_user_profile_update', 'pz_ur_admin_save');

function pz_ur_admin_save($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    $r = isset($_POST['pz_user_rating']) ? (float)$_POST['pz_user_rating'] : 0;
    if ($r >= 1.0 && $r <= 7.0) {
        update_user_meta($user_id, 'pz_user_rating', $r);
    } elseif (isset($_POST['pz_user_rating']) && $_POST['pz_user_rating'] === '') {
        delete_user_meta($user_id, 'pz_user_rating');
    }
}

