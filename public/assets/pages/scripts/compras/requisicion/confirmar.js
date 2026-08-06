/**
 * Banner y anti-doble-submit al confirmar requisición provisoria.
 * Si hay varios CC de destino, usa el mismo modal que EN COMPRAS antes de confirmar.
 * Permite comentario opcional al árbol de aprobación.
 */
(function ($) {
    'use strict';

    var enviandoConfirmacion = false;
    var confirmacionPendiente = null;

    function asegurarBannerConfirmandoRequisicion() {
        if ($('#requisicion-banner-confirmando').length) {
            return;
        }
        var html = '<div id="requisicion-banner-confirmando" class="requisicion-confirmando-overlay" role="status" aria-live="polite" aria-busy="true">';
        html += '<div class="alert alert-warning shadow requisicion-confirmando-banner mb-0 px-4 py-3">';
        html += '<div class="requisicion-confirmando-spinner-wrap" aria-hidden="true">';
        html += '<div class="spinner-border text-dark" role="status"><span class="sr-only">Cargando&hellip;</span></div>';
        html += '</div>';
        html += '<strong class="d-block mb-2 text-dark">Confirmando requisici&oacute;n&hellip;</strong>';
        html += '<span class="small d-block text-dark">Validando datos, enviando al &aacute;rbol de aprobaci&oacute;n y sincronizando con Anita.<br>Por favor espere; puede tardar varios minutos.</span>';
        html += '</div></div>';
        $('body').append(html);
    }

    function deshabilitarBotonesConfirmacion() {
        $([
            'form.form-confirmar-requisicion button[type="submit"]',
            '#btn-confirmar-requisicion-provisorio',
            'button[form="form-requisicion-confirmar"]',
        ].join(', ')).prop('disabled', true).addClass('disabled');
    }

    function mostrarBannerConfirmandoRequisicion() {
        if (enviandoConfirmacion) {
            return false;
        }
        enviandoConfirmacion = true;
        asegurarBannerConfirmandoRequisicion();
        $('#requisicion-banner-confirmando').addClass('is-visible');
        deshabilitarBotonesConfirmacion();
        return true;
    }

    function asegurarHidden($form, name, value) {
        var $hidden = $form.find('input[name="' + name + '"]');
        if (!$hidden.length) {
            $hidden = $('<input type="hidden" name="' + name + '">');
            $form.append($hidden);
        }
        $hidden.val(value);
    }

    function asegurarHiddenCcArbol($form, centrocostoId) {
        asegurarHidden($form, 'centrocostodestino_arbol_id', centrocostoId > 0 ? centrocostoId : '');
    }

    function urlPreviewCc($form) {
        var preview = $form.data('previewCcUrl') || $form.attr('data-preview-cc-url') || '';
        if (preview) {
            return preview;
        }
        var action = $form.attr('action') || '';
        return action.replace(/\/confirmar(\?.*)?$/, '/centros-costo-arbol$1');
    }

    function enviarConfirmacionRequisicion($formConfirmar, centrocostoId, observacionEnvio) {
        if (!$formConfirmar || !$formConfirmar.length) {
            return;
        }
        if (centrocostoId) {
            asegurarHiddenCcArbol($formConfirmar, centrocostoId);
        }
        if (observacionEnvio) {
            asegurarHidden($formConfirmar, 'observacion_envio', observacionEnvio);
        } else {
            $formConfirmar.find('input[name="observacion_envio"]').remove();
        }
        if (!mostrarBannerConfirmandoRequisicion()) {
            return;
        }
        window.setTimeout(function () {
            $formConfirmar.get(0).submit();
        }, 50);
    }

    function abrirModalComentario($form, centrocostoId) {
        var $modal = $('#modalRequisicionConfirmarEnvioArbol');
        if (!$modal.length) {
            if (!window.confirm('¿Confirmar requisición? Enviará al árbol de aprobación y sincronizará con Anita.')) {
                return;
            }
            enviarConfirmacionRequisicion($form, centrocostoId, '');
            return;
        }
        confirmacionPendiente = {
            $form: $form,
            centrocostoId: centrocostoId || null
        };
        $('#requisicionConfirmarEnvioArbolObservacion').val('');
        $modal.modal('show');
    }

    function pedirCcSiCorresponde($form, continuar) {
        var previewUrl = urlPreviewCc($form);
        if (!previewUrl) {
            continuar(null);
            return;
        }

        $.get(previewUrl)
            .done(function (data) {
                if (data && data.requiere_seleccion_centrocosto) {
                    if (!window.RequisicionCentrocostoArbolModal) {
                        alert('No se pudo abrir la selección de centro de costo.');
                        return;
                    }
                    window.RequisicionCentrocostoArbolModal.abrir({
                        centrosCosto: data.centros_costo || [],
                        texto: 'La requisición tiene renglones con distintos centros de costo de destino. Elija con cuál enviar al árbol de aprobación.',
                        onConfirm: function (centrocostoId) {
                            continuar(centrocostoId);
                        }
                    });
                    return;
                }
                continuar(data && data.centrocosto_arbol_id ? data.centrocosto_arbol_id : null);
            })
            .fail(function (xhr) {
                var msg = 'No se pudo verificar los centros de costo de la requisición.';
                if (xhr.responseJSON && xhr.responseJSON.errores) {
                    msg = xhr.responseJSON.errores;
                }
                alert(msg);
            });
    }

    function iniciarConfirmacion($form) {
        if (enviandoConfirmacion || !$form || !$form.length) {
            return;
        }
        pedirCcSiCorresponde($form, function (centrocostoId) {
            abrirModalComentario($form, centrocostoId);
        });
    }

    function initConfirmacionDesdeListado() {
        $(document).on('submit', 'form.form-confirmar-requisicion', function (e) {
            e.preventDefault();
            if (enviandoConfirmacion) {
                return false;
            }
            iniciarConfirmacion($(this));
            return false;
        });
    }

    window.RequisicionConfirmar = {
        enviarConfirmacionRequisicion: enviarConfirmacionRequisicion,
        mostrarBannerConfirmandoRequisicion: mostrarBannerConfirmandoRequisicion,
    };

    $(function () {
        initConfirmacionDesdeListado();

        $(document).on('click', '#btn-confirmar-requisicion-provisorio, button[form="form-requisicion-confirmar"]', function (e) {
            e.preventDefault();
            if (enviandoConfirmacion) {
                return false;
            }
            iniciarConfirmacion($('#form-requisicion-confirmar'));
            return false;
        });

        $('#requisicionConfirmarEnvioArbolConfirmar').on('click', function () {
            if (!confirmacionPendiente || enviandoConfirmacion) {
                return;
            }
            var obs = $.trim($('#requisicionConfirmarEnvioArbolObservacion').val() || '');
            var pendiente = confirmacionPendiente;
            confirmacionPendiente = null;
            $('#modalRequisicionConfirmarEnvioArbol').modal('hide');
            enviarConfirmacionRequisicion(pendiente.$form, pendiente.centrocostoId, obs);
        });

        $('#modalRequisicionConfirmarEnvioArbol').on('hidden.bs.modal', function () {
            if (!enviandoConfirmacion) {
                confirmacionPendiente = null;
            }
            $('#requisicionConfirmarEnvioArbolObservacion').val('');
        });
    });
})(jQuery);
