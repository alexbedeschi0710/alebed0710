<?php
/**
 * My Bookings - Frontend shortcode
 * Shortcode: [pz_my_bookings]
 */

if (!defined('ABSPATH')) exit;

class PZ_My_Bookings {

    public static function init() {
        add_shortcode('pz_my_bookings', [__CLASS__, 'render']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>Devi essere loggato per vedere le tue prenotazioni.</p>';
        }
        ob_start();
        self::render_styles();
        self::render_html();
        return ob_get_clean();
    }