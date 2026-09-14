$(function () {
    if (typeof window.carpetaBase === 'undefined') {
        var __locCb = window.location.pathname || '';
        var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
        window.carpetaBase = __mCb ? __mCb[1] : '';
    }

    var estado = {
        articuloId: 0,
        combinacionId: 0,
        data: null
    };

    function mostrarError(msg) {
        $('#modalKardexCombinacionError').removeClass('d-none').text(msg || 'Error.');
    }

    function limpiarError() {
        $('#modalKardexCombinacionError').addClass('d-none').text('');
    }

    function formatearSaldoClass(saldo) {
        if (Math.abs(saldo) < 0.000001) return 'is-zero';
        if (saldo < 0) return 'is-neg';
        return '';
    }

    function renderCombinaciones(combinaciones) {
        var $list = $('#modalKardexCombinacionList').empty();
        $('#modalKardexCombinacionCount').text(combinaciones.length);

        if (!combinaciones.length) {
            $list.append(
                $('<div class="kx-comb-card is-active">').append(
                    $('<div class="font-weight-bold">').text('Sin combinaciones'),
                    $('<div class="small text-muted">').text('Stock agregado del artículo')
                ).attr('data-combinacion-id', '0')
            );
            estado.combinacionId = 0;
            return;
        }

        combinaciones.forEach(function (c, idx) {
            var $card = $('<div class="kx-comb-card">')
                .attr('data-combinacion-id', c.id)
                .toggleClass('is-active', idx === 0)
                .append(
                    $('<div class="font-weight-bold">').text(c.codigo || ('#' + c.id)),
                    $('<div class="small text-muted">').text(c.nombre || '')
                );
            $list.append($card);
        });
        estado.combinacionId = combinaciones[0].id;
    }

    function filasDepositoActual() {
        if (!estado.data) return [];
        if (estado.combinacionId > 0) {
            return (estado.data.saldos_por_combinacion && estado.data.saldos_por_combinacion[String(estado.combinacionId)]) || [];
        }
        return estado.data.saldo_sin_combinacion || estado.data.depositos || [];
    }

    function renderDepositos() {
        var filas = filasDepositoActual();
        var $grid = $('#modalKardexCombinacionDeps').empty();
        var $empty = $('#modalKardexCombinacionDepsEmpty');

        var etiqueta = 'Stock por depósito';
        if (estado.combinacionId > 0 && estado.data) {
            var comb = (estado.data.combinaciones || []).find(function (c) {
                return parseInt(c.id, 10) === estado.combinacionId;
            });
            if (comb) {
                etiqueta = (comb.codigo || '') + ' — ' + (comb.nombre || '');
            }
        } else {
            etiqueta = 'Artículo (sin filtro de combinación)';
        }
        $('#modalKardexCombinacionSeleccion').text(etiqueta);

        if (!filas.length) {
            $empty.removeClass('d-none');
            return;
        }
        $empty.addClass('d-none');

        filas.forEach(function (f) {
            var saldo = parseFloat(f.saldo) || 0;
            var $card = $('<button type="button" class="kx-dep-card text-left">')
                .attr('data-deposito-id', f.deposito_id)
                .append(
                    $('<div class="small text-muted mb-1">').text(
                        (f.codigo || '') + (f.empresa_nombre && estado.data.mostrar_empresa ? ' · ' + f.empresa_nombre : '')
                    ),
                    $('<div class="font-weight-bold mb-2" style="font-size:.9rem;line-height:1.2">').text(f.nombre || ''),
                    $('<div class="kx-dep-saldo">').addClass(formatearSaldoClass(saldo)).text(f.saldo_fmt || '0'),
                    $('<div class="small text-primary mt-1">').html('<i class="fa fa-external-link-alt"></i> Abrir kardex')
                );
            $grid.append($card);
        });
    }

    function urlKardex(depositoId) {
        var params = {
            articulo_id: estado.articuloId,
            deposito_id: depositoId || 0,
            vista: 'consulta',
            volver: window.location.pathname + window.location.search
        };
        if (estado.combinacionId > 0) {
            params.combinacion_id = estado.combinacionId;
        }
        return carpetaBase + '/stock/recuento/movimientos-articulo?' + $.param(params);
    }

    function abrirKardex(depositoId) {
        var url = urlKardex(depositoId);
        $('#modalKardexCombinacion').modal('hide');
        window.open(url, '_blank', 'noopener');
    }

    function cargar(articuloId) {
        limpiarError();
        $('#modalKardexCombinacionLoading').removeClass('d-none');
        $('#modalKardexCombinacionPanel').addClass('d-none');

        $.getJSON(carpetaBase + '/stock/articulo/api/kardex-combinacion', { articulo_id: articuloId })
            .done(function (data) {
                estado.data = data;
                $('#modalKardexCombinacionSku').text(data.articulo.sku || '');
                $('#modalKardexCombinacionDesc').text(data.articulo.descripcion || '');
                $('#modalKardexCombinacionTotal').text(data.total_fmt || '0');
                renderCombinaciones(data.combinaciones || []);
                renderDepositos();
                $('#modalKardexCombinacionPanel').removeClass('d-none');
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar el explorador de kardex.';
                mostrarError(msg);
            })
            .always(function () {
                $('#modalKardexCombinacionLoading').addClass('d-none');
            });
    }

    $(document).on('click', '.btn-kardex-combinacion-ferli', function () {
        estado.articuloId = parseInt($(this).data('articulo-id'), 10) || 0;
        estado.combinacionId = 0;
        estado.data = null;
        $('#modalKardexCombinacion').modal({ focus: false });
        $('#modalKardexCombinacion').modal('show');
        cargar(estado.articuloId);
    });

    $(document).on('click', '#modalKardexCombinacionList .kx-comb-card', function () {
        $('#modalKardexCombinacionList .kx-comb-card').removeClass('is-active');
        $(this).addClass('is-active');
        estado.combinacionId = parseInt($(this).data('combinacion-id'), 10) || 0;
        renderDepositos();
    });

    $(document).on('click', '#modalKardexCombinacionDeps .kx-dep-card', function () {
        var depId = parseInt($(this).data('deposito-id'), 10) || 0;
        abrirKardex(depId);
    });

    $('#modalKardexCombinacionTodos').on('click', function () {
        abrirKardex(0);
    });

    $('#modalKardexCombinacion').on('hidden.bs.modal', function () {
        estado.articuloId = 0;
        estado.combinacionId = 0;
        estado.data = null;
        limpiarError();
    });
});
