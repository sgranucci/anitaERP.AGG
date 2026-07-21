/**
 * Recalcular transferencias a depósito fórmulas desde ABM artículo (coeficiente).
 */
(function ($) {
    'use strict';

    var previewData = null;
    var previewTimer = null;

    function carpetaBase() {
        if (typeof window.carpetaBase === 'string' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
    }

    function articuloId() {
        var el = document.getElementById('articulo_id');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function urlPreview(id) {
        var el = document.getElementById('articulo-preview-recalcular-tra-formula-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\/|$)/, '/' + id + '$1');
        }
        return carpetaBase() + '/stock/articulo/' + id + '/api/preview-recalcular-transferencias-formula';
    }

    function urlAplicar(id) {
        var el = document.getElementById('articulo-aplicar-recalcular-tra-formula-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\/|$)/, '/' + id + '$1');
        }
        return carpetaBase() + '/stock/articulo/' + id + '/api/aplicar-recalcular-transferencias-formula';
    }

    function modoActual() {
        var checked = document.querySelector('input[name="rtf-modo"]:checked');
        return checked ? checked.value : 'ultima';
    }

    function coeficienteActual() {
        var modalCoef = document.getElementById('rtf-coeficiente');
        if (modalCoef && String(modalCoef.value).trim() !== '') {
            return String(modalCoef.value).trim();
        }
        var formCoef = document.getElementById('coeficienteconversion');
        return formCoef ? String(formCoef.value || '').trim() : '';
    }

    function payloadBase() {
        var payload = {
            modo: modoActual(),
            coeficiente: coeficienteActual(),
        };
        if (payload.modo === 'rango') {
            payload.fecha_desde = $('#rtf-fecha-desde').val() || '';
            payload.fecha_hasta = $('#rtf-fecha-hasta').val() || '';
        }
        return payload;
    }

    function fmtNum(val) {
        var n = parseFloat(val);
        if (isNaN(n)) {
            return '';
        }
        return n.toLocaleString('es-AR', { maximumFractionDigits: 6 });
    }

    function filaRequiereCambio(f) {
        return f && (f.requiere_cambio === true || f.requiere_cambio === 1 || f.requiere_cambio === '1');
    }

    function toggleRango() {
        var esRango = modoActual() === 'rango';
        $('.rtf-rango-campos').toggleClass('d-none', !esRango);
    }

    function setEstado(opts) {
        opts = opts || {};
        if (Object.prototype.hasOwnProperty.call(opts, 'loading')) {
            $('#rtf-loading').toggleClass('d-none', !opts.loading);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'error')) {
            $('#rtf-error').toggleClass('d-none', !opts.error).text(opts.error || '');
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'vacio')) {
            $('#rtf-vacio').toggleClass('d-none', !opts.vacio);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'tabla')) {
            $('#rtf-tabla-wrap').toggleClass('d-none', !opts.tabla);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'sinCambio')) {
            $('#rtf-sin-cambio').toggleClass('d-none', !opts.sinCambio);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'puedeAplicar')) {
            $('#rtf-btn-aplicar').prop('disabled', !opts.puedeAplicar);
        }
    }

    function actualizarBotonAplicar() {
        var n = $('#rtf-tbody .rtf-check-linea:checked').length;
        setEstado({ puedeAplicar: n > 0 });
        $('#rtf-seleccion-hint').text(n > 0 ? ('Seleccionadas: ' + n) : 'Ninguna línea seleccionada');
    }

    function renderPreview(data) {
        previewData = data;
        var $tb = $('#rtf-tbody');
        $tb.empty();

        var filas = (data && Array.isArray(data.filas)) ? data.filas : [];
        if (filas.length === 0) {
            setEstado({
                loading: false,
                error: '',
                vacio: true,
                tabla: false,
                sinCambio: false,
                puedeAplicar: false,
            });
            $('#rtf-resumen').text('');
            $('#rtf-seleccion-hint').text('');
            return;
        }

        var conCambio = 0;
        filas.forEach(function (f) {
            var cambio = filaRequiereCambio(f);
            if (cambio) {
                conCambio++;
            }
            var $tr = $('<tr></tr>').toggleClass('table-warning', cambio);
            var $chk = $('<input type="checkbox" class="rtf-check-linea">')
                .attr('data-linea-id', String(f.linea_id || 0))
                .prop('checked', cambio);
            $tr.append($('<td class="text-center"></td>').append($chk));
            $tr.append($('<td class="small text-nowrap"></td>').text(f.fecha || ''));
            $tr.append($('<td class="small text-monospace"></td>').text(f.codigo || ''));
            $tr.append($('<td class="small"></td>').text(f.empresa_nombre || ''));
            $tr.append($('<td class="small"></td>').text(f.deposito_destino || ''));
            $tr.append($('<td class="small text-monospace"></td>').text(f.articulo_destino_sku || ''));

            var coefTxt = fmtNum(f.coeficiente_antes);
            if (cambio) {
                coefTxt += ' → ' + fmtNum(f.coeficiente_despues);
            }
            $tr.append($('<td class="small text-right text-monospace"></td>').text(coefTxt));

            var cantTxt = fmtNum(f.cantidad_destino_antes);
            if (cambio) {
                cantTxt += ' → ' + fmtNum(f.cantidad_destino_despues);
            }
            $tr.append($('<td class="small text-right text-monospace"></td>').text(cantTxt));

            var precioTxt = fmtNum(f.precio_destino_antes);
            if (cambio) {
                precioTxt += ' → ' + fmtNum(f.precio_destino_despues);
            }
            $tr.append($('<td class="small text-right text-monospace"></td>').text(precioTxt));

            $tb.append($tr);
        });

        $('#rtf-resumen').text(
            'Líneas: ' + filas.length + ' · Con diferencias: ' + conCambio
            + ' · Coeficiente: ' + fmtNum(data.coeficiente)
        );

        setEstado({
            loading: false,
            error: '',
            vacio: false,
            tabla: true,
            sinCambio: conCambio === 0,
            puedeAplicar: conCambio > 0,
        });
        actualizarBotonAplicar();
    }

    function cargarPreview() {
        var id = articuloId();
        if (id <= 0) {
            setEstado({ loading: false, error: 'Artículo inválido.', puedeAplicar: false });
            return;
        }

        var payload = payloadBase();
        if (!payload.coeficiente || parseFloat(payload.coeficiente) <= 0) {
            setEstado({
                loading: false,
                error: 'Indique un coeficiente de conversión mayor a 0.',
                vacio: false,
                tabla: false,
                sinCambio: false,
                puedeAplicar: false,
            });
            return;
        }
        if (payload.modo === 'rango' && (!payload.fecha_desde || !payload.fecha_hasta)) {
            setEstado({
                loading: false,
                error: 'Indique fecha desde y hasta.',
                vacio: false,
                tabla: false,
                sinCambio: false,
                puedeAplicar: false,
            });
            return;
        }

        setEstado({ loading: true, error: '', vacio: false, sinCambio: false });
        previewData = null;

        $.ajax({
            url: urlPreview(id),
            type: 'GET',
            dataType: 'json',
            data: payload,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        })
            .done(function (data) {
                renderPreview(data);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'No se pudo obtener la vista previa.';
                setEstado({
                    loading: false,
                    error: msg,
                    vacio: false,
                    tabla: false,
                    sinCambio: false,
                    puedeAplicar: false,
                });
            });
    }

    function programarPreview() {
        if (previewTimer) {
            clearTimeout(previewTimer);
        }
        previewTimer = setTimeout(cargarPreview, 350);
    }

    function aplicar() {
        var id = articuloId();
        if (id <= 0 || !previewData || !Array.isArray(previewData.filas)) {
            return;
        }

        var lineaIds = [];
        $('#rtf-tbody .rtf-check-linea:checked').each(function () {
            var lid = parseInt($(this).attr('data-linea-id'), 10) || 0;
            if (lid > 0) {
                lineaIds.push(lid);
            }
        });

        if (lineaIds.length === 0) {
            setEstado({ error: 'Seleccione al menos una línea para aplicar.' });
            return;
        }

        if (!window.confirm('¿Aplicar el recálculo a ' + lineaIds.length + ' línea(s) de transferencia?')) {
            return;
        }

        var payload = payloadBase();
        payload.linea_ids = lineaIds;
        payload.solo_con_cambio = 0;
        payload._token = csrfToken();

        setEstado({ loading: true, error: '' });

        $.ajax({
            url: urlAplicar(id),
            type: 'POST',
            dataType: 'json',
            data: payload,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        })
            .done(function (data) {
                if (window.toastr) {
                    toastr.success('Transferencias recalculadas.');
                } else {
                    alert('Transferencias recalculadas.');
                }
                cargarPreview();
                $('#rtf-resumen').text(
                    'Aplicado: ' + (data.lineas_actualizadas || 0) + ' línea(s), '
                    + (data.movimientos_actualizados || 0) + ' movimiento(s). Recargando vista previa…'
                );
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'No se pudo aplicar el recálculo.';
                setEstado({
                    loading: false,
                    error: msg,
                    tabla: true,
                });
                actualizarBotonAplicar();
            });
    }

    function abrirModal() {
        var id = articuloId();
        if (id <= 0) {
            return;
        }

        var sku = ($('#sku').val() || '').toString();
        var desc = ($('#descripcion').val() || $('.descripcion').val() || '').toString();
        $('#rtf-articulo-info').text((sku ? sku + ' — ' : '') + desc);

        var coefForm = $('#coeficienteconversion').val();
        $('#rtf-coeficiente').val(coefForm || '');

        $('#rtf-modo-ultima').prop('checked', true);
        toggleRango();
        previewData = null;
        $('#rtf-tbody').empty();
        $('#rtf-resumen').text('');
        $('#rtf-seleccion-hint').text('');
        setEstado({
            loading: false,
            error: '',
            vacio: false,
            tabla: false,
            sinCambio: false,
            puedeAplicar: false,
        });

        $('#modalRecalcularTransferenciasFormula').modal('show');
        cargarPreview();
    }

    $(document).on('click', '#btn-recalcular-transferencias-formula', function (e) {
        e.preventDefault();
        abrirModal();
    });

    $(document).on('change', 'input[name="rtf-modo"]', function () {
        toggleRango();
        programarPreview();
    });
    $(document).on('change input', '#rtf-coeficiente, #rtf-fecha-desde, #rtf-fecha-hasta', programarPreview);
    $(document).on('change', '#rtf-tbody .rtf-check-linea', actualizarBotonAplicar);
    $(document).on('click', '#rtf-btn-preview', function (e) {
        e.preventDefault();
        cargarPreview();
    });
    $(document).on('click', '#rtf-btn-aplicar', function (e) {
        e.preventDefault();
        aplicar();
    });
    $(document).on('click', '#rtf-check-todas', function (e) {
        e.preventDefault();
        $('#rtf-tbody .rtf-check-linea').prop('checked', true);
        actualizarBotonAplicar();
    });
    $(document).on('click', '#rtf-check-cambios', function (e) {
        e.preventDefault();
        $('#rtf-tbody .rtf-check-linea').each(function () {
            var $tr = $(this).closest('tr');
            $(this).prop('checked', $tr.hasClass('table-warning'));
        });
        actualizarBotonAplicar();
    });
})(jQuery);
