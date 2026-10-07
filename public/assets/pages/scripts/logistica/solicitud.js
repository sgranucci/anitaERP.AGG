$(function () {
    var catalogo = window.LOGISTICA_CATALOGO || { categorias: [], items: [], tipos: [] };
    var trabajos = window.LOGISTICA_TRABAJOS || [];
    var carrito = [];
    var categoriaId = null;
    var rango = { Baja: 1, Normal: 2, Media: 2, Alta: 3, Urgente: 4 };
    var etiquetas = ['Baja', 'Media', 'Alta', 'Urgente'];

    function money(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ccActual() {
        return parseInt($('#solicitud_centrocosto_id').val(), 10) || 0;
    }

    function itemVisible(it) {
        if (!it.cc_ids || it.cc_ids.length === 0) {
            return true;
        }
        var cc = ccActual();
        if (!cc) {
            return true;
        }
        return it.cc_ids.indexOf(cc) !== -1;
    }

    function itemsDe(catId) {
        return (catalogo.items || []).filter(function (it) {
            return it.categoria_id === catId && itemVisible(it);
        });
    }

    function pintarRailCategorias() {
        var $box = $('#log-rail-categorias').empty();
        (catalogo.categorias || []).forEach(function (cat) {
            if (!itemsDe(cat.id).length) {
                return;
            }
            var $b = $('<button type="button" class="log-tile log-cat"></button>');
            $b.attr('data-id', cat.id);
            $b.append('<span class="log-ico"><i class="fa ' + (cat.icono || 'fa-cube') + '"></i></span>');
            $b.append($('<span></span>').text(cat.nombre));
            if (categoriaId === cat.id) {
                $b.addClass('is-on');
            }
            $box.append($b);
        });
        if (!$box.children().length) {
            $box.append('<p class="small px-2 mb-0">No hay insumos habilitados.</p>');
        }
    }

    function mostrarCategoria(id, nombre) {
        categoriaId = id;
        $('#log-rail-categorias .log-cat').removeClass('is-on');
        $('#log-rail-categorias .log-cat[data-id="' + id + '"]').addClass('is-on');
        $('#log-titulo-insumos').text(nombre || 'Insumos');
        var q = $.trim($('#log-buscar').val() || '').toLowerCase();
        var visibles = 0;
        $('#log-grilla .log-card').each(function () {
            var $card = $(this);
            var deCat = parseInt($card.data('categoria'), 10) === id;
            var texto = String($card.data('texto') || '');
            var cc = String($card.data('cc') || '');
            var ccOk = true;
            var actual = ccActual();
            if (cc && actual) {
                ccOk = cc.split(',').indexOf(String(actual)) !== -1;
            }
            var show = deCat && ccOk && (!q || texto.indexOf(q) !== -1);
            $card.toggle(show);
            if (show) {
                visibles += 1;
            }
        });
        $('#log-vacio').toggle(visibles === 0);
    }

    function pintarCarrito() {
        var $body = $('#log-carrito-body').empty();
        var total = 0;
        carrito.forEach(function (linea, idx) {
            var importe = linea.precio * linea.cantidad;
            total += importe;
            var $tr = $('<tr></tr>');
            $tr.append($('<td></td>').text(linea.sku || ''));
            $tr.append($('<td></td>').text(linea.nombre));
            $tr.append($('<td class="text-right"></td>').text(linea.cantidad));
            $tr.append($('<td class="text-right"></td>').text(money(importe)));
            var $x = $('<button type="button" class="btn-accion-tabla" title="Quitar"><i class="fa fa-times-circle text-danger"></i></button>');
            $x.on('click', function () {
                carrito.splice(idx, 1);
                pintarCarrito();
            });
            $tr.append($('<td class="text-center"></td>').append($x));
            $body.append($tr);
        });
        if (!carrito.length) {
            $body.append('<tr><td colspan="5" class="text-center text-muted">Todavía no agregaste ítems.</td></tr>');
        }
        $('#log-total').text(money(total));
        $('#log-cant-items').text(String(carrito.length));
    }

    function elegirNaturaleza(codigo) {
        $('.log-naturaleza').removeClass('is-on');
        $('.log-naturaleza[data-codigo="' + codigo + '"]').addClass('is-on');
        if (codigo === 'insumos') {
            $('#log-rail-insumos').show();
            $('#log-rail-trabajos').hide();
            $('#form-solicitud-logistica').removeClass('log-off');
            $('#form-solicitud-trabajo').addClass('log-off');
            pintarRailCategorias();
            var $primera = $('#log-rail-categorias .log-cat').first();
            if ($primera.length) {
                mostrarCategoria(parseInt($primera.data('id'), 10), $primera.find('span').last().text());
            } else {
                $('#log-grilla .log-card').hide();
                $('#log-vacio').show();
                $('#log-titulo-insumos').text('Insumos');
            }
            return;
        }
        $('#log-rail-insumos').hide();
        $('#log-rail-trabajos').show();
        $('#form-solicitud-logistica').addClass('log-off');
        $('#form-solicitud-trabajo').removeClass('log-off');
    }

    function prioridadesDesde(piso) {
        var minimo = rango[piso] || 2;
        var $sel = $('#trab-prioridad').empty();
        etiquetas.forEach(function (nombre) {
            if (rango[nombre] >= minimo) {
                $sel.append($('<option></option>').val(nombre).text(nombre));
            }
        });
        $sel.val(piso && rango[piso] ? piso : $sel.find('option').first().val());
    }

    function activarPanel(codigo) {
        $('.trab-panel').hide().find(':input').prop('disabled', true);
        var $panel = $('.trab-panel[data-codigo="' + codigo + '"]');
        $panel.show().find(':input').prop('disabled', false);
        $panel.find('[name="accesorios_detalle"]').prop('disabled', true).hide();
        $('#log-trabajo-placeholder').hide();
        $('#log-trabajo-cuerpo').show();
    }

    $(document).on('click', '.log-naturaleza', function () {
        elegirNaturaleza($(this).data('codigo'));
    });

    $(document).on('click', '.log-cat', function () {
        mostrarCategoria(parseInt($(this).data('id'), 10), $(this).find('span').last().text());
    });

    $('#log-buscar').on('input', function () {
        var $on = $('#log-rail-categorias .log-cat.is-on');
        if ($on.length) {
            mostrarCategoria(parseInt($on.data('id'), 10), $on.find('span').last().text());
        }
    });

    $(document).on('input', '.log-cant', function () {
        var $card = $(this).closest('.log-card');
        var cant = parseFloat($(this).val()) || 0;
        var disp = parseFloat($card.data('disponible')) || 0;
        $card.find('.log-compra').toggle(cant > disp);
    });

    $(document).on('click', '.log-agregar', function () {
        var $card = $(this).closest('.log-card');
        var id = parseInt($card.data('id'), 10);
        var cant = parseFloat($card.find('.log-cant').val());
        if (!id || !(cant > 0)) {
            return;
        }
        var ex = carrito.find(function (linea) { return linea.id === id; });
        if (ex) {
            ex.cantidad += cant;
        } else {
            carrito.push({
                id: id,
                sku: $card.data('sku'),
                nombre: $card.data('nombre'),
                precio: parseFloat($card.data('precio')) || 0,
                cantidad: cant
            });
        }
        $card.find('.log-cant').val('1');
        pintarCarrito();
    });

    $('#solicitud_centrocosto_id').on('change', function () {
        if (!$('#form-solicitud-logistica').hasClass('log-off')) {
            pintarRailCategorias();
            var $on = $('#log-rail-categorias .log-cat.is-on');
            if (!$on.length) {
                $on = $('#log-rail-categorias .log-cat').first();
            }
            if ($on.length) {
                mostrarCategoria(parseInt($on.data('id'), 10), $on.find('span').last().text());
            }
        }
    });

    $(document).on('click', '.log-trabajo', function () {
        var codigo = $(this).data('codigo');
        var trabajo = trabajos.find(function (row) { return row.codigo === codigo; }) || {};
        $('.log-trabajo').removeClass('is-on');
        $(this).addClass('is-on');
        $('#trabajo_codigo').val(codigo);
        $('#log-trabajo-titulo').text($(this).data('nombre') || trabajo.nombre || '');
        $('#log-trabajo-responsable').text(trabajo.responsable ? ('Asignado a ' + trabajo.responsable) : '');
        prioridadesDesde($(this).data('piso') || trabajo.prioridad_piso);
        activarPanel(codigo);
    });

    $(document).on('click', '.ubi-btn', function () {
        var id = $(this).data('id');
        var $grupo = $(this).closest('.ubi-grupo');
        $grupo.find('.ubi-btn').removeClass('btn-secondary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
        $grupo.next('input').val(id);
    });

    $(document).on('change', '.trab-panel[data-codigo="slots"] [name="accesorios"]', function () {
        var $det = $('.trab-panel[data-codigo="slots"] [name="accesorios_detalle"]');
        if ($(this).val() === '1') {
            $det.show().prop('disabled', false);
        } else {
            $det.hide().prop('disabled', true).val('');
        }
    });

    $(document).on('click', '.retiro-empresa', function () {
        $('#retiro_empresa_id').val($(this).data('id'));
        $('.retiro-empresa').removeClass('btn-secondary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
    });

    $('#retiro_oc').on('blur', function () {
        var numero = $.trim($(this).val());
        var $ayuda = $('#retiro_oc_ayuda').text('');
        if (!numero) {
            return;
        }
        $.post(window.LOGISTICA_RESOLVER_OC, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            numero: numero
        }).done(function (res) {
            if (!res || !res.ok) {
                $ayuda.text(res && res.mensaje ? res.mensaje : 'Orden no encontrada.');
                return;
            }
            $ayuda.text(res.proveedor || 'Orden encontrada.');
            if (res.domicilio && !$('#retiro_direccion').val()) {
                $('#retiro_direccion').val(res.domicilio);
            }
            if (res.empresa_id) {
                var $btn = $('.retiro-empresa[data-id="' + res.empresa_id + '"]');
                if ($btn.length) {
                    $btn.trigger('click');
                }
            }
        });
    });

    $('#form-solicitud-logistica').on('submit', function (e) {
        if (!carrito.length) {
            e.preventDefault();
            alert('Agregá al menos un ítem.');
            return false;
        }
        var $lineas = $('#logistica-lineas').empty();
        carrito.forEach(function (linea) {
            $lineas.append($('<input type="hidden" name="item_articulo_id[]">').val(linea.id));
            $lineas.append($('<input type="hidden" name="item_cantidad[]">').val(linea.cantidad));
        });
    });

    var inicial = $('.log-naturaleza.is-on').data('codigo');
    if (inicial) {
        elegirNaturaleza(inicial);
    }
    pintarCarrito();
});
