<?php
/**
 * PadelZero - Prenotazione Partita Privata
 *
 * Shortcode: [pz_private_booking]
 *
 * Wizard mobile-first per prenotare un campo padel (uso privato).
 * Step: scelta data → durata → orario → campo → conferma.
 *
 * Note architetturali:
 *  - CSS locali solo per componenti specifici (card, slot, toggle durata…)
 *  - Header / back / titolo / sottotitolo: pz_global_styles() (pz-global.php)
 *  - CTA fisso in basso: pz_global_styles() (.pz-g-cta-wrap / .pz-g-cta)
 *  - Disponibilità via AJAX (pz_private_availability)
 *  - Prenotazione via AJAX (pz_private_book)
 */