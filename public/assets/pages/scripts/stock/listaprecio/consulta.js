var ptrListaprecioContext;

function parsearHtmlConsultaListaprecio(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function csrfTokenListaprecio() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function actualizarLinkEditarListaprecio($ctx, listaprecioId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-listaprecio');
    if (!$link.length) {
        return;
    }
    var id = parseInt(listaprecioId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/listaprecio/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function notificarListaprecioSeleccionado(data) {
    if (typeof window.onListaprecioSeleccionado === 'function') {
        window.onListaprecioSeleccionado(data);
    }
}

function listaprecioActualizaIdsGlobales($ctx) {
    if (!$ctx || !$ctx.length) {
        return true;
    }

    return $ctx.find('#listaprecio_id').length > 0;
}

function enfocarSiguienteListaprecio($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var sig = $ctx.find('.codigolistaprecio').attr('data-siguiente');
    if (!sig) {
        return;
    }
    var $sig = $(sig);
    if ($sig.length) {
        $sig.trigger('focus');
    }
}

function aplicarListaprecioEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    if ($ctx && $ctx.closest('#modal-emitir-lista-vigente').length) {
        opts.notificar = false;
    }
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';

    if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val(id);
        $ctx.find('.codigolistaprecio').val(codigo);
        $ctx.find('.nombrelistaprecio').val(nombre);
        $ctx.find('.codigolistaprecio').removeAttr('data-listaprecio-invalido');
        actualizarLinkEditarListaprecio($ctx, id);
    }

    if (!listaprecioActualizaIdsGlobales($ctx)) {
        if (opts.avanzar && id) {
            enfocarSiguienteListaprecio($ctx);
        }
        if (opts.notificar !== false) {
            notificarListaprecioSeleccionado(data);
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(data);
        }
        if ($ctx.attr('id') === 'tm_listaprecio_listaferli' && id && typeof window.listaFerliAgregarLista === 'function') {
            window.listaFerliAgregarLista();
        }
        return;
    }

    // Compat: IDs globales del ABM / filtros (solo el campo #listaprecio_id)
    if ($('#listaprecio_id').length) {
        $('#listaprecio_id').val(id);
    }
    if ($('#consultaprecioarticuloListaId').length) {
        $('#consultaprecioarticuloListaId').val(id);
    }
    if ($('#codigolistaprecio').length) {
        $('#codigolistaprecio').val(codigo);
    }
    if ($('#nombrelistaprecio').length) {
        $('#nombrelistaprecio').val(nombre);
    }
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), id);

    if (opts.notificar !== false) {
        notificarListaprecioSeleccionado(data);
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(data);
    }
}

function limpiarListaprecioEnContexto($ctx, opciones) {
    var opts = opciones || {};
    if ($ctx && $ctx.closest('#modal-emitir-lista-vigente').length) {
        opts.notificar = false;
    }
    if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val('');
        $ctx.find('.codigolistaprecio').val('');
        $ctx.find('.nombrelistaprecio').val('');
        actualizarLinkEditarListaprecio($ctx, 0);
    }

    if (!listaprecioActualizaIdsGlobales($ctx)) {
        if (opts.notificar !== false) {
            notificarListaprecioSeleccionado({ id: '', codigo: '', nombre: '' });
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
        return;
    }

    if ($('#listaprecio_id').length) {
        $('#listaprecio_id').val('');
    }
    if ($('#consultaprecioarticuloListaId').length) {
        $('#consultaprecioarticuloListaId').val('');
    }
    if ($('#codigolistaprecio').length) {
        $('#codigolistaprecio').val('');
    }
    if ($('#nombrelistaprecio').length) {
        $('#nombrelistaprecio').val('');
    }
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), 0);

    if (opts.notificar !== false) {
        notificarListaprecioSeleccionado({ id: '', codigo: '', nombre: '' });
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(null);
    }
}

function limpiarListaprecioManteniendoCodigo($ctx, codigo) {
        if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val('');
        $ctx.find('.codigolistaprecio').val(codigo);
        $ctx.find('.nombrelistaprecio').val('');
        actualizarLinkEditarListaprecio($ctx, 0);
    }

    if (!listaprecioActualizaIdsGlobales($ctx)) {
        return;
    }

    if ($('#listaprecio_id').length) {
        $('#listaprecio_id').val('');
    }
    if ($('#consultaprecioarticuloListaId').length) {
        $('#consultaprecioarticuloListaId').val('');
    }
    if ($('#codigolistaprecio').length) {
        $('#codigolistaprecio').val(codigo);
    }
    if ($('#nombrelistaprecio').length) {
        $('#nombrelistaprecio').val('');
    }
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), 0);
}

