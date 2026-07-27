(function ($) {
    'use strict';

    var OVERLAY_ID = 'flash-calculo-aviso';
    var TITULO_ID = 'flash-calculo-aviso-titulo';
    var SUBTITULO_ID = 'flash-calculo-aviso-subtitulo';
    var TITULO_DEFAULT = 'Calculando flash…';
    var SUBTITULO_DEFAULT = 'Consultando ERP, Wigos y Anita. Por favor espere. No cierre ni recargue la página.';

    var CAMPOS_DECIMAL = [
        'ayb', 'estac', 'vending', 'bingo_total_venta', 'bingo_resultado',
        'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count', 'win_ol_slot',
        'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul', 'win_ol_rul',
        'show'
    ];

    var CAMPOS_ENTERO = [
        'cant_vehic', 'bingo_cant_carton', 'cant_slots', 'cant_rul'
    ];

    var SELECTOR_DECIMAL = CAMPOS_DECIMAL.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_ENTERO = CAMPOS_ENTERO.map(function (c) { return '#' + c; }).join(', ');
    var SELECTOR_TODOS = [SELECTOR_DECIMAL, SELECTOR_ENTERO].filter(Boolean).join(', ');

    function parseDecimal(str) {
        if (str == null || str === '') {
            return 0;
        }
        var t = String(str).trim().replace(/\s/g, '');
        if (t.indexOf(',') >= 0) {
            t = t.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(t)) {
            t = t.replace(/\./g, '');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    }

    function parseEntero(str) {
        return Math.round(parseDecimal(str));
    }

    function fmtDecimal(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fmtEntero(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function formatearDecimalInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtDecimal(parseDecimal(el.value));
    }

    function formatearEnteroInput(el) {
        if (!el) {
            return;
        }
        if (el.value === '' || el.value == null) {
            el.value = '';
            return;
        }
        el.value = fmtEntero(parseEntero(el.value));
    }

    function desformatearDecimalInput(el) {
        if (!el || el.value === '') {
            return;
        }
        var n = parseDecimal(el.value);
        el.value = String(n);
    }

    function desformatearEnteroInput(el) {
        if (!el || el.value === '') {
            return;
        }
        el.value = String(parseEntero(el.value));
    }

    function marcarValoresDesdeFormulario() {
        var $flag = $('#flash_valores_desde_formulario');
        if ($flag.length) {
            $flag.val('1');
        }
    }

    function aplicarDatos(datos) {
        if (!datos) {
            return;
        }
        CAMPOS_DECIMAL.forEach(function (campo) {
            if (typeof datos[campo] !== 'undefined') {
                $('#' + campo).val(fmtDecimal(datos[campo]));
            }
        });
        CAMPOS_ENTERO.forEach(function (campo) {
            if (typeof datos[campo] !== 'undefined') {
                $('#' + campo).val(fmtEntero(datos[campo]));
            }
        });
        marcarValoresDesdeFormulario();
    }

    function normalizarAntesDeEnviar(form) {
        $(form).find(SELECTOR_DECIMAL).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseDecimal(this.value));
        });
        $(form).find(SELECTOR_ENTERO).each(function () {
            if (this.value === '' || this.value == null) {
                this.value = '0';
                return;
            }
            this.value = String(parseEntero(this.value));
        });
    }

    function initFormatoCampos() {
        $(SELECTOR_DECIMAL).each(function () {
            formatearDecimalInput(this);
        });
        $(SELECTOR_ENTERO).each(function () {
            formatearEnteroInput(this);
        });
    }

    function mostrarAvisoCalculo(visible, titulo, subtitulo) {
        var $aviso = $('#' + OVERLAY_ID);
        if (!$aviso.length) {
            return;
        }

        if (visible) {
            $('#' + TITULO_ID).text(titulo || TITULO_DEFAULT);
            $('#' + SUBTITULO_ID).text(subtitulo || SUBTITULO_DEFAULT);
            $aviso.removeClass('d-none').css('display', 'flex').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
            return;
        }

        $aviso.addClass('d-none').css('display', '').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
    }

    $(document).on('focus', SELECTOR_DECIMAL, function () {
        desformatearDecimalInput(this);
        this.select();
    });

    $(document).on('blur', SELECTOR_DECIMAL, function () {
        formatearDecimalInput(this);
    });

    $(document).on('focus', SELECTOR_ENTERO, function () {
        desformatearEnteroInput(this);
        this.select();
    });

    $(document).on('blur', SELECTOR_ENTERO, function () {
        formatearEnteroInput(this);
    });

    $(document).on('change input', SELECTOR_TODOS, function () {
        marcarValoresDesdeFormulario();
    });

    $(document).on('submit', '#form-general', function () {
        normalizarAntesDeEnviar(this);
    });

    $('#btn-flash-calcular').on('click', function () {
        var empresaId = $('#empresa_id').val();
        var fecha = $('#fecha').val();
        if (!empresaId || !fecha) {
            alert('Seleccione empresa y fecha.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        mostrarAvisoCalculo(true);
        $.ajax({
            url: window.flashCalcularUrl || '/caja/flash/api/calcular',
            method: 'POST',
            timeout: 300000,
            data: {
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                empresa_id: empresaId,
                fecha: fecha
            }
        }).done(function (resp) {
            if (resp && resp.ok && resp.datos) {
                aplicarDatos(resp.datos);
                if (resp.datos.advertencias_wigos && resp.datos.advertencias_wigos.length) {
                    alert('Flash calculado con advertencias Wigos:\n' + resp.datos.advertencias_wigos.join('\n'));
                }
            } else {
                alert((resp && resp.message) ? resp.message : 'No se recibieron datos de c\u00e1lculo.');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al calcular flash.';
            alert(msg);
        }).always(function () {
            mostrarAvisoCalculo(false);
            $btn.prop('disabled', false);
        });
    });

    $(function () {
        initFormatoCampos();
    });
})(jQuery);
