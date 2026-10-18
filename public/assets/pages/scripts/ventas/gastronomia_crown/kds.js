(function ($) {
    'use strict';

    var cfg = window.GastroCrownKds || {};
    var estado = {
        timer: null,
        cargando: false,
        comandas: []
    };

    function toast(msg, tipo) {
        if (window.toastr) {
            toastr[tipo || 'info'](msg);
            return;
        }
        window.alert(msg);
    }

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function esc(txt) {
        return $('<div>').text(txt === null || txt === undefined ? '' : txt).html();
    }

    function cantidadTexto(n) {
        var v = Number(n) || 0;
        return (Math.round(v * 1000) / 1000).toString().replace('.', ',');
    }

    function claseDemora(minutos) {
        if (minutos >= 20) {
            return 'gck-demora-alta';
        }
        if (minutos >= 10) {
            return 'gck-demora-media';
        }
        return '';
    }

    function etiquetaEstado(est) {
        if (est === 'preparing') {
            return 'Preparando';
        }
        if (est === 'ready') {
            return 'Listo';
        }
        return 'Marchada';
    }

    function foto(url, alt, clase) {
        if (!url) {
            return '<span class="' + clase + ' gck-sinfoto"><i class="fa fa-utensils"></i></span>';
        }
        return '<img class="' + clase + '" src="' + esc(url) + '" alt="' + esc(alt || '') + '" loading="lazy">';
    }

    function renderInsumos(insumos) {
        if (!insumos || !insumos.length) {
            return '';
        }
        var html = '<ul class="gck-insumos">';
        insumos.forEach(function (ins) {
            html += '<li>'
                + foto(ins.foto_url, ins.descripcion, 'gck-insumo-foto')
                + '<span class="gck-insumo-cant">' + cantidadTexto(ins.cantidad) + '</span>'
                + '<span class="gck-insumo-desc">' + esc(ins.descripcion || '') + '</span>'
                + '</li>';
        });
        return html + '</ul>';
    }

    function renderOpcionales(opcionales) {
        if (!opcionales || !opcionales.length) {
            return '';
        }
        var html = '<div class="gck-opcionales">';
        opcionales.forEach(function (o) {
            html += '<span class="gck-opcional">'
                + '<i class="fa fa-plus"></i> ' + esc(o.descripcion || o.sku || '')
                + '</span>';
        });
        return html + '</div>';
    }

    function renderLinea(l) {
        return '<li class="gck-linea">'
            + '<div class="gck-linea-head">'
            + foto(l.foto_url, l.descripcion, 'gck-plato-foto')
            + '<div class="gck-linea-txt">'
            + '<span class="gck-linea-cant">' + cantidadTexto(l.cantidad) + '×</span> '
            + '<span class="gck-linea-desc">' + esc(l.descripcion || l.sku || '') + '</span>'
            + (l.observacion ? '<div class="gck-linea-obs"><i class="fa fa-comment"></i> ' + esc(l.observacion) + '</div>' : '')
            + renderOpcionales(l.opcionales)
            + '</div>'
            + '</div>'
            + renderInsumos(l.insumos)
            + '</li>';
    }

    function renderComandas(comandas) {
        var $grid = $('#gck-grid');
        if (!comandas.length) {
            $grid.html('<div class="gck-vacio">Sin comandas pendientes en este filtro.</div>');
            return;
        }
        var html = '';
        comandas.forEach(function (c) {
            html += '<div class="gck-card gck-estado-' + esc(c.estado) + ' ' + claseDemora(c.minutos) + '"'
                + ' data-comanda-id="' + c.id + '">'
                + '<div class="gck-card-head">'
                + '<div class="gck-card-mesa">' + esc(c.mesa || '') + '</div>'
                + '<div class="gck-card-tiempo"><i class="fa fa-clock"></i> ' + c.minutos + '\''
                + (c.fired_hora ? ' <small>(' + esc(c.fired_hora) + ')</small>' : '') + '</div>'
                + '</div>'
                + '<div class="gck-card-sub">'
                + '<span class="gck-badge">#' + esc(c.numero || c.id) + '</span>'
                + (c.area ? '<span class="gck-badge">' + esc(c.area_codigo || c.area) + '</span>' : '')
                + (c.tipo === 'postres' ? '<span class="gck-badge gck-badge-postres">Postres</span>' : '')
                + (c.mozo ? '<span class="gck-badge">' + esc(c.mozo) + '</span>' : '')
                + (c.cubiertos ? '<span class="gck-badge">' + c.cubiertos + ' cub.</span>' : '')
                + '<span class="gck-badge gck-badge-estado">' + esc(etiquetaEstado(c.estado)) + '</span>'
                + '</div>'
                + (c.observacion ? '<div class="gck-card-obs"><i class="fa fa-exclamation-circle"></i> ' + esc(c.observacion) + '</div>' : '')
                + '<ul class="gck-lineas">'
                + (c.lineas || []).map(renderLinea).join('')
                + '</ul>'
                + '<div class="gck-card-acciones">'
                + '<button type="button" class="btn btn-sm btn-warning gck-btn-estado" data-estado="preparing">Preparando</button>'
                + '<button type="button" class="btn btn-sm btn-success gck-btn-estado" data-estado="ready">Listo</button>'
                + '<button type="button" class="btn btn-sm btn-outline-light gck-btn-estado" data-estado="delivered">Entregado</button>'
                + '</div>'
                + '</div>';
        });
        $grid.html(html);
    }

    function cargar() {
        if (estado.cargando) {
            return;
        }
        estado.cargando = true;
        var data = {};
        if (cfg.salaId) {
            data.sala_id = cfg.salaId;
        }
        if (cfg.areaId) {
            data.area_id = cfg.areaId;
        }
        $.getJSON(cfg.urls.estado, data)
            .done(function (resp) {
                if (!resp.ok) {
                    return;
                }
                estado.comandas = resp.comandas || [];
                renderComandas(estado.comandas);
                $('#gck-contador').text(estado.comandas.length + (estado.comandas.length === 1 ? ' comanda' : ' comandas'));
                $('#gck-actualizado').text('Actualizado ' + (resp.generado || ''));
            })
            .fail(function () {
                $('#gck-actualizado').text('Sin conexión');
            })
            .always(function () { estado.cargando = false; });
    }

    function cambiarEstado(comandaId, nuevoEstado) {
        $.ajax({
            url: cfg.urls.comandaBase + '/' + comandaId + '/estado',
            method: 'POST',
            data: { estado: nuevoEstado },
            headers: { 'X-CSRF-TOKEN': csrf() }
        })
            .done(function (resp) {
                if (!resp.ok) {
                    toast(resp.mensaje || 'No se pudo actualizar', 'error');
                    return;
                }
                cargar();
            })
            .fail(function (xhr) {
                toast((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No se pudo actualizar la comanda', 'error');
            });
    }

    function programarAuto() {
        if (estado.timer) {
            window.clearInterval(estado.timer);
            estado.timer = null;
        }
        if (!$('#gck-auto').is(':checked')) {
            return;
        }
        var segundos = Math.max(3, parseInt(cfg.refrescoSegundos, 10) || 5);
        estado.timer = window.setInterval(cargar, segundos * 1000);
    }

    $(function () {
        if (!$('#gck-root').length) {
            return;
        }

        $('#gck-btn-refresh').on('click', cargar);
        $('#gck-auto').on('change', programarAuto);

        $('#gck-grid').on('click', '.gck-btn-estado', function () {
            var $card = $(this).closest('.gck-card');
            var id = parseInt($card.data('comanda-id'), 10);
            if (!id) {
                return;
            }
            cambiarEstado(id, $(this).data('estado'));
        });

        cargar();
        programarAuto();
    });
})(jQuery);
