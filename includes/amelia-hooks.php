<?php
/**
 * PadelZero - Hook Amelia
 * Compatibile con Amelia 9.x
 */

if (!defined('ABSPATH')) exit;

// Non caricare hooks se siamo nell'admin di Amelia
if (defined('PZ_AMELIA_ADMIN_MODE') && PZ_AMELIA_ADMIN_MODE) {
    return;
}

if (is_admin() && !wp_doing_ajax()) {
    if (isset($_GET['page']) && strpos($_GET['page'], 'wpamelia') !== false) {
        return;
    }
}

// ============================================================
// HOOK PRENOTAZIONE AGGIUNTA
// Amelia < 9.x  → AmeliaBookingAdded        ($data = array)
// Amelia 9.x    → amelia_after_booking_added ($booking = object, $appointment = object)
// ============================================================

// Vecchio hook (backward compatibility)
add_action('AmeliaBookingAdded', 'pz_on_booking_added_legacy', 10, 1);

// Nuovi hook Amelia 9.x
add_action('amelia_after_booking_added', 'pz_on_booking_added_new', 10, 2);

// Hook aggiunta appuntamento dal backend (quando admin crea direttamente)
add_action('amelia_after_appointment_added', 'pz_on_appointment_added_new', 10, 1);

// Hook aggiornamento appuntamento (quando si modifica dal backend)
add_action('amelia_after_appointment_updated', 'pz_on_appointment_updated_new', 10, 1);

// Hook cancellazione prenotazione
add_action('amelia_after_booking_canceled', 'pz_on_booking_canceled_new', 10, 2);
add_action('AmeliaBookingCanceled', 'pz_on_booking_canceled_legacy', 10, 1);
add_action('AmeliaBookingRejected', 'pz_on_booking_canceled_legacy', 10, 1);

// ============================================================
// FUNZIONE CORE: sincronizza un appointment nel post pz_match
// ============================================================

function pz_sync_appointment($apt_id, $service_id = 0) {
    try {
        if (!$apt_id) return;

        global $wpdb;
        $prefix = PZ_DB_PREFIX;

        // Carica appointment da Amelia
        $apt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}appointments WHERE id = %d",
            $apt_id
        ));

        if (!$apt) {
            error_log('PZ: Appointment #' . $apt_id . ' non trovato in Amelia');
            return;
        }

        if (!$service_id) {
            $service_id = (int)$apt->serviceId;
        }

        // Controlla che sia un servizio pubblico PadelZero
        if (!in_array($service_id, PZ_PUBLIC_SERVICE_IDS, true)) {
            error_log('PZ: ServiceId ' . $service_id . ' non è in PZ_PUBLIC_SERVICE_IDS, skip.');
            return;
        }

        $k = pz_meta_keys();

        // Cerca post pz_match esistente collegato a questo appointment
        $existing = get_posts([
            'post_type'      => 'pz_match',
            'meta_key'       => $k['amelia_appointment_id'],
            'meta_value'     => $apt_id,
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);

        if ($existing) {
            $post_id = $existing[0]->ID;
            $blocked = get_post_meta($post_id, 'pz_block_auto_add', true);
            if ($blocked === '1') {
                error_log('PZ: Blocco attivo per partita #' . $post_id . ', skip sync.');
                return;
            }
        }

        // Leggi partecipanti live da Amelia
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT cb.*, u.email, u.firstName, u.lastName
             FROM {$prefix}customer_bookings cb
             LEFT JOIN {$prefix}users u ON cb.customerId = u.id
             WHERE cb.appointmentId = %d AND cb.status IN ('approved', 'paid')
             ORDER BY cb.created ASC",
            $apt_id
        ));

        $participants = [];
        if ($bookings) {
            foreach ($bookings as $bk) {
                if (empty($bk->email)) continue;
                $wp_user = get_user_by('email', $bk->email);
                if ($wp_user && user_can($wp_user, 'manage_options')) continue;
                $participants[] = [
                    'email' => $bk->email,
                    'name'  => trim(($bk->firstName ?? '') . ' ' . ($bk->lastName ?? '')),
                ];
            }
        }

        $level_info = pz_get_level_info($service_id);

        $starts_date = substr($apt->bookingStart, 0, 10);
        $starts_time = substr($apt->bookingStart, 11, 8);

        $post_data = [
            'post_type'   => 'pz_match',
            'post_title'  => 'Partita ' . $level_info['level'] . ' - ' . $starts_date,
            'post_status' => 'publish',
        ];

        if ($existing) {
            $post_data['ID'] = $existing[0]->ID;
            wp_update_post($post_data);
            $post_id = $existing[0]->ID;
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (!$post_id || is_wp_error($post_id)) {
            error_log('PZ: Errore creazione/aggiornamento post pz_match per apt #' . $apt_id);
            return;
        }

        update_post_meta($post_id, $k['amelia_appointment_id'], $apt_id);
        update_post_meta($post_id, $k['service_id'],            $service_id);
        update_post_meta($post_id, $k['location_id'],           (int)$apt->locationId);
        update_post_meta($post_id, $k['starts_date'],           $starts_date);
        update_post_meta($post_id, $k['starts_time'],           $starts_time);
        update_post_meta($post_id, $k['level'],                 $level_info['level']);
        update_post_meta($post_id, $k['max_capacity'],          4);
        update_post_meta($post_id, $k['participants'],          $participants);
        update_post_meta($post_id, $k['booked_count'],          count($participants));

        error_log('PZ: Sincronizzata partita #' . $post_id . ' ← apt #' . $apt_id . ' (' . $level_info['level'] . ' ' . $starts_date . ')');

    } catch (Exception $e) {
        error_log('PZ Error in pz_sync_appointment: ' . $e->getMessage());
    }
}

