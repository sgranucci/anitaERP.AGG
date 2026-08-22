(function ($) {
    'use strict';

    var scanner = null;
    var ultimoCodigo = '';
    var ultimoTs = 0;
    var DEBOUNCE_MS = 1600;

    function formatosSoportados() {
        if (typeof Html5QrcodeSupportedFormats === 'undefined') {
            return undefined;
        }

        return [
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.ITF,
            Html5QrcodeSupportedFormats.QR_CODE,
        ];
    }

    function setFeedback(texto, esError) {
        var $f = $('#tm_camara_feedback');
        $f.text(texto || '');
        $f.toggleClass('text-danger', !!esError);
        $f.toggleClass('text-success', !esError && !!texto);
    }

    function contextoInseguro() {
        return !window.isSecureContext;
    }

    function aplicarCodigo(codigo) {
        codigo = String(codigo || '').trim();
        if (!codigo) {
            return;
        }
        var ahora = Date.now();
        if (codigo === ultimoCodigo && ahora - ultimoTs < DEBOUNCE_MS) {
            return;
        }
        ultimoCodigo = codigo;
        ultimoTs = ahora;
        setFeedback('Leído: ' + codigo);
        if (typeof window.tmAplicarCodigoPickeo === 'function') {
            window.tmAplicarCodigoPickeo(codigo);
        }
    }

    function detenerScanner() {
        window.tmCamaraPickeoActiva = false;
        if (!scanner) {
            return $.Deferred().resolve().promise();
        }
        var instancia = scanner;
        scanner = null;
        return instancia.stop().catch(function () {
            return null;
        }).then(function () {
            try {
                instancia.clear();
            } catch (e) {
                // ignore
            }
        });
    }

    function cerrarCamara() {
        detenerScanner().always(function () {
            $('#tm_camara_overlay').removeClass('tm-camara-visible');
            $('#tm_camara_foto_wrap').addClass('d-none');
            setFeedback('');
            ultimoCodigo = '';
            if (typeof window.tmAplicarCodigoPickeo === 'function') {
                var $pickeo = $('#tm_pickeo_codigo');
                if ($pickeo.length) {
                    $pickeo.trigger('focus');
                }
            }
        });
    }

    function mostrarFotoFallback(motivo) {
        $('#tm_camara_foto_wrap').removeClass('d-none');
        setFeedback(
            motivo || 'El navegador no pudo abrir la cámara en vivo. Sacá una foto del código.',
            true
        );
    }

    function iniciarCamaraViva() {
        if (typeof Html5Qrcode === 'undefined') {
            mostrarFotoFallback('No se pudo cargar el lector de códigos.');
            return;
        }

        window.tmCamaraPickeoActiva = true;
        $('#tm_camara_overlay').addClass('tm-camara-visible');
        $('#tm_camara_foto_wrap').addClass('d-none');
        setFeedback('Apuntá al código de barras…');

        if (contextoInseguro()) {
            mostrarFotoFallback(
                'La cámara en vivo requiere HTTPS. En esta red podés sacar una foto del código, usar un lector Bluetooth o tipear el SKU.'
            );
            return;
        }

        scanner = new Html5Qrcode('tm_camara_reader', {
            formatsToSupport: formatosSoportados(),
            verbose: false,
        });

        var config = {
            fps: 10,
            qrbox: { width: 260, height: 140 },
            aspectRatio: 1.333,
        };

        scanner.start(
            { facingMode: 'environment' },
            config,
            function (decodedText) {
                aplicarCodigo(decodedText);
            },
            function () {
                // frame sin código
            }
        ).catch(function (err) {
            scanner = null;
            var msg = (err && err.message) ? err.message : String(err || '');
            mostrarFotoFallback(
                'No se pudo usar la cámara en vivo (' + msg + '). Sacá una foto del código.'
            );
        });
    }

    function leerFoto(file) {
        if (!file || typeof Html5Qrcode === 'undefined') {
            return;
        }
        setFeedback('Leyendo foto…');
        var lector = new Html5Qrcode('tm_camara_reader_foto', { verbose: false });
        lector.scanFile(file, true)
            .then(function (decodedText) {
                aplicarCodigo(decodedText);
                try {
                    lector.clear();
                } catch (e) {
                    // ignore
                }
            })
            .catch(function () {
                setFeedback('No se leyó un código en la foto. Probá de nuevo con más luz y el código al centro.', true);
            });
    }

    $(function () {
        $('#tm_btn_camara').on('click', iniciarCamaraViva);
        $('#tm_camara_cerrar').on('click', cerrarCamara);
        $('#tm_camara_foto').on('change', function () {
            var file = this.files && this.files[0];
            this.value = '';
            leerFoto(file);
        });
    });
})(jQuery);
