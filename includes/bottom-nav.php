<?php
/**
 * PadelZero - Bottom Navigation
 * Shortcode: [pz_bottom_nav]
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 *  CONFIG — rotte costruite con PZ_APP_BASE
 * ============================================================ */
function pz_nav_routes() {
    $base = defined('PZ_APP_BASE') ? PZ_APP_BASE : '/app/';
    return [
        'home'         => $base,
        'prenotazioni' => $base . 'le-mie-prenotazioni/',
        'wallet'       => $base . 'borsellino/',
        'account'      => $base . 'account/',
    ];
}

/* ============================================================
 *  HELPER AVATAR
 *  Priorità: 1) pz_avatar (custom upload), 2) simple_local_avatar, 3) Gravatar
 * ============================================================ */
function pz_get_user_avatar_url($user_id, $size = 64) {
    // 1. Avatar caricato dal modulo account
    $pz = get_user_meta($user_id, 'pz_avatar', true);
    if ($pz && is_string($pz)) return $pz;

    // 2. Simple Local Avatars plugin
    $meta = get_user_meta($user_id, 'simple_local_avatar', true);
    if (!empty($meta) && is_array($meta)) {
        if (!empty($meta[$size])) return $meta[$size];
        $biggest = 0;
        $url     = '';
        foreach ($meta as $k => $v) {
            if (is_numeric($k) && (int)$k > $biggest) {
                $biggest = (int)$k;
                $url     = $v;
            }
        }
        if ($url) return $url;
        $first = reset($meta);
        if ($first && is_string($first)) return $first;
    }

    // 3. Gravatar / default WP
    return get_avatar_url($user_id, ['size' => $size, 'default' => 'mp']);
}

add_shortcode('pz_bottom_nav', 'pz_nav_render');

function pz_nav_render($atts) {
    if (!is_user_logged_in()) return '';

    $routes = pz_nav_routes();
    $home   = home_url('/');

    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $req_path = '/' . trim($req_path, '/') . '/';

    $active = '';
    foreach ($routes as $key => $slug) {
        $normalized = '/' . trim($slug, '/') . '/';
        if ($normalized !== '//' && $req_path === $normalized) {
            $active = $key;
            break;
        }
    }
    if (!$active && ($req_path === '//' || $req_path === '/')) $active = 'home';

    $url = function($slug) use ($home) {
        return rtrim($home, '/') . $slug;
    };

    $user_id = get_current_user_id();
    $av_url  = pz_get_user_avatar_url($user_id, 64);

    ob_start();
    ?>
    <style>
    .pz-nav{
      position:fixed !important;
      bottom:0 !important;left:0 !important;right:0 !important;
      width:100% !important;
      background:#FFFFFF !important;
      border-top:1px solid #ECEEF2 !important;
      box-shadow:0 -4px 12px -6px rgba(22,27,46,.08) !important;
      z-index:100 !important;
      padding:0 !important;
      box-sizing:border-box !important;
      font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;
    }
    .pz-nav-inner{
      display:flex !important;
      max-width:480px !important;
      margin:0 auto !important;
      height:64px !important;
    }
    .pz-nav a{
      flex:1 !important;
      display:flex !important;
      flex-direction:column !important;
      align-items:center !important;
      justify-content:center !important;
      gap:3px !important;
      text-decoration:none !important;
      color:#8B92A5 !important;
      background:transparent !important;
      border:none !important;
      padding:8px 4px !important;
      transition:color .15s ease !important;
      box-shadow:none !important;
      border-radius:0 !important;
    }
    .pz-nav a:hover{color:#161B2E !important;background:transparent !important}
    .pz-nav a.is-active{color:#1FB856 !important}
    .pz-nav a svg{
      width:22px !important;height:22px !important;
      stroke:currentColor !important;stroke-width:2 !important;fill:none !important;
      stroke-linecap:round !important;stroke-linejoin:round !important;
    }
    .pz-nav-label{
      font-size:11px !important;
      font-weight:600 !important;
      letter-spacing:0 !important;
      line-height:1 !important;
      text-transform:none !important;
    }
    .pz-nav-avatar{
      width:24px !important;height:24px !important;
      border-radius:50% !important;
      object-fit:cover !important;
      border:2px solid transparent !important;
      transition:border-color .15s ease !important;
      display:block !important;
      flex-shrink:0 !important;
    }
    .pz-nav a.is-active .pz-nav-avatar{
      border-color:#1FB856 !important;
    }
    </style>

    <nav class="pz-nav" aria-label="Menu principale">
      <div class="pz-nav-inner">

        <a href="<?php echo esc_url($url($routes['home'])); ?>" class="<?php echo $active === 'home' ? 'is-active' : ''; ?>">
          <svg viewBox="0 0 24 24"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1V9.5z"/></svg>
          <span class="pz-nav-label">Home</span>
        </a>

        <a href="<?php echo esc_url($url($routes['prenotazioni'])); ?>" class="<?php echo $active === 'prenotazioni' ? 'is-active' : ''; ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/></svg>
          <span class="pz-nav-label">Prenotazioni</span>
        </a>

        <a href="#" onclick="event.preventDefault();pzOpenPwaSheet();" class="">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="2" width="14" height="20" rx="2"/>
            <path d="M9 9l3 3 3-3"/>
            <line x1="12" y1="7" x2="12" y2="12"/>
          </svg>
          <span class="pz-nav-label">App</span>
        </a>

        <a href="<?php echo esc_url($url($routes['account'])); ?>" class="<?php echo $active === 'account' ? 'is-active' : ''; ?>">
          <img
            class="pz-nav-avatar"
            src="<?php echo esc_url($av_url); ?>"
            alt="Profilo"
            width="24"
            height="24"
            loading="lazy"
          >
          <span class="pz-nav-label">Account</span>
        </a>

      </div>
    </nav>
    <?php
    return ob_get_clean();
}

/* ============================================================
 *  AUTO-INJECT
 * ============================================================ */
add_action('wp_footer', 'pz_nav_auto_inject', 5);

function pz_nav_auto_inject() {
    if (!is_user_logged_in() || !is_singular()) return;

    global $post;
    if (!$post) return;

    if (has_shortcode($post->post_content, 'pz_bottom_nav')) return;

    $triggers = [
        'pz_wizard', 'pz_book_private', 'pz_create_public',
        'pz_my_bookings', 'pzlobby', 'pz_wallet_balance', 'pz_rating_setup',
        'pz_account',
    ];

    $found = false;
    foreach ($triggers as $sc) {
        if (has_shortcode($post->post_content, $sc)) { $found = true; break; }
    }

    if (!$found) {
        $el_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($el_data) {
            foreach ($triggers as $sc) {
                if (strpos($el_data, $sc) !== false) { $found = true; break; }
            }
        }
    }

    if (!$found) return;

    echo do_shortcode('[pz_bottom_nav]');
}