function buscar_datos_listaprecio(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/listaprecio/consultalistaprecio',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': csrfTokenListaprecio()
        },
        data: {
            consulta: consulta || '',
            _token: csrfTokenListaprecio(),
        },
    })
        .done(function (respuesta) {
            $('#datoslistaprecio').html(parsearHtmlConsultaListaprecio(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar listas de precios';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar listas de precios';
            } else if (xhr && xhr.status === 419) {
                msg = 'Sesión expirada (CSRF). Recargue la página.';
            }
            $('#datoslistaprecio').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoListaprecio(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (cod === '') {
        limpiarListaprecioEnContexto($ctx, opts);
        return;
    }

    var codOriginal = cod;
    var urlRes = carpetaBase + '/stock/leerlistaprecio/' + encodeURIComponent(cod);
    $.get(urlRes, function (data) {
        if (data && data.id) {
            if ($ctx && $ctx.length) {
                $ctx.find('.codigolistaprecio').removeAttr('data-listaprecio-invalido');
            }
            aplicarListaprecioEnContexto($ctx, data, opts);
        } else {
            var $codigo = $ctx && $ctx.length ? $ctx.find('.codigolistaprecio') : $();
            var yaAvisado = $codigo.attr('data-listaprecio-invalido') === codOriginal;
            limpiarListaprecioManteniendoCodigo($ctx, codOriginal);
            $codigo.attr('data-listaprecio-invalido', codOriginal);
            if (!opts.silencioso && !yaAvisado) {
                alert('Lista de precios no encontrada');
            }
            if (typeof opts.onDone === 'function') {
                opts.onDone(null);
            }
        }
    }).fail(function () {
        limpiarListaprecioManteniendoCodigo($ctx, codOriginal);
        if (!opts.silencioso) {
            alert('No se pudo validar la lista de precios');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    });
}

function esTeclaF1Listaprecio(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaListaprecioAbierto() {
    var $m = $('#consultalistaprecioModal');
    return $m.length && $m.hasClass('show');
}

function abrirModalConsultaListaprecioDesdeInput($input) {
    ptrListaprecioContext = $input.closest('.tm-listaprecio-campo');
    if (!ptrListaprecioContext.length) {
        ptrListaprecioContext = $('.tm-listaprecio-campo').first();
    }
    $('#consultalistaprecioModal').modal('show');
    buscar_datos_listaprecio('');
}

function aceptarCodigoListaprecioDesdeInput($input) {
    var $ctx = $input.closest('.tm-listaprecio-campo');
    resolverPorCodigoListaprecio($input.val(), $ctx.length ? $ctx : null, {
        notificar: debeNotificarAlResolverCodigo($input),
        silencioso: false,
        avanzar: true,
    });
}

function campoCodigoListaprecioPermitido(target) {
    if (!target) {
        return false;
    }
    var esCampoCodigo = target.classList.contains('codigolistaprecio') || target.id === 'codigolistaprecio';
    if (!esCampoCodigo) {
        return false;
    }
    if (target.readOnly || target.disabled) {
        return false;
    }
    var $campo = $(target).closest('.tm-listaprecio-campo');
    if (!$campo.length) {
        return false;
    }
    var formGeneral = document.getElementById('form-general');
    if (formGeneral && formGeneral.contains(target) && formGeneral.getAttribute('data-consultas-modales-abm') === '1') {
        return false;
    }
    return true;
}

function debeNotificarAlResolverCodigo($input) {
    if ($input.closest('#modal-emitir-lista-vigente').length) {
        return false;
    }
    // Index precios / consulta $ artículo: hay que refrescar al resolver
    if ($input.closest('#consultaprecioarticuloModal').length) {
        return true;
    }
    if ($input.closest('#form-filtros-precio').length || $input.closest('.precio-toolbar-lista').length) {
        return true;
    }
    // ABM crear/editar precio: no hace falta notificar
    return false;
}

var listaprecioAtajosTecladoRegistrados = false;

function registrarAtajosTecladoListaprecio() {
    if (listaprecioAtajosTecladoRegistrados) {
        return;
    }
    listaprecioAtajosTecladoRegistrados = true;

    document.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!campoCodigoListaprecioPermitido(target)) {
            return;
        }

        if (esTeclaF1Listaprecio(e)) {
            if (modalConsultaListaprecioAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaListaprecioDesdeInput($(target));
            return;
        }

        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoListaprecioDesdeInput($(target));
    }, true);
}

$(document).on('keyup', '#consultalistaprecio', function (e) {
    if (e.which === 13 || e.key === 'Enter') {
        return;
    }
    buscar_datos_listaprecio($(this).val());
});

function elegirPrimeraListaprecioDelModal() {
    var $btn = $('#datoslistaprecio .eligeconsultalistaprecio').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

$(document)
    .off('keydown.consultaListaprecioEnter', '#consultalistaprecio')
    .on('keydown.consultaListaprecioEnter', '#consultalistaprecio', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraListaprecioDelModal()) {
            buscar_datos_listaprecio($(this).val());
        }
    });

// Evita que Enter en el buscador del modal recargue la página; Enter = primera fila.
$(document).on('submit', '#consultalistaprecioModal form', function (e) {
    e.preventDefault();
    elegirPrimeraListaprecioDelModal();
    return false;
});

function activa_eventos_consultalistaprecio() {
    registrarAtajosTecladoListaprecio();

    // Sacarlo del layout anidado (modales/cards) para que Bootstrap lo muestre bien
    var $modalLista = $('#consultalistaprecioModal');
    if ($modalLista.length && $modalLista.parent()[0] !== document.body) {
        $modalLista.appendTo('body');
    }

    // Delegado: funciona también dentro de modales anidados
    $(document).off('mousedown.listaprecioLupa', '.consultalistaprecio').on('mousedown.listaprecioLupa', '.consultalistaprecio', function () {
        $(this).closest('.tm-listaprecio-campo').find('.codigolistaprecio').data('consulta-abriendo', 1);
    });

    $(document).off('click.listaprecioLupa', '.consultalistaprecio').on('click.listaprecioLupa', '.consultalistaprecio', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!$('#consultalistaprecioModal').length) {
            alert('No está disponible el modal de consulta de listas de precios.');
            return;
        }
        ptrListaprecioContext = $(this).closest('.tm-listaprecio-campo');
        if (!ptrListaprecioContext.length) {
            ptrListaprecioContext = $('.tm-listaprecio-campo').first();
        }
        $('#consultalistaprecioModal').modal('show');
        buscar_datos_listaprecio('');
    });

    $('#consultalistaprecioModal').off('show.bs.modal.listaprecio').on('show.bs.modal.listaprecio', function () {
        var otrosAbiertos = $('.modal.show, .modal.in').not(this).length;
        if (otrosAbiertos > 0) {
            var zHijo = 1060 + (10 * otrosAbiertos);
            $(this).css('z-index', zHijo).data('listaprecioModalApilado', true);
            setTimeout(function () {
                $('.modal-backdrop').not('.modal-stack').last().css('z-index', zHijo - 1).addClass('modal-stack');
            }, 0);
        }
    });

    $('#consultalistaprecioModal').off('shown.bs.modal.listaprecio').on('shown.bs.modal.listaprecio', function () {
        // Bootstrap atrapa el foco en el modal padre; liberar para poder usar la lupa anidada
        $(document).off('focusin.modal');
        $(this).find('#consultalistaprecio').trigger('focus');
    });

    $('#consultalistaprecioModal').off('hidden.bs.modal.listaprecio').on('hidden.bs.modal.listaprecio', function () {
        var $m = $(this);
        if ($m.data('listaprecioModalApilado')) {
            $m.removeData('listaprecioModalApilado');
            $m.css('z-index', '');
        }
        if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    $('#aceptaconsultalistaprecioModal').off('click.listaprecio').on('click.listaprecio', function () {
        if (!elegirPrimeraListaprecioDelModal()) {
            $('#consultalistaprecioModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultalistaprecio').on('click.eligeconsultalistaprecio', '.eligeconsultalistaprecio', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            nombre: $.trim($row.find('.nombre').text()),
            codigo: $.trim($row.find('.codigo').text()),
        };

        if (ptrListaprecioContext && ptrListaprecioContext.length) {
            aplicarListaprecioEnContexto(ptrListaprecioContext, data, { notificar: true });
        } else {
            aplicarListaprecioEnContexto($('.tm-listaprecio-campo').first(), data, { notificar: true });
        }

        $('#consultalistaprecioModal').modal('hide');
    });

    $(document).off('change.listaprecio blur.listaprecio', '.codigolistaprecio')
        .on('change.listaprecio blur.listaprecio', '.codigolistaprecio', function () {
            var $input = $(this);
            var $ctx = $input.closest('.tm-listaprecio-campo');
            var notificar = debeNotificarAlResolverCodigo($input);
            if ($input.data('consulta-abriendo') || modalConsultaListaprecioAbierto()) {
                $input.removeData('consulta-abriendo');
                return;
            }
            resolverPorCodigoListaprecio($input.val(), $ctx.length ? $ctx : null, {
                notificar: notificar,
                silencioso: $input.closest('#consultaprecioarticuloModal').length > 0
                    || $input.closest('#modal-emitir-lista-vigente').length > 0,
            });
        });
}

// Auto-activa al cargar el script (idempotente)
$(function () {
    if ($('#consultalistaprecioModal').length || $('.consultalistaprecio').length || $('.tm-listaprecio-campo').length) {
        activa_eventos_consultalistaprecio();
    }
});
