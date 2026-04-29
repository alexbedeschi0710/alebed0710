<?php
/**
 * PadelZero - Funzioni Helper
 */

if (!defined('ABSPATH')) exit;

function pz_meta_keys() {
  return [
    'amelia_appointment_id' => 'pz_amelia_appointment_id',
    'service_id' => 'pz_service_id',
    'location_id' => 'pz_location_id',
    'starts_date' => 'pz_starts_date',
    'starts_time' => 'pz_starts_time',
    'level' => 'pz_level',
    'max_capacity' => 'pz_max_capacity',
    'booked_count' => 'pz_booked_count',
    'created_by_user_id' => 'pz_created_by_user_id',
    'participants' => 'pz_participants',
    'payment_method' => 'pz_payment_method',
    'payment_status' => 'pz_payment_status',
    'payer_email' => 'pz_payer_email',
    'total_paid' => 'pz_total_paid',
  ];
}

function pz_get_level_info($service_id) {
  $map = [
    1 => ['level' => 'Principiante', 'color' => '#00ff00'],
    6 => ['level' => 'Intermedio', 'color' => '#ffec00'],
    7 => ['level' => 'Avanzato', 'color' => '#ff7900'],
    8 => ['level' => 'Pro', 'color' => '#ff0000'],
  ];
  return isset($map[$service_id]) ? $map[$service_id] : ['level' => '', 'color' => '#cccccc'];
}

function pz_dt_from_meta($date, $time) {
  if (!$date) return null;
  $t = $time ? $time : '00:00:00';
  $dt = date_create($date . ' ' . $t, wp_timezone());
  return $dt ?: null;
}

function pz_is_weekend($dt) {
  if (!$dt) return false;
  return ((int)$dt->format('N') >= 6);
}

function pz_start_of_today() {
  $dt = new DateTime('now', wp_timezone());
  $dt->setTime(0,0,0);
  return $dt;
}

function pz_end_of_day(DateTime $dt) {
  $x = clone $dt;
  $x->setTime(23,59,59);
  return $x;
}

/**
 * Schermata "accesso richiesto" con design coerente al design system PadelZero.
 * Usata da tutti gli shortcode che richiedono il login.
 *
 * @param string $icon     Emoji o testo icona
 * @param string $title    Titolo sezione
 * @param string $subtitle Testo descrittivo
 * @param string $login_url URL della pagina login
 */
function pz_render_login_wall( $icon = '🔒', $title = 'Accesso richiesto', $subtitle = 'Effettua il login per continuare.', $login_url = '/inizio/login/' ) {
    $login_url = esc_url( home_url( $login_url ) );
    ob_start();
    ?>
    <div style="font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px 48px;text-align:center;min-height:320px">

        <div style="width:72px;height:72px;background:#E8F8EE;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin-bottom:20px"><?php echo $icon; ?></div>

        <h2 style="font-size:22px;font-weight:700;color:#161B2E;margin:0 0 10px"><?php echo esc_html( $title ); ?></h2>

        <p style="font-size:15px;color:#8B92A5;margin:0 0 32px;max-width:300px;line-height:1.6"><?php echo esc_html( $subtitle ); ?></p>

        <a href="<?php echo $login_url; ?>" style="display:inline-block;background:#1FB856;color:#fff !important;font-size:15px;font-weight:700;text-decoration:none !important;padding:14px 40px;border-radius:12px;letter-spacing:0.3px">Accedi</a>

        <p style="margin:20px 0 0;font-size:13px;color:#8B92A5">
            Non hai un account? <a href="<?php echo $login_url; ?>" style="color:#1FB856;font-weight:600;text-decoration:none">Registrati</a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}
