(function () {
    'use strict';

    function carpetaBase() {
        return typeof window.carpetaBase !== 'undefined' ? window.carpetaBase : '';
    }

    function enFormularioRecuento() {
        return !!document.getElementById('form-recuento');
    }

    function enFormularioArticulo() {
        return !!document.getElementById('form-general') && !!document.getElementById('articulo_id');
    }

    function enPaginaMovimientosArticulo() {
        return !!document.getElementById('filtro-deposito-movimientos-articulo');
    }

    function urlIndexMovimientosArticulo() {
        var el = document.getElementById('recuento-movimientos-articulo-url');
        return el ? el.value : (carpetaBase() + '/stock/recuento/movimientos-articulo');
    }

    function notificar(titulo, mensaje, tipo) {
        if (typeof Biblioteca !== 'undefined' && Biblioteca.notificaciones) {
            Biblioteca.notificaciones(mensaje, titulo, tipo || 'warning');
        } else {
            alert(mensaje);
        }
    }

    function abrirConsultaMovimientosRecuento(articuloId) {
        if (!enFormularioRecuento()) {
            return;
        }

        articuloId = parseInt(articuloId, 10) || 0;
        var el = document.getElementById('recuento_deposito_id')
            || document.querySelector('#form-recuento .deposito_id');
        var depId = el ? parseInt(el.value, 10) || 0 : 0;

        if (!depId) {
            notificar('Recuento', 'Valide el depósito del recuento antes de consultar movimientos.');
            return;
        }
        if (articuloId <= 0) {
            notificar('Recuento', 'Seleccione un artículo válido.');
            return;
        }

        var params = new URLSearchParams({
            articulo_id: String(articuloId),
            deposito_id: String(depId),
            volver: window.location.pathname + window.location.search,
            vista: 'consulta',
        });
        window.open(urlIndexMovimientosArticulo() + '?' + params.toString(), '_blank', 'noopener');
    }

    function resolverDepositoDefaultArticulo(depositoIdAttr) {
        var depId = parseInt(depositoIdAttr, 10) || 0;

        if (enFormularioArticulo()) {
            var $sel = $('#depositoentrega_id');
            if ($sel.length) {
                var desdeForm = parseInt($sel.val(), 10) || 0;
                if (desdeForm > 0) {
                    return desdeForm;
                }
            }
        }

        return depId;
    }

    function abrirMovimientosDesdeArticulo(articuloId, depositoIdAttr) {
        articuloId = parseInt(articuloId, 10) || 0;
        if (articuloId <= 0) {
            var hiddenArt = document.getElementById('articulo_id');
            articuloId = hiddenArt ? parseInt(hiddenArt.value, 10) || 0 : 0;
        }

        if (articuloId <= 0) {
            notificar('Movimientos de stock', 'Seleccione un artículo válido.');
            return;
        }

        var depositoId = resolverDepositoDefaultArticulo(depositoIdAttr);
        var volverUrl = window.location.pathname + window.location.search;

        var params = new URLSearchParams({
            articulo_id: String(articuloId),
            deposito_id: depositoId > 0 ? String(depositoId) : '0',
            vista: 'consulta',
            volver: volverUrl,
        });

        window.open(urlIndexMovimientosArticulo() + '?' + params.toString(), '_blank', 'noopener');
    }

    function recargarPaginaMovimientos(depositoId) {
        var params = new URLSearchParams(window.location.search);
        var articuloId = document.getElementById('movimientos-articulo-id');
        if (articuloId && articuloId.value) {
            params.set('articulo_id', articuloId.value);
        }

        depositoId = parseInt(depositoId, 10) || 0;
        params.set('deposito_id', depositoId > 0 ? String(depositoId) : '0');
        window.location.href = urlIndexMovimientosArticulo() + '?' + params.toString();
    }

    function datosDesdeFilaRecuento(tr) {
        if (!tr) {
            return null;
        }
        return {
            articuloId: parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0,
            sku: ((tr.querySelector('.codigoarticulo') || {}).value || '').trim(),
            descripcion: ((tr.querySelector('.descripcionarticulo') || {}).value || '').trim(),
        };
    }

    function actualizarBotonesMovimientosFila(tr) {
        if (!tr || !enFormularioRecuento()) {
            return;
        }
        var btn = tr.querySelector('.btn-movimientos-articulo-deposito');
        if (!btn) {
            return;
        }
        var articuloId = parseInt((tr.querySelector('.articulo_id') || {}).value, 10) || 0;
        btn.disabled = articuloId <= 0;
        btn.classList.toggle('d-none', articuloId <= 0);
    }

    function toggleBotonesModalConsulta() {
        if (!enFormularioRecuento()) {
            $('#consultaarticuloModal .btn-movimientos-articulo-deposito').addClass('d-none');
            return;
        }
        $('#consultaarticuloModal .btn-movimientos-articulo-deposito').removeClass('d-none');
    }

    window.abrirMovimientosArticuloDepositoRecuento = abrirConsultaMovimientosRecuento;
    window.actualizarBotonMovimientosRecuentoFila = actualizarBotonesMovimientosFila;

    var onDepositoAplicadoAnterior = window.onDepositoAplicadoEnFormulario;
    window.onDepositoAplicadoEnFormulario = function (data, $ctx) {
        if ($ctx && $ctx.length) {
            if ($ctx.closest('#filtro-deposito-movimientos-articulo').length) {
                recargarPaginaMovimientos(data.id);
                return;
            }

        }

        if (typeof onDepositoAplicadoAnterior === 'function') {
            onDepositoAplicadoAnterior(data, $ctx);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultadeposito === 'function') {
            activa_eventos_consultadeposito();
        }

        if (enPaginaMovimientosArticulo()) {
            var btnTodos = document.getElementById('btn-movimientos-todos-depositos');
            if (btnTodos) {
                btnTodos.addEventListener('click', function (e) {
                    e.preventDefault();
                    recargarPaginaMovimientos(0);
                });
            }

            return;
        }

        document.addEventListener('click', function (e) {
            var btnArticulo = e.target.closest('.btn-movimientos-stock-articulo');
            if (btnArticulo) {
                e.preventDefault();
                e.stopPropagation();
                abrirMovimientosDesdeArticulo(
                    btnArticulo.getAttribute('data-articulo-id'),
                    btnArticulo.getAttribute('data-deposito-id') || ''
                );
                return;
            }

            var btnRecuento = e.target.closest('.btn-movimientos-articulo-deposito');
            if (!btnRecuento || !enFormularioRecuento()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var articuloId = parseInt(btnRecuento.getAttribute('data-articulo-id'), 10) || 0;
            if (!articuloId) {
                var tr = btnRecuento.closest('tr.recuento-item-row');
                var datos = datosDesdeFilaRecuento(tr);
                if (datos) {
                    articuloId = datos.articuloId;
                }
            }

            abrirConsultaMovimientosRecuento(articuloId);
        });

        if (enFormularioRecuento()) {
            $('#consultaarticuloModal')
                .on('show.bs.modal.movArtDep', toggleBotonesModalConsulta)
                .on('shown.bs.modal.movArtDep', toggleBotonesModalConsulta);

            document.querySelectorAll('#tbody-recuento-items tr.recuento-item-row').forEach(actualizarBotonesMovimientosFila);
        }
    });
})();
