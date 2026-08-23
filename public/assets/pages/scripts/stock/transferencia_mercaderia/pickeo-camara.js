(function ($) {
    'use strict';

    var scanner = null;
    var ultimoCodigo = '';
    var ultimoTs = 0;
    var DEBOUNCE_MS = 1600;
    var MAX_LADO_FOTO = 1600;

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
        var $estado = $('#tm_estado');
        if ($estado.length) {
            $estado.text(texto || '');
            $estado.toggleClass('text-danger', !!esError);
        }
    }

    function mostrarPreview(src) {
        var $img = $('#tm_camara_preview');
        if (!$img.length) {
            return;
        }
        if (src) {
            $img.attr('src', src).addClass('tm-camara-preview-visible');
        } else {
            $img.removeAttr('src').removeClass('tm-camara-preview-visible');
        }
    }

    function aplicarCodigo(codigo) {
        codigo = String(codigo || '').replace(/\s+/g, '').trim();
        if (!codigo) {
            return false;
        }
        var ahora = Date.now();
        if (codigo === ultimoCodigo && ahora - ultimoTs < DEBOUNCE_MS) {
            return true;
        }
        ultimoCodigo = codigo;
        ultimoTs = ahora;
        $('#tm_pickeo_codigo').val(codigo);
        setFeedback('Leído: ' + codigo + '. Si no está en el ERP, igual ya se vio el código.', false);
        if (typeof window.tmAplicarCodigoPickeo === 'function') {
            window.tmAplicarCodigoPickeo(codigo);
        }
        return true;
    }

    function limpiarCodigosLeidos() {
        $('#tm_camara_codigos').empty().removeClass('tm-camara-codigos-visible');
    }

    function mostrarCodigosLeidos(codigos) {
        var $box = $('#tm_camara_codigos');
        $box.empty();
        if (!codigos || !codigos.length) {
            $box.removeClass('tm-camara-codigos-visible');
            return;
        }
        if (codigos.length === 1) {
            $box.removeClass('tm-camara-codigos-visible');
            return;
        }
        codigos.forEach(function (codigo) {
            $('<button type="button" class="btn btn-outline-light btn-sm"/>')
                .text(codigo)
                .on('click', function () {
                    aplicarCodigo(codigo);
                })
                .appendTo($box);
        });
        $box.addClass('tm-camara-codigos-visible');
    }

    function decodificarEnServidor(file) {
        var url = window.TM_URLS && window.TM_URLS.decodificarFoto;
        if (!url || !file) {
            return $.Deferred().resolve([]).promise();
        }
        var data = new FormData();
        data.append('foto', file);
        data.append('_token', $('meta[name="csrf-token"]').attr('content') || '');
        return $.ajax({
            url: url,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
        }).then(function (resp) {
            return (resp && resp.codigos) ? resp.codigos : [];
        }, function () {
            return [];
        });
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
            mostrarPreview('');
            limpiarCodigosLeidos();
            setFeedback('');
            ultimoCodigo = '';
        });
    }

    function abrirFotoNativa() {
        var el = document.getElementById('tm_camara_foto');
        if (el) {
            el.click();
        }
    }

    function mostrarFotoFallback(motivo, abrirNativa) {
        window.tmCamaraPickeoActiva = true;
        $('#tm_camara_overlay').addClass('tm-camara-visible');
        $('#tm_camara_foto_wrap').removeClass('d-none');
        setFeedback(motivo || 'Sacá una foto del código de barras.', false);
        if (abrirNativa) {
            abrirFotoNativa();
        }
    }

    function iniciarCamaraViva() {
        window.tmCamaraPickeoActiva = true;
        $('#tm_camara_overlay').addClass('tm-camara-visible');
        $('#tm_camara_foto_wrap').addClass('d-none');
        mostrarPreview('');
        setFeedback('Apuntá al código de barras…');

        if (typeof Html5Qrcode === 'undefined') {
            mostrarFotoFallback('No se pudo cargar el lector. Se abre la cámara para sacar foto.', true);
            return;
        }

        scanner = new Html5Qrcode('tm_camara_reader', {
            formatsToSupport: formatosSoportados(),
            verbose: false,
        });

        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 260, height: 140 }, aspectRatio: 1.333 },
            function (decodedText) {
                aplicarCodigo(decodedText);
            },
            function () {
                // frame sin código
            }
        ).catch(function () {
            scanner = null;
            mostrarFotoFallback('Sacá una foto del código, bien de cerca y con luz.', true);
        });
    }

    function cargarImagen(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('No se pudo abrir la foto.'));
            };
            img.src = url;
        });
    }

    function canvasDesdeImagen(img, maxLado) {
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        var escala = Math.min(1, maxLado / Math.max(w, h));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * escala));
        canvas.height = Math.max(1, Math.round(h * escala));
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function detectarBarcodeDetector(canvas) {
        if (typeof BarcodeDetector === 'undefined') {
            return Promise.resolve('');
        }
        var pedidos = ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'itf', 'qr_code'];
        var crear = BarcodeDetector.getSupportedFormats
            ? BarcodeDetector.getSupportedFormats().then(function (soportados) {
                var usar = pedidos.filter(function (f) {
                    return soportados.indexOf(f) !== -1;
                });
                return new BarcodeDetector(usar.length ? { formats: usar } : undefined);
            })
            : Promise.resolve(new BarcodeDetector({ formats: pedidos }));

        return crear
            .then(function (detector) {
                return detector.detect(canvas);
            })
            .then(function (hallados) {
                if (!hallados || !hallados.length) {
                    return '';
                }
                var valores = [];
                hallados.forEach(function (item) {
                    var v = item && item.rawValue ? String(item.rawValue).replace(/\s+/g, '').trim() : '';
                    if (v && valores.indexOf(v) === -1) {
                        valores.push(v);
                    }
                });
                if (valores.length > 1) {
                    setFeedback('Se leyeron ' + valores.length + ' códigos. Se usa el primero: ' + valores[0], false);
                }
                return valores[0] || '';
            })
            .catch(function () {
                return '';
            });
    }

    function canvasAArchivo(canvas) {
        return new Promise(function (resolve, reject) {
            if (!canvas.toBlob) {
                reject(new Error('El navegador no puede procesar la foto.'));
                return;
            }
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('No se pudo preparar la foto.'));
                    return;
                }
                resolve(new File([blob], 'pickeo.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.85);
        });
    }

    function detectarHtml5Qrcode(file) {
        if (typeof Html5Qrcode === 'undefined') {
            return Promise.resolve('');
        }
        var lector = new Html5Qrcode('tm_camara_reader_foto', {
            formatsToSupport: formatosSoportados(),
            verbose: false,
        });
        return lector.scanFile(file, true)
            .then(function (texto) {
                try {
                    lector.clear();
                } catch (e) {
                    // ignore
                }
                return texto ? String(texto).replace(/\s+/g, '').trim() : '';
            })
            .catch(function () {
                try {
                    lector.clear();
                } catch (e) {
                    // ignore
                }
                return '';
            });
    }

    function leerFoto(file) {
        if (!file) {
            setFeedback('No llegó la foto. Probá de nuevo.', true);
            return;
        }

        window.tmCamaraPickeoActiva = true;
        $('#tm_camara_overlay').addClass('tm-camara-visible');
        $('#tm_camara_foto_wrap').removeClass('d-none');
        limpiarCodigosLeidos();
        setFeedback('Leyendo foto…');

        cargarImagen(file)
            .then(function (img) {
                var canvas = canvasDesdeImagen(img, MAX_LADO_FOTO);
                mostrarPreview(canvas.toDataURL('image/jpeg', 0.8));
                return canvasAArchivo(canvas).then(function (archivo) {
                    return decodificarEnServidor(archivo).then(function (codigos) {
                        if (codigos && codigos.length) {
                            return codigos;
                        }
                        return detectarBarcodeDetector(canvas).then(function (codigo) {
                            if (codigo) {
                                return [codigo];
                            }
                            return detectarHtml5Qrcode(archivo).then(function (local) {
                                return local ? [local] : [];
                            });
                        });
                    });
                });
            })
            .then(function (codigos) {
                if (!codigos || !codigos.length) {
                    setFeedback(
                        'No se leyó un código. Acercá el celular, un solo código, nítido y de frente.',
                        true
                    );
                    return;
                }
                mostrarCodigosLeidos(codigos);
                if (codigos.length > 1) {
                    setFeedback(
                        'Se leyeron ' + codigos.length + ' códigos. Tocá uno para usarlo. El primero ya quedó cargado.',
                        false
                    );
                }
                aplicarCodigo(codigos[0]);
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'No se pudo leer la foto.';
                setFeedback(msg, true);
            });
    }

    $(function () {
        $('#tm_btn_camara').on('click', iniciarCamaraViva);
        $('#tm_camara_cerrar').on('click', cerrarCamara);
        $('#tm_btn_otra_foto').on('click', function () {
            abrirFotoNativa();
        });
        $(document).on('change', '#tm_camara_foto', function () {
            var input = this;
            var file = input.files && input.files[0];
            leerFoto(file);
            input.value = '';
        });
    });
})(jQuery);
