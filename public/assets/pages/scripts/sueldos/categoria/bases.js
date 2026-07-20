(function ($) {
    'use strict';

    var $panel = $('#bases-categoria-panel');
    if (!$panel.length) {
        return;
    }

    var csrf = $panel.data('csrf');
    var urlHistorial = $panel.data('url-historial');
    var urlLote = $panel.data('url-lote');
    var urlEliminarBaseTpl = $panel.data('url-eliminar-base');
    var puedeEditar = String($panel.data('puede-editar')) === '1';
    var puedeBorrar = String($panel.data('puede-borrar')) === '1';
    var usaTabla = String($panel.data('usa-tabla')) === '1';
    var hoy = $panel.data('hoy');

    var modalNombrebaseId = null;

    function formatNumero(valor) {
        var n = parseFloat(valor);
        if (isNaN(n)) {
            n = 0;
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    }

    function escapeHtml(texto) {
        return $('<div>').text(texto == null ? '' : texto).html();
    }

    // ---------- Grilla principal (una fila por base, valor vigente + próxima) ----------

    function celdaValor(base) {
        var html = base.tiene_vigente
            ? formatNumero(base.valor)
            : '<span class="text-muted font-italic">sin vigencia hoy</span>';
        if (base.proxima) {
            html += '<div class="small text-primary" title="Versión programada a futuro">'
                + '<i class="fa fa-clock"></i> Próxima: ' + base.proxima.valor_fmt
                + ' <span class="text-muted">desde ' + escapeHtml(base.proxima.fecha_vigencia_fmt) + '</span>';
            if (base.futuras_count > 1) {
                html += ' <span class="badge badge-light">+' + (base.futuras_count - 1) + '</span>';
            }
            html += '</div>';
        }
        return html;
    }

    function renderBases(bases) {
        var $tbody = $('#tbody-bases-vigentes');
        $tbody.empty();
        if (!bases || !bases.length) {
            $tbody.append('<tr id="fila-sin-bases"><td colspan="5" class="text-center text-muted">Sin bases cargadas.</td></tr>');
            $('#badge-cant-bases').text(0);
            return;
        }
        bases.forEach(function (base) {
            var $tr = $('<tr>')
                .attr('data-nombrebase-id', base.nombrebase_id)
                .attr('data-nombrebase-desc', base.nombrebase_descripcion);
            $tr.append('<td>' + base.nombrebase_codigo + '</td>');
            $tr.append('<td>' + escapeHtml(base.nombrebase_descripcion) + '</td>');
            $tr.append('<td class="text-right">' + celdaValor(base) + '</td>');
            $tr.append('<td>' + escapeHtml(base.fecha_vigencia_fmt || '—') + '</td>');
            var acciones = '<button type="button" class="btn-accion-tabla tooltipsC btn-gestionar-vigencias"'
                + ' title="' + (puedeEditar ? 'Gestionar vigencias' : 'Ver vigencias') + '"'
                + ' data-nombrebase-id="' + base.nombrebase_id + '"'
                + ' data-nombrebase-desc="' + escapeHtml(base.nombrebase_descripcion) + '">'
                + '<i class="fa fa-list-ol"></i></button>';
            if (usaTabla && puedeBorrar) {
                acciones += ' <button type="button" class="btn-accion-tabla tooltipsC text-danger btn-eliminar-base-completa"'
                    + ' title="Eliminar base completa (todas las vigencias)"'
                    + ' data-nombrebase-id="' + base.nombrebase_id + '"'
                    + ' data-nombrebase-desc="' + escapeHtml(base.nombrebase_descripcion) + '">'
                    + '<i class="fa fa-trash"></i></button>';
            }
            $tr.append('<td class="text-nowrap">' + acciones + '</td>');
            $tbody.append($tr);
        });
        $('#badge-cant-bases').text(bases.length);
    }

    // ---------- Modal de vigencias (grilla editable por base) ----------

    function estadoBadge(h) {
        if (h.es_vigente) {
            return '<span class="badge badge-success">Vigente</span>';
        }
        if (h.es_futura) {
            return '<span class="badge badge-info">Programada</span>';
        }
        return '<span class="badge badge-light">Histórica</span>';
    }

    function mostrarErrorModal(texto) {
        var $e = $('#vigencias-error');
        if (texto) {
            $e.text(texto).show();
        } else {
            $e.hide().text('');
        }
    }

    function filaExistente(h) {
        var $tr = $('<tr>').attr('data-base-id', h.id);
        if (h.es_vigente) {
            $tr.addClass('table-success');
        }

        var celdaFecha = puedeEditar
            ? '<input type="date" class="form-control form-control-sm vig-fecha" value="' + h.fecha_vigencia + '">'
            : escapeHtml(h.fecha_vigencia_fmt);
        var celdaValor = puedeEditar
            ? '<input type="number" step="0.0001" class="form-control form-control-sm text-right vig-valor" value="' + h.valor + '">'
            : formatNumero(h.valor);

        $tr.append('<td>' + celdaFecha + '</td>');
        $tr.append('<td class="text-right">' + celdaValor + '</td>');
        $tr.append('<td>' + estadoBadge(h) + '</td>');

        var acc = '';
        if (puedeBorrar) {
            acc = '<button type="button" class="btn btn-sm btn-outline-danger btn-marcar-eliminar" title="Marcar para eliminar"><i class="fa fa-trash"></i></button>';
        }
        $tr.append('<td class="text-nowrap">' + acc + '</td>');
        return $tr;
    }

    function filaNueva() {
        var $tr = $('<tr class="fila-nueva bg-light">');
        $tr.append('<td><input type="date" class="form-control form-control-sm vig-fecha" value="' + hoy + '"></td>');
        $tr.append('<td class="text-right"><input type="number" step="0.0001" class="form-control form-control-sm text-right vig-valor" placeholder="0,0000"></td>');
        $tr.append('<td><span class="badge badge-info">Nueva</span></td>');
        $tr.append('<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary btn-quitar-fila" title="Quitar fila"><i class="fa fa-times"></i></button></td>');
        return $tr;
    }

    function renderVigencias(historial) {
        var $tb = $('#tbody-vigencias');
        $tb.empty();

        (historial || []).forEach(function (h) {
            $tb.append(filaExistente(h));
        });

        if (!historial || !historial.length) {
            $tb.append('<tr class="fila-vacia"><td colspan="4" class="text-center text-muted">Sin vigencias. Usá «Agregar fila» para cargar una.</td></tr>');
        }

        var mostrarAcciones = puedeEditar && !!modalNombrebaseId;
        $('#btn-agregar-fila-vigencia').toggle(mostrarAcciones);
        $('#btn-guardar-vigencias').toggle(mostrarAcciones);
    }

    function cargarVigencias(nombrebaseId) {
        mostrarErrorModal('');
        $('#tbody-vigencias').html('<tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr>');
        $.ajax({
            url: urlHistorial,
            method: 'GET',
            data: { nombrebase_id: nombrebaseId }
        }).done(function (resp) {
            renderVigencias(resp ? resp.historial : []);
        }).fail(function () {
            $('#tbody-vigencias').html('<tr><td colspan="4" class="text-center text-danger">Error al cargar las vigencias.</td></tr>');
        });
    }

    function abrirModalVigencias(opts) {
        opts = opts || {};
        mostrarErrorModal('');

        if (opts.nueva) {
            modalNombrebaseId = null;
            $('#vigencias-select-wrap').show();
            $('#vigencias_nombrebase_id').val('');
            $('#vigencias-base-titulo').text('');
            $('#tbody-vigencias').html('<tr><td colspan="4" class="text-center text-muted">Elegí una base para ver o cargar sus vigencias.</td></tr>');
            $('#btn-agregar-fila-vigencia').hide();
            $('#btn-guardar-vigencias').hide();
        } else {
            modalNombrebaseId = opts.nombrebaseId;
            $('#vigencias-select-wrap').hide();
            $('#vigencias-base-titulo').text(opts.desc ? '· ' + opts.desc : '');
            cargarVigencias(opts.nombrebaseId);
        }

        $('#modal-vigencias-base').modal('show');
    }

    function refrescarTodo(resp) {
        if (resp && resp.bases) {
            renderBases(resp.bases);
        }
        renderVigencias(resp ? resp.historial : []);
    }

    // ---------- Disparadores ----------

    $('#btn-nueva-base').on('click', function () {
        abrirModalVigencias({ nueva: true });
    });

    $('#tbody-bases-vigentes').on('click', '.btn-gestionar-vigencias', function () {
        abrirModalVigencias({
            nombrebaseId: $(this).data('nombrebase-id'),
            desc: $(this).data('nombrebase-desc')
        });
    });

    // Eliminar la base completa (todas sus vigencias) desde la grilla principal.
    $('#tbody-bases-vigentes').on('click', '.btn-eliminar-base-completa', function () {
        var nbId = $(this).data('nombrebase-id');
        var desc = $(this).data('nombrebase-desc') || '';
        if (!confirm('¿Eliminar la base «' + desc + '» completa? Se borrarán TODAS sus vigencias (incluido el histórico). Esta acción no se puede deshacer.')) {
            return;
        }

        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: urlEliminarBaseTpl.replace('NBID', nbId),
            method: 'POST',
            data: { _token: csrf, _method: 'DELETE' }
        }).done(function (resp) {
            if (resp && resp.mensaje === 'ok') {
                renderBases(resp.bases);
            } else {
                alert('No se pudo eliminar la base.');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            alert('Error al eliminar la base.');
            $btn.prop('disabled', false);
        });
    });

    $('#vigencias_nombrebase_id').on('change', function () {
        var nbId = $(this).val();
        modalNombrebaseId = nbId || null;
        $('#vigencias-base-titulo').text(nbId ? '· ' + $('option:selected', this).text() : '');
        if (nbId) {
            cargarVigencias(nbId);
        } else {
            $('#tbody-vigencias').html('<tr><td colspan="4" class="text-center text-muted">Elegí una base.</td></tr>');
            $('#btn-agregar-fila-vigencia').hide();
            $('#btn-guardar-vigencias').hide();
        }
    });

    // Agregar una fila nueva editable (no persiste hasta Guardar cambios).
    $('#btn-agregar-fila-vigencia').on('click', function () {
        if (!modalNombrebaseId) { mostrarErrorModal('Seleccione una base.'); return; }
        $('#tbody-vigencias .fila-vacia').remove();
        $('#tbody-vigencias').append(filaNueva());
    });

    // Quitar una fila nueva del formulario (solo la descarta, no toca BD).
    $('#tbody-vigencias').on('click', '.btn-quitar-fila', function () {
        $(this).closest('tr').remove();
    });

    // Marcar / desmarcar una vigencia existente para eliminar.
    $('#tbody-vigencias').on('click', '.btn-marcar-eliminar', function () {
        var $tr = $(this).closest('tr');
        var $btn = $(this);
        var marcada = $tr.toggleClass('fila-eliminar').hasClass('fila-eliminar');
        $tr.find('.vig-fecha, .vig-valor').prop('disabled', marcada);
        $tr.toggleClass('table-danger', marcada);
        if (marcada) {
            $tr.removeClass('table-success');
            $btn.attr('title', 'Deshacer eliminación')
                .removeClass('btn-outline-danger').addClass('btn-outline-secondary')
                .html('<i class="fa fa-undo"></i>');
        } else {
            $btn.attr('title', 'Marcar para eliminar')
                .removeClass('btn-outline-secondary').addClass('btn-outline-danger')
                .html('<i class="fa fa-trash"></i>');
        }
    });

    // Guardar todos los cambios del modal en una sola operación.
    $('#btn-guardar-vigencias').on('click', function () {
        if (!modalNombrebaseId) { mostrarErrorModal('Seleccione una base.'); return; }

        var items = [];
        var eliminar = [];
        var error = null;
        var fechasVistas = {};

        $('#tbody-vigencias tr').each(function () {
            var $tr = $(this);
            if ($tr.hasClass('fila-vacia')) { return; }

            var baseId = $tr.data('base-id');

            if ($tr.hasClass('fila-eliminar')) {
                if (baseId) { eliminar.push(baseId); }
                return;
            }

            var fecha = $tr.find('.vig-fecha').val();
            var valor = $tr.find('.vig-valor').val();
            var esNueva = $tr.hasClass('fila-nueva');

            if (esNueva && !fecha && (valor === '' || valor === null)) {
                return; // fila nueva vacía, se ignora
            }
            if (!fecha) { error = 'Todas las filas deben tener fecha de vigencia.'; return false; }
            if (valor === '' || valor === null) { error = 'Todas las filas deben tener un valor.'; return false; }
            if (fechasVistas[fecha]) { error = 'Hay dos vigencias con la misma fecha.'; return false; }
            fechasVistas[fecha] = true;

            var it = { fecha: fecha, valor: valor };
            if (baseId) { it.id = baseId; }
            items.push(it);
        });

        if (error) { mostrarErrorModal(error); return; }
        if (!items.length && !eliminar.length) {
            mostrarErrorModal('No hay cambios para guardar.');
            return;
        }

        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: urlLote,
            method: 'POST',
            data: {
                _token: csrf,
                nombrebase_id: modalNombrebaseId,
                items: items,
                eliminar: eliminar
            }
        }).done(function (resp) {
            if (resp && resp.mensaje === 'ok') {
                mostrarErrorModal('');
                refrescarTodo(resp);
                $('#modal-vigencias-base').modal('hide');
            } else {
                mostrarErrorModal((resp && resp.error) ? resp.error : 'No se pudieron guardar los cambios.');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al guardar los cambios.';
            mostrarErrorModal(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
