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

    function esPantallaRequisicionSalaEditable() {
        if (!esPantallaRequisicionSala()) {
            return false;
        }
        var $cant = $('#tabla-articulos-requisicion-sala tbody .cantidad-linea').first();
        return $cant.length && !$cant.prop('readonly');
    }

    function enfocarInput($inp) {
        if (!$inp || !$inp.length || $inp.prop('readonly') || $inp.prop('disabled')) {
            return;
        }
        setTimeout(function () {
            $inp.trigger('focus');
            if ($inp[0] && typeof $inp[0].select === 'function' && $inp.is('input[type="text"], input[type="number"]')) {
                $inp[0].select();
            }
        }, 0);
    }

    function validarCantidadLinea($input, $row) {
        if (!$input || !$input.length || !$row || !$row.length) {
            return false;
        }
        if ($input.prop('readonly') || $input.prop('disabled')) {
            return true;
        }

        var articuloId = ($row.find('.articulo_id').val() || '').trim();
        if (!articuloId) {
            alert('Indique un artículo válido antes de cargar la cantidad.');
            enfocarInput($row.find('.codigoarticulo').first());
            return false;
        }

        var raw = ($input.val() || '').toString().trim();
        if (raw === '') {
            alert('Indique la cantidad del ítem.');
            enfocarInput($input);
            return false;
        }

        var cant = parseFloat(raw.replace(',', '.'));
        if (Number.isNaN(cant) || cant <= 0) {
            alert('La cantidad debe ser mayor a cero.');
            enfocarInput($input);
            return false;
        }

        $input.val(cant);
        return true;
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
                if (!esPantallaRequisicionSalaEditable()) {
                    return;
                }

                var target = e.target;
                if (!target) {
                    return;
                }

                if (target.classList.contains('codigoarticulo')) {
                    e.preventDefault();
                    e.stopPropagation();
                    validarSkuLinea(target);
                    return;
                }

                if (target.classList.contains('cantidad-linea')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $target = $(target);
                    var $row = $target.closest('tr');
                    if (!validarCantidadLinea($target, $row)) {
                        return;
                    }
                    enfocarInput($row.find('.fueradeservicio-linea').first());
                }
            }, true);
        }

        $(document)
            .off('blur.reqSalaCantidad', '#tabla-articulos-requisicion-sala .cantidad-linea')
            .on('blur.reqSalaCantidad', '#tabla-articulos-requisicion-sala .cantidad-linea', function () {
                if (!esPantallaRequisicionSalaEditable()) {
                    return;
                }
                var $input = $(this);
                if (!$input.val() && !($input.closest('tr').find('.articulo_id').val() || '').trim()) {
                    return;
                }
                if (!validarCantidadLinea($input, $input.closest('tr'))) {
                    return;
                }
            });
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
