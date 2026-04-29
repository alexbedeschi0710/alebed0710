<?php
/**
 * PadelZero - Top Bar personalizzata
 *
 * - Nasconde la admin bar di WordPress per gli utenti non-admin
 * - Inietta una top bar fissa coerente con il design system PadelZero
 * - Auto-inject nel wp_body_open / wp_head per tutti gli utenti loggati
 *   sulle pagine che contengono shortcode del plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 *  1. NASCONDI ADMIN BAR WORDPRESS (utenti non-admin)
 * ============================================================ */
add_action( 'after_setup_theme', 'pz_maybe_hide_admin_bar' );

function pz_maybe_hide_admin_bar() {
    if ( ! is_user_logged_in() ) return;
    if ( current_user_can( 'manage_options' ) ) return;
    show_admin_bar( false );
}

add_action( 'wp_head', 'pz_remove_admin_bar_css', 99 );

function pz_remove_admin_bar_css() {
    if ( ! is_user_logged_in() ) return;
    if ( current_user_can( 'manage_options' ) ) return;
    ?>
    <style>
    #wpadminbar { display: none !important; }
    html { margin-top: 0 !important; }
    body { margin-top: 0 !important; padding-top: 0 !important; }
    </style>
    <?php
}

/* ============================================================
 *  2. TOP BAR — shortcode + auto-inject
 * ============================================================ */
add_shortcode( 'pz_top_bar', 'pz_top_bar_render' );

