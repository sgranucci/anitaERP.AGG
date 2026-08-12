/* Proyección de pagos (Compras): filtros, consulta de proveedores y configurador de columnas. */

var PROY_PRESETS = {
    ejecutivo: [
        'proveedor_codigo', 'proveedor_nombre', 'total_aprobado', 'pend_aprobacion',
        'adelantos', 'total_adeudado',
    ],
    tesoreria: [
        'proveedor_codigo', 'proveedor_nombre', 'tipo', 'comprobante', 'fecha_vencimiento',
        'dias_vencimiento', 'moneda', 'medio_pago', 'condicion_pago', 'total_adeudado',
    ],
    analisis: [
        'proveedor_codigo', 'proveedor_nombre', 'tipo', 'comprobante', 'fecha_comprobante',
        'fecha_vencimiento', 'nro_referencia', 'requisicion', 'usuario_requisicion',
        'detalle_item', 'concepto', 'detalle_concepto', 'aprobacion', 'total_adeudado',
    ],
    cashflow: [
        'concepto', 'detalle_concepto', 'cuenta_concepto', 'proveedor_codigo', 'proveedor_nombre',
        'comprobante', 'fecha_vencimiento', 'medio_pago', 'total_aprobado', 'pend_aprobacion',
        'total_adeudado',
    ],
};

function proyNormalizar(valor) {
    return String(valor || '').trim();
}

