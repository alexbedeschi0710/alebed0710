<?php
/**
 * PadelZero — Pagina Account
 * Shortcode [pz_account]
 *
 * pz_get_user_avatar_url() e' definita in bottom-nav.php
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 *  SHORTCODE [pz_account]
 * ============================================================ */
add_shortcode('pz_account', function() {
    if (!is_user_logged_in()) {
        return pz_render_login_wall('', 'Il mio account', 'Accedi per gestire il tuo profilo.', 'login/');
    }

    $user    = wp_get_current_user();
    $uid     = $user->ID;
    $fname   = get_user_meta($uid, 'first_name', true);
    $lname   = get_user_meta($uid, 'last_name', true);
    $phone   = get_user_meta($uid, 'pz_phone', true);
    $email   = $user->user_email;
    $avatar  = pz_get_user_avatar_url($uid, 128);

    $initial     = strtoupper(substr($fname ?: $user->display_name, 0, 1)) ?: '?';
    $placeholder = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        . '<rect fill="#E8F8EE" width="64" height="64" rx="32"/>'
        . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#1FB856" font-size="28" font-family="Arial">' . $initial . '</text>'
        . '</svg>'
    );
    $avatar_src = $avatar ?: $placeholder;

    $levels  = pz_get_all_levels();
    $current = pz_get_user_rating($uid);

    $config = [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('pz_user_rating'),
        'nonceProfile' => wp_create_nonce('pz_save_profile'),
        'nonceAvatar'  => wp_create_nonce('pz_upload_avatar'),
    ];

    ob_start();
    ?>
    <style>
    #pzAccount, #pzAccount * {
        box-sizing: border-box !important;
        font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    #pzAccount {
        max-width: 480px !important;
        margin: 0 auto !important;
        padding: 0 16px 100px !important;
        color: #161B2E !important;
    }
    .pza-header {
        display: flex !important; align-items: center !important;
        position: relative !important; min-height: 52px !important;
        margin-bottom: 24px !important;
    }
    .pza-back {
        width: 40px !important; height: 40px !important;
        background: #fff !important; border: 1.5px solid #D9DCE3 !important;
        border-radius: 50% !important;
        display: flex !important; align-items: center !important; justify-content: center !important;
        cursor: pointer !important; text-decoration: none !important;
        transition: background .15s, border-color .15s !important; flex-shrink: 0 !important;
    }
    .pza-back:hover { background: #F4F5F8 !important; border-color: #8B92A5 !important; }
    .pza-back svg { stroke: #8B92A5 !important; fill: none !important; width: 18px !important; height: 18px !important; }
    .pza-title {
        position: absolute !important; left: 0 !important; right: 0 !important;
        text-align: center !important; font-size: 17px !important; font-weight: 700 !important;
        letter-spacing: -0.02em !important; pointer-events: none !important;
        margin: 0 !important; color: #161B2E !important;
    }
    .pza-section {
        background: #fff !important; border: 1.5px solid #ECEEF2 !important;
        border-radius: 16px !important; padding: 16px !important; margin-bottom: 16px !important;
    }
    .pza-section-title {
        font-size: 11px !important; font-weight: 700 !important;
        letter-spacing: .08em !important; text-transform: uppercase !important;
        color: #8B92A5 !important; margin: 0 0 14px !important;
    }
    .pza-avatar-row { display: flex !important; align-items: center !important; gap: 16px !important; }
    .pza-avatar-wrap { position: relative !important; flex-shrink: 0 !important; }
    .pza-avatar-img {
        width: 72px !important; height: 72px !important;
        border-radius: 50% !important; object-fit: cover !important;
        border: 2px solid #ECEEF2 !important; display: block !important;
    }
    .pza-avatar-edit {
        position: absolute !important; bottom: 0 !important; right: 0 !important;
        width: 24px !important; height: 24px !important;
        background: #1FB856 !important; border-radius: 50% !important;
        display: flex !important; align-items: center !important; justify-content: center !important;
        cursor: pointer !important; border: 2px solid #fff !important;
    }
    .pza-avatar-edit svg { stroke: #fff !important; fill: none !important; width: 12px !important; height: 12px !important; }
    /* sr-only mobile-safe: visibile al sistema ma non all'utente */
    #pzAvatarInput {
        position: absolute !important;
        width: 1px !important; height: 1px !important;
        padding: 0 !important; margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0,0,0,0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
    }
    .pza-avatar-info { flex: 1 !important; }
    .pza-avatar-name { font-size: 17px !important; font-weight: 700 !important; color: #161B2E !important; margin: 0 0 2px !important; }
    .pza-avatar-sub  { font-size: 13px !important; color: #8B92A5 !important; margin: 0 !important; }
    .pza-avatar-uploading { font-size: 12px !important; color: #1FB856 !important; margin-top: 4px !important; display: none !important; }
    .pza-level-compact { display: flex !important; flex-direction: column !important; gap: 8px !important; }
    .pza-level-btn {
        display: flex !important; align-items: center !important; gap: 12px !important;
        background: #fff !important; border: 1.5px solid #D9DCE3 !important;
        border-radius: 12px !important; padding: 10px 14px !important;
        cursor: pointer !important; width: 100% !important; text-align: left !important;
        transition: border-color .18s, background .18s !important;
    }
    .pza-level-btn:hover  { border-color: #161B2E !important; }
    .pza-level-btn.active { border-color: #1FB856 !important; background: #FAFFF4 !important; }
    .pza-level-dot  { width: 10px !important; height: 10px !important; border-radius: 50% !important; flex-shrink: 0 !important; }
    .pza-level-name { font-size: 13px !important; font-weight: 600 !important; color: #161B2E !important; flex: 1 !important; }
    .pza-level-range {
        font-size: 11px !important; font-weight: 600 !important;
        color: #8B92A5 !important; letter-spacing: .02em !important;
        background: #F4F5F8 !important; border-radius: 6px !important;
        padding: 2px 7px !important; flex-shrink: 0 !important;
    }
    .pza-level-btn.active .pza-level-range {
        background: #E2F7EA !important; color: #1FB856 !important;
    }
    .pza-level-check {
        width: 18px !important; height: 18px !important; border-radius: 50% !important;
        border: 1.5px solid #D9DCE3 !important;
        display: flex !important; align-items: center !important; justify-content: center !important;
        transition: background .15s, border-color .15s !important;
        flex-shrink: 0 !important;
    }
    .pza-level-btn.active .pza-level-check { background: #1FB856 !important; border-color: #1FB856 !important; }
    .pza-level-btn.active .pza-level-check::after {
        content: '' !important; width: 6px !important; height: 6px !important;
        border-radius: 50% !important; background: #fff !important; display: block !important;
    }
    .pza-level-save {
        width: 100% !important; background: #9FD731 !important; color: #fff !important;
        border: none !important; border-radius: 10px !important;
        font-size: 13px !important; font-weight: 700 !important;
        letter-spacing: .04em !important; text-transform: uppercase !important;
        padding: 11px !important; cursor: pointer !important; margin-top: 4px !important;
        transition: background .15s !important;
    }
    .pza-level-save:disabled { background: #D9DCE3 !important; color: #8B92A5 !important; cursor: not-allowed !important; }
    .pza-level-save:hover:not(:disabled) { background: #8BC41F !important; }
    .pza-field { margin-bottom: 14px !important; }
    .pza-field:last-child { margin-bottom: 0 !important; }
    .pza-label {
        display: block !important; font-size: 11px !important; font-weight: 700 !important;
        letter-spacing: .06em !important; text-transform: uppercase !important;
        color: #8B92A5 !important; margin-bottom: 6px !important;
    }
    .pza-input {
        width: 100% !important; background: #F7F8FA !important;
        border: 1.5px solid #ECEEF2 !important; border-radius: 10px !important;
        padding: 11px 14px !important; font-size: 15px !important;
        color: #161B2E !important; outline: none !important;
        transition: border-color .15s !important;
    }
    .pza-input:focus { border-color: #1FB856 !important; background: #fff !important; }
    .pza-save-btn {
        width: 100% !important; background: #1FB856 !important; color: #fff !important;
        border: none !important; border-radius: 12px !important;
        font-size: 15px !important; font-weight: 700 !important;
        padding: 15px !important; cursor: pointer !important; margin-top: 8px !important;
        transition: background .15s !important;
        box-shadow: 0 4px 14px -4px rgba(31,184,86,.4) !important;
    }
    .pza-save-btn:hover { background: #18A049 !important; }
    .pza-save-btn:disabled { background: #D9DCE3 !important; color: #8B92A5 !important; box-shadow: none !important; cursor: not-allowed !important; }
    #pzaToast {
        position: fixed !important; bottom: 90px !important; left: 50% !important;
        transform: translateX(-50%) translateY(20px) !important;
        background: #161B2E !important; color: #fff !important;
        font-size: 13px !important; font-weight: 600 !important;
        padding: 10px 22px !important; border-radius: 50px !important;
        opacity: 0 !important; pointer-events: none !important;
        transition: opacity .25s, transform .25s !important;
        z-index: 9999 !important; white-space: nowrap !important;
    }
    #pzaToast.show { opacity: 1 !important; transform: translateX(-50%) translateY(0) !important; }
    </style>

    <div id="pzAccount">

        <div class="pza-header">
            <a href="javascript:history.back()" class="pza-back" aria-label="Indietro">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <p class="pza-title">Il mio profilo</p>
        </div>

        <!-- 1. Foto + nome -->
        <div class="pza-section">
            <div class="pza-avatar-row">
                <div class="pza-avatar-wrap">
                    <img id="pzaAvatarImg"
                         src="<?php echo esc_attr($avatar_src); ?>"
                         class="pza-avatar-img" alt="Foto profilo">
                    <label for="pzAvatarInput" class="pza-avatar-edit" title="Cambia foto">
                        <svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    </label>
                    <!-- Nessun capture="": iOS/Android mostra il menu Scatta foto / Scegli dalla libreria -->
                    <input type="file" id="pzAvatarInput" accept="image/*">
                </div>
                <div class="pza-avatar-info">
                    <p class="pza-avatar-name"><?php echo esc_html(trim($fname . ' ' . $lname) ?: $user->display_name); ?></p>
                    <p class="pza-avatar-sub"><?php echo esc_html($email); ?></p>
                    <p class="pza-avatar-uploading" id="pzaUploading">Caricamento...</p>
                </div>
            </div>
        </div>

        <!-- 2. Livello di gioco -->
        <div class="pza-section">
            <p class="pza-section-title">Livello di gioco</p>
            <div class="pza-level-compact">
                <?php foreach ($levels as $lvl):
                    $is_active = $current && ($current >= $lvl['rating_min'] && $current <= $lvl['rating_max']);
                    $range_label = number_format($lvl['rating_min'], 1) . ' – ' . number_format($lvl['rating_max'], 1);
                ?>
                <button type="button"
                        class="pza-level-btn<?php echo $is_active ? ' active' : ''; ?>"
                        data-rating="<?php echo (float)$lvl['rating_default']; ?>">
                    <span class="pza-level-dot" style="background:<?php echo esc_attr($lvl['color']); ?>"></span>
                    <span class="pza-level-name"><?php echo esc_html($lvl['label']); ?></span>
                    <span class="pza-level-range"><?php echo esc_html($range_label); ?></span>
                    <span class="pza-level-check"></span>
                </button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="pza-level-save" id="pzaLevelSave" <?php echo $current ? '' : 'disabled'; ?>>
                <?php echo $current ? 'Aggiorna livello' : 'Salva livello'; ?>
            </button>
        </div>

        <!-- 3. Dati personali -->
        <div class="pza-section">
            <p class="pza-section-title">Dati personali</p>
            <div class="pza-field">
                <label class="pza-label" for="pzaFname">Nome</label>
                <input class="pza-input" type="text" id="pzaFname" value="<?php echo esc_attr($fname); ?>" placeholder="Il tuo nome" autocomplete="given-name">
            </div>
            <div class="pza-field">
                <label class="pza-label" for="pzaLname">Cognome</label>
                <input class="pza-input" type="text" id="pzaLname" value="<?php echo esc_attr($lname); ?>" placeholder="Il tuo cognome" autocomplete="family-name">
            </div>
            <div class="pza-field">
                <label class="pza-label" for="pzaEmail">Email</label>
                <input class="pza-input" type="email" id="pzaEmail" value="<?php echo esc_attr($email); ?>" placeholder="La tua email" autocomplete="email">
            </div>
            <div class="pza-field">
                <label class="pza-label" for="pzaPhone">Telefono</label>
                <input class="pza-input" type="tel" id="pzaPhone" value="<?php echo esc_attr($phone); ?>" placeholder="+39 333 000 0000" autocomplete="tel">
            </div>
            <button type="button" class="pza-save-btn" id="pzaSaveProfile">Salva modifiche</button>
        </div>

    </div>
    <div id="pzaToast"></div>

    <script>
    (function(){
        var CFG = <?php echo wp_json_encode($config); ?>;

        function toast(msg, ok) {
            var el = document.getElementById('pzaToast');
            el.textContent = msg;
            el.style.background = ok === false ? '#EF4444' : '#161B2E';
            el.classList.add('show');
            setTimeout(function(){ el.classList.remove('show'); }, 2800);
        }

        document.getElementById('pzAvatarInput').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;
            var up = document.getElementById('pzaUploading');
            up.style.display = 'block';
            var fd = new FormData();
            fd.append('action', 'pz_upload_avatar');
            fd.append('nonce',  CFG.nonceAvatar);
            fd.append('avatar', file);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.ajaxUrl, true);
            xhr.onload = function() {
                up.style.display = 'none';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        document.getElementById('pzaAvatarImg').src = res.data.url + '?t=' + Date.now();
                        toast('Foto aggiornata!');
                    } else { toast(res.data || 'Errore upload', false); }
                } catch(err) { toast('Errore di connessione', false); }
            };
            xhr.onerror = function() { up.style.display = 'none'; toast('Errore di rete', false); };
            xhr.send(fd);
        });

        var picked  = <?php echo $current ? (float)$current : 'null'; ?>;
        var lvlBtns = document.querySelectorAll('.pza-level-btn');
        var lvlSave = document.getElementById('pzaLevelSave');

        lvlBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                lvlBtns.forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                picked = parseFloat(btn.getAttribute('data-rating'));
                lvlSave.disabled = false;
                lvlSave.textContent = 'Aggiorna livello';
            });
        });

        lvlSave.addEventListener('click', function() {
            if (!picked) return;
            lvlSave.disabled = true;
            lvlSave.textContent = 'Salvataggio...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        lvlSave.disabled = false;
                        lvlSave.textContent = 'Aggiorna livello';
                        toast('Livello aggiornato!');
                    } else {
                        toast(res.data || 'Errore salvataggio', false);
                        lvlSave.disabled = false;
                        lvlSave.textContent = 'Aggiorna livello';
                    }
                } catch(e) {
                    toast('Errore di connessione', false);
                    lvlSave.disabled = false;
                    lvlSave.textContent = 'Aggiorna livello';
                }
            };
            xhr.send('action=pz_save_rating&rating=' + picked + '&nonce=' + encodeURIComponent(CFG.nonce));
        });

        document.getElementById('pzaSaveProfile').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Salvataggio...';
            var fd = new FormData();
            fd.append('action', 'pz_save_profile');
            fd.append('nonce',  CFG.nonceProfile);
            fd.append('first_name', document.getElementById('pzaFname').value.trim());
            fd.append('last_name',  document.getElementById('pzaLname').value.trim());
            fd.append('email',      document.getElementById('pzaEmail').value.trim());
            fd.append('phone',      document.getElementById('pzaPhone').value.trim());
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.ajaxUrl, true);
            xhr.onload = function() {
                btn.disabled = false;
                btn.textContent = 'Salva modifiche';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        toast('Profilo aggiornato!');
                        var fn = document.getElementById('pzaFname').value.trim();
                        var ln = document.getElementById('pzaLname').value.trim();
                        document.querySelector('.pza-avatar-name').textContent = (fn + ' ' + ln).trim() || '-';
                    } else { toast(res.data || 'Errore salvataggio', false); }
                } catch(e) { toast('Errore di connessione', false); }
            };
            xhr.onerror = function() { btn.disabled = false; btn.textContent = 'Salva modifiche'; toast('Errore di rete', false); };
            xhr.send(fd);
        });

    })();
    </script>

    <?php
    return ob_get_clean();
});


