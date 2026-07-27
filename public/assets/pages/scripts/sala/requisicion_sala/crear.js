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

    function filasArticulo() {
        return $('#tabla-articulos-requisicion-sala tbody tr.item-requisicion-sala-articulo');
    }

    function filaLeyendaDe($trArticulo) {
        return $trArticulo.next('tr.item-requisicion-sala-leyenda');
    }

    function eliminarParLinea($trArticulo) {
        filaLeyendaDe($trArticulo).remove();
        $trArticulo.remove();
    }

    function sincronizarEstadoLeyenda($trArticulo) {
        var $leyenda = filaLeyendaDe($trArticulo);
        var $ta = $leyenda.find('.rs-leyenda-linea');
        var $btn = $trArticulo.find('.rs-toggle-leyenda');
        var $preview = $leyenda.find('.rs-leyenda-preview');
        var $resumen = $trArticulo.find('.rs-leyenda-resumen');
        var $resumenTexto = $resumen.find('.rs-leyenda-resumen-texto');
        var texto = $.trim($ta.val() || '');
        var tiene = texto.length > 0;
        var abierto = !$leyenda.hasClass('d-none');

        $btn.toggleClass('has-leyenda btn-info', tiene)
            .toggleClass('btn-outline-secondary', !tiene);
        $btn.find('i').toggleClass('fa-comment', tiene).toggleClass('fa-comment-o', !tiene);
        $btn.attr('title', tiene
            ? (abierto ? 'Ocultar leyenda' : 'Ver / editar leyenda')
            : (abierto ? 'Ocultar leyenda' : 'Agregar leyenda'));
        $btn.attr('aria-expanded', abierto ? 'true' : 'false');

        if (tiene && !abierto) {
            $preview.text(texto).attr('title', texto).removeClass('d-none');
            $resumenTexto.text(texto);
            $resumen.attr('title', texto).removeClass('d-none');
        } else {
            $preview.text(tiene ? texto : '').attr('title', tiene ? texto : null);
            $preview.addClass('d-none');
            $resumenTexto.text(tiene ? texto : '');
            $resumen.attr('title', tiene ? texto : null).addClass('d-none');
        }
    }

    function toggleLeyendaLinea($trArticulo, forzarAbrir) {
        var $leyenda = filaLeyendaDe($trArticulo);
        if (!$leyenda.length) {
            return;
        }
        var abrir = typeof forzarAbrir === 'boolean'
            ? forzarAbrir
            : $leyenda.hasClass('d-none');
        $leyenda.toggleClass('d-none', !abrir);
        sincronizarEstadoLeyenda($trArticulo);
        if (abrir) {
            setTimeout(function () {
                $leyenda.find('.rs-leyenda-linea').trigger('focus');
            }, 0);
        }
    }

    function marcarLineasNuevasSinTmIniciales() {
        if (!tieneTransferenciaLaboratorio()) {
            return;
        }
        filasArticulo().each(function () {
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
        filasArticulo().filter('.linea-nueva-sin-tm').each(function () {
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
        filasArticulo().filter('.linea-nueva-sin-tm').each(function () {
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
        filasArticulo().each(function () {
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
            marcarCampoInvalido($uid[0], pendiente);
        } else {
            $uid.prop('required', false);
            $uid.removeClass('is-invalid');
            marcarCampoInvalido($uid[0], false);
        }
    }

    function actualizarControlesUid() {
        filasArticulo().each(function () {
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
            marcarCampoInvalido($uid[0], true);
            $uid.trigger('focus');
            return false;
        }
        return true;
    }

    function marcarCampoInvalido(campo, invalido) {
        if (!campo) {
            return;
        }
        if (typeof marcarCampoObligatorio === 'function') {
            marcarCampoObligatorio(campo, invalido);
        }
        $(campo).toggleClass('is-invalid', invalido);
    }

    function limpiarMarcasValidacionLineas() {
        filasArticulo().each(function () {
            var $tr = $(this);
            marcarCampoInvalido($tr.find('.codigoarticulo')[0], false);
            marcarCampoInvalido($tr.find('.cantidad-linea')[0], false);
            marcarCampoInvalido($tr.find('.uid-linea')[0], false);
        });
    }

    function esEdicionMenor() {
        return String($('#form-general').data('edicionMenor') || '') === '1';
    }

    function validarRequisicionSalaAntesDeEnviar(form) {
        if (esEdicionMenor()) {
            return { valido: true, primerInvalido: null, cantidadInvalidos: 0 };
        }

        var resultado = typeof validarCamposObligatoriosFormulario === 'function'
            ? validarCamposObligatoriosFormulario(form)
            : { valido: true, primerInvalido: null, cantidadInvalidos: 0 };

        limpiarMarcasValidacionLineas();

        var depositoId = parseInt(($(form).find('.deposito_id').val() || '0'), 10);
        if (depositoId <= 0) {
            resultado.valido = false;
            resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
            var depCod = form.querySelector('#deposito_id_codigo') || form.querySelector('.codigodeposito');
            marcarCampoInvalido(depCod, true);
            if (!resultado.primerInvalido) {
                resultado.primerInvalido = depCod;
            }
        }

        var filasConArticulo = 0;
        var primeraLineaSinArticulo = null;
        filasArticulo().each(function () {
            var $tr = $(this);
            var articuloId = parseInt($tr.find('.articulo_id').val() || '0', 10);
            var skuInp = $tr.find('.codigoarticulo')[0];
            var skuVal = ($tr.find('.codigoarticulo').val() || '').trim();

            if (articuloId <= 0) {
                if (!primeraLineaSinArticulo && skuInp) {
                    primeraLineaSinArticulo = skuInp;
                }
                if (skuVal !== '') {
                    resultado.valido = false;
                    resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                    marcarCampoInvalido(skuInp, true);
                    if (!resultado.primerInvalido) {
                        resultado.primerInvalido = skuInp;
                    }
                }
                return;
            }

            filasConArticulo++;
            var cantInp = $tr.find('.cantidad-linea')[0];
            var cant = parseFloat(String($tr.find('.cantidad-linea').val() || '').replace(',', '.'));
            if (Number.isNaN(cant) || cant <= 0) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                marcarCampoInvalido(cantInp, true);
                if (!resultado.primerInvalido) {
                    resultado.primerInvalido = cantInp;
                }
            }

            if (lineaPendienteUid($tr)) {
                resultado.valido = false;
                resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
                var uidInp = $tr.find('.uid-linea')[0];
                marcarCampoInvalido(uidInp, true);
                if (!resultado.primerInvalido) {
                    resultado.primerInvalido = uidInp;
                }
            }
        });

        if (filasConArticulo === 0) {
            resultado.valido = false;
            resultado.cantidadInvalidos = (resultado.cantidadInvalidos || 0) + 1;
            var primerSku = primeraLineaSinArticulo || form.querySelector('#tabla-articulos-requisicion-sala .codigoarticulo');
            marcarCampoInvalido(primerSku, true);
            if (!resultado.primerInvalido) {
                resultado.primerInvalido = primerSku;
            }
        }

        return resultado;
    }

    function notificarErroresValidacion(resultado) {
        if (!resultado || resultado.valido) {
            return;
        }

        if (typeof mostrarSolapaDelPrimerCampoInvalido === 'function') {
            mostrarSolapaDelPrimerCampoInvalido(resultado.primerInvalido);
        }
        if (typeof notificarCamposObligatoriosPendientes === 'function') {
            notificarCamposObligatoriosPendientes(resultado.primerInvalido, resultado.cantidadInvalidos);
        } else if (typeof window.alert === 'function') {
            window.alert('Complete los campos obligatorios antes de grabar.');
        }

        var primer = resultado.primerInvalido;
        if (primer && primer.classList && primer.classList.contains('codigoarticulo')) {
            if (typeof enfocarCampoInvalido === 'function') {
                enfocarCampoInvalido(primer);
            }
            return;
        }
        if (typeof enfocarCampoInvalido === 'function') {
            enfocarCampoInvalido(primer);
        }
    }

    function marcarCamposInvalidosDesdeValidator(val) {
        if (!val || !val.errorList) {
            return;
        }
        val.errorList.forEach(function (err) {
            var el = err.element;
            if (!el) {
                return;
            }
            if ($(el).hasClass('deposito_id')) {
                marcarCampoInvalido(document.getElementById('deposito_id_codigo') || document.querySelector('.codigodeposito'), true);
                return;
            }
            marcarCampoInvalido(el, true);
        });
    }

    function restaurarBotonGrabar() {
        $('#botonform0').prop('disabled', false).html(htmlBotonGrabar);
        if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.ocultar === 'function') {
            window.RequisicionSalaGrabando.ocultar();
        }
    }

    function mostrarEstadoGrabando() {
        if (window.RequisicionSalaGrabando && typeof window.RequisicionSalaGrabando.mostrar === 'function') {
            window.RequisicionSalaGrabando.mostrar();
        }
        $('#botonform0').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Grabando…');
    }

    function prepararValidacionGrabado() {
        var $form = $('#form-general');
        if (!$form.length || !$.fn.validate) {
            return;
        }
        var validator = $form.data('validator');
        if (!validator) {
            return;
        }

        var invalidHandlerOriginal = validator.settings.invalidHandler;

        validator.settings.submitHandler = function () {
            mostrarEstadoGrabando();
            return true;
        };

        validator.settings.invalidHandler = function (event, val) {
            restaurarBotonGrabar();
            marcarCamposInvalidosDesdeValidator(val);
            if (typeof invalidHandlerOriginal === 'function') {
                invalidHandlerOriginal(event, val);
            }
        };
    }

    function registrarValidacionAntesDeEnviar() {
        var form = document.getElementById('form-general');
        if (!form || !document.getElementById('tabla-articulos-requisicion-sala')) {
            return;
        }

        form.addEventListener('submit', function (e) {
            var resultado = validarRequisicionSalaAntesDeEnviar(form);
            if (resultado.valido) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            restaurarBotonGrabar();
            notificarErroresValidacion(resultado);
        }, true);
    }

    function bindLinea($tr) {
        $tr.find('.eliminar_linea_sala').on('click', function () {
            if (filasArticulo().length > 1) {
                eliminarParLinea($tr);
                actualizarControlesUid();
                actualizarAvisoLineasNuevasSinTm();
            }
        });
        $tr.find('.rs-toggle-leyenda').on('click', function () {
            toggleLeyendaLinea($tr);
        });
        $tr.find('.rs-leyenda-resumen').on('click', function () {
            toggleLeyendaLinea($tr, true);
        });
        filaLeyendaDe($tr).find('.rs-leyenda-linea').on('input change', function () {
            sincronizarEstadoLeyenda($tr);
        });
        $tr.find('.codigoarticulo').on('change blur', function () {
            var sku = $(this).val();
            var $row = $(this).closest('tr.item-requisicion-sala-articulo');
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
            marcarCampoInvalido($tr.find('.codigoarticulo')[0], false);
            actualizarAvisoLineasNuevasSinTm();
        });
        $tr.find('.cantidad-linea').on('input change', function () {
            var cant = parseFloat(String($(this).val() || '').replace(',', '.'));
            var articuloId = parseInt($tr.find('.articulo_id').val() || '0', 10);
            if (articuloId > 0 && !Number.isNaN(cant) && cant > 0) {
                marcarCampoInvalido(this, false);
            }
        });
        $tr.find('.codigoarticulo').on('input change', function () {
            if (parseInt($tr.find('.articulo_id').val() || '0', 10) > 0) {
                marcarCampoInvalido(this, false);
            }
        });
        $tr.find('.fueradeservicio-linea').on('change', function () {
            actualizarEstadoUidLinea($(this).closest('tr.item-requisicion-sala-articulo'));
            actualizarControlesUid();
            if (esFueraDeServicio($(this).closest('tr.item-requisicion-sala-articulo'))) {
                $(this).closest('tr.item-requisicion-sala-articulo').find('.uid-linea').trigger('focus');
            }
        });
        $tr.find('.uid-linea').on('input change blur', function () {
            actualizarEstadoUidLinea($(this).closest('tr.item-requisicion-sala-articulo'));
            actualizarControlesUid();
        });
        actualizarEstadoUidLinea($tr);
        sincronizarEstadoLeyenda($tr);
    }

    $(function () {
        urlNpu = $('#form-general').data('url-npu') || urlNpu;
        htmlBotonGrabar = $('#botonform0').html() || 'Grabar';
        filasArticulo().each(function () {
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
            var $rows = $('<tbody></tbody>').append($.parseHTML($.trim(tpl))).children();
            var $row = $rows.filter('tr.item-requisicion-sala-articulo').first();
            if (tieneTransferenciaLaboratorio() && $row.length) {
                $row.addClass('linea-nueva-sin-tm');
            }
            $('#tabla-articulos-requisicion-sala tbody').append($rows);
            if ($row.length) {
                bindLinea($row);
            }
            actualizarControlesUid();
            actualizarAvisoLineasNuevasSinTm();
            setTimeout(function () {
                if ($row.length) {
                    $row.find('.codigoarticulo').trigger('focus');
                }
            }, 0);
        });

        prepararValidacionGrabado();
        registrarValidacionAntesDeEnviar();

        $(document).on('change input', '#form-general .codigodeposito, #form-general .deposito_id', function () {
            var depId = parseInt($('#deposito_id').val() || '0', 10);
            marcarCampoInvalido(document.getElementById('deposito_id_codigo') || document.querySelector('.codigodeposito'), depId <= 0);
        });

        $(document).on('click', '#botonform0', function (e) {
            e.preventDefault();
            var form = document.getElementById('form-general');
            if (!form) {
                return;
            }
            var resultado = validarRequisicionSalaAntesDeEnviar(form);
            if (!resultado.valido) {
                restaurarBotonGrabar();
                notificarErroresValidacion(resultado);
                return;
            }
            if (!confirmarGrabadoConLineasNuevasSinTm()) {
                return;
            }
            $('#form-general').trigger('submit');
        });

        $(window).on('pageshow', function (event) {
            if (event.originalEvent && event.originalEvent.persisted) {
                restaurarBotonGrabar();
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
