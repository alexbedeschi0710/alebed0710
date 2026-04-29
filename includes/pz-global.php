<?php
/**
 * PadelZero — Global Shared Styles
 *
 * Stili CSS condivisi da tutti i moduli del plugin.
 * Contiene i componenti ricorrenti: header, back button, titolo pagina,
 * sottotitolo, CTA fisso in basso, wrap principale.
 *
 * Classi globali (prefisso pz-g-):
 *   .pz-g-wrap         → contenitore principale pagina
 *   .pz-g-header       → header con back + titolo centrato
 *   .pz-g-back         → bottone tondo freccia indietro
 *   .pz-g-title        → titolo pagina (centrato assoluto)
 *   .pz-g-sub          → sottotitolo / descrizione
 *   .pz-g-cta-wrap     → barra CTA fissa in basso
 *   .pz-g-cta-inner    → contenitore interno CTA (max-width)
 *   .pz-g-cta          → bottone CTA principale verde
 *   .pz-g-cta--lime    → bottone CTA variante lime (#9FD731)
 *   .pz-g-cta:disabled → stato disabilitato
 */
function pz_global_styles() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
<style id="pz-global-css">
/* ============================================================
   PZ GLOBAL — reset base per tutti i moduli
   ============================================================ */
.pz-g-wrap {
  max-width: 480px !important;
  margin: 0 auto !important;
  padding: 0 18px 160px !important;
  font-family: 'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;
  color: #161B2E !important;
  -webkit-font-smoothing: antialiased !important;
  position: relative !important;
  box-sizing: border-box !important;
}
.pz-g-wrap *, .pz-g-wrap *::before, .pz-g-wrap *::after {
  box-sizing: border-box !important;
}

/* ============================================================
   HEADER (back + titolo centrato)
   ============================================================ */
.pz-g-header {
  display: flex !important;
  align-items: center !important;
  position: relative !important;
  min-height: 44px !important;
  margin-top: 16px !important;
  margin-bottom: 14px !important;
}

/* ============================================================
   BACK BUTTON
   ============================================================ */
.pz-g-back {
  width: 44px !important;
  height: 44px !important;
  background: #FFFFFF !important;
  border: 1.5px solid #D9DCE3 !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  cursor: pointer !important;
  box-shadow: none !important;
  padding: 0 !important;
  flex-shrink: 0 !important;
  position: relative !important;
  z-index: 1 !important;
  text-decoration: none !important;
  transition: background .15s ease, border-color .15s ease !important;
}
.pz-g-back svg {
  stroke: #8B92A5 !important;
  fill: none !important;
  width: 18px !important;
  height: 18px !important;
}
.pz-g-back:hover {
  background: #F4F5F8 !important;
  border-color: #8B92A5 !important;
}
.pz-g-back.is-hidden {
  visibility: hidden !important;
  pointer-events: none !important;
}

/* ============================================================
   TITOLO PAGINA (centrato in assoluto)
   ============================================================ */
.pz-g-title {
  position: absolute !important;
  left: 0 !important;
  right: 0 !important;
  font-size: 19px !important;
  font-weight: 700 !important;
  letter-spacing: -0.02em !important;
  text-align: center !important;
  pointer-events: none !important;
  margin: 0 !important;
  color: #161B2E !important;
  background: transparent !important;
  text-transform: none !important;
}

/* ============================================================
   SOTTOTITOLO / DESCRIZIONE
   ============================================================ */
.pz-g-sub {
  font-size: 14px !important;
  color: #8B92A5 !important;
  line-height: 1.5 !important;
  margin: 0 0 22px !important;
  padding: 0 !important;
  background: transparent !important;
  text-transform: none !important;
  text-align: center !important;
}

/* ============================================================
   CTA FISSO IN BASSO
   ============================================================ */
.pz-g-cta-wrap {
  position: fixed !important;
  bottom: 64px !important;
  left: 0 !important;
  right: 0 !important;
  width: 100% !important;
  box-sizing: border-box !important;
  background: #FFFFFF !important;
  border-top: 1px solid #ECEEF2 !important;
  padding: 16px 18px !important;
  z-index: 99 !important;
}
.pz-g-cta-inner {
  max-width: 480px !important;
  margin: 0 auto !important;
  width: 100% !important;
}

/* CTA verde principale */
.pz-g-cta {
  width: 100% !important;
  display: block !important;
  border: none !important;
  background: #1FB856 !important;
  color: #FFFFFF !important;
  font-family: 'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  letter-spacing: .08em !important;
  text-transform: uppercase !important;
  padding: 17px !important;
  border-radius: 14px !important;
  cursor: pointer !important;
  transition: background .2s ease, transform .2s ease, box-shadow .2s ease !important;
  box-shadow: 0 10px 24px -8px rgba(31,184,86,.55) !important;
  text-align: center !important;
}
.pz-g-cta:hover:not(:disabled) {
  background: #18A049 !important;
  transform: translateY(-1px) !important;
}
.pz-g-cta:disabled {
  background: #D5D8DE !important;
  color: #9097A5 !important;
  cursor: not-allowed !important;
  box-shadow: none !important;
  transform: none !important;
}

/* CTA variante lime (es. Partecipa / Prenota lobby) */
.pz-g-cta--lime {
  background: #9FD731 !important;
  box-shadow: 0 10px 24px -8px rgba(159,215,49,.45) !important;
}
.pz-g-cta--lime:hover:not(:disabled) {
  background: #8BC41F !important;
}
</style>
    <?php
}
add_action( 'wp_head', 'pz_global_styles', 1 );
