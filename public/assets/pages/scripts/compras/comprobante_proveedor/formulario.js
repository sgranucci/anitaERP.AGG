$(function () {
    if (!$('#form-comprobante-proveedor').length) {
        return;
    }

    var $form = $('#form-comprobante-proveedor');
    var comprobanteId = parseInt($form.data('comprobante-id') || '0', 10);
    var contabilizado = String($form.data('contabilizado') || '0') === '1';
    var previewUrl = String($form.data('preview-url') || '');
    var puedeEditarConceptoIva = String($form.data('puede-editar-concepto-iva') || '0') === '1';
    var urlEditarConceptoIvaTpl = String($form.data('url-editar-concepto-iva') || '');
    var previewTimer = null;
    var previewXhr = null;
    var conceptosMeta = {};

    try {
        conceptosMeta = JSON.parse($('#cp-conceptos-cuenta-meta').text() || '{}');
    } catch (e) {
        conceptosMeta = {};
    }

    var TIPOS_NETO = ['N', 'G', 'E'];

    function mostrarSolapa(sel) {
        $('.cp-solapa').hide();
        $(sel).show();
    }

    function marcarTabActivo(btnDomId) {
        $('.cp-tab-solapa').removeClass('font-weight-bold');
        var $b = $('#' + btnDomId);
        if ($b.length) {
            $b.addClass('font-weight-bold');
        }
    }

    function esModoAsignaRecepcion() {
        return $('#modo_carga').val() === 'ASIGNA_RECEPCION';
    }

    function conceptoRequiereCuentaDebe(tipoConcepto) {
        if (esModoAsignaRecepcion() && TIPOS_NETO.indexOf(String(tipoConcepto || '')) >= 0) {
            return false;
        }
        return true;
    }

    function actualizarBadgeAsiento(tieneProblema) {
        var $tab = $('#cp-boton-asiento-contable');
        if (!$tab.length) {
            return;
        }
        $tab.find('.cp-badge-asiento-error').remove();
        if (tieneProblema) {
            $tab.append('<span class="badge badge-warning ml-1 cp-badge-asiento-error" title="Revise el cuadre antes de contabilizar">!</span>');
        }
    }

    function marcarAvisosConceptosLocales() {
        $('#tbody-concepto-table tr.item-concepto').each(function () {
            var $row = $(this);
            var $select = $row.find('.concepto_ivacompra_id');
            var $monto = $row.find('.monto');
            var $aviso = $row.find('.cp-aviso-concepto-cuenta');
            var conceptoId = parseInt($select.val() || '0', 10);
            var monto = parseFloat($monto.val() || '0');

            $row.removeClass('table-warning');
            $aviso.removeClass('text-danger fa fa-exclamation-triangle').text('').attr('title', '');

            if (conceptoId <= 0 || monto <= 0) {
                return;
            }

            var meta = conceptosMeta[conceptoId] || {};
            if (!conceptoRequiereCuentaDebe(meta.tipoconcepto)) {
                $aviso.addClass('text-muted').text('—').attr('title', 'Neto: revierte provisión COM');
                return;
            }

            if (!meta.cuenta_debe_id || parseInt(meta.cuenta_debe_id, 10) <= 0) {
                $row.addClass('table-warning');
                if (puedeEditarConceptoIva && urlEditarConceptoIvaTpl && conceptoId > 0) {
                    var urlEditar = urlEditarConceptoIvaTpl.replace('__ID__', String(conceptoId));
                    $aviso.html(
                        '<a href="' + urlEditar + '" class="text-danger" target="_blank" rel="noopener noreferrer" ' +
                        'title="Falta cuenta DEBE en concepto IVA «' + (meta.nombre || '') + '»">' +
                        '<i class="fa fa-exclamation-triangle"></i></a>'
                    );
                } else {
                    $aviso.addClass('text-danger fa fa-exclamation-triangle')
                        .attr('title', 'Falta cuenta DEBE en concepto IVA «' + (meta.nombre || '') + '»');
                }
            } else {
                $aviso.addClass('text-success fa fa-check').attr('title', 'Cuenta DEBE configurada');
            }
        });
    }

    function renderBannerAvisos(avisos, error) {
        var $banner = $('#cp-asiento-avisos-banner');
        if (!$banner.length) {
            return;
        }

        var mensajes = [];
        if (error) {
            mensajes.push(error);
        }
        (avisos || []).forEach(function (aviso) {
            if (aviso && aviso.mensaje) {
                mensajes.push(aviso.mensaje);
            }
        });

        if (mensajes.length === 0) {
            $banner.addClass('d-none').empty();
            return;
        }

        var html = '<strong><i class="fa fa-exclamation-triangle"></i> Asiento contable:</strong><ul class="mb-0 mt-1 pl-3">';
        mensajes.forEach(function (msg) {
            html += '<li>' + $('<div>').text(msg).html() + '</li>';
        });
        html += '</ul>';
        $banner.removeClass('d-none').html(html);
    }

    function aplicarAvisosProveedor(avisos) {
        var $aviso = $('#cp-aviso-proveedor-cuenta');
        if (!$aviso.length) {
            return;
        }

        var problema = (avisos || []).some(function (a) {
            return a.tipo === 'proveedor_sin_cuenta' || a.tipo === 'proveedor_sin_seleccionar';
        });

        if (problema) {
            $aviso.removeClass('d-none').text('Sin cuenta contable de proveedores');
        } else {
            $aviso.addClass('d-none').text('');
        }
    }

    function recargarPreviewAsiento() {
        if (contabilizado || !previewUrl) {
            return;
        }

        if (previewXhr && previewXhr.readyState !== 4) {
            previewXhr.abort();
        }

        var $body = $('#cp-asiento-preview-body');
        if ($body.length && !$body.data('loading')) {
            $body.data('loading', 1);
        }

        previewXhr = $.ajax({
            url: previewUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            data: $form.serialize()
        })
            .done(function (res) {
                if ($body.length && res && res.html) {
                    $body.html(res.html);
                    $body.removeData('loading');
                }
                renderBannerAvisos(res.avisos || [], res.error || null);
                aplicarAvisosProveedor(res.avisos || []);
                actualizarBadgeAsiento(!!(res.error || (res.avisos && res.avisos.length)));
            })
            .fail(function () {
                if ($body.length) {
                    $body.removeData('loading');
                }
            });
    }

    function programarPreviewAsiento() {
        if (contabilizado) {
            return;
        }
        marcarAvisosConceptosLocales();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(recargarPreviewAsiento, 350);
    }

    $('#cp-boton-principal').on('click', function () {
        mostrarSolapa('#cp-solapa-principal');
        marcarTabActivo('cp-boton-principal');
    });
    $('#cp-boton-conceptos').on('click', function () {
        mostrarSolapa('#cp-solapa-conceptos');
        marcarTabActivo('cp-boton-conceptos');
    });
    $('#cp-boton-cuotas').on('click', function () {
        mostrarSolapa('#cp-solapa-cuotas');
        marcarTabActivo('cp-boton-cuotas');
    });
    $('#cp-boton-asiento-contable').on('click', function () {
        mostrarSolapa('#cp-solapa-asiento-contable');
        marcarTabActivo('cp-boton-asiento-contable');
    });
    $('#cp-boton-estados').on('click', function () {
        mostrarSolapa('#cp-solapa-estados');
        marcarTabActivo('cp-boton-estados');
    });
    $('#cp-boton-archivos').on('click', function () {
        mostrarSolapa('#cp-solapa-archivos');
        marcarTabActivo('cp-boton-archivos');
    });

    $('#agrega_renglon_concepto').on('click', function (e) {
        e.preventDefault();
        var renglon = $('#template-renglon-concepto').html();
        $('#tbody-concepto-table').append(renglon);
        programarPreviewAsiento();
    });

    $(document).on('click', '.eliminar_concepto', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        programarPreviewAsiento();
    });

    function toggleBloqueRecepcionesCom() {
        var modo = $('#modo_carga').val();
        if (modo === 'ASIGNA_RECEPCION') {
            $('#cp-bloque-recepciones-com').show();
        } else {
            $('#cp-bloque-recepciones-com').hide();
        }
        programarPreviewAsiento();
    }

    $('#modo_carga').on('change', toggleBloqueRecepcionesCom);
    toggleBloqueRecepcionesCom();

    $form.on('input change', 'input, select, textarea', function () {
        programarPreviewAsiento();
    });

    $(document).on('change', '#tbody-concepto-table .concepto_ivacompra_id, #tbody-concepto-table .monto', function () {
        programarPreviewAsiento();
    });

    $(document).on('change', '#cp-bloque-recepciones-com input[type=checkbox]', function () {
        programarPreviewAsiento();
    });

    // Archivos adjuntos
    $('#cp-agrega-renglon-archivo').on('click', function (e) {
        e.preventDefault();
        var tpl = document.getElementById('cp-template-renglon-archivo');
        if (tpl && tpl.content) {
            $('#cp-tbody-tabla-archivo').append(tpl.content.cloneNode(true));
        }
    });

    $(document).on('click', '.cp-eliminararchivo', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.cp-eliminar-archivo', function (e) {
        e.preventDefault();
        $(this).closest('.cp-archivo-item').remove();
    });

    if (typeof activa_eventos_consultaproveedor === 'function') {
        activa_eventos_consultaproveedor();
    }

    (function initProveedorArcaComprobante() {
        var $cfg = $('#cp-proveedor-arca-config');
        if (!$cfg.length || typeof window.ArcaPadronValidacionAsync === 'undefined') {
            return;
        }

        window.cpLimpiarAvisoProveedorArca = function () {
            window.ArcaPadronValidacionAsync.limpiarUltimoModal();
        };

        window.cpValidarProveedorArca = function (proveedorId, condicionivaId) {
            var id = parseInt(proveedorId || '0', 10);
            if (id <= 0) {
                window.cpLimpiarAvisoProveedorArca();
                return;
            }
            window.ArcaPadronValidacionAsync.encolar({
                $config: $cfg,
                proveedorId: id,
                condicionivaId: condicionivaId,
                suspenderUi: false,
            });
        };

        var proveedorInicial = parseInt($('#proveedor_id').val() || '0', 10);
        if (proveedorInicial > 0) {
            window.cpValidarProveedorArca(proveedorInicial);
        }
    })();

    var paramsUrl = new URLSearchParams(window.location.search);
    if (paramsUrl.get('solapa') === 'asiento' && $('#cp-solapa-asiento-contable').length) {
        mostrarSolapa('#cp-solapa-asiento-contable');
        marcarTabActivo('cp-boton-asiento-contable');
    } else if (paramsUrl.get('solapa') === 'archivos' && $('#cp-solapa-archivos').length) {
        mostrarSolapa('#cp-solapa-archivos');
        marcarTabActivo('cp-boton-archivos');
    }

    if (!contabilizado) {
        marcarAvisosConceptosLocales();
        programarPreviewAsiento();
    }
});
