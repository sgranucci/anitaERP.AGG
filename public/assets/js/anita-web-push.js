/**
 * Suscripción Web Push (PWA / navegador). Opt-in del usuario.
 */
(function () {
    'use strict';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.AnitaWebPush = {
        async activar: async function () {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                throw new Error('Este navegador no soporta Web Push.');
            }
            var keyRes = await fetch((window.AnitaWebPushUrls && window.AnitaWebPushUrls.publicKey) || '/configuracion/web-push/public-key', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            var keyJson = await keyRes.json();
            if (!keyJson.ok || !keyJson.publicKey) {
                throw new Error('Web Push no está habilitado en el servidor (faltan claves VAPID).');
            }
            var perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                throw new Error('Permiso de notificaciones denegado.');
            }
            var reg = await navigator.serviceWorker.ready;
            var sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(keyJson.publicKey),
            });
            var res = await fetch((window.AnitaWebPushUrls && window.AnitaWebPushUrls.suscribir) || '/configuracion/web-push/suscribir', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify(sub.toJSON()),
            });
            var json = await res.json();
            if (!json.ok) throw new Error('No se pudo guardar la suscripción.');
            return json;
        },
    };
})();