function proyEsTeclaF1(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function proyPantallaActiva() {
    return $('#form-proyeccion-pagos').length > 0;
}

function proyClavesTramos() {
    var claves = [];
    $('#proy-columnas-grupos .proy-columna-check').each(function () {
        var clave = $(this).data('clave');
        if (/^tramo_\d+$/.test(clave) || clave === 'saldo_anterior' || clave === 'posterior') {
            claves.push(clave);
        }
    });

    return claves;
}

function proyColumnaEtiqueta(clave) {
    var $check = $('#proy-columnas-grupos .proy-columna-check[data-clave="' + clave + '"]');

    return $check.length ? $check.data('etiqueta') : clave;
}

function proyColumnaFija(clave) {
    return $('#proy-columnas-grupos .proy-columna-check[data-clave="' + clave + '"]').is(':disabled');
}

function proyRenderOrden(claves) {
    var $lista = $('#proy-columnas-orden');
    $lista.empty();

    claves.forEach(function (clave) {
        var fija = proyColumnaFija(clave);
        var $item = $('<li class="list-group-item py-1 px-2 d-flex align-items-center proy-orden-item" draggable="true"></li>')
            .attr('data-clave', clave);

        $item.append('<i class="fa fa-arrows-alt-v text-muted mr-2"></i>');
        $item.append($('<span class="flex-grow-1"></span>').text(proyColumnaEtiqueta(clave)));

        var $acciones = $('<span class="btn-group btn-group-sm"></span>');
        $acciones.append('<button type="button" class="btn btn-outline-secondary proy-orden-subir" title="Subir"><i class="fa fa-arrow-up"></i></button>');
        $acciones.append('<button type="button" class="btn btn-outline-secondary proy-orden-bajar" title="Bajar"><i class="fa fa-arrow-down"></i></button>');
        if (!fija) {
            $acciones.append('<button type="button" class="btn btn-outline-danger proy-orden-quitar" title="Quitar"><i class="fa fa-times"></i></button>');
        }
        $item.append($acciones);

        $lista.append($item);
    });

    $('#proy-columnas-contador').text(claves.length);
}

function proyClavesOrden() {
    return $('#proy-columnas-orden .proy-orden-item')
        .map(function () {
            return $(this).attr('data-clave');
        })
        .get();
}

function proySincronizarDesdeChecks() {
    var actuales = proyClavesOrden();
    var activas = [];

    $('#proy-columnas-grupos .proy-columna-check').each(function () {
        if ($(this).is(':checked') || $(this).is(':disabled')) {
            activas.push($(this).data('clave'));
        }
    });

    var ordenadas = actuales.filter(function (clave) {
        return activas.indexOf(clave) >= 0;
    });

    activas.forEach(function (clave) {
        if (ordenadas.indexOf(clave) < 0) {
            ordenadas.push(clave);
        }
    });

    proyRenderOrden(ordenadas);
}

function proyAplicarPreset(nombre) {
    var claves;

    if (nombre === 'completo') {
        claves = $('#proy-columnas-grupos .proy-columna-check')
            .map(function () {
                return $(this).data('clave');
            })
            .get();
    } else {
        claves = (PROY_PRESETS[nombre] || []).slice();
        var tramos = proyClavesTramos();
        var posicion = claves.indexOf('total_aprobado');
        if (posicion < 0) {
            posicion = claves.length;
        }
        claves = claves.slice(0, posicion).concat(tramos, claves.slice(posicion));
    }

    var disponibles = $('#proy-columnas-grupos .proy-columna-check')
        .map(function () {
            return $(this).data('clave');
        })
        .get();

    var finales = claves.filter(function (clave, indice) {
        return disponibles.indexOf(clave) >= 0 && claves.indexOf(clave) === indice;
    });

    $('#proy-columnas-grupos .proy-columna-check').each(function () {
        var clave = $(this).data('clave');
        if ($(this).is(':disabled')) {
            if (finales.indexOf(clave) < 0) {
                finales.push(clave);
            }
            return;
        }
        $(this).prop('checked', finales.indexOf(clave) >= 0);
    });

    proyRenderOrden(finales);
}

function proyMoverItem($item, direccion) {
    if (direccion < 0) {
        var $previo = $item.prev('.proy-orden-item');
        if ($previo.length) {
            $item.insertBefore($previo);
        }

        return;
    }

    var $siguiente = $item.next('.proy-orden-item');
    if ($siguiente.length) {
        $item.insertAfter($siguiente);
    }
}

function proyBuscarProveedoresModal(consulta) {
    $.ajax({
        url: carpetaBase + '/compras/proveedor/consultaproveedor',
        type: 'POST',
        dataType: 'HTML',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: { consulta: consulta },
    })
        .done(function (respuesta) {
            var html = '';
            try {
                html = JSON.parse(respuesta).data || '';
            } catch (e) {
                html = respuesta;
            }
            $('#datosproveedor').html(html);
        })
        .fail(function () {
            $('#datosproveedor').html('<tr><td colspan="8">Error al consultar proveedores</td></tr>');
        });
}

function proyAbrirModalProveedor() {
    var valor = proyNormalizar($('#proveedores_codigo').val());
    proyBuscarProveedoresModal(valor);
    $('#consultaproveedorModal').modal('show');
}

function proyAgregarProveedor(codigo) {
    var $inp = $('#proveedores_codigo');
    var actual = proyNormalizar($inp.val());
    var codigos = actual === '' ? [] : actual.split(/[,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
    var codigoStr = String(codigo).trim();

    if (codigoStr !== '' && codigos.indexOf(codigoStr) < 0) {
        codigos.push(codigoStr);
    }

    $inp.val(codigos.join(','));
}

function proyToggleGrupo(grupoId, colapsar) {
    var $detalle = $('.proy-grupo-' + grupoId);
    var $cabecera = $('.proy-grupo-cabecera[data-grupo-id="' + grupoId + '"]');

    if (colapsar === undefined) {
        colapsar = !$cabecera.hasClass('proy-colapsado');
    }

    if (colapsar) {
        $cabecera.addClass('proy-colapsado');
        $detalle.addClass('proy-colapsado');
        $cabecera.find('.proy-grupo-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');
    } else {
        $cabecera.removeClass('proy-colapsado');
        $detalle.removeClass('proy-colapsado');
        $cabecera.find('.proy-grupo-icon').removeClass('fa-chevron-right').addClass('fa-chevron-down');
    }
}

function proyToggleTodosGrupos() {
    var $cabeceras = $('#tabla-proyeccion-pagos .proy-grupo-cabecera');
    if (!$cabeceras.length) {
        return;
    }

    var algunoExpandido = $cabeceras.filter(':not(.proy-colapsado)').length > 0;
    $cabeceras.each(function () {
        proyToggleGrupo($(this).data('grupo-id'), algunoExpandido);
    });
}

function proyAlternarCamposTramos() {
    var porMes = $('#tipo_vencimiento').val() === 'mes';
    $('#proy-campo-tramos-dias').toggleClass('d-none', porMes);
    $('#proy-campo-tramos-meses').toggleClass('d-none', !porMes);
}

function proyAtajoF1Handler(e) {
    if (!proyEsTeclaF1(e) || !proyPantallaActiva()) {
        return;
    }

    var target = e.target;
    if (!target || !target.closest('#form-proyeccion-pagos') || target.readOnly || target.disabled) {
        return;
    }

    if (target.classList.contains('codigoproveedor')) {
        if ($('#consultaproveedorModal').hasClass('show')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        proyAbrirModalProveedor();
    }
}

function activaEventosProyeccionPagos() {
    var $form = $('#form-proyeccion-pagos');
    if (!$form.length) {
        return;
    }

    proyAlternarCamposTramos();

    var configuradas = proyNormalizar($('#proy-columnas-config').val());
    if (configuradas === '') {
        var activasIniciales = [];
        $('#proy-columnas-grupos .proy-columna-check').each(function () {
            if ($(this).is(':checked') || $(this).is(':disabled')) {
                activasIniciales.push($(this).data('clave'));
            }
        });
        proyRenderOrden(activasIniciales);
    } else {
        var disponibles = $('#proy-columnas-grupos .proy-columna-check')
            .map(function () { return $(this).data('clave'); })
            .get();
        var claves = configuradas.split(',').map(function (c) { return c.trim(); }).filter(function (c) {
            return c !== '' && disponibles.indexOf(c) >= 0;
        });
        $('#proy-columnas-grupos .proy-columna-check').each(function () {
            var clave = $(this).data('clave');
            if ($(this).is(':disabled')) {
                if (claves.indexOf(clave) < 0) {
                    claves.push(clave);
                }
                return;
            }
            $(this).prop('checked', claves.indexOf(clave) >= 0);
        });
        proyRenderOrden(claves);
    }

    $form.on('keydown', 'input:not([type="submit"])', function (e) {
        if (e.which === 13 && $(this).hasClass('codigoproveedor')) {
            e.preventDefault();
            return false;
        }
    });

    $(document)
        .off('change.proy', '#tipo_vencimiento')
        .on('change.proy', '#tipo_vencimiento', proyAlternarCamposTramos);

    $(document)
        .off('click.proy', '.consultaproveedor-proy')
        .on('click.proy', '.consultaproveedor-proy', function (e) {
            e.preventDefault();
            proyAbrirModalProveedor();
        });

    $('#consultaproveedorModal')
        .off('shown.bs.modal.proy')
        .on('shown.bs.modal.proy', function () {
            if (!proyPantallaActiva()) {
                return;
            }
            var valor = proyNormalizar($('#proveedores_codigo').val());
            $('#consultaproveedor').val(valor);
            proyBuscarProveedoresModal(valor);
            $(this).find('[autofocus]').focus();
        });

    $(document)
        .off('keyup.proy', '#consultaproveedor')
        .on('keyup.proy', '#consultaproveedor', function () {
            if (!proyPantallaActiva()) {
                return;
            }
            proyBuscarProveedoresModal(proyNormalizar($(this).val()));
        });

    $(document)
        .off('click.proy', '.eligeconsultaproveedor')
        .on('click.proy', '.eligeconsultaproveedor', function (e) {
            if (!proyPantallaActiva()) {
                return;
            }
            e.stopImmediatePropagation();

            var $tr = $(this).closest('tr');
            var codigo = $tr.find('.codigo').first().text().trim();
            if (codigo !== '') {
                proyAgregarProveedor(codigo);
            }

            $('#consultaproveedorModal').modal('hide');
            return false;
        });

    $(document)
        .off('change.proy', '.proy-columna-check')
        .on('change.proy', '.proy-columna-check', function () {
            proySincronizarDesdeChecks();
        });

    $(document)
        .off('click.proy', '.proy-grupo-todas')
        .on('click.proy', '.proy-grupo-todas', function () {
            $(this).closest('.proy-grupo-columnas')
                .find('.proy-columna-check:not(:disabled)')
                .prop('checked', true);
            proySincronizarDesdeChecks();
        });

    $(document)
        .off('click.proy', '.proy-preset')
        .on('click.proy', '.proy-preset', function () {
            proyAplicarPreset($(this).data('preset'));
        });

    $(document)
        .off('click.proy', '#proy-columnas-reset')
        .on('click.proy', '#proy-columnas-reset', function () {
            $('#proy-columnas-config').val('');
            $('#form-proyeccion-pagos').trigger('submit');
        });

    $(document)
        .off('keyup.proy', '#proy-buscar-columna')
        .on('keyup.proy', '#proy-buscar-columna', function () {
            var texto = proyNormalizar($(this).val()).toLowerCase();
            $('#proy-columnas-grupos .proy-columna-item').each(function () {
                var etiqueta = String($(this).data('etiqueta') || '');
                $(this).toggleClass('d-none', texto !== '' && etiqueta.indexOf(texto) < 0);
            });
            $('#proy-columnas-grupos .proy-grupo-columnas').each(function () {
                var visibles = $(this).find('.proy-columna-item:not(.d-none)').length;
                $(this).toggleClass('d-none', visibles === 0);
            });
        });

    $(document)
        .off('click.proy', '.proy-orden-subir')
        .on('click.proy', '.proy-orden-subir', function () {
            proyMoverItem($(this).closest('.proy-orden-item'), -1);
        });

    $(document)
        .off('click.proy', '.proy-orden-bajar')
        .on('click.proy', '.proy-orden-bajar', function () {
            proyMoverItem($(this).closest('.proy-orden-item'), 1);
        });

    $(document)
        .off('click.proy', '.proy-orden-quitar')
        .on('click.proy', '.proy-orden-quitar', function () {
            var clave = $(this).closest('.proy-orden-item').attr('data-clave');
            $('#proy-columnas-grupos .proy-columna-check[data-clave="' + clave + '"]').prop('checked', false);
            proySincronizarDesdeChecks();
        });

    $(document)
        .off('dragstart.proy dragover.proy drop.proy dragend.proy', '.proy-orden-item')
        .on('dragstart.proy', '.proy-orden-item', function (e) {
            e.originalEvent.dataTransfer.setData('text/plain', $(this).attr('data-clave'));
            $(this).addClass('bg-light');
        })
        .on('dragover.proy', '.proy-orden-item', function (e) {
            e.preventDefault();
        })
        .on('drop.proy', '.proy-orden-item', function (e) {
            e.preventDefault();
            var clave = e.originalEvent.dataTransfer.getData('text/plain');
            var $origen = $('.proy-orden-item[data-clave="' + clave + '"]');
            if ($origen.length && $origen[0] !== this) {
                $origen.insertBefore($(this));
            }
        })
        .on('dragend.proy', '.proy-orden-item', function () {
            $(this).removeClass('bg-light');
        });

    $(document)
        .off('click.proy', '#proy-columnas-aplicar')
        .on('click.proy', '#proy-columnas-aplicar', function () {
            $('#proy-columnas-config').val(proyClavesOrden().join(','));
            $('#modalColumnasProyeccion').modal('hide');
            $('#form-proyeccion-pagos').trigger('submit');
        });

    $(document)
        .off('click.proy', '#tabla-proyeccion-pagos .proy-grupo-cabecera')
        .on('click.proy', '#tabla-proyeccion-pagos .proy-grupo-cabecera', function () {
            proyToggleGrupo($(this).data('grupo-id'));
        });

    $(document)
        .off('click.proy', '#proy-toggle-grupos')
        .on('click.proy', '#proy-toggle-grupos', function () {
            proyToggleTodosGrupos();
        });

    document.removeEventListener('keydown', proyAtajoF1Handler, true);
    document.addEventListener('keydown', proyAtajoF1Handler, true);
}

$(function () {
    activaEventosProyeccionPagos();
});
