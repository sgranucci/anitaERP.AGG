(function ($) {
    'use strict';

    function agregarPersona() {
        var tpl = document.getElementById('ingreso-template-persona');
        if (!tpl) {
            return;
        }
        var $item = $(tpl.content.cloneNode(true));
        $('#ingreso-personas #ingreso-agregar-persona').before($item);
    }

    function agregarArchivo() {
        var tpl = document.getElementById('ingreso-template-renglon-archivo');
        if (!tpl) {
            return;
        }
        $('#ingreso-tbody-tabla-archivo').append(tpl.content.cloneNode(true));
    }

    function sincronizarVisitante(limpiarSiVisitante) {
        var visitante = $('#es_visitante').is(':checked');
        $('.ingreso-campo-proveedor').toggle(!visitante);
        $('.ingreso-campo-contrato').toggle(!visitante);
        $('.ingreso-campo-visitante').toggle(visitante);
        if (visitante && limpiarSiVisitante) {
            $('#proveedor_id').val('');
            $('#codigoproveedor').val('');
            $('#nombreproveedor').val('');
            if (!$('#ordencompra_id').data('locked')) {
                $('#ordencompra_id').val('');
                $('#numero_contrato').val('');
                $('#descripcion_contrato').val('');
            }
        }
    }

    function sincronizarMotivoOtro() {
        var codigo = String($('#motivo_id option:selected').data('codigo') || $('#ingreso-modal-motivo_id option:selected').data('codigo') || '').toUpperCase();
        $('.ingreso-campo-motivo-otro').toggle(codigo === 'OTRO');
    }

    function payloadContrato() {
        return {
            proveedor_id: $('#proveedor_id').val() || '',
            empresa_id: $('#empresa_id').val() || '',
            _token: $('meta[name="csrf-token"]').attr('content')
        };
    }

    function aplicarContrato(p) {
        if (!p || !p.id) {
            return;
        }
        var proveedorId = p.proveedor_id || p.proveedorId || '';
        $('#ordencompra_id').val(p.id);
        $('#numero_contrato').val(p.numero || '');
        $('#descripcion_contrato').val((p.estado || '') + ' · Contrato');
        if (proveedorId && !$('#proveedor_id').val()) {
            $('#proveedor_id').val(proveedorId);
            $('#codigoproveedor').val(p.proveedor_codigo || p.proveedorCodigo || '');
            $('#nombreproveedor').val(p.proveedor_nombre || p.proveedorNombre || '');
        }
    }

    function resolverContrato(avisar) {
        var numero = $.trim($('#numero_contrato').val() || '');
        if ($('#ordencompra_id').data('locked')) {
            return;
        }
        if (numero === '') {
            $('#ordencompra_id').val('');
            $('#descripcion_contrato').val('');
            return;
        }
        var url = $('#modal-consulta-contrato-ingreso').data('url-resolver');
        if (!url) {
            return;
        }
        $.getJSON(url, $.extend({
            numero: numero
        }, payloadContrato()))
            .done(function (r) {
                if (r && r.ok) {
                    aplicarContrato(r);
                }
            })
            .fail(function () {
                $('#ordencompra_id').val('');
                $('#descripcion_contrato').val('');
                if (avisar) {
                    window.alert('No hay un contrato activo con ese número.');
                }
            });
    }

    function buscarContratos() {
        var $modal = $('#modal-consulta-contrato-ingreso');
        var url = $modal.data('url-consulta');
        if (!url) {
            return;
        }
        $.post(url, $.extend({
            busqueda: $('#busqueda-contrato-ingreso').val() || ''
        }, payloadContrato()))
            .done(function (html) {
                $('#resultado-consulta-contrato-ingreso').html(html);
            });
    }

    $(function () {
        $('#es_visitante').on('change', function () {
            sincronizarVisitante(true);
        });
        sincronizarVisitante(false);
        $('#motivo_id, #ingreso-modal-motivo_id').on('change', sincronizarMotivoOtro);
        sincronizarMotivoOtro();

        $(document).on('keydown', '#numero_contrato', function (e) {
            if (e.key === 'F1') {
                e.preventDefault();
                if ($('#ordencompra_id').data('locked')) {
                    return;
                }
                $('#modal-consulta-contrato-ingreso').modal('show');
                setTimeout(buscarContratos, 150);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverContrato(true);
            }
        });
        $('#numero_contrato').on('blur', function () {
            resolverContrato(false);
        });
        $(document).on('click', '.consultacontrato', function () {
            $('#modal-consulta-contrato-ingreso').modal('show');
            setTimeout(buscarContratos, 150);
        });
        $('#btn-buscar-contrato-ingreso').on('click', buscarContratos);
        $('#busqueda-contrato-ingreso').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarContratos();
            }
        });
        $(document).on('click', '.js-elegir-contrato-ingreso', function () {
            aplicarContrato($(this).data());
            $('#modal-consulta-contrato-ingreso').modal('hide');
        });

        $('#ingreso-agregar-persona').on('click', function () {
            agregarPersona();
        });

        $('#ingreso-agrega-renglon-archivo').on('click', function () {
            agregarArchivo();
        });

        $(document).on('click', '.ingreso-eliminararchivo', function () {
            var $filas = $('#ingreso-tbody-tabla-archivo tr.item-archivo-ingreso');
            if ($filas.length <= 1) {
                $filas.find('input[type=file]').val('');
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.ingreso-quitar-archivo', function () {
            $(this).closest('.ingreso-archivo-item').remove();
        });

        if (typeof window.activa_eventos_consultaproveedor === 'function') {
            window.activa_eventos_consultaproveedor();
        }

        var $modalAlta = $('#modal-alta-rapida-proveedor-ingreso');

        function aplicarProveedorElegido(item) {
            $('#es_visitante').prop('checked', false);
            sincronizarVisitante(false);
            $('#proveedor_id').val(item.id);
            $('#codigoproveedor').val(item.codigo || '');
            $('#nombreproveedor').val(item.nombre || '');
            $modalAlta.modal('hide');
        }

        function usarComoVisitante() {
            var nombre = $.trim($('#alta-rapida-nombre').val() || '');
            $('#es_visitante').prop('checked', true);
            sincronizarVisitante(true);
            if (nombre) {
                $('#visitante_nombre').val(nombre);
            }
            $modalAlta.modal('hide');
        }

        function buscarProveedorRapido() {
            var url = $modalAlta.data('url-buscar');
            if (!url) {
                return;
            }
            var nombre = $.trim($('#alta-rapida-nombre').val() || '');
            var cuit = $.trim($('#alta-rapida-cuit').val() || '');
            if (nombre === '' && cuit === '') {
                $('#alta-rapida-resultados').html('<tr><td colspan="4" class="text-muted">Ingrese nombre o CUIT.</td></tr>');
                $('#alta-rapida-sin-resultado').hide();
                return;
            }
            $('#alta-rapida-resultados').html('<tr><td colspan="4" class="text-muted">Buscando…</td></tr>');
            $.getJSON(url, {
                nombre: nombre,
                cuit: cuit,
                empresa_id: $('#empresa_id').val() || ''
            }).done(function (resp) {
                var items = (resp && resp.items) ? resp.items : [];
                if (!items.length) {
                    $('#alta-rapida-resultados').html('<tr><td colspan="4" class="text-muted">Sin coincidencias.</td></tr>');
                    $('#alta-rapida-sin-resultado').show();
                    return;
                }
                $('#alta-rapida-sin-resultado').hide();
                var html = '';
                items.forEach(function (item) {
                    html += '<tr>'
                        + '<td>' + $('<div>').text(item.codigo || '').html() + '</td>'
                        + '<td>' + $('<div>').text(item.nombre || '').html() + '</td>'
                        + '<td>' + $('<div>').text(item.cuit || '').html() + '</td>'
                        + '<td><button type="button" class="btn btn-sm btn-primary alta-rapida-elegir"'
                        + ' data-id="' + item.id + '"'
                        + ' data-codigo="' + $('<div>').text(item.codigo || '').html() + '"'
                        + ' data-nombre="' + $('<div>').text(item.nombre || '').html() + '">Elegir</button></td>'
                        + '</tr>';
                });
                $('#alta-rapida-resultados').html(html);
            }).fail(function () {
                $('#alta-rapida-resultados').html('<tr><td colspan="4" class="text-danger">No se pudo buscar.</td></tr>');
                $('#alta-rapida-sin-resultado').hide();
            });
        }

        $('#ingreso-alta-rapida-proveedor').on('click', function () {
            if (typeof window.liberarPantallaModalesBloqueados === 'function') {
                window.liberarPantallaModalesBloqueados();
            }
            $('#alta-rapida-nombre').val($('#nombreproveedor').val() || '');
            $('#alta-rapida-cuit').val('');
            $('#alta-rapida-resultados').html('<tr><td colspan="4" class="text-muted">Escriba nombre o CUIT y pulse Buscar.</td></tr>');
            $('#alta-rapida-sin-resultado').hide();
            $modalAlta.modal('show');
            setTimeout(function () {
                $('#alta-rapida-nombre').trigger('focus');
            }, 200);
        });

        $('#btn-buscar-alta-rapida-proveedor').on('click', function () {
            buscarProveedorRapido();
        });

        $('#alta-rapida-nombre, #alta-rapida-cuit').on('keydown', function (e) {
            if (e.key === 'Enter' || e.which === 13) {
                e.preventDefault();
                buscarProveedorRapido();
            }
        });

        $(document).on('click', '.alta-rapida-elegir', function () {
            aplicarProveedorElegido({
                id: $(this).data('id'),
                codigo: $(this).data('codigo'),
                nombre: $(this).data('nombre')
            });
        });

        $('#btn-usar-visitante-ingreso').on('click', function () {
            usarComoVisitante();
        });

        $('#btn-abrir-alta-proveedor-ingreso').on('click', function () {
            var base = $modalAlta.data('url-alta');
            if (!base) {
                return;
            }
            var params = new URLSearchParams();
            var nombre = $.trim($('#alta-rapida-nombre').val() || '');
            var cuit = $.trim($('#alta-rapida-cuit').val() || '');
            if (nombre) {
                params.set('nombre', nombre);
            }
            if (cuit) {
                params.set('nroinscripcion', cuit);
            }
            var sep = base.indexOf('?') >= 0 ? '&' : '?';
            window.open(base + (params.toString() ? sep + params.toString() : ''), '_blank', 'noopener');
        });
    });
})(jQuery);
