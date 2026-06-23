/**
 * Requisición sala: modales de consulta fuera del layout anidado + atajos F1 / SKU manual.
 * Solo aplica en crear/editar de requisición sala; no modifica consulta.js global.
 */
(function ($) {
    'use strict';

    function esPantallaRequisicionSala() {
        return $('#form-general[data-url-npu]').length
            && $('#tabla-articulos-requisicion-sala').length;
    }

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function moverModalesAlBody() {
        ['#consultadepositoModal', '#consultaarticuloModal'].forEach(function (selector) {
            var $modal = $(selector);
            if ($modal.length && !$modal.parent().is('body')) {
                $modal.appendTo('body');
            }
        });
    }

    function urlNpuRequisicionSala() {
        return ($('#form-general[data-url-npu]').data('url-npu') || '').toString();
    }

    function consultarNpuLinea($tr) {
        var urlNpu = urlNpuRequisicionSala();
        var sku = ($tr.find('.codigoarticulo').val() || '').trim();
        if (!urlNpu || !sku) {
            return;
        }
        $.getJSON(urlNpu, { sku: sku }).done(function (resp) {
            if (resp.encontrado) {
                $tr.find('.numeroparte-linea').val(resp.numeroparte).prop('readonly', true);
                $tr.find('.articulo_lleva_npu').val('1');
            } else {
                $tr.find('.numeroparte-linea').prop('readonly', false);
            }
        });
    }

    function abrirModalDepositoDesdeCampo($btn) {
        var $ctx = $btn.closest('.tm-deposito-campo');
        if (!$ctx.length) {
            return;
        }

        window.ptrDeposito_id = $ctx.find('.deposito_id');
        window.ptrCodigoDeposito_id = $ctx.find('.codigodeposito');
        window.ptrDescripcionDeposito = $ctx.find('.descripciondeposito');

        $('#consultadepositoModal')
            .removeAttr('inert')
            .css('display', '')
            .modal('show');
    }

    function abrirModalArticuloDesdeFila($btn) {
        var $tr = $btn.closest('tr');
        if (!$tr.length) {
            return;
        }

        window.ptrarticulo_id = $tr.find('.articulo_id');
        window.ptrcodigoarticulo = $tr.find('.codigoarticulo');
        window.ptrnombrearticulo = $tr.find('.descripcionarticulo');
        window.ptrunidadmedida = $tr.find('.unidadmedida');
        window.ptrcategoria_id = $tr.find('.categoria_id');
        window.ptrsubcategoria_id = $tr.find('.subcategoria_id');

        $('#consultaarticuloModal')
            .removeAttr('inert')
            .css('display', '')
            .modal('show');
    }

    function validarSkuLinea(input) {
        if (!input || !input.classList.contains('codigoarticulo')) {
            return;
        }
        $(input).trigger('change');
    }

    function registrarAtajosRequisicionSala() {
        document.addEventListener('keydown', function (e) {
            if (!esTeclaF1(e)) {
                return;
            }

            var target = e.target;
            if (!target || !target.closest('#form-general[data-url-npu]')) {
                return;
            }

            if (target.classList.contains('codigodeposito') && !target.readOnly) {
                if (!target.closest('.tm-deposito-campo')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var $btnDep = $(target).closest('.tm-deposito-campo').find('.consultadeposito').first();
                if ($btnDep.length) {
                    $btnDep.trigger('click');
                }
                return;
            }

            if (target.classList.contains('codigoarticulo') && !target.readOnly) {
                if (!target.closest('#tabla-articulos-requisicion-sala')) {
                    return;
                }
                if ($('#consultaarticuloModal').hasClass('show')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var $btnArt = $(target).closest('tr').find('.consultaarticulo').first();
                if ($btnArt.length) {
                    $btnArt.trigger('click');
                }
            }
        }, true);

        var tbody = document.querySelector('#tabla-articulos-requisicion-sala tbody');
        if (tbody) {
            tbody.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.which !== 13) {
                    return;
                }
                if (!e.target.classList.contains('codigoarticulo')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                validarSkuLinea(e.target);
            }, true);
        }
    }

    function initConsultasRequisicionSala() {
        if (!esPantallaRequisicionSala()) {
            return;
        }

        moverModalesAlBody();

        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }

        $('#form-general[data-url-npu] .consultadeposito').off('click.consultaDeposito');
        $(document)
            .off('click.consultaArtBtn', '#tabla-articulos-requisicion-sala .consultaarticulo')
            .off('click.requisicionSalaDeposito', '.tm-deposito-campo .consultadeposito')
            .off('click.requisicionSalaArticulo', '#tabla-articulos-requisicion-sala .consultaarticulo');

        $(document)
            .on('click.requisicionSalaDeposito', '.tm-deposito-campo .consultadeposito', function (e) {
                e.preventDefault();
                abrirModalDepositoDesdeCampo($(this));
            })
            .on('click.requisicionSalaArticulo', '#tabla-articulos-requisicion-sala .consultaarticulo', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                abrirModalArticuloDesdeFila($(this));
            });

        window.onArticuloSeleccionado = function (dataArticulo, ctx) {
            if (!ctx || !ctx.row) {
                return;
            }
            var $tr = $(ctx.row);
            if (!$tr.closest('#tabla-articulos-requisicion-sala').length) {
                return;
            }
            if (typeof actualizarLinkEditarArticulo === 'function') {
                actualizarLinkEditarArticulo($tr, dataArticulo.id);
            }
            consultarNpuLinea($tr);
        };

        registrarAtajosRequisicionSala();
    }

    $(initConsultasRequisicionSala);
}(jQuery));