/* ============================================================
 *  AJAX — upload avatar
 * ============================================================ */
add_action('wp_ajax_pz_upload_avatar', function() {
    check_ajax_referer('pz_upload_avatar', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');
    if (empty($_FILES['avatar']['tmp_name'])) wp_send_json_error('Nessun file ricevuto');

    $filetype    = wp_check_filetype_and_ext($_FILES['avatar']['tmp_name'], $_FILES['avatar']['name']);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (empty($filetype['ext']) || !in_array(strtolower($filetype['ext']), $allowed_ext, true)) {
        wp_send_json_error('Formato non supportato (usa JPG, PNG, GIF, WEBP)');
    }
    if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) wp_send_json_error('Immagine troppo grande (max 5MB)');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uid      = get_current_user_id();
    $uploaded = media_handle_upload('avatar', 0);
    if (is_wp_error($uploaded)) wp_send_json_error($uploaded->get_error_message());

    $url = wp_get_attachment_url($uploaded);

    // pz_avatar = fonte principale letta da pz_get_user_avatar_url
    update_user_meta($uid, 'pz_avatar', $url);
    // simple_local_avatar = compatibilità con plugin avatar WP
    update_user_meta($uid, 'simple_local_avatar', [
        32  => $url,
        64  => $url,
        96  => $url,
        128 => $url,
    ]);

    wp_send_json_success(['url' => $url]);
});


/* ============================================================
 *  AJAX — salva dati profilo
 * ============================================================ */
add_action('wp_ajax_pz_save_profile', function() {
    check_ajax_referer('pz_save_profile', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login richiesto');

    $uid   = get_current_user_id();
    $fname = sanitize_text_field($_POST['first_name'] ?? '');
    $lname = sanitize_text_field($_POST['last_name']  ?? '');
    $email = sanitize_email($_POST['email']           ?? '');
    $phone = sanitize_text_field($_POST['phone']      ?? '');

    if ($email && !is_email($email)) wp_send_json_error('Email non valida');

    update_user_meta($uid, 'first_name', $fname);
    update_user_meta($uid, 'last_name',  $lname);
    update_user_meta($uid, 'pz_phone',   $phone);

    $user = get_userdata($uid);
    if ($email && $email !== $user->user_email) {
        $exists = email_exists($email);
        if ($exists && $exists !== $uid) wp_send_json_error('Email gia in uso da un altro account');
        wp_update_user(['ID' => $uid, 'user_email' => $email]);
    }

    $display = trim($fname . ' ' . $lname);
    if ($display) wp_update_user(['ID' => $uid, 'display_name' => $display]);

    wp_send_json_success();
});
