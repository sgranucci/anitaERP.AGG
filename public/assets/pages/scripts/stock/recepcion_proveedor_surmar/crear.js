(function ($) {
    'use strict';

    var carpetaBase = window.carpetaBase || '';
    var cfg = window.SURMAR_RECEPCION_CREAR || {};
    var urls = cfg.urls || {};
    var enviando = false;

    function escHtml(texto) {
        return $('<div>').text(texto == null ? '' : texto).html();
    }

    function urlBuscarOc() {
        return urls.buscarOc || (carpetaBase + '/stock/recepcion-proveedor-surmar/api/buscar-oc-pendientes');
    }

    function urlPrecargaOc() {
        return urls.precargaOc || (carpetaBase + '/stock/recepcion-proveedor-surmar/api/precarga-oc');
    }

    function esTeclaEnter(e) {
        if (!e) return false;
        return e.key === 'Enter' || e.code === 'Enter' || e.which === 13 || e.keyCode === 13 || e.keyCode === 10;
    }

    function actualizarLinkConsultarOc(ocId) {
        var id = parseInt(ocId, 10) || 0;
        var $a = $('#btn-consultar-oc-recepcion-surmar');
        if (!$a.length) {
            return;
        }
        if (id <= 0) {
            $a.addClass('d-none').attr('href', '#');
            return;
        }
        $a.removeClass('d-none').attr(
            'href',
            carpetaBase + '/compras/ordencompra/' + id + '/editar?origen=modal_consulta&vista=consulta'
        );
    }

    function aplicarOc(data) {
        if (!data || !data.ordencompra_id) {
            return;
        }
        $('#ordencompra_id').val(data.ordencompra_id);
        $('#numero_oc_buscar').val(data.numeroordencompra || '');
        $('#proveedor_id').val(data.proveedor_id || '');
        $('#proveedor_nombre').val(data.proveedor_nombre || '');
        $('#codigoproveedor').val(data.proveedor_codigo || '');
        actualizarLinkConsultarOc(data.ordencompra_id);
    }

    function limpiarOc() {
        $('#ordencompra_id').val('');
        $('#proveedor_id').val('');
        $('#proveedor_nombre').val('');
        $('#codigoproveedor').val('');
        actualizarLinkConsultarOc(0);
    }

    function depositoIdActual() {
        return parseInt($('#deposito_id').val(), 10) || 0;
    }

    function depositoCodigoActual() {
        return $.trim($('#deposito_id_codigo').val() || '');
    }

    function focusDeposito() {
        var $dep = $('#deposito_id_codigo');
        if ($dep.length) {
            $dep.trigger('focus').select();
        }
    }

    function sincronizarHiddensDeposito() {
        $('#deposito_codigo_old').val(depositoCodigoActual());
        $('#deposito_descripcion_old').val($.trim($('#deposito_id_descripcion').val() || ''));
    }

    /** Orden de foco con Enter en alta de recepción. */
    function secuenciaCamposCrear() {
        return [
            '#numero_oc_buscar',
            '#fecha',
            '#deposito_id_codigo',
            '#certificado_senasa',
            '#tropa',
            '#temperatura_ingreso',
            '#destino_senasa',
            '#camara',
            '#nro_establecimiento'
        ];
    }

    function avanzarFocusCrear(desdeSelector) {
        var seq = secuenciaCamposCrear();
        var idx = -1;
        for (var i = 0; i < seq.length; i++) {
            if ($(desdeSelector).is(seq[i]) || $(desdeSelector).attr('id') === seq[i].replace('#', '')) {
                idx = i;
                break;
            }
        }
        if (idx < 0) {
            return false;
        }
        for (var j = idx + 1; j < seq.length; j++) {
            var $n = $(seq[j]);
            if ($n.length && $n.is(':visible') && !$n.prop('disabled') && !$n.prop('readonly')) {
                $n.trigger('focus');
                try { $n.select(); } catch (err) { /* ignore */ }
                return true;
            }
        }
        $('#form-recepcion-surmar button[type=submit]').trigger('focus');
        return true;
    }

    function cargaTablaOcPendientes() {
        var q = $('#consultaocrecepcion').val() || '';
        $.getJSON(urlBuscarOc(), { q: q })
            .done(function (rows) {
                var $body = $('#datosocrecepcion').empty();
                (rows || []).forEach(function (r) {
                    var $tr = $('<tr/>');
                    $tr.data('oc', r);
                    $tr.append('<td class="oc_tabla_num">' + escHtml(r.numeroordencompra) + '</td>');
                    $tr.append('<td>' + escHtml(r.fecha || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.proveedor_nombre || '') + '</td>');
                    $tr.append('<td>' + escHtml(r.empresa_nombre || '') + '</td>');
                    $tr.append(
                        '<td><span class="badge badge-' +
                        (r.estado_com === 'SIN COM' ? 'warning' : 'info') +
                        '">' +
                        escHtml(r.estado_com) +
                        '</span></td>'
                    );
                    $tr.append(
                        '<td class="text-right">' +
                        escHtml(
                            r.cantidad_pendiente != null
                                ? Number(r.cantidad_pendiente).toLocaleString('es-AR', { maximumFractionDigits: 3 })
                                : ''
                        ) +
                        '</td>'
                    );
                    $tr.append('<td><a href="#" class="btn btn-warning btn-sm eligeconsultaocrecepcion">Elegir</a></td>');
                    $tr.append('<td><a href="#" class="btn btn-info btn-sm consultaocrecepciontabla">Consultar</a></td>');
                    $body.append($tr);
                });
            })
            .fail(function (xhr) {
                alert(
                    (xhr.responseJSON && xhr.responseJSON.error) ||
                    'Error al buscar OC Surmar pendientes'
                );
            });
    }

    function precargarOc(opciones) {
        opciones = opciones || {};
        var ocId = parseInt(opciones.ordencompra_id || $('#ordencompra_id').val(), 10) || 0;
        var numeroOc = parseInt(opciones.numero_oc || $('#numero_oc_buscar').val(), 10) || 0;
        if (!ocId && !numeroOc) {
            if (!opciones.silencioso) {
                alert('Indique el número de OC o búsquela con la lupa.');
            }
            return;
        }
        var params = ocId ? { ordencompra_id: ocId } : { numero_oc: numeroOc };
        $.getJSON(urlPrecargaOc(), params)
            .done(function (data) {
                aplicarOc(data);
                if (opciones.focusDeposito !== false) {
                    focusDeposito();
                }
            })
            .fail(function (xhr) {
                if (!opciones.conservarSiFalla) {
                    limpiarOc();
                }
                if (!opciones.silencioso) {
                    alert((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar la OC.');
                }
            });
    }

    function elegirOcDesdeModal(r) {
        if (!r || !r.id) {
            return;
        }
        $('#consultaocrecepcionModal').modal('hide');
        precargarOc({ ordencompra_id: r.id, focusDeposito: true });
    }

    function enviarFormulario($form) {
        sincronizarHiddensDeposito();
        enviando = true;
        $form.off('submit.surmarGuard');
        $form[0].submit();
    }

    function asegurarDepositoYEnviar($form) {
        if (depositoIdActual() > 0) {
            enviarFormulario($form);
            return;
        }

        var codigo = depositoCodigoActual();
        if (!codigo) {
            alert('Seleccione el depósito (código o lupa).');
            focusDeposito();
            return;
        }

        if (typeof leerDepositoPorCodigo !== 'function') {
            alert('No se pudo resolver el depósito. Recargue la página.');
            return;
        }

        leerDepositoPorCodigo(codigo, document.getElementById('deposito_id_codigo'), function (data) {
            if (data && data.id && depositoIdActual() > 0) {
                enviarFormulario($form);
                return;
            }
            alert('Depósito no válido o sin autorización para Surmar. Elija otro con la lupa.');
            focusDeposito();
        });
    }

    $(function () {
        $('#btn-consulta-oc-recepcion-modal').on('click', function () {
            $('#consultaocrecepcionModal').modal('show');
            $('#consultaocrecepcion').val('').trigger('focus');
            cargaTablaOcPendientes();
        });

        $('#consultaocrecepcionModal').on('shown.bs.modal', function () {
            $('#consultaocrecepcion').trigger('focus');
        });

        $('#aceptaconsultaocrecepcionModal').on('click', function () {
            $('#consultaocrecepcionModal').modal('hide');
        });

        $(document).on('click', '.eligeconsultaocrecepcion', function (e) {
            e.preventDefault();
            elegirOcDesdeModal($(this).closest('tr').data('oc'));
        });

        $(document).on('click', '.consultaocrecepciontabla', function (e) {
            e.preventDefault();
            var r = $(this).closest('tr').data('oc');
            if (r && r.url_consulta) {
                // Sin noopener: permite que «Cerrar solapa» cierre la pestaña.
                window.open(r.url_consulta, '_blank');
            }
        });

        $(document).on('keyup', '#consultaocrecepcion', function () {
            clearTimeout(window._tocrecepSurmar);
            window._tocrecepSurmar = setTimeout(cargaTablaOcPendientes, 300);
        });

        // Enter en Nº OC: carga OC y salta a depósito
        $('#numero_oc_buscar').on('keydown', function (e) {
            if (!esTeclaEnter(e) && e.key !== 'Tab') {
                return;
            }
            if (esTeclaEnter(e)) {
                e.preventDefault();
                e.stopPropagation();
                precargarOc({
                    numero_oc: $(this).val(),
                    forzar: true,
                    focusDeposito: true
                });
            }
        }).on('blur', function () {
            var n = parseInt($(this).val(), 10) || 0;
            var actual = parseInt($('#ordencompra_id').val(), 10) || 0;
            if (n > 0 && !actual) {
                precargarOc({ numero_oc: n, focusDeposito: false, silencioso: true });
            }
        });

        // Enter en el resto de campos del alta: secuencia fija
        $('#form-recepcion-surmar').on('keydown', 'input, select', function (e) {
            if (!esTeclaEnter(e)) {
                return;
            }
            if ($(this).is('textarea') || $(this).is('#numero_oc_buscar')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            // En código depósito: resolver y seguir a certificado
            if ($(this).is('#deposito_id_codigo, .codigodeposito')) {
                var codigo = $.trim($(this).val() || '');
                var self = this;
                if (codigo && typeof leerDepositoPorCodigo === 'function') {
                    leerDepositoPorCodigo(codigo, self, function () {
                        avanzarFocusCrear(self);
                    });
                } else {
                    avanzarFocusCrear(self);
                }
                return;
            }

            avanzarFocusCrear(this);
        });

        $('#form-recepcion-surmar').on('submit.surmarGuard', function (e) {
            if (enviando) {
                return;
            }
            e.preventDefault();
            if (!(parseInt($('#ordencompra_id').val(), 10) > 0)) {
                alert('Debe cargar una orden de compra Surmar pendiente.');
                $('#numero_oc_buscar').focus();
                return;
            }
            asegurarDepositoYEnviar($(this));
        });

        // Tras error de validación: restaurar proveedor/OC desde hidden old.
        var oldOcId = parseInt($('#ordencompra_id').val(), 10) || 0;
        var oldNumero = parseInt($('#numero_oc_buscar').val(), 10) || 0;
        if (oldOcId > 0 || oldNumero > 0) {
            precargarOc({
                ordencompra_id: oldOcId || undefined,
                numero_oc: oldOcId ? undefined : oldNumero,
                silencioso: true,
                conservarSiFalla: true,
                focusDeposito: false
            });
        } else {
            actualizarLinkConsultarOc(0);
        }

        if (depositoIdActual() <= 0 && depositoCodigoActual()) {
            if (typeof leerDepositoPorCodigo === 'function') {
                leerDepositoPorCodigo(depositoCodigoActual(), document.getElementById('deposito_id_codigo'));
            }
        }

        if (typeof window.activa_eventos_consultadeposito === 'function') {
            window.activa_eventos_consultadeposito();
        }

        // Tras elegir/resolver depósito, seguir a certificado SENASA
        window.onDepositoAplicadoEnFormulario = function () {
            setTimeout(function () {
                var $c = $('#certificado_senasa');
                if ($c.length && $c.is(':visible')) {
                    $c.trigger('focus').select();
                }
            }, 50);
        };
    });
})(jQuery);
