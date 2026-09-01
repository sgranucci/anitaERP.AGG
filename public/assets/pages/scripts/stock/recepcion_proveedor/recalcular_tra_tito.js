/**
 * Recalcular TRA TITO del mes en curso al cambiar cotización de una COM.
 */
(function ($) {
    'use strict';

    var previewData = null;
    var recepcionIdActiva = 0;

    function carpetaBase() {
        if (typeof window.carpetaBase === 'string' && window.carpetaBase) {
            return String(window.carpetaBase).replace(/\/$/, '');
        }
        return '';
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
        var el = document.getElementById('rp-preview-recalcular-tra-tito-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\/|$)/, '/' + id + '$1').replace(/__ID__/g, String(id));
        }
        return carpetaBase() + '/stock/recepcion-proveedor/' + id + '/api/preview-recalcular-tra-tito';
    }

    function urlAplicar(id) {
        var el = document.getElementById('rp-aplicar-recalcular-tra-tito-url');
        if (el && el.value) {
            return String(el.value).replace(/\/0(\/|$)/, '/' + id + '$1').replace(/__ID__/g, String(id));
        }
        return carpetaBase() + '/stock/recepcion-proveedor/' + id + '/api/aplicar-recalcular-tra-tito';
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

    function setEstado(opts) {
        opts = opts || {};
        if (Object.prototype.hasOwnProperty.call(opts, 'loading')) {
            $('#rtt-loading').toggleClass('d-none', !opts.loading);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'error')) {
            $('#rtt-error').toggleClass('d-none', !opts.error).text(opts.error || '');
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'vacio')) {
            $('#rtt-vacio').toggleClass('d-none', !opts.vacio);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'tabla')) {
            $('#rtt-tabla-wrap').toggleClass('d-none', !opts.tabla);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'sinCambio')) {
            $('#rtt-sin-cambio').toggleClass('d-none', !opts.sinCambio);
        }
        if (Object.prototype.hasOwnProperty.call(opts, 'puedeAplicar')) {
            $('#rtt-btn-aplicar').prop('disabled', !opts.puedeAplicar);
        }
    }

    function actualizarBotonAplicar() {
        var n = $('#rtt-tbody .rtt-check-linea:checked').length;
        setEstado({ puedeAplicar: n > 0 });
        $('#rtt-seleccion-hint').text(n > 0 ? ('Seleccionadas: ' + n) : 'Ninguna línea seleccionada');
    }

    function renderPreview(data) {
        previewData = data;
        var $tb = $('#rtt-tbody');
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
            $('#rtt-resumen').text('');
            $('#rtt-seleccion-hint').text('');
            return;
        }

        var conCambio = 0;
        filas.forEach(function (f) {
            var cambio = filaRequiereCambio(f);
            if (cambio) {
                conCambio++;
            }
            var $tr = $('<tr></tr>').toggleClass('table-warning', cambio);
            var $chk = $('<input type="checkbox" class="rtt-check-linea">')
                .attr('data-linea-id', String(f.linea_id || 0))
                .prop('checked', cambio);
            $tr.append($('<td class="text-center"></td>').append($chk));
            $tr.append($('<td class="small text-nowrap"></td>').text(f.fecha || ''));
            $tr.append($('<td class="small text-monospace"></td>').text(f.codigo || ''));
            $tr.append($('<td class="small text-monospace"></td>').text(f.sku || ''));
            $tr.append($('<td class="small text-right text-monospace"></td>').text(fmtNum(f.cantidad)));

            var precioTxt = fmtNum(f.precio_antes);
            if (cambio) {
                precioTxt += ' → ' + fmtNum(f.precio_despues);
            }
            $tr.append($('<td class="small text-right text-monospace"></td>').text(precioTxt));

            var impTxt = fmtNum(f.importe_antes);
            if (cambio) {
                impTxt += ' → ' + fmtNum(f.importe_despues);
            }
            $tr.append($('<td class="small text-right text-monospace"></td>').text(impTxt));

            $tb.append($tr);
        });

        $('#rtt-resumen').text(
            'Líneas: ' + filas.length + ' · Con diferencias: ' + conCambio
            + ' · Empresa: ' + (data.empresa_nombre || '')
            + ' · Mes: ' + (data.fecha_desde || '') + ' a ' + (data.fecha_hasta || '')
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
        var id = recepcionIdActiva;
        if (id <= 0) {
            setEstado({ loading: false, error: 'Recepción inválida.', puedeAplicar: false });
            return;
        }

        setEstado({ loading: true, error: '', vacio: false, sinCambio: false });
        previewData = null;

        $.ajax({
            url: urlPreview(id),
            type: 'GET',
            dataType: 'json',
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

    function aplicar() {
        var id = recepcionIdActiva;
        if (id <= 0 || !previewData || !Array.isArray(previewData.filas)) {
            return;
        }

        var lineaIds = [];
        $('#rtt-tbody .rtt-check-linea:checked').each(function () {
            var lid = parseInt($(this).attr('data-linea-id'), 10) || 0;
            if (lid > 0) {
                lineaIds.push(lid);
            }
        });

        if (lineaIds.length === 0) {
            setEstado({ error: 'Seleccione al menos una línea para aplicar.' });
            return;
        }

        if (!window.confirm('¿Aplicar el recálculo a ' + lineaIds.length + ' TRA TITO del mes en curso?')) {
            return;
        }

        setEstado({ loading: true, error: '' });

        $.ajax({
            url: urlAplicar(id),
            type: 'POST',
            dataType: 'json',
            data: {
                linea_ids: lineaIds,
                _token: csrfToken(),
            },
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        })
            .done(function (data) {
                if (window.toastr) {
                    toastr.success('TRA TITO recalculadas.');
                } else {
                    alert('TRA TITO recalculadas.');
                }
                cargarPreview();
                $('#rtt-resumen').text(
                    'Aplicado: ' + (data.lineas_actualizadas || 0) + ' línea(s), '
                    + (data.movimientos_actualizados || 0) + ' movimiento(s), '
                    + (data.asientos_actualizados || 0) + ' asiento(s). Recargando vista previa…'
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

    function abrirModal(cfg) {
        cfg = cfg || {};
        var id = parseInt(cfg.recepcion_id, 10) || 0;
        if (id <= 0) {
            return;
        }
        recepcionIdActiva = id;

        var info = 'COM ' + (cfg.numero || id);
        if (cfg.empresa_nombre) {
            info += ' — ' + cfg.empresa_nombre;
        }
        if (cfg.cotizacion_anterior != null || cfg.cotizacion_nueva != null) {
            info += '. Cotización'
                + (cfg.cotizacion_anterior != null ? ' de ' + fmtNum(cfg.cotizacion_anterior) : '')
                + (cfg.cotizacion_nueva != null ? ' a ' + fmtNum(cfg.cotizacion_nueva) : '')
                + '.';
        }
        if (cfg.aviso) {
            info += ' ' + cfg.aviso;
        }
        $('#rtt-info').text(info);

        previewData = null;
        $('#rtt-tbody').empty();
        $('#rtt-resumen').text('');
        $('#rtt-seleccion-hint').text('');
        setEstado({
            loading: false,
            error: '',
            vacio: false,
            tabla: false,
            sinCambio: false,
            puedeAplicar: false,
        });

        $('#modalRecalcularTraTito').modal('show');
        cargarPreview();
    }

    $(document).on('change', '#rtt-tbody .rtt-check-linea', actualizarBotonAplicar);
    $(document).on('click', '#rtt-btn-aplicar', function (e) {
        e.preventDefault();
        aplicar();
    });
    $(document).on('click', '#rtt-check-todas', function (e) {
        e.preventDefault();
        $('#rtt-tbody .rtt-check-linea').prop('checked', true);
        actualizarBotonAplicar();
    });
    $(document).on('click', '#rtt-check-cambios', function (e) {
        e.preventDefault();
        $('#rtt-tbody .rtt-check-linea').each(function () {
            var $tr = $(this).closest('tr');
            $(this).prop('checked', $tr.hasClass('table-warning'));
        });
        actualizarBotonAplicar();
    });

    $(function () {
        var cfg = window.abrirRecalcularTraTito;
        if (!cfg || typeof cfg !== 'object' || !cfg.recepcion_id) {
            return;
        }
        setTimeout(function () {
            abrirModal(cfg);
        }, 400);
    });
})(jQuery);
