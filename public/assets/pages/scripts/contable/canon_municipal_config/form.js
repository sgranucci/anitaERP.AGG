(function ($) {
    'use strict';

    function listaSector() {
        return (window.CANON_CONFIG && window.CANON_CONFIG.sectores) || [];
    }

    function listaForma() {
        return (window.CANON_CONFIG && window.CANON_CONFIG.formasPago) || [];
    }

    function resolverPorCodigo(lista, codigo) {
        var c = String(codigo || '').trim();
        if (!c) {
            return null;
        }
        for (var i = 0; i < lista.length; i++) {
            if (String(lista[i].codigo) === c) {
                return lista[i];
            }
        }
        return null;
    }

    function aplicarSector($campo, item) {
        $campo.find('.sector_solicitudpago_id').val(item ? item.id : '');
        $campo.find('.codigo-sector-sp').val(item ? item.codigo : '');
        $campo.find('.nombre-sector-sp').val(item ? item.nombre : '');
    }

    function aplicarForma($campo, item) {
        $campo.find('.formapagosol_id').val(item ? item.id : '');
        $campo.find('.codigo-formapagosol').val(item ? item.codigo : '');
        $campo.find('.nombre-formapagosol').val(item ? item.nombre : '');
    }

    function abrirModalLista(titulo, filas, onElegir) {
        var $modal = $('#modal-canon-config-lista');
        if (!$modal.length) {
            return;
        }
        $modal.find('.modal-title').text(titulo);
        var $tbody = $modal.find('tbody').empty();
        filas.forEach(function (row) {
            var $tr = $('<tr>');
            $tr.append($('<td>').text(row.codigo));
            $tr.append($('<td>').text(row.nombre));
            var $btn = $('<button type="button" class="btn btn-warning btn-sm">Elegir</button>');
            $btn.on('click', function () {
                onElegir(row);
                $modal.modal('hide');
            });
            $tr.append($('<td>').append($btn));
            $tbody.append($tr);
        });
        $modal.modal('show');
    }

    $(function () {
        $(document).on('blur', '.codigo-sector-sp', function () {
            var $campo = $(this).closest('.tm-sector-sp-campo');
            var item = resolverPorCodigo(listaSector(), $(this).val());
            if ($(this).val() && !item) {
                $campo.find('.sector_solicitudpago_id').val('');
                $campo.find('.nombre-sector-sp').val('');
                return;
            }
            if (item) {
                aplicarSector($campo, item);
            }
        });

        $(document).on('blur', '.codigo-formapagosol', function () {
            var $campo = $(this).closest('.tm-formapagosol-campo');
            var item = resolverPorCodigo(listaForma(), $(this).val());
            if ($(this).val() && !item) {
                $campo.find('.formapagosol_id').val('');
                $campo.find('.nombre-formapagosol').val('');
                return;
            }
            if (item) {
                aplicarForma($campo, item);
            }
        });

        $(document).on('click', '.consulta-sector-sp', function () {
            var $campo = $(this).closest('.tm-sector-sp-campo');
            abrirModalLista('Sectores', listaSector(), function (row) {
                aplicarSector($campo, row);
            });
        });

        $(document).on('click', '.consulta-formapagosol', function () {
            var $campo = $(this).closest('.tm-formapagosol-campo');
            abrirModalLista('Formas de pago', listaForma(), function (row) {
                aplicarForma($campo, row);
            });
        });

        $(document).on('keydown', '.codigo-sector-sp, .codigo-formapagosol', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                $(this).closest('.form-group').find('.btn-accion-tabla').first().trigger('click');
            }
        });

        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }
        if (typeof activa_eventos_consultaconcepto_solicitudpago === 'function') {
            activa_eventos_consultaconcepto_solicitudpago();
        }
        if (typeof activa_eventos_consultamoneda === 'function') {
            activa_eventos_consultamoneda();
        }
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consultacuentacontable === 'function') {
            activa_eventos_consultacuentacontable();
        }
    });
})(jQuery);
