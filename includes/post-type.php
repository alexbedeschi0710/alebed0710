<?php
/**
 * PadelZero - Custom Post Type
 */

if (!defined('ABSPATH')) exit;

add_action('init', function() {
    register_post_type('pz_match', [
        'labels' => [
            'name' => 'Partite (PZ)',
            'singular_name' => 'Partita (PZ)'
        ],
        'public' => false,
        'show_ui' => true,
        'menu_position' => 26,
        'menu_icon' => 'dashicons-groups',
        'supports' => ['title', 'custom-fields'],  // ← MODIFICATO QUI
    ]);
});

add_filter('manage_pz_match_posts_columns', function($cols){
    $cols['pz_when'] = 'Quando';
    $cols['pz_service'] = 'Service';
    $cols['pz_loc'] = 'Loc';
    $cols['pz_booked'] = 'Booked';
    return $cols;
});

add_action('manage_pz_match_posts_custom_column', function($col, $post_id){
    $k = pz_meta_keys();
    
    if ($col === 'pz_when') {
        $d = get_post_meta($post_id, $k['starts_date'], true);
        $t = get_post_meta($post_id, $k['starts_time'], true);
        echo esc_html(trim($d.' '.$t));
    }
    if ($col === 'pz_service') echo esc_html(get_post_meta($post_id, $k['service_id'], true));
    if ($col === 'pz_loc') echo esc_html(get_post_meta($post_id, $k['location_id'], true));
    if ($col === 'pz_booked') {
        $b = (int)get_post_meta($post_id, $k['booked_count'], true);
        $m = (int)get_post_meta($post_id, $k['max_capacity'], true);
        echo esc_html($b.' / '.$m);
    }
}, 10, 2);

