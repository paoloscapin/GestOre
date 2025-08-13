<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
            appId: "4d2be59f-5ba2-4852-9003-ad42e718d424",
            safari_web_id: "web.onesignal.auto.3f58661c-f8ad-4946-a9b6-84125eec4421",
            notifyButton: {
                enable: true,
                text: {
                    'tip.state.unsubscribed': 'Iscriviti alle notifiche di GestOre',
                    'tip.state.subscribed': 'Sei iscritto alle notifiche di GestOre',
                    'tip.state.blocked': 'Hai bloccato le notifiche di GestOre',
                    'message.prenotify': 'Clicca per attivare le notifiche di GestOre',
                    'message.action.subscribed': 'Grazie per esserti iscritto!',
                    'message.action.resubscribed': 'Hai riattivato le notifiche di GestOre',
                    'message.action.unsubscribed': 'Non riceverai più notifiche di GestOre',
                    'dialog.main.title': 'Gestione notifiche GestOre',
                    'dialog.main.button.subscribe': 'ISCRIVITI ORA',
                    'dialog.main.button.unsubscribe': 'DISISCRIVITI',
                    'dialog.blocked.title': 'Sblocco notifiche GestOre',
                    'dialog.blocked.message': 'Segui le istruzioni per riattivare le notifiche di GestOre'
                }
            },
            welcomeNotification: {
                disable: true
            }
        });
    });
    OneSignal.push(function() {
        OneSignal.on('subscriptionChange', function(isSubscribed) {
            if (isSubscribed) {
                // Assumi che userId sia disponibile in JS
                var userId = "<?php echo $_SESSION['username']; ?>"; // Sostituisci con il metodo per ottenere l'ID utente

                // Imposta il tag userId su OneSignal
                OneSignal.sendTag("userId", userId).then(function() {
                    console.log("Tag userId impostato: " + userId);
                }).catch(function(e) {
                    console.error("Errore impostazione tag: ", e);
                });
                
            }
        });
    });
</script>

<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// favicon ref
echo '<link rel="icon" href="' . $__application_base_path . '/ore-32.png" />';
echo '<link rel="stylesheet" href="' . $__application_base_path . '/css/releaseversion.css">';
?>