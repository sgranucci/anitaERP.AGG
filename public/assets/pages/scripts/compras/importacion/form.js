(function ($) {
    'use strict';

    function carpeta() {
        return (typeof carpetaBase !== 'undefined' && carpetaBase) ? carpetaBase : '';
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('input[name="_token"]').first().val()
            || '';
    }

    function empresaId() {
        return $('#empresa_id').val() || $('select[name="empresa_id"]').val() || '';
    }

    function filaEmbarque() {
        return (
            '<tr>' +
            '<td><input type="hidden" name="embarques[{i}][id]" value="">' +
            '<select name="embarques[{i}][tipo_documento]" class="form-control form-control-sm">' +
            '<option value="BL">BL</option><option value="AWB">AWB</option><option value="CRT">CRT</option><option value="OTRO">Otro</option>' +
            '</select></td>' +
            '<td><input name="embarques[{i}][numero_documento]" class="form-control form-control-sm"></td>' +
            '<td><input name="embarques[{i}][contenedor]" class="form-control form-control-sm"></td>' +
            '<td><input name="embarques[{i}][buque_vuelo]" class="form-control form-control-sm"></td>' +
            '<td><input name="embarques[{i}][puerto_origen]" class="form-control form-control-sm"></td>' +
            '<td><input name="embarques[{i}][puerto_destino]" class="form-control form-control-sm"></td>' +
            '<td><input type="date" name="embarques[{i}][fecha_embarque]" class="form-control form-control-sm"></td>' +
            '<td><input type="date" name="embarques[{i}][fecha_arribo]" class="form-control form-control-sm"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-fila"><i class="fa fa-times-circle"></i></button></td>' +
            '</tr>'
        );
    }

    function filaGasto() {
        return (
            '<tr class="js-gasto-fila">' +
            '<td><input type="hidden" name="gastos[{i}][id]" value="">' +
            '<input type="hidden" name="gastos[{i}][importacion_gasto_tipo_id]" class="js-gasto-tipo-id" value="">' +
            '<div class="d-flex align-items-center" style="gap:2px;">' +
            '<button type="button" class="btn-accion-tabla js-consulta-gasto-tipo" title="Tipo de gasto (F1)"><i class="fa fa-search text-primary"></i></button>' +
            '<input type="text" class="form-control form-control-sm js-gasto-tipo-codigo" style="width:4.5rem;" placeholder="Cód." autocomplete="off">' +
            '<input type="text" class="form-control form-control-sm js-gasto-tipo-nombre" readonly placeholder="Tipo">' +
            '</div></td>' +
            '<td><select name="gastos[{i}][naturaleza]" class="form-control form-control-sm"><option value="estimado">Estimado</option><option value="real">Real</option></select></td>' +
            '<td><input type="number" step="0.01" name="gastos[{i}][importe]" class="form-control form-control-sm"></td>' +
            '<td><input type="number" step="0.0001" name="gastos[{i}][cotizacion]" class="form-control form-control-sm"></td>' +
            '<td><input type="date" name="gastos[{i}][fecha]" class="form-control form-control-sm"></td>' +
            '<td><input type="hidden" name="gastos[{i}][comprobante_proveedor_id]" class="js-gasto-cp-id" value="">' +
            '<div class="d-flex align-items-center" style="gap:2px;">' +
            '<button type="button" class="btn-accion-tabla js-consulta-gasto-cp" title="Factura proveedor (F1)"><i class="fa fa-search text-primary"></i></button>' +
            '<input type="text" class="form-control form-control-sm js-gasto-cp-codigo" placeholder="Nº / ID" autocomplete="off">' +
            '</div></td>' +
            '<td><input name="gastos[{i}][referencia]" class="form-control form-control-sm"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-fila"><i class="fa fa-times-circle"></i></button></td>' +
            '</tr>'
        );
    }

    var $gastoFilaActiva = $();

    function aplicarGastoTipo($tr, item) {
        if (!$tr.length || !item) return;
        $tr.find('.js-gasto-tipo-id').val(item.id || '');
        $tr.find('.js-gasto-tipo-codigo').val(item.codigo || '');
        $tr.find('.js-gasto-tipo-nombre').val(item.nombre || '');
    }

    function aplicarGastoCp($tr, item) {
        if (!$tr.length) return;
        if (!item || !item.id) {
            $tr.find('.js-gasto-cp-id').val('');
            $tr.find('.js-gasto-cp-codigo').val('');
            return;
        }
        $tr.find('.js-gasto-cp-id').val(item.id);
        $tr.find('.js-gasto-cp-codigo').val(item.codigo || '');
        // Factura = gasto real: precarga importe/fecha/cotiz. y marca naturaleza Real.
        $tr.find('select[name*="[naturaleza]"]').val('real');
        if (item.importe !== null && item.importe !== undefined && item.importe !== '') {
            $tr.find('input[name*="[importe]"]').val(item.importe);
        }
        if (item.fecha) {
            $tr.find('input[name*="[fecha]"]').val(item.fecha);
        }
        if (item.cotizacion !== null && item.cotizacion !== undefined && item.cotizacion !== '') {
            $tr.find('input[name*="[cotizacion]"]').val(item.cotizacion);
        }
    }

    function buscarGastoTipo(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion-gasto-tipo/consulta',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-gasto-tipo').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-gasto-tipo').html('<tr><td colspan="4">Error al consultar tipos</td></tr>');
            });
    }

    function resolverGastoTipo($tr) {
        var codigo = String($tr.find('.js-gasto-tipo-codigo').val() || '').trim();
        if (!codigo) {
            aplicarGastoTipo($tr, { id: '', codigo: '', nombre: '' });
            return;
        }
        $.ajax({
            url: carpeta() + '/compras/importacion-gasto-tipo/resolver',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { codigo: codigo, _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    aplicarGastoTipo($tr, r.item);
                } else {
                    aplicarGastoTipo($tr, { id: '', codigo: codigo, nombre: '' });
                }
            })
            .fail(function () {
                aplicarGastoTipo($tr, { id: '', codigo: codigo, nombre: '' });
            });
    }

    function buscarCp(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion/consulta-comprobante-proveedor',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-cp').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-cp').html('<tr><td colspan="5">Error al consultar comprobantes</td></tr>');
            });
    }

    function resolverCp($tr) {
        var codigo = String($tr.find('.js-gasto-cp-codigo').val() || '').trim();
        if (!codigo) {
            aplicarGastoCp($tr, null);
            return;
        }
        $.ajax({
            url: carpeta() + '/compras/importacion/resolver-comprobante-proveedor',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { valor: codigo, empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    aplicarGastoCp($tr, r.item);
                } else {
                    aplicarGastoCp($tr, null);
                    $tr.find('.js-gasto-cp-codigo').val(codigo);
                }
            })
            .fail(function () {
                aplicarGastoCp($tr, null);
                $tr.find('.js-gasto-cp-codigo').val(codigo);
            });
    }

    function buscarCom(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion/consulta-recepcion',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-com').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-com').html('<tr><td colspan="6">Error al consultar COM</td></tr>');
            });
    }

    function aplicarComNacionalizar(item) {
        if (!item || !item.id) {
            $('#nacionalizar_recepcion_id').val('');
            $('#nacionalizar_recepcion_numero').val('');
            $('#nacionalizar_recepcion_desc').val('');
            return;
        }
        $('#nacionalizar_recepcion_id').val(item.id);
        $('#nacionalizar_recepcion_numero').val(item.numero || '');
        $('#nacionalizar_recepcion_desc').val(item.desc || '');
    }

    function resolverComNacionalizar() {
        if ($('#consultaImportacionComModal').hasClass('show')) {
            return;
        }
        var numero = String($('#nacionalizar_recepcion_numero').val() || '').trim();
        if (!numero) {
            aplicarComNacionalizar(null);
            return;
        }
        $.ajax({
            url: carpeta() + '/compras/importacion/resolver-recepcion',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { valor: numero, empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    aplicarComNacionalizar(r.item);
                } else {
                    aplicarComNacionalizar(null);
                    $('#nacionalizar_recepcion_numero').val(numero);
                }
            })
            .fail(function () {
                aplicarComNacionalizar(null);
                $('#nacionalizar_recepcion_numero').val(numero);
            });
    }

    function reindex(tbody, namePrefix) {
        tbody.find('tr').each(function (idx) {
            $(this).find('[name]').each(function () {
                var n = $(this).attr('name');
                if (!n) return;
                $(this).attr('name', n.replace(new RegExp(namePrefix + '\\[\\d+\\]'), namePrefix + '[' + idx + ']'));
            });
        });
    }

    function agregarOc(item) {
        if (!item || !item.id) return;
        var id = String(item.id);
        if ($('#tbody-oc-importacion tr[data-oc-id="' + id + '"]').length) {
            return;
        }
        $('.js-oc-orphan').remove();
        var tr =
            '<tr data-oc-id="' + id + '">' +
            '<td><input type="hidden" name="ordencompra_ids[]" value="' + id + '">' + id + '</td>' +
            '<td>' + $('<div>').text(item.numero || '').html() + '</td>' +
            '<td>' + $('<div>').text(item.fecha || '').html() + '</td>' +
            '<td>' + $('<div>').text(item.proveedor || '').html() + '</td>' +
            '<td>' + $('<div>').text(item.estado || '').html() + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-oc"><i class="fa fa-times-circle"></i></button></td>' +
            '</tr>';
        $('#tbody-oc-importacion').append(tr);
    }

    function agregarLote(item) {
        if (!item || !item.id) return;
        var id = String(item.id);
        if ($('#tbody-lote-importacion tr[data-lote-id="' + id + '"]').length) {
            return;
        }
        $('.js-lote-orphan').remove();
        var tr =
            '<tr data-lote-id="' + id + '">' +
            '<td><input type="hidden" name="lote_ids[]" value="' + id + '">' + id + '</td>' +
            '<td>' + $('<div>').text(item.despacho || '').html() + '</td>' +
            '<td>' + $('<div>').text(item.ingreso || '').html() + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-lote"><i class="fa fa-times-circle"></i></button></td>' +
            '</tr>';
        $('#tbody-lote-importacion').append(tr);
    }

    function buscarOc(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion/consulta-ordencompra',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-oc').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-oc').html('<tr><td colspan="5">Error al consultar OC</td></tr>');
            });
    }

    function buscarLote(consulta) {
        $.ajax({
            url: carpeta() + '/compras/importacion/consulta-lote',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { consulta: consulta || '', _token: csrf() },
        })
            .done(function (r) {
                $('#datos-importacion-lote').html((r && r.data) || '');
            })
            .fail(function () {
                $('#datos-importacion-lote').html('<tr><td colspan="4">Error al consultar lotes</td></tr>');
            });
    }

    function resolverOcPorCodigo() {
        var codigo = String($('#importacion_oc_codigo').val() || '').trim();
        if (!codigo) return;
        $.ajax({
            url: carpeta() + '/compras/importacion/resolver-ordencompra',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { valor: codigo, empresa_id: empresaId(), _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    agregarOc(r.item);
                    $('#importacion_oc_codigo').val('');
                }
            });
    }

    function resolverLotePorCodigo() {
        var codigo = String($('#importacion_lote_codigo').val() || '').trim();
        if (!codigo) return;
        $.ajax({
            url: carpeta() + '/compras/importacion/resolver-lote',
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrf() },
            data: { valor: codigo, _token: csrf() },
        })
            .done(function (r) {
                if (r && r.ok && r.item) {
                    agregarLote(r.item);
                    $('#importacion_lote_codigo').val('');
                }
            });
    }

    $(function () {
        var idxEmb = $('#tbody-embarques tr').length;
        var idxGas = $('#tbody-gastos tr').length;

        $('#btn-agregar-embarque').on('click', function () {
            var html = filaEmbarque().replace(/\{i\}/g, String(idxEmb++));
            $('#tbody-embarques').append(html);
            reindex($('#tbody-embarques'), 'embarques');
        });

        $('#btn-agregar-gasto').on('click', function () {
            var html = filaGasto().replace(/\{i\}/g, String(idxGas++));
            $('#tbody-gastos').append(html);
            reindex($('#tbody-gastos'), 'gastos');
        });

        $(document).on('click', '.js-quitar-fila', function () {
            var tbody = $(this).closest('tbody');
            var prefix = tbody.attr('id') === 'tbody-embarques' ? 'embarques' : 'gastos';
            $(this).closest('tr').remove();
            reindex(tbody, prefix);
        });

        $('#btn-agregar-archivo').on('click', function () {
            var tpl = document.getElementById('importacion-template-renglon-archivo');
            if (!tpl) return;
            var node = tpl.content.cloneNode(true);
            $('#tbody-archivos-nuevos').append(node);
        });

        $(document).on('click', '.js-quitar-archivo-nuevo', function () {
            var rows = $('#tbody-archivos-nuevos tr');
            if (rows.length <= 1) {
                rows.find('input[type=file]').val('');
                return;
            }
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.js-quitar-archivo-existente', function () {
            var id = $(this).data('id');
            if (id) {
                $('<input type="hidden" name="archivos_quitar[]">').val(id).appendTo('#form-importacion');
            }
            $(this).closest('.js-archivo-card').remove();
        });

        $('#btn-consulta-oc-importacion').on('click', function () {
            $('#consultaImportacionOcModal').modal('show');
            buscarOc('');
            setTimeout(function () { $('#consulta-importacion-oc').focus(); }, 300);
        });

        $('#consulta-importacion-oc').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-oc .eligeconsulta-importacion-oc').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarOc($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-oc', function () {
            var $tr = $(this).closest('tr');
            agregarOc({
                id: $tr.data('id'),
                numero: $tr.data('numero'),
                fecha: $tr.data('fecha'),
                estado: $tr.data('estado'),
                proveedor: $tr.data('proveedor'),
            });
            $('#consultaImportacionOcModal').modal('hide');
        });

        $('#importacion_oc_codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverOcPorCodigo();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                $('#btn-consulta-oc-importacion').trigger('click');
            }
        });

        $(document).on('click', '.js-quitar-oc', function () {
            $(this).closest('tr').remove();
        });

        $('#btn-consulta-lote-importacion').on('click', function () {
            $('#consultaImportacionLoteModal').modal('show');
            buscarLote('');
            setTimeout(function () { $('#consulta-importacion-lote').focus(); }, 300);
        });

        $('#consulta-importacion-lote').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-lote .eligeconsulta-importacion-lote').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarLote($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-lote', function () {
            var $tr = $(this).closest('tr');
            agregarLote({
                id: $tr.data('id'),
                despacho: $tr.data('despacho'),
                ingreso: $tr.data('ingreso'),
            });
            $('#consultaImportacionLoteModal').modal('hide');
        });

        $('#importacion_lote_codigo').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverLotePorCodigo();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                $('#btn-consulta-lote-importacion').trigger('click');
            }
        });

        $(document).on('click', '.js-quitar-lote', function () {
            $(this).closest('tr').remove();
        });

        // Tipo de gasto (modal)
        $(document).on('click', '.js-consulta-gasto-tipo', function () {
            $gastoFilaActiva = $(this).closest('tr');
            $('#consultaImportacionGastoTipoModal').modal('show');
            buscarGastoTipo('');
            setTimeout(function () { $('#consulta-importacion-gasto-tipo').focus(); }, 300);
        });

        $('#consulta-importacion-gasto-tipo').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-gasto-tipo .eligeconsulta-importacion-gasto-tipo').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarGastoTipo($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-gasto-tipo', function () {
            var $tr = $(this).closest('tr');
            aplicarGastoTipo($gastoFilaActiva, {
                id: $tr.data('id'),
                codigo: $tr.data('codigo'),
                nombre: $tr.data('nombre'),
            });
            $('#consultaImportacionGastoTipoModal').modal('hide');
        });

        $(document).on('keydown', '.js-gasto-tipo-codigo', function (e) {
            var $tr = $(this).closest('tr');
            if (e.key === 'F1') {
                e.preventDefault();
                $tr.find('.js-consulta-gasto-tipo').trigger('click');
            }
        });

        $(document).on('blur', '.js-gasto-tipo-codigo', function () {
            if ($('#consultaImportacionGastoTipoModal').hasClass('show')) {
                return;
            }
            resolverGastoTipo($(this).closest('tr'));
        });

        // Comprobante proveedor (modal)
        $(document).on('click', '.js-consulta-gasto-cp', function () {
            $gastoFilaActiva = $(this).closest('tr');
            $('#consultaImportacionCpModal').modal('show');
            buscarCp('');
            setTimeout(function () { $('#consulta-importacion-cp').focus(); }, 300);
        });

        $('#consulta-importacion-cp').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-cp .eligeconsulta-importacion-cp').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarCp($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-cp', function () {
            var $tr = $(this).closest('tr');
            aplicarGastoCp($gastoFilaActiva, {
                id: $tr.data('id'),
                codigo: $tr.data('codigo'),
                fecha: $tr.attr('data-fecha-ymd') || $tr.data('fechaYmd') || '',
                importe: $tr.attr('data-importe') || '',
                cotizacion: $tr.attr('data-cotizacion') || '',
            });
            $('#consultaImportacionCpModal').modal('hide');
        });

        $(document).on('keydown', '.js-gasto-cp-codigo', function (e) {
            var $tr = $(this).closest('tr');
            if (e.key === 'F1') {
                e.preventDefault();
                $tr.find('.js-consulta-gasto-cp').trigger('click');
            }
        });

        $(document).on('blur', '.js-gasto-cp-codigo', function () {
            if ($('#consultaImportacionCpModal').hasClass('show')) {
                return;
            }
            resolverCp($(this).closest('tr'));
        });

        // COM nacionalizar
        $('#btn-consulta-com-nacionalizar').on('click', function () {
            $('#consultaImportacionComModal').modal('show');
            buscarCom('');
            setTimeout(function () { $('#consulta-importacion-com').focus(); }, 300);
        });

        $('#consulta-importacion-com').on('keyup', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $first = $('#datos-importacion-com .eligeconsulta-importacion-com').first();
                if ($first.length) {
                    $first.trigger('click');
                }
                return;
            }
            buscarCom($(this).val());
        });

        $(document).on('click', '.eligeconsulta-importacion-com', function () {
            var $tr = $(this).closest('tr');
            aplicarComNacionalizar({
                id: $tr.data('id'),
                numero: $tr.data('numero'),
                desc: $tr.data('desc'),
            });
            $('#consultaImportacionComModal').modal('hide');
        });

        $('#nacionalizar_recepcion_numero').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                resolverComNacionalizar();
            }
            if (e.key === 'F1') {
                e.preventDefault();
                $('#btn-consulta-com-nacionalizar').trigger('click');
            }
        }).on('blur', function () {
            resolverComNacionalizar();
        });

        if (typeof window.activa_eventos_consultapais === 'function') {
            window.activa_eventos_consultapais();
        }
    });
})(jQuery);
