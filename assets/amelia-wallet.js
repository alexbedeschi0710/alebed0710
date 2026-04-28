/**
 * PadelZero - Wallet Integration in Amelia Frontend
 */

console.log('=== PadelZero Wallet: START ===');

jQuery(document).ready(function($) {
    console.log('jQuery OK');
    
    var config = window.pzAmeliaWallet || {};
    console.log('User logged in:', config.is_logged_in);
    console.log('Balance:', config.user_balance);
    
    if (!config.is_logged_in) {
        console.log('Utente non loggato - skip wallet');
        return;
    }
    
    // Controlla ogni secondo se sei nello step Pagamenti
    var attempts = 0;
    
    var checkPaymentStep = setInterval(function() {
        attempts++;
        
        if (attempts > 60) {
            console.log('Timeout - stop ricerca');
            clearInterval(checkPaymentStep);
            return;
        }
        
        // METODO 1: Cerca se il tab "Pagamenti" è attivo
        var paymentTab = document.querySelector('.el-step.is-process, .el-step.is-finish');
        var paymentActive = false;
        
        if (paymentTab && paymentTab.textContent.indexOf('Pagamenti') > -1) {
            paymentActive = true;
        }
        
        // METODO 2: Cerca il testo "Il pagamento verrà effettuato"
        var pageText = document.body.innerText;
        if (pageText.indexOf('Il pagamento verrà effettuato') > -1 || 
            pageText.indexOf('Importo Totale') > -1) {
            paymentActive = true;
        }
        
        // METODO 3: Cerca l'URL con ameliaAppointmentBooking
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('ameliaAppointmentBooking')) {
            paymentActive = true;
        }
        
        if (paymentActive && !document.getElementById('pz-wallet-option')) {
            console.log('✓ Step Pagamenti attivo! Inserisco wallet...');
            clearInterval(checkPaymentStep);
            addWalletOption();
        }
    }, 1000);
    
    function addWalletOption() {
        if (document.getElementById('pz-wallet-option')) {
            console.log('Wallet già inserito');
            return;
        }
        
        var div = document.createElement('div');
        div.id = 'pz-wallet-option';
        div.style.cssText = 'border:3px solid #9FD731;border-radius:8px;padding:20px;margin:20px auto;background:white;max-width:500px;box-shadow:0 4px 12px rgba(0,0,0,0.2)';
        
        var balance = parseFloat(config.user_balance || 0).toFixed(2);
        
        div.innerHTML = '<div style="text-align:center">' +
            '<h3 style="margin:0 0 10px 0;color:#333">💳 Paga con Borsellino</h3>' +
            '<p style="margin:0 0 15px 0;color:#666">Saldo disponibile: <strong style="color:#9FD731;font-size:20px">€' + balance + '</strong></p>' +
            '<button id="pz-wallet-pay-btn" style="padding:15px 30px;background:#9FD731;color:white;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;width:100%">CONFERMA PAGAMENTO WALLET</button>' +
            '</div>';
        
        // Trova dove inserire (cerca il container del riepilogo)
        var container = document.querySelector('.am-confirmation-booking, [class*="summary"], .am-el-content, body');
        
        if (container && container.querySelector) {
            // Cerca "Sommario" o "Importo Totale"
            var elements = container.querySelectorAll('*');
            var summary = null;
            
            for (var i = 0; i < elements.length; i++) {
                var text = elements[i].textContent;
                if (text.indexOf('Sommario') > -1 || text.indexOf('Importo Totale') > -1) {
                    summary = elements[i];
                    break;
                }
            }
            
            if (summary) {
                summary.parentElement.insertBefore(div, summary.nextSibling);
                console.log('✓ Wallet inserito dopo Sommario');
            } else {
                container.insertBefore(div, container.firstChild);
                console.log('✓ Wallet inserito in container');
            }
        } else {
            document.body.appendChild(div);
            console.log('✓ Wallet inserito in body');
        }
        
        document.getElementById('pz-wallet-pay-btn').addEventListener('click', function() {
            payWithWallet();
        });
    }
    
    function payWithWallet() {
        console.log('Inizio pagamento Wallet...');
        
        var urlParams = new URLSearchParams(window.location.search);
        var appointmentId = urlParams.get('ameliaAppointmentBooking');
        
        if (!appointmentId) {
            alert('Errore: completa prima tutti gli step della prenotazione');
            return;
        }
        
        var serviceId = extractServiceId();
        
        if (!serviceId) {
            alert('Errore: impossibile identificare il servizio. Contatta il supporto.');
            return;
        }
        
        console.log('Appointment ID:', appointmentId);
        console.log('Service ID:', serviceId);
        
        // Mostra loader
        $('body').append(
            '<div id="pz-loader" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:999999;display:flex;align-items:center;justify-content:center">' +
            '<div style="background:white;padding:40px;border-radius:12px;text-align:center">' +
            '<h3 style="margin:0 0 20px 0">Elaborazione pagamento...</h3>' +
            '<div style="border:4px solid #f3f3f3;border-top:4px solid #9FD731;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto"></div>' +
            '</div>' +
            '</div>' +
            '<style>@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}</style>'
        );
        
        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'pz_amelia_wallet_payment',
                nonce: config.nonce,
                appointment_id: appointmentId,
                service_id: serviceId
            },
            success: function(response) {
                $('#pz-loader').remove();
                
                if (response.success) {
                    var newBalance = parseFloat(response.data.new_balance || 0).toFixed(2);
                    alert('✅ Prenotazione confermata!\n\nPagato con Wallet\nNuovo saldo: €' + newBalance);
                    window.location.href = window.location.pathname;
                } else {
                    var errorMsg = response.data || 'Errore durante il pagamento';
                    alert('❌ Errore: ' + errorMsg);
                }
            },
            error: function() {
                $('#pz-loader').remove();
                alert('❌ Errore di connessione. Riprova.');
            }
        });
    }
    
    function extractServiceId() {
        var text = document.body.innerText.toLowerCase();
        
        if (text.indexOf('principiante') > -1) return 3;
        if (text.indexOf('intermedio') > -1) return 4;
        if (text.indexOf('avanzato') > -1) return 5;
        if (text.indexOf('pro') > -1) return 6;
        
        console.warn('Service ID non trovato nel testo della pagina');
        return null;
    }
});

console.log('=== PadelZero Wallet: END ===');

