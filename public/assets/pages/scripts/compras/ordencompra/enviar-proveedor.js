(function ($) {
    'use strict';

    var ocIdActual = null;
    var colaEnvioIds = [];
    var colaEnvioIndice = 0;
    var colaEnvioActiva = false;
    var colaOnComplete = null;

    function resetModal() {
        ocIdActual = null;
        $('#oc-envio-proveedor-cargando').addClass('d-none');
        $('#oc-envio-proveedor-error').addClass('d-none').text('');
        $('#oc-envio-proveedor-form-wrap').addClass('d-none');
        $('#oc-envio-proveedor-advertencia').addClass('d-none').text('');
        $('#oc-envio-proveedor-cola-info').addClass('d-none').text('');
        $('#oc_envio_proveedor_email').val('');
        $('#oc_envio_proveedor_mensaje').val('');
        $('#oc_envio_proveedor_confirmar').addClass('d-none').prop('disabled', false);
        $('#oc_envio_proveedor_omitir').addClass('d-none');
        $('#oc_envio_proveedor_omitir_restantes').addClass('d-none');
        $('#oc_envio_proveedor_cancelar').text('Cancelar');
    }

    function actualizarUiCola() {
        if (!colaEnvioActiva || colaEnvioIds.length === 0) {
            $('#oc-envio-proveedor-cola-info').addClass('d-none');
            $('#oc_envio_proveedor_omitir').addClass('d-none');
            $('#oc_envio_proveedor_omitir_restantes').addClass('d-none');
            $('#oc_envio_proveedor_cancelar').text('Cancelar');
            return;
        }
        var total = colaEnvioIds.length;
        var actual = Math.min(colaEnvioIndice + 1, total);
        $('#oc-envio-proveedor-cola-info')
            .removeClass('d-none')
            .text('Orden ' + actual + ' de ' + total + ' pendientes de envío al proveedor.');
        $('#oc_envio_proveedor_omitir').removeClass('d-none');
        $('#oc_envio_proveedor_omitir_restantes').removeClass('d-none');
        $('#oc_envio_proveedor_cancelar').text('Cerrar');
    }

    function finalizarColaEnvio() {
        colaEnvioActiva = false;
        colaEnvioIds = [];
        colaEnvioIndice = 0;
        var cb = colaOnComplete;
        colaOnComplete = null;
        if (typeof cb === 'function') {
            cb();
        }
    }

    function procesarSiguienteEnCola() {
        if (!colaEnvioActiva) {
            return;
        }
        if (colaEnvioIndice >= colaEnvioIds.length) {
            finalizarColaEnvio();
            if (typeof toastr !== 'undefined') {
                toastr.info('Finalizó la revisión de envíos al proveedor.');
            }
            return;
        }
        abrirModalEnvioProveedor(colaEnvioIds[colaEnvioIndice], true);
    }

    function avanzarColaEnvio() {
        if (!colaEnvioActiva) {
            return;
        }
        colaEnvioIndice += 1;
        if (colaEnvioIndice >= colaEnvioIds.length) {
            $('#modalOcEnviarProveedor').modal('hide');
            finalizarColaEnvio();
            if (typeof toastr !== 'undefined') {
                toastr.info('Finalizó la revisión de envíos al proveedor.');
            }
            return;
        }
        var $m = $('#modalOcEnviarProveedor');
        var continuar = function () {
            procesarSiguienteEnCola();
        };
        if ($m.hasClass('show')) {
            $m.one('hidden.bs.modal', function handler() {
                $m.off('hidden.bs.modal', handler);
                continuar();
            });
            $m.modal('hide');
        } else {
            continuar();
        }
    }

    function mostrarError(msg) {
        $('#oc-envio-proveedor-error').removeClass('d-none').text(msg || 'No se pudo completar la operación.');
        $('#oc-envio-proveedor-form-wrap').addClass('d-none');
        $('#oc_envio_proveedor_confirmar').addClass('d-none');
    }

    function abrirModalEnvioProveedor(ordencompraId, desdeCola) {
        if (!ordencompraId) {
            return;
        }
        if (!desdeCola) {
            colaEnvioActiva = false;
            colaEnvioIds = [];
            colaEnvioIndice = 0;
            colaOnComplete = null;
        }
        resetModal();
        ocIdActual = ordencompraId;
        actualizarUiCola();
        $('#modalOcEnviarProveedor').modal('show');
        $('#oc-envio-proveedor-cargando').removeClass('d-none');

        var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
            '/compras/ordencompra/' + ordencompraId + '/datos-envio-proveedor';

        $.get(url).done(function (data) {
            $('#oc-envio-proveedor-cargando').addClass('d-none');
            if (!data || !data.puede_enviar) {
                if (colaEnvioActiva) {
                    avanzarColaEnvio();
                    return;
                }
                mostrarError((data && data.mensaje) ? data.mensaje : 'No se puede enviar esta orden al proveedor.');
                return;
            }
            $('#oc-envio-proveedor-form-wrap').removeClass('d-none');
            $('#oc_envio_proveedor_email').val(data.email || '');
            if (data.advertencia_estado) {
                $('#oc-envio-proveedor-advertencia').removeClass('d-none').text(data.advertencia_estado);
            }
            var titulo = 'Enviar OC Nº ' + (data.numeroordencompra || ordencompraId);
            if (data.proveedor_nombre) {
                titulo += ' — ' + data.proveedor_nombre;
            }
            $('#modalOcEnviarProveedor .modal-title').text(titulo);
            $('#oc_envio_proveedor_confirmar').removeClass('d-none');
            actualizarUiCola();
        }).fail(function (xhr) {
            $('#oc-envio-proveedor-cargando').addClass('d-none');
            if (colaEnvioActiva) {
                avanzarColaEnvio();
                return;
            }
            var msg = 'No se pudieron cargar los datos de envío.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            mostrarError(msg);
        });
    }

    window.abrirModalEnvioProveedorOc = abrirModalEnvioProveedor;

    /**
     * @param {number[]} ids
     * @param {{ onComplete?: function }} [opciones]
     */
    window.iniciarColaEnvioProveedorOc = function (ids, opciones) {
        var lista = (ids || []).map(function (id) {
            return parseInt(String(id), 10);
        }).filter(function (id) {
            return Number.isFinite(id) && id > 0;
        });
        if (!lista.length) {
            return;
        }
        colaEnvioActiva = true;
        colaEnvioIds = lista;
        colaEnvioIndice = 0;
        colaOnComplete = (opciones && typeof opciones.onComplete === 'function') ? opciones.onComplete : null;
        procesarSiguienteEnCola();
    };

    /**
     * Tras generar múltiples OC: muestra aviso y ofrece iniciar la cola de envío.
     * @param {{ envios_pendientes?: Array<{id:number}> }} res
     * @param {{ resultadosModal?: string, onComplete?: function }} [opciones]
     */
    window.ocWizardOfrecerEnvioProveedor = function (res, opciones) {
        var pendientes = (res && res.envios_pendientes) ? res.envios_pendientes : [];
        if (!pendientes.length) {
            return;
        }
        var ids = pendientes.map(function (p) { return p.id; });
        var n = ids.length;
        var msg = 'Se generaron órdenes de compra y ' + n + ' proveedor(es) tienen email de envío configurado.\n\n' +
            '¿Desea revisar y confirmar el envío por correo ahora?';
        var iniciar = function () {
            var $resModal = opciones && opciones.resultadosModal ? $(opciones.resultadosModal) : $();
            if ($resModal.length) {
                $resModal.modal('hide');
            }
            window.iniciarColaEnvioProveedorOc(ids, {
                onComplete: opciones && opciones.onComplete ? opciones.onComplete : null
            });
        };
        if (window.confirm(msg)) {
            iniciar();
        }
    };

    $(document).on('click', '.js-oc-enviar-proveedor', function (e) {
        e.preventDefault();
        var id = $(this).data('ordencompra-id');
        abrirModalEnvioProveedor(id, false);
    });

    $(document).on('click', '.js-oc-wizard-iniciar-envios', function (e) {
        e.preventDefault();
        var raw = $(this).attr('data-envio-ids') || '[]';
        var ids;
        try {
            ids = JSON.parse(raw);
        } catch (err) {
            ids = [];
        }
        var selModal = $(this).attr('data-resultados-modal');
        if (selModal) {
            $(selModal).modal('hide');
        }
        window.iniciarColaEnvioProveedorOc(ids);
    });

    $('#oc_envio_proveedor_confirmar').on('click', function () {
        if (!ocIdActual) {
            return;
        }
        var email = $.trim($('#oc_envio_proveedor_email').val());
        if (!email) {
            alert('Indique al menos un email de destino.');
            return;
        }
        if (!window.confirm('¿Confirma el envío de la orden de compra al proveedor?\n\nDestino: ' + email)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        var url = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') +
            '/compras/ordencompra/' + ocIdActual + '/enviar-proveedor';
        var token = $('meta[name="csrf-token"]').attr('content') ||
            $('#form-ordencompra-general input[name="_token"]').val() ||
            $('input[name="_token"]').first().val();

        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: token,
                email: email,
                mensaje: $('#oc_envio_proveedor_mensaje').val()
            }
        }).done(function (data) {
            if (data && data.mensaje === 'ok') {
                if (colaEnvioActiva) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success('OC enviada al proveedor.');
                    }
                    avanzarColaEnvio();
                } else {
                    $('#modalOcEnviarProveedor').modal('hide');
                    if (typeof toastr !== 'undefined') {
                        toastr.success('La orden de compra fue enviada al proveedor.');
                    } else {
                        alert('La orden de compra fue enviada al proveedor.');
                    }
                }
            } else {
                alert((data && data.errores) ? data.errores : 'No se pudo enviar el correo.');
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            var msg = 'No se pudo enviar el correo.';
            if (xhr.responseJSON && xhr.responseJSON.errores) {
                msg = xhr.responseJSON.errores;
            }
            alert(msg);
            $btn.prop('disabled', false);
        });
    });

    $('#oc_envio_proveedor_omitir').on('click', function () {
        if (colaEnvioActiva) {
            avanzarColaEnvio();
            return;
        }
        $('#modalOcEnviarProveedor').modal('hide');
    });

    $('#oc_envio_proveedor_omitir_restantes').on('click', function () {
        if (colaEnvioActiva) {
            $('#modalOcEnviarProveedor').modal('hide');
            finalizarColaEnvio();
            return;
        }
        $('#modalOcEnviarProveedor').modal('hide');
    });

    $('#modalOcEnviarProveedor').on('hidden.bs.modal', function () {
        if (!colaEnvioActiva) {
            resetModal();
        }
    });

    $(function () {
        if (window.ocSugerirEnvioProveedor && window.ocSugerirEnvioProveedor.ordencompra_id) {
            abrirModalEnvioProveedor(window.ocSugerirEnvioProveedor.ordencompra_id, false);
        }
    });
}(jQuery));