function pz_top_bar_render( $atts = [] ) {
    if ( ! is_user_logged_in() ) return '';

    $atts = shortcode_atts( [
        'show_back'  => 'false',
        'back_url'   => '',
        'title'      => '',
    ], $atts );

    $user        = wp_get_current_user();
    $first_name  = esc_html( $user->first_name ?: $user->display_name );
    $account_url = esc_url( pz_app_url( 'account/' ) );

    $initials = '';
    if ( $user->first_name ) $initials .= mb_strtoupper( mb_substr( $user->first_name, 0, 1 ) );
    if ( $user->last_name )  $initials .= mb_strtoupper( mb_substr( $user->last_name,  0, 1 ) );
    if ( ! $initials )       $initials  = mb_strtoupper( mb_substr( $user->display_name, 0, 2 ) );

    $avatar_url = get_avatar_url( $user->ID, [
        'size'          => 64,
        'default'       => 'blank',
        'force_default' => false,
    ] );

    $has_photo = $avatar_url && strpos( $avatar_url, 'd=blank' ) === false && strpos( $avatar_url, 'gravatar.com/avatar/00000000000000000000000000000000' ) === false;

    $show_back  = filter_var( $atts['show_back'], FILTER_VALIDATE_BOOLEAN );
    $back_url   = esc_url( $atts['back_url'] );
    $page_title = esc_html( $atts['title'] );

    ob_start();
    ?>
    <style>
    .pz-top-bar {
        position: fixed !important;
        top: 0 !important; left: 0 !important; right: 0 !important;
        width: 100% !important;
        height: 56px !important;
        background: #FFFFFF !important;
        border-bottom: 1px solid #ECEEF2 !important;
        box-shadow: 0 1px 8px -4px rgba(22,27,46,.10) !important;
        z-index: 99 !important;
        box-sizing: border-box !important;
        font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    .pz-top-bar-inner {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        max-width: 480px !important;
        margin: 0 auto !important;
        height: 100% !important;
        padding: 0 16px !important;
        position: relative !important;
    }
    .pz-top-bar-left {
        display: flex !important;
        align-items: center !important;
        flex: 1 !important;
    }
    .pz-top-bar-logo {
        display: flex !important;
        align-items: center !important;
        gap: 7px !important;
        text-decoration: none !important;
    }
    .pz-top-bar-logo svg {
        width: 28px !important;
        height: 28px !important;
        flex-shrink: 0 !important;
    }
    .pz-top-bar-logo-text {
        font-size: 17px !important;
        font-weight: 800 !important;
        color: #161B2E !important;
        letter-spacing: -0.03em !important;
        line-height: 1 !important;
    }
    .pz-top-bar-logo-text span { color: #1FB856 !important; }
    .pz-top-bar-back {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 36px !important; height: 36px !important;
        background: #FFFFFF !important;
        border: 1.5px solid #D9DCE3 !important;
        border-radius: 50% !important;
        cursor: pointer !important;
        text-decoration: none !important;
        flex-shrink: 0 !important;
    }
    .pz-top-bar-back svg {
        width: 18px !important; height: 18px !important;
        stroke: #8B92A5 !important; stroke-width: 2 !important;
        fill: none !important;
        stroke-linecap: round !important; stroke-linejoin: round !important;
    }
    .pz-top-bar-title {
        position: absolute !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        font-size: 17px !important;
        font-weight: 700 !important;
        color: #161B2E !important;
        letter-spacing: -0.01em !important;
        pointer-events: none !important;
        white-space: nowrap !important;
    }
    .pz-top-bar-right {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        flex: 1 !important;
    }
    .pz-top-bar-user {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        text-decoration: none !important;
        padding: 4px 0 !important;
    }
    .pz-top-bar-name {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #161B2E !important;
        max-width: 90px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    .pz-top-bar-avatar {
        width: 32px !important; height: 32px !important;
        border-radius: 50% !important;
        border: 1.5px solid #D9DCE3 !important;
        flex-shrink: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #E8F8EE !important;
        box-sizing: border-box !important;
    }
    .pz-top-bar-avatar img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
        border-radius: 50% !important;
    }
    .pz-top-bar-avatar-initials {
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #1FB856 !important;
        letter-spacing: 0 !important;
        line-height: 1 !important;
    }
    .pz-top-bar-spacer {
        height: 56px !important;
        display: block !important;
    }
    </style>

    <div class="pz-top-bar">
        <div class="pz-top-bar-inner">

            <div class="pz-top-bar-left">
                <?php if ( $show_back ) : ?>
                    <?php if ( $back_url ) : ?>
                        <a href="<?php echo $back_url; ?>" class="pz-top-bar-back" aria-label="Torna indietro">
                    <?php else : ?>
                        <a href="#" onclick="history.back();return false;" class="pz-top-bar-back" aria-label="Torna indietro">
                    <?php endif; ?>
                        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( pz_app_url() ); ?>" class="pz-top-bar-logo" aria-label="PadelZero Home">
                        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect width="32" height="32" rx="8" fill="#1FB856"/>
                            <path d="M8 22 C8 22 10 10 16 10 C20 10 22 13 20 17 C18 21 13 21 11 18" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                            <circle cx="21" cy="21" r="2.5" fill="#fff"/>
                        </svg>
                        <span class="pz-top-bar-logo-text">Padel<span>Zero</span></span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( $page_title ) : ?>
                <span class="pz-top-bar-title"><?php echo $page_title; ?></span>
            <?php endif; ?>

            <div class="pz-top-bar-right">
                <a href="<?php echo $account_url; ?>" class="pz-top-bar-user" aria-label="Il tuo account">
                    <span class="pz-top-bar-name"><?php echo $first_name; ?></span>
                    <div class="pz-top-bar-avatar">
                        <?php if ( $has_photo ) : ?>
                            <img
                                src="<?php echo esc_url( $avatar_url ); ?>"
                                alt="<?php echo esc_attr( $user->display_name ); ?>"
                                width="32" height="32"
                                loading="eager"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                            />
                            <span class="pz-top-bar-avatar-initials" style="display:none"><?php echo esc_html( $initials ); ?></span>
                        <?php else : ?>
                            <span class="pz-top-bar-avatar-initials"><?php echo esc_html( $initials ); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>

        </div>
    </div>
    <div class="pz-top-bar-spacer"></div>
    <?php
    return ob_get_clean();
}

/* ============================================================
 *  3. AUTO-INJECT nel wp_body_open
 * ============================================================ */
add_action( 'wp_body_open', 'pz_top_bar_auto_inject', 1 );

function pz_top_bar_auto_inject() {
    if ( ! is_user_logged_in() ) return;
    if ( current_user_can( 'manage_options' ) ) return;
    if ( ! is_singular() ) return;

    global $post;
    if ( ! $post ) return;

    if ( has_shortcode( $post->post_content, 'pz_top_bar' ) ) return;

    $triggers = [
        'pz_wizard', 'pz_book_private', 'pz_create_public',
        'pz_my_bookings', 'pzlobby', 'pz_wallet_balance', 'pz_rating_setup',
        'pz_bottom_nav', 'pz_account',
    ];

    $found = false;
    foreach ( $triggers as $sc ) {
        if ( has_shortcode( $post->post_content, $sc ) ) { $found = true; break; }
    }

    if ( ! $found ) {
        $el_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( $el_data ) {
            foreach ( $triggers as $sc ) {
                if ( strpos( $el_data, $sc ) !== false ) { $found = true; break; }
            }
        }
    }

    if ( ! $found ) return;

    echo do_shortcode( '[pz_top_bar]' );
}
