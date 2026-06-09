$(function () {
    var $tabla = $('#tabla-waitry-conciliacion');
    if ($tabla.length === 0 || $.fn.DataTable.isDataTable($tabla)) {
        return;
    }

    var filtroEstadosActivos = null;
    var filtroCircuitoActivo = null;
    var filtroEtiquetaActiva = '';
    var filtroSearchIndex = null;

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) {
            return '$ 0,00';
        }
        return '$ ' + x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function leerMontoData($row, attr) {
        var raw = $row.attr(attr);
        if (raw === undefined || raw === null || String(raw).trim() === '') {
            return null;
        }
        var n = parseFloat(raw);
        return isNaN(n) ? null : n;
    }

    function parsearEstadosDesdeBadge($badge) {
        var raw = ($badge.attr('data-filtro-estados') || '').trim();
        if (raw === '') {
            return [];
        }
        return raw.split(',').map(function (e) {
            return e.trim();
        }).filter(function (e) {
            return e !== '';
        });
    }

    function hayFiltroActivo() {
        return (filtroCircuitoActivo && filtroCircuitoActivo.length > 0)
            || (filtroEstadosActivos && filtroEstadosActivos.length > 0);
    }

    function parsearCircuitosDesdeBadge($badge) {
        var raw = ($badge.attr('data-filtro-circuito') || '').trim();
        if (raw === '') {
            return [];
        }
        return raw.split(',').map(function (e) {
            return e.trim();
        }).filter(function (e) {
            return e !== '';
        });
    }

    function filaCoincideFiltros(estadoFila, circuitoFila) {
        if (filtroCircuitoActivo && filtroCircuitoActivo.length > 0) {
            return filtroCircuitoActivo.indexOf(String(circuitoFila || '')) !== -1;
        }
        if (filtroEstadosActivos && filtroEstadosActivos.length > 0) {
            return filtroEstadosActivos.indexOf(estadoFila) !== -1;
        }
        return true;
    }

    function registrarFiltroDataTable() {
        if (filtroSearchIndex !== null) {
            return;
        }
        filtroSearchIndex = $.fn.dataTable.ext.search.length;
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabla-waitry-conciliacion') {
                return true;
            }
            if (!hayFiltroActivo()) {
                return true;
            }
            var api = new $.fn.dataTable.Api(settings);
            var nodo = api.row(dataIndex).node();
            if (!nodo) {
                return false;
            }
            var $row = $(nodo);
            var estado = String($row.data('estado') || '');
            var circuito = String($row.data('circuito') || '');
            return filaCoincideFiltros(estado, circuito);
        });
    }

    function calcularTotalesFilasVisibles(dt) {
        var totales = {
            filas: 0,
            waitry: 0,
            anita: 0,
            diferencia: 0,
            tieneWaitry: false,
            tieneAnita: false,
            tieneDiferencia: false,
        };

        dt.rows({ search: 'applied' }).every(function () {
            var nodo = this.node();
            if (!nodo) {
                return;
            }
            var $row = $(nodo);
            totales.filas++;

            var waitry = leerMontoData($row, 'data-waitry-total');
            if (waitry !== null) {
                totales.waitry = Math.round((totales.waitry + waitry) * 100) / 100;
                totales.tieneWaitry = true;
            }

            var anita = leerMontoData($row, 'data-anita-total');
            if (anita !== null) {
                totales.anita = Math.round((totales.anita + anita) * 100) / 100;
                totales.tieneAnita = true;
            }

            var dif = leerMontoData($row, 'data-diferencia');
            if (dif !== null) {
                totales.diferencia = Math.round((totales.diferencia + dif) * 100) / 100;
                totales.tieneDiferencia = true;
            }
        });

        return totales;
    }

    function actualizarTotalesFiltro(dt) {
        var $panel = $('#waitry-conciliacion-totales-filtro');
        if ($panel.length === 0) {
            return;
        }

        if (!hayFiltroActivo()) {
            $panel.addClass('d-none');
            return;
        }

        var totales = calcularTotalesFilasVisibles(dt);
        $('#waitry-conciliacion-totales-etiqueta').text(filtroEtiquetaActiva);
        $('#waitry-conciliacion-totales-filas').text(String(totales.filas));
        $('#waitry-conciliacion-totales-waitry').text(
            totales.tieneWaitry ? fmtMoney(totales.waitry) : '—',
        );
        $('#waitry-conciliacion-totales-anita').text(
            totales.tieneAnita ? fmtMoney(totales.anita) : '—',
        );
        $('#waitry-conciliacion-totales-diferencia').text(
            totales.tieneDiferencia ? fmtMoney(totales.diferencia) : '—',
        );
        $panel.removeClass('d-none');
    }

    function actualizarUiFiltro(dt) {
        var $badgesEstado = $('#waitry-conciliacion-filtros .js-filtro-estado-conciliacion');
        var $badgesCircuito = $('.js-filtro-circuito-conciliacion');
        var $circuitosBox = $('#waitry-conciliacion-circuitos .js-filtro-circuito-conciliacion');

        $badgesEstado.removeClass('filtro-activo');
        $badgesCircuito.removeClass('filtro-activo');
        $circuitosBox.removeClass('filtro-activo');

        if (filtroCircuitoActivo && filtroCircuitoActivo.length > 0) {
            $badgesCircuito.each(function () {
                var circuitosBadge = parsearCircuitosDesdeBadge($(this));
                var coincide = circuitosBadge.some(function (c) {
                    return filtroCircuitoActivo.indexOf(c) !== -1;
                });
                if (coincide) {
                    $(this).addClass('filtro-activo');
                }
            });
        } else if (filtroEstadosActivos && filtroEstadosActivos.length > 0) {
            $badgesEstado.each(function () {
                var $b = $(this);
                var estadosBadge = parsearEstadosDesdeBadge($b);
                var coincide = estadosBadge.some(function (e) {
                    return filtroEstadosActivos.indexOf(e) !== -1;
                });
                if (coincide) {
                    $b.addClass('filtro-activo');
                }
            });
        }

        var $aviso = $('#waitry-conciliacion-filtro-aviso');
        if (!hayFiltroActivo()) {
            $aviso.addClass('d-none');
            actualizarTotalesFiltro(dt);
            return;
        }

        var visibles = dt.rows({ search: 'applied' }).count();
        $('#waitry-conciliacion-filtro-etiqueta').text(filtroEtiquetaActiva);
        $('#waitry-conciliacion-filtro-visible').text(String(visibles));
        $aviso.removeClass('d-none');
        actualizarTotalesFiltro(dt);
    }

    function aplicarFiltroEstado(dt, estados, etiqueta) {
        filtroEstadosActivos = estados && estados.length > 0 ? estados : null;
        filtroCircuitoActivo = null;
        filtroEtiquetaActiva = etiqueta || '';
        dt.draw();
        actualizarUiFiltro(dt);
    }

    function aplicarFiltroCircuito(dt, circuitos, etiqueta) {
        filtroCircuitoActivo = circuitos && circuitos.length > 0 ? circuitos : null;
        filtroEstadosActivos = null;
        filtroEtiquetaActiva = etiqueta || '';
        dt.draw();
        actualizarUiFiltro(dt);
    }

    function limpiarFiltro(dt) {
        filtroEstadosActivos = null;
        filtroCircuitoActivo = null;
        filtroEtiquetaActiva = '';
        dt.draw();
        actualizarUiFiltro(dt);
    }

    function alternarFiltroDesdeBadgeEstado($badge, dt) {
        var count = parseInt($badge.attr('data-filtro-count') || '0', 10);
        if (count <= 0) {
            return;
        }

        var estados = parsearEstadosDesdeBadge($badge);
        var etiqueta = $badge.attr('data-filtro-etiqueta') || '';
        var yaActivo = $badge.hasClass('filtro-activo');

        if (yaActivo) {
            limpiarFiltro(dt);
            return;
        }

        aplicarFiltroEstado(dt, estados, etiqueta);
    }

    function alternarFiltroDesdeCircuito($el, dt) {
        var count = parseInt($el.attr('data-filtro-count') || '0', 10);
        if (count <= 0) {
            return;
        }

        var circuitos = parsearCircuitosDesdeBadge($el);
        var etiqueta = $el.attr('data-filtro-etiqueta') || '';
        var yaActivo = $el.hasClass('filtro-activo');

        if (yaActivo) {
            limpiarFiltro(dt);
            return;
        }

        aplicarFiltroCircuito(dt, circuitos, etiqueta);
    }

    registrarFiltroDataTable();

    var dt = $tabla.DataTable({
        processing: true,
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        order: [[0, 'desc']],
        info: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todo']],
        language: typeof idioma !== 'undefined' ? idioma : undefined,
        columnDefs: [
            { targets: [3, 6, 10], className: 'text-right' },
        ],
    });

    $('#waitry-conciliacion-filtros').on('click', '.js-filtro-estado-conciliacion', function (e) {
        e.preventDefault();
        alternarFiltroDesdeBadgeEstado($(this), dt);
    });

    $('#waitry-conciliacion-filtros').on('keydown', '.js-filtro-estado-conciliacion', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            alternarFiltroDesdeBadgeEstado($(this), dt);
        }
    });

    $('#waitry-conciliacion-filtros-circuitos, #waitry-conciliacion-circuitos').on(
        'click',
        '.js-filtro-circuito-conciliacion',
        function (e) {
            e.preventDefault();
            alternarFiltroDesdeCircuito($(this), dt);
        },
    );

    $('#waitry-conciliacion-filtros-circuitos, #waitry-conciliacion-circuitos').on(
        'keydown',
        '.js-filtro-circuito-conciliacion',
        function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                alternarFiltroDesdeCircuito($(this), dt);
            }
        },
    );

    $('#waitry-conciliacion-limpiar-filtro').on('click', function (e) {
        e.preventDefault();
        limpiarFiltro(dt);
    });

    dt.on('draw', function () {
        if (hayFiltroActivo()) {
            var visibles = dt.rows({ search: 'applied' }).count();
            $('#waitry-conciliacion-filtro-visible').text(String(visibles));
            actualizarTotalesFiltro(dt);
        }
    });

    $('#waitry-auditoria-conciliacion-collapse').on('shown.bs.collapse', function () {
        dt.columns.adjust().draw(false);
    });
});