// ============================================================
// WRAPPER HOOK - Vecchio formato (Amelia < 9.x)
// $data['id'] = appointment ID
// ============================================================

function pz_on_booking_added_legacy($data) {
    if (empty($data['id'])) return;
    $apt_id     = (int)$data['id'];
    $service_id = isset($data['serviceId']) ? (int)$data['serviceId'] : 0;
    pz_sync_appointment($apt_id, $service_id);
}

function pz_on_booking_canceled_legacy($data) {
    if (empty($data['id'])) return;
    pz_sync_appointment((int)$data['id']);
}

// ============================================================
// WRAPPER HOOK - Nuovo formato (Amelia 9.x)
// $booking  = oggetto booking singolo
// $appointment = oggetto appointment
// ============================================================

function pz_on_booking_added_new($booking, $appointment) {
    try {
        // $appointment può essere oggetto o array
        if (is_object($appointment) && !empty($appointment->id)) {
            $apt_id     = (int)$appointment->id;
            $service_id = isset($appointment->serviceId) ? (int)$appointment->serviceId : 0;
        } elseif (is_array($appointment) && !empty($appointment['id'])) {
            $apt_id     = (int)$appointment['id'];
            $service_id = isset($appointment['serviceId']) ? (int)$appointment['serviceId'] : 0;
        } elseif (is_object($booking) && !empty($booking->appointmentId)) {
            $apt_id     = (int)$booking->appointmentId;
            $service_id = 0;
        } elseif (is_array($booking) && !empty($booking['appointmentId'])) {
            $apt_id     = (int)$booking['appointmentId'];
            $service_id = 0;
        } else {
            error_log('PZ amelia_after_booking_added: payload non riconosciuto. ' . print_r($booking, true));
            return;
        }

        pz_sync_appointment($apt_id, $service_id);

    } catch (Exception $e) {
        error_log('PZ Error in pz_on_booking_added_new: ' . $e->getMessage());
    }
}

