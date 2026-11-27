(function ($) {
    'use strict';

    var $modal = null;

    function urlPara(opId, tipo) {
        var tpl = tipo === 'post'
            ? ($modal.data('url-post-template') || '/compras/pagoproveedor/__ID__/deshacer-aop')
            : ($modal.data('url-plan-template') || '/compras/pagoproveedor/__ID__/deshacer-aop/plan');
        return String(tpl).replace('__ID__', String(opId));
    }

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fechaDmy(ymd) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(ymd || ''));
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : String(ymd || '');
    }

    function plural(n, singular, plural) {
        return n + ' ' + (n === 1 ? singular : plural);
    }

    function reset() {
        $('#op-deshacer-aop-cargando').addClass('d-none');
        $('#op-deshacer-aop-error').addClass('d-none').text('');
        $('#op-deshacer-aop-contenido').addClass('d-none');
        $('#op-deshacer-aop-bloqueos').addClass('d-none');
        $('#op-deshacer-aop-bloqueos-lista').empty();
        $('#op-deshacer-aop-lista').empty();
        $('#op-deshacer-aop-confirmar')
            .addClass('d-none')
            .prop('disabled', false)
            .html('<i class="fa fa-reply-all"></i> Deshacer AOP');
    }

    function mostrarError(msg) {
        $('#op-deshacer-aop-cargando').addClass('d-none');
        $('#op-deshacer-aop-error').removeClass('d-none').text(msg || 'No se pudo obtener la vista previa.');
    }

    function item(texto) {
        return $('<li>').text(texto);
    }

    function render(plan) {
        var aop = plan.aop || {};
        var origen = plan.origen || {};
        var c = plan.cambios || {};
        var $lista = $('#op-deshacer-aop-lista').empty();
        var $bloqueos = $('#op-deshacer-aop-bloqueos-lista').empty();

        $('#op-deshacer-aop-aop').text(aop.etiqueta || '');
        $('#op-deshacer-aop-aop-detalle').text(
            [fechaDmy(aop.fecha), fmt(aop.monto), aop.proveedor || ''].filter(Boolean).join(' · ')
        );
        $('#op-deshacer-aop-origen').text(origen.etiqueta || '—');
        $('#op-deshacer-aop-origen-detalle').text(
            [fechaDmy(origen.fecha), origen.estado_actual || ''].filter(Boolean).join(' · ')
        );

        (plan.errores || []).forEach(function (e) {
            $bloqueos.append(item(e));
        });
        $('#op-deshacer-aop-bloqueos').toggleClass('d-none', !(plan.errores || []).length);

        if (plan.puede_deshacer) {
            if (c.anita_filas_aop === 0) {
                $lista.append(item('Anita: la cabecera AOP ya no está (reintento); se continúa con el ERP.'));
            } else if (c.anita_filas_aop !== null && c.anita_filas_aop !== undefined) {
                $lista.append(item('Borrar la cabecera AOP en Anita (' + plural(c.anita_filas_aop, 'fila encontrada', 'filas encontradas') + ').'));
            }
            $lista.append(item(
                'Borrar ' + plural(c.cc_aplicaciones_aop || 0, 'aplicación', 'aplicaciones')
                + ' y ' + plural(c.cc_filas_aop || 0, 'fila', 'filas') + ' de cuenta corriente del AOP.'
            ));
            var asientos = c.asientos_aop || [];
            if (asientos.length) {
                $lista.append(item('Borrar ' + (asientos.length === 1 ? 'el asiento ' : 'los asientos ') + asientos.join(', ') + ' del AOP.'));
            }
            $lista.append(item(
                'Borrar ' + plural(c.comprobantes_aop || 0, 'comprobante', 'comprobantes')
                + ' y ' + plural(c.estados_aop || 0, 'estado', 'estados') + ' del AOP.'
            ));
            $lista.append(item(
                'Restaurar la OPP ' + (origen.etiqueta || '') + ' a ' + (c.restaurar_estado || '')
                + ' y quitar «REVERTIDA:» del detalle.'
            ));
            $('#op-deshacer-aop-plan').removeClass('d-none');
            $('#op-deshacer-aop-confirmar').removeClass('d-none');
        } else {
            $('#op-deshacer-aop-plan').addClass('d-none');
        }

        $('#op-deshacer-aop-cargando').addClass('d-none');
        $('#op-deshacer-aop-contenido').removeClass('d-none');
    }

    $(function () {
        $modal = $('#modalOpDeshacerAop');
        if (!$modal.length) {
            return;
        }

        $modal.on('hidden.bs.modal', reset);

        $(document).on('click', '.js-op-deshacer-aop', function () {
            var opId = $(this).data('id');
            reset();
            $('#form-op-deshacer-aop').attr('action', urlPara(opId, 'post'));
            $('#op-deshacer-aop-cargando').removeClass('d-none');
            $modal.modal('show');

            $.ajax({
                url: urlPara(opId, 'plan'),
                method: 'GET',
                dataType: 'json'
            }).done(function (plan) {
                render(plan);
            }).fail(function (xhr) {
                var msg = xhr && xhr.responseJSON && xhr.responseJSON.mensaje;
                mostrarError(msg || 'No se pudo obtener la vista previa.');
            });
        });

        // Un solo envío: evita doble clic mientras corre Anita + ERP.
        $('#form-op-deshacer-aop').on('submit', function () {
            $('#op-deshacer-aop-confirmar')
                .prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin"></i> Deshaciendo…');
        });
    });
})(jQuery);
