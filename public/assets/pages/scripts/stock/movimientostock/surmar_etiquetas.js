(function ($) {
    'use strict';

    var etiquetas = [];

    function $panel() {
        return $('#ms-panel-etiquetas-surmar');
    }

    function tiposPiqueo() {
        var raw = String($panel().data('tipos') || 'AP,DES,TRA');
        return raw.split(',').map(function (t) { return String(t).trim().toUpperCase(); }).filter(Boolean);
    }

    function empresaEsSurmar() {
        if (String($panel().data('surmar-activo') || '') !== '1') {
            return false;
        }
        var empSurmar = parseInt($panel().data('empresa-surmar'), 10) || 3;
        var emp = parseInt($('#empresa_id').val(), 10) || 0;
        return emp === empSurmar;
    }

    function abreviaturaTipo() {
        if (typeof window.msTipoTransaccionMeta === 'function') {
            return String(window.msTipoTransaccionMeta().abreviatura || '').trim().toUpperCase();
        }
        return String($('#tipotransaccion_stock_id_abreviatura').val() || '').trim().toUpperCase();
    }

    function debeMostrar() {
        return empresaEsSurmar() && tiposPiqueo().indexOf(abreviaturaTipo()) !== -1;
    }

    function setMsg(text, ok) {
        var $m = $('#ms_etiqueta_msg');
        $m.text(text || '');
        $m.removeClass('text-danger text-success');
        if (text) {
            $m.addClass(ok ? 'text-success' : 'text-danger');
        }
    }

    function render() {
        var $tb = $('#ms-tbody-etiquetas-surmar');
        var $h = $('#ms-etiquetas-consumo-hiddens');
        $tb.empty();
        $h.empty();
        var total = 0;
        if (!etiquetas.length) {
            $tb.append('<tr class="ms-etiq-empty"><td colspan="7" class="text-center text-muted">Sin etiquetas piqueadas.</td></tr>');
        } else {
            etiquetas.forEach(function (e) {
                total += parseFloat(e.peso_neto) || 0;
                $tb.append(
                    '<tr data-id="' + e.etiqueta_id + '">' +
                    '<td>' + e.etiqueta_id + '</td>' +
                    '<td>' + $('<div>').text(e.sku || '').html() + '</td>' +
                    '<td>' + $('<div>').text(e.descripcion || '').html() + '</td>' +
                    '<td class="text-right">' + (parseFloat(e.peso_neto) || 0).toFixed(2) + '</td>' +
                    '<td>' + $('<div>').text(e.lote_proveedor || '—').html() + '</td>' +
                    '<td>' + $('<div>').text(e.deposito_nombre || e.deposito_codigo || '—').html() + '</td>' +
                    '<td class="text-center">' +
                    '<button type="button" class="btn-accion-tabla ms-etiq-quitar" title="Quitar" data-id="' + e.etiqueta_id + '">' +
                    '<i class="fa fa-times-circle text-danger"></i></button></td></tr>'
                );
                $h.append('<input type="hidden" name="etiquetas_consumo_id[]" value="' + e.etiqueta_id + '">');
            });
        }
        $('#ms_etiqueta_total_neto').text(total.toFixed(2));
    }

    function togglePanel() {
        var show = debeMostrar();
        var $p = $panel();
        if (!$p.length) {
            return;
        }
        $p.toggle(show);
        $('#ms_etiqueta_scan, #ms_etiqueta_agregar').prop('disabled', !show);
        if (!show) {
            etiquetas = [];
            render();
            setMsg('');
        }
    }

    function agregar() {
        if (!debeMostrar()) {
            return;
        }
        var raw = String($('#ms_etiqueta_scan').val() || '').trim();
        if (!raw) {
            setMsg('Ingrese un código de etiqueta', false);
            return;
        }
        var url = $('#ms-resolver-etiqueta-surmar-url').val();
        if (!url) {
            setMsg('URL resolver no configurada', false);
            return;
        }
        setMsg('Resolviendo…', true);
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: $('#csrf_token').val() || $('meta[name="csrf-token"]').attr('content'),
                codigo: raw,
                empresa_id: $('#empresa_id').val() || $panel().data('empresa-surmar')
            },
            success: function (resp) {
                if (!resp || !resp.ok || !resp.etiqueta) {
                    setMsg((resp && resp.message) || 'No se pudo resolver', false);
                    return;
                }
                var e = resp.etiqueta;
                if (etiquetas.some(function (x) { return x.etiqueta_id === e.etiqueta_id; })) {
                    setMsg('Etiqueta #' + e.etiqueta_id + ' ya agregada', false);
                    return;
                }
                etiquetas.push(e);
                $('#ms_etiqueta_scan').val('');
                render();
                setMsg('Etiqueta #' + e.etiqueta_id + ' agregada', true);
                $('#ms_etiqueta_scan').focus();
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error al resolver etiqueta';
                setMsg(msg, false);
            }
        });
    }

    function validarAntesDeSubmit() {
        if (!debeMostrar()) {
            return true;
        }
        if (!etiquetas.length) {
            setMsg('Debe piquear al menos una etiqueta disponible para ' + abreviaturaTipo(), false);
            alert('Surmar ' + abreviaturaTipo() + ': piqueá al menos una etiqueta DISPONIBLE.');
            $('#ms_etiqueta_scan').focus();
            return false;
        }
        return true;
    }

    $(function () {
        if (!$panel().length) {
            return;
        }
        togglePanel();
        render();

        $(document).on('change', '#empresa_id, #tipotransaccion_stock_id', togglePanel);
        $(document).on('ms:tipotransaccion-changed', togglePanel);

        $('#ms_etiqueta_agregar').on('click', function (e) {
            e.preventDefault();
            agregar();
        });
        $('#ms_etiqueta_scan').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregar();
            }
        });
        $(document).on('click', '.ms-etiq-quitar', function () {
            var id = parseInt($(this).data('id'), 10);
            etiquetas = etiquetas.filter(function (x) { return x.etiqueta_id !== id; });
            render();
            setMsg('Etiqueta quitada', true);
        });

        $('form').on('submit', function (e) {
            if (!validarAntesDeSubmit()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        });
    });

    window.msSurmarEtiquetasToggle = togglePanel;
})(jQuery);
