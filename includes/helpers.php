<?php
/**
 * PadelZero - Funzioni Helper
 * Aggiornato: 3 fasce livello + rating utente numerico (1.0-7.0)
 */

if (!defined('ABSPATH')) exit;


/* ============================================================
 *  META KEYS  (invariato)
 * ============================================================ */
function pz_meta_keys() {
  return [
    'amelia_appointment_id' => 'pz_amelia_appointment_id',
    'service_id'            => 'pz_service_id',
    'location_id'           => 'pz_location_id',
    'starts_date'           => 'pz_starts_date',
    'starts_time'           => 'pz_starts_time',
    'level'                 => 'pz_level',
    'max_capacity'          => 'pz_max_capacity',
    'booked_count'          => 'pz_booked_count',
    'created_by_user_id'    => 'pz_created_by_user_id',
    'participants'          => 'pz_participants',
    'payment_method'        => 'pz_payment_method',
    'payment_status'        => 'pz_payment_status',
    'payer_email'           => 'pz_payer_email',
    'total_paid'            => 'pz_total_paid',
  ];
}


/* ============================================================
 *  LIVELLI - 3 FASCE
 * ============================================================ */

function pz_get_level_info($service_id) {
  $service_id = (int)$service_id;
  $map = [
    1 => ['level' => 'Principiante',  'color' => '#1FB856', 'rating_min' => 1.0, 'rating_max' => 2.5],
    6 => ['level' => 'Intermedio',    'color' => '#F59E0B', 'rating_min' => 3.0, 'rating_max' => 4.5],
    7 => ['level' => 'Avanzato-Pro',  'color' => '#EF4444', 'rating_min' => 5.0, 'rating_max' => 7.0],
    8 => ['level' => 'Avanzato-Pro',  'color' => '#EF4444', 'rating_min' => 5.0, 'rating_max' => 7.0],
  ];
  return $map[$service_id] ?? ['level' => '', 'color' => '#cccccc', 'rating_min' => 0, 'rating_max' => 0];
}

/** Ritorna l'elenco delle 3 fasce, per UI di selezione/filtro */
function pz_get_all_levels() {
  return [
    [
      'key'            => 'Principiante',
      'label'          => 'Principiante',
      'rating_min'     => 1.0,
      'rating_max'     => 2.5,
      'rating_default' => 1.75,
      'color'          => '#1FB856',
      'description'    => 'Imparo le basi: servizio, diritto, primi colpi.',
    ],
    [
      'key'            => 'Intermedio',
      'label'          => 'Intermedio',
      'rating_min'     => 3.0,
      'rating_max'     => 4.5,
      'rating_default' => 3.75,
      'color'          => '#F59E0B',
      'description'    => 'Controllo la palla, uso le pareti, gioco con tattica.',
    ],
    [
      'key'            => 'Avanzato-Pro',
      'label'          => 'Avanzato-Pro',
      'rating_min'     => 5.0,
      'rating_max'     => 7.0,
      'rating_default' => 6.0,
      'color'          => '#EF4444',
      'description'    => 'Gioco aggressivo, alta precisione, tornei agonistici.',
    ],
  ];
}

/** Rating dell'utente (1.0 – 7.0). 0 se non impostato. */
function pz_get_user_rating($user_id = null) {
  if (!$user_id) $user_id = get_current_user_id();
  if (!$user_id) return 0.0;
  $r = get_user_meta($user_id, 'pz_user_rating', true);
  return $r ? (float)$r : 0.0;
}

/** True se l'utente ha già auto-dichiarato il proprio livello */
function pz_user_has_rating($user_id = null) {
  return pz_get_user_rating($user_id) > 0;
}

/** Ritorna la fascia (chiave) corrispondente al rating dell'utente */
function pz_get_user_level_key($user_id = null) {
  $rating = pz_get_user_rating($user_id);
  if (!$rating) return '';
  foreach (pz_get_all_levels() as $lvl) {
    if ($rating >= $lvl['rating_min'] && $rating <= $lvl['rating_max']) return $lvl['key'];
  }
  return '';
}

/**
 * Verifica se l'utente è "in range" rispetto a una partita.
 * @return bool|null   true=in range, false=fuori, null=rating sconosciuto
 */
function pz_user_matches_level($user_id, $service_id) {
  $rating = pz_get_user_rating($user_id);
  if (!$rating) return null;
  $info = pz_get_level_info($service_id);
  if (!$info['rating_min']) return null;
  return ($rating >= $info['rating_min'] && $rating <= $info['rating_max']);
}


/* ============================================================
 *  DATETIME HELPERS
 * ============================================================ */
function pz_dt_from_meta($date, $time) {
  if (!$date) return null;
  $t = $time ?: '00:00:00';
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

add_shortcode('pz_debug_nav', function() {
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $req_path = '/' . trim($req_path, '/') . '/';
    return '<pre>REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . '<br>req_path: ' . $req_path . '</pre>';
});


/* ============================================================
 *  LOGIN WALL
 *  Schermata "accesso richiesto" coerente con il design system.
 *  Usata da tutti gli shortcode che richiedono autenticazione.
 * ============================================================ */

/**
 * @param string $icon      Emoji icona (es. '💰')
 * @param string $title     Titolo (es. 'Il tuo borsellino')
 * @param string $subtitle  Testo descrittivo
 * @param string $login_url Path pagina login (default: /inizio/login/)
 */
function pz_render_login_wall( $icon = '🔒', $title = 'Accesso richiesto', $subtitle = 'Effettua il login per continuare.', $login_url = '/inizio/login/' ) {
    $url = esc_url( home_url( $login_url ) );
    ob_start();
    ?>
    <div style="font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px 48px;text-align:center;min-height:320px">

        <div style="width:72px;height:72px;background:#E8F8EE;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin-bottom:20px"><?php echo $icon; ?></div>

        <h2 style="font-size:22px;font-weight:700;color:#161B2E;margin:0 0 10px"><?php echo esc_html( $title ); ?></h2>

        <p style="font-size:15px;color:#8B92A5;margin:0 0 32px;max-width:300px;line-height:1.6"><?php echo esc_html( $subtitle ); ?></p>

        <a href="<?php echo $url; ?>" style="display:inline-block;background:#1FB856;color:#fff !important;font-size:15px;font-weight:700;text-decoration:none !important;padding:14px 40px;border-radius:12px;letter-spacing:0.3px">Accedi</a>

        <p style="margin:20px 0 0;font-size:13px;color:#8B92A5">
            Non hai un account? <a href="<?php echo $url; ?>" style="color:#1FB856;font-weight:600;text-decoration:none">Registrati</a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}
