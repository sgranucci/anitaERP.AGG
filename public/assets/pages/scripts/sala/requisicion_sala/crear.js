(function () {
    'use strict';

    var urlNpu = window.requisicionSalaUrlNpu || '';
    var htmlBotonGrabar = '';

    function tieneTransferenciaLaboratorio() {
        return String($('#form-general').data('tieneTransferenciaLaboratorio') || '') === '1';
    }

    function esLineaExistente($tr) {
        var idLinea = $.trim($tr.find('.requisicion_sala_articulo_id').val() || '');
        return idLinea !== '' && idLinea !== '0';
    }

    function lineaTieneArticulo($tr) {
        var articuloId = parseInt($tr.find('.articulo_id').val() || '0', 10);
        return articuloId > 0;
    }

    function marcarLineasNuevasSinTmIniciales() {
        if (!tieneTransferenciaLaboratorio()) {
            return;
        }
        $('#tabla-articulos-requisicion-sala tbody tr').each(function () {
            var $tr = $(this);
            if (!esLineaExistente($tr)) {
                $tr.addClass('linea-nueva-sin-tm');
            }
        });
    }

    function actualizarAvisoLineasNuevasSinTm() {
        var $aviso = $('#aviso-nuevos-articulos-sin-tm');
        if (!$aviso.length || !tieneTransferenciaLaboratorio()) {
            return;
        }
        var hayNuevasConArticulo = false;
        $('#tabla-articulos-requisicion-sala tbody tr.linea-nueva-sin-tm').each(function () {
            if (lineaTieneArticulo($(this))) {
                hayNuevasConArticulo = true;
                return false;
            }
        });
        $aviso.toggleClass('d-none', !hayNuevasConArticulo);
    }

    function avisoAlAgregarLineaConTransferencia() {
        if (!tieneTransferenciaLaboratorio() || typeof window.alert !== 'function') {
            return;
        }
        window.alert(
            'Esta requisición ya tiene transferencia al laboratorio.\n\n' +
            'Los artículos que agregue ahora NO se incluirán en esa transferencia. ' +
            'Deberá registrarlos en otra transferencia de mercadería manualmente (Stock → Transferencia de mercadería).'
        );
    }

    function confirmarGrabadoConLineasNuevasSinTm() {
        if (!tieneTransferenciaLaboratorio() || !hayLineasNuevasConArticulo()) {
            return true;
        }
        if (typeof window.confirm !== 'function') {
            return true;
        }
        return window.confirm(
            'Hay artículos nuevos que no están incluidos en la transferencia al laboratorio.\n\n' +
            'Deberá moverlos con otra transferencia de mercadería manualmente.\n\n' +
            '¿Desea continuar grabando la requisición?'
        );
    }

    function hayLineasNuevasConArticulo() {
        var hay = false;
        $('#tabla-articulos-requisicion-sala tbody tr.linea-nueva-sin-tm').each(function () {
            if (lineaTieneArticulo($(this))) {
                hay = true;
                return false;
            }
        });
        return hay;
    }

    function esFueraDeServicio($tr) {
        return ($tr.find('.fueradeservicio-linea').val() || 'N') === 'S';
    }

    function uidLineaVacio($tr) {
        return $.trim($tr.find('.uid-linea').val() || '') === '';
    }

    function lineaPendienteUid($tr) {
        return esFueraDeServicio($tr) && uidLineaVacio($tr);
    }

    function primeraLineaPendienteUid() {
        var $pendiente = null;
        $('#tabla-articulos-requisicion-sala tbody tr').each(function () {
            var $tr = $(this);
            if (lineaPendienteUid($tr)) {
                $pendiente = $tr;
                return false;
            }
        });
        return $pendiente;
    }

    function actualizarEstadoUidLinea($tr) {
        var $uid = $tr.find('.uid-linea');
        var pendiente = lineaPendienteUid($tr);
        if (esFueraDeServicio($tr)) {
            $uid.prop('required', true);
            $uid.toggleClass('is-invalid', pendiente);
        } else {
            $uid.prop('required', false);
            $uid.removeClass('is-invalid');
        }
    }

    function actualizarControlesUid() {
        $('#tabla-articulos-requisicion-sala tbody tr').each(function () {
            actualizarEstadoUidLinea($(this));
        });

        var $pendiente = primeraLineaPendienteUid();
        var bloqueado = $pendiente !== null;
        var $btn = $('#agrega_renglon_sala');
        var $aviso = $('#aviso-uid-fuera-servicio');

        $btn.prop('disabled', bloqueado);
        $aviso.toggleClass('d-none', !bloqueado);

        if (bloqueado) {
            $btn.attr('title', 'Complete el UID del ítem fuera de servicio antes de agregar otro renglón.');
        } else {
            $btn.removeAttr('title');
        }
    }

    function validarUidsFueraDeServicio() {
        var $pendiente = primeraLineaPendienteUid();
        if ($pendiente) {
            actualizarControlesUid();
            var $uid = $pendiente.find('.uid-linea');
            $uid.trigger('focus');
            if (typeof window.alert === 'function') {
                window.alert('Debe ingresar el UID cuando el ítem está fuera de servicio (F/S = S).');
            }
            return false;
        }
        return true;
    }

    function bindLinea($tr) {
        $tr.find('.eliminar_linea_sala').on('click', function () {
            if ($('#tabla-articulos-requisicion-sala tbody tr').length > 1) {
                $tr.remove();
                actualizarControlesUid();
                actualizarAvisoLineasNuevasSinTm();
            }
        });
        $tr.find('.codigoarticulo').on('change blur', function () {
            var sku = $(this).val();
            var $row = $(this).closest('tr');
            if (!sku || !urlNpu) {
                actualizarAvisoLineasNuevasSinTm();
                return;
            }
            $.getJSON(urlNpu, { sku: sku }).done(function (resp) {
                if (resp.encontrado) {
                    $row.find('.numeroparte-linea').val(resp.numeroparte).prop('readonly', true);
                } else {
                    $row.find('.numeroparte-linea').prop('readonly', false);
                }
            }).always(function () {
                actualizarAvisoLineasNuevasSinTm();
            });
        });
        $tr.find('.articulo_id').on('change', function () {
            actualizarAvisoLineasNuevasSinTm();
        });
        $tr.find('.fueradeservicio-linea').on('change', function () {
            actualizarEstadoUidLinea($(this).closest('tr'));
            actualizarControlesUid();
            if (esFueraDeServicio($(this).closest('tr'))) {
                $(this).closest('tr').find('.uid-linea').trigger('focus');
            }
        });
        $tr.find('.uid-linea').on('input change blur', function () {
            actualizarEstadoUidLinea($(this).closest('tr'));
            actualizarControlesUid();
        });
        actualizarEstadoUidLinea($tr);
    }

    $(function () {
        urlNpu = $('#form-general').data('url-npu') || urlNpu;
        htmlBotonGrabar = $('#botonform0').html() || 'Grabar';
        $('#tabla-articulos-requisicion-sala tbody tr').each(function () {
            bindLinea($(this));
        });
        marcarLineasNuevasSinTmIniciales();
        actualizarControlesUid();
        actualizarAvisoLineasNuevasSinTm();

        $('#agrega_renglon_sala').on('click', function () {
            if (!validarUidsFueraDeServicio()) {
                return;
            }
            if (tieneTransferenciaLaboratorio()) {
                avisoAlAgregarLineaConTransferencia();
            }
            var tpl = $('#template-linea-requisicion-sala').html();
            if (!tpl) {
                return;
            }
            var $row = $(tpl);
            if (tieneTransferenciaLaboratorio()) {
                $row.addClass('linea-nueva-sin-tm');
            }
            $('#tabla-articulos-requisicion-sala tbody').append($row);
            bindLinea($row);
            actualizarControlesUid();
            actualizarAvisoLineasNuevasSinTm();
            setTimeout(function () {
                $row.find('.codigoarticulo').trigger('focus');
            }, 0);
        });

        $(document).on('click', '#botonform0', function (e) {
            e.preventDefault();
            if (!validarUidsFueraDeServicio()) {
                return;
            }
            if (!confirmarGrabadoConLineasNuevasSinTm()) {
                return;
            }
            var $f = $('#form-general');
            if ($f.length) {
                if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.mostrar === 'function') {
                    window.RequisicionSalaGrabando.mostrar();
                }
                $('#botonform0').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando…');
                $f.trigger('submit');
            }
        });

        $('#form-general').on('submit', function (e) {
            if (!validarUidsFueraDeServicio()) {
                e.preventDefault();
                if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.ocultar === 'function') {
                    window.RequisicionSalaGrabando.ocultar();
                }
                $('#botonform0').prop('disabled', false).html(htmlBotonGrabar);
                return;
            }
            if (!confirmarGrabadoConLineasNuevasSinTm()) {
                e.preventDefault();
                if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.ocultar === 'function') {
                    window.RequisicionSalaGrabando.ocultar();
                }
                $('#botonform0').prop('disabled', false).html(htmlBotonGrabar);
            }
        });

        $('#botonform1').on('click', function () {
            $('.form1').show();
            $('.form4').hide();
        });

        $('#botonform4').on('click', function () {
            $('.form1').hide();
            $('.form4').show();
            var sol = document.getElementById('requisicion-sala-solapa-archivos');
            if (sol) {
                sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        $(document).on('click', '.eliminar-archivo-requisicion-sala', function () {
            $(this).closest('.requisicion-sala-archivo-item').remove();
        });

        $('#agrega_renglon_archivo_sala').on('click', function (e) {
            e.preventDefault();
            var tpl = $('#template-renglon-archivo-sala').html();
            if (!tpl) {
                return;
            }
            $('#tbody-tabla-archivo-sala').append(tpl);
        });

        $(document).on('click', '#tbody-tabla-archivo-sala .eliminararchivo-sala', function (e) {
            e.preventDefault();
            $(this).closest('tr.item-archivo-sala').remove();
        });

        $(document).on('click', '.eligeconsultaarticulo', function () {
            setTimeout(actualizarAvisoLineasNuevasSinTm, 150);
        });
    });
})();