function pz_on_booking_canceled_new($booking, $appointment) {
    try {
        if (is_object($appointment) && !empty($appointment->id)) {
            $apt_id = (int)$appointment->id;
        } elseif (is_array($appointment) && !empty($appointment['id'])) {
            $apt_id = (int)$appointment['id'];
        } elseif (is_object($booking) && !empty($booking->appointmentId)) {
            $apt_id = (int)$booking->appointmentId;
        } elseif (is_array($booking) && !empty($booking['appointmentId'])) {
            $apt_id = (int)$booking['appointmentId'];
        } else {
            error_log('PZ amelia_after_booking_canceled: payload non riconosciuto.');
            return;
        }

        pz_sync_appointment($apt_id);

    } catch (Exception $e) {
        error_log('PZ Error in pz_on_booking_canceled_new: ' . $e->getMessage());
    }
}

// ============================================================
// WRAPPER HOOK - Appointment aggiunto/modificato dal backend
// $appointment = oggetto o array appointment
// ============================================================

function pz_on_appointment_added_new($appointment) {
    try {
        if (is_object($appointment) && !empty($appointment->id)) {
            $apt_id     = (int)$appointment->id;
            $service_id = isset($appointment->serviceId) ? (int)$appointment->serviceId : 0;
        } elseif (is_array($appointment) && !empty($appointment['id'])) {
            $apt_id     = (int)$appointment['id'];
            $service_id = isset($appointment['serviceId']) ? (int)$appointment['serviceId'] : 0;
        } else {
            error_log('PZ amelia_after_appointment_added: payload non riconosciuto. ' . print_r($appointment, true));
            return;
        }

        pz_sync_appointment($apt_id, $service_id);

    } catch (Exception $e) {
        error_log('PZ Error in pz_on_appointment_added_new: ' . $e->getMessage());
    }
}

function pz_on_appointment_updated_new($appointment) {
    pz_on_appointment_added_new($appointment);
}

// ============================================================
// HOOK PAGAMENTO CONFERMATO (invariato, compatibile)
// ============================================================

function pz_on_payment_confirmed($data) {
    try {
        if (empty($data['id'])) return;

        $apt_id = (int)$data['id'];
        $k      = pz_meta_keys();

        $posts = get_posts([
            'post_type'      => 'pz_match',
            'meta_key'       => $k['amelia_appointment_id'],
            'meta_value'     => $apt_id,
            'posts_per_page' => 1,
        ]);

        if (empty($posts)) return;

        $post_id = $posts[0]->ID;
        $blocked = get_post_meta($post_id, 'pz_block_auto_add', true);

        if ($blocked !== '1') return;

        delete_post_meta($post_id, 'pz_block_auto_add');

        global $wpdb;
        $prefix = PZ_DB_PREFIX;

        $paid_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT cb.*, u.email, u.firstName, u.lastName
             FROM {$prefix}customer_bookings cb
             LEFT JOIN {$prefix}users u ON cb.customerId = u.id
             WHERE cb.appointmentId = %d AND cb.status IN ('approved', 'paid') AND cb.price > 0
             ORDER BY cb.created ASC LIMIT 1",
            $apt_id
        ));

        if (!$paid_booking || empty($paid_booking->email)) return;

        $participants    = get_post_meta($post_id, $k['participants'], true) ?: [];
        $already_exists  = false;

        foreach ($participants as $p) {
            if (isset($p['email']) && $p['email'] === $paid_booking->email) {
                $already_exists = true;
                break;
            }
        }

        if (!$already_exists) {
            $participants[] = [
                'email' => $paid_booking->email,
                'name'  => trim(($paid_booking->firstName ?? '') . ' ' . ($paid_booking->lastName ?? '')),
            ];
            update_post_meta($post_id, $k['participants'],    $participants);
            update_post_meta($post_id, $k['booked_count'],    count($participants));
            update_post_meta($post_id, $k['payment_method'],  'amelia');
            update_post_meta($post_id, $k['payment_status'],  'paid');
        }

        delete_post_meta($post_id, 'pz_pending_payment_user');
        delete_post_meta($post_id, 'pz_pending_payment_time');

    } catch (Exception $e) {
        error_log('PZ Error in pz_on_payment_confirmed: ' . $e->getMessage());
    }
}

