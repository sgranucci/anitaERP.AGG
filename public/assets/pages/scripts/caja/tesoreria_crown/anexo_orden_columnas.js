/**
 * Orden de columnas en anexos Crown (solo pantalla).
 * Clic en el encabezado ordena el bloque de filas; "Quitar orden" restaura el original.
 */
(function ($) {
    'use strict';

    function esThOrdenable($th) {
        var colspan = parseInt($th.attr('colspan') || '1', 10);
        if (colspan > 1) {
            return false;
        }
        var texto = $.trim($th.text());
        return texto !== '';
    }

    function indiceColumna($th) {
        var marcado = $th.attr('data-tc-col');
        if (marcado !== undefined && marcado !== '') {
            return parseInt(marcado, 10) || 0;
        }
        var $tr = $th.closest('tr');
        var idx = 0;
        $tr.children('th').each(function () {
            if (this === $th[0]) {
                return false;
            }
            idx += parseInt($(this).attr('colspan') || '1', 10);
        });
        return idx;
    }

    /** Asigna data-tc-col según la grilla visual (soporta rowspan/colspan). */
    function asignarIndicesColumnas($tabla) {
        var grid = [];
        $tabla.find('thead tr').each(function (r) {
            if (! grid[r]) {
                grid[r] = [];
            }
            var c = 0;
            $(this).children('th').each(function () {
                while (grid[r][c]) {
                    c += 1;
                }
                var $th = $(this);
                var cs = parseInt($th.attr('colspan') || '1', 10);
                var rs = parseInt($th.attr('rowspan') || '1', 10);
                if (cs === 1 && esThOrdenable($th)) {
                    $th.attr('data-tc-col', String(c));
                }
                var rr;
                var cc;
                for (rr = 0; rr < rs; rr += 1) {
                    if (! grid[r + rr]) {
                        grid[r + rr] = [];
                    }
                    for (cc = 0; cc < cs; cc += 1) {
                        grid[r + rr][c + cc] = true;
                    }
                }
                c += cs;
            });
        });
    }

    function valorCelda($tr, colIdx) {
        var $td = $tr.children('td').eq(colIdx);
        if (! $td.length) {
            return '';
        }
        var raw = $.trim($td.text()).replace(/\s+/g, ' ');
        var num = raw.replace(/\./g, '').replace(',', '.').replace(/[^\d.\-]/g, '');
        if (num !== '' && /^-?\d+(\.\d+)?$/.test(num)) {
            return { tipo: 'n', valor: parseFloat(num) };
        }
        return { tipo: 't', valor: raw.toLowerCase() };
    }

    function comparar(a, b, dir) {
        if (a.tipo === 'n' && b.tipo === 'n') {
            if (a.valor === b.valor) {
                return 0;
            }
            return (a.valor < b.valor ? -1 : 1) * dir;
        }
        if (a.valor === b.valor) {
            return 0;
        }
        return (a.valor < b.valor ? -1 : 1) * dir;
    }

    function marcarOriginal($tbody) {
        $tbody.children('tr').each(function (i) {
            var $tr = $(this);
            if ($tr.attr('data-tc-orden-orig') === undefined) {
                $tr.attr('data-tc-orden-orig', String(i));
            }
        });
    }

    function actualizarBoton($tabla) {
        var $wrap = $tabla.closest('.tc-orden-wrap');
        var $btn = $wrap.find('.js-tc-quitar-orden');
        if (! $btn.length) {
            return;
        }
        if ($tabla.attr('data-tc-ordenado') === '1') {
            $btn.removeClass('d-none');
        } else {
            $btn.addClass('d-none');
        }
    }

    function ordenarTabla($tabla, colIdx, dir) {
        var $tbody = $tabla.children('tbody');
        if (! $tbody.length) {
            return;
        }
        marcarOriginal($tbody);
        var children = $tbody.children('tr').toArray();
        var resultado = [];
        var bloque = [];

        function vaciarBloque() {
            if (! bloque.length) {
                return;
            }
            bloque.sort(function (ra, rb) {
                return comparar(valorCelda($(ra), colIdx), valorCelda($(rb), colIdx), dir);
            });
            resultado = resultado.concat(bloque);
            bloque = [];
        }

        children.forEach(function (tr) {
            if ($(tr).is('[data-tc-fija="1"]')) {
                vaciarBloque();
                resultado.push(tr);
                return;
            }
            bloque.push(tr);
        });
        vaciarBloque();
        $tbody.append(resultado);

        $tabla.attr('data-tc-ordenado', '1');
        $tabla.find('thead th').removeClass('tc-th-sorted-asc tc-th-sorted-desc');
        $tabla.find('thead th').each(function () {
            var $th = $(this);
            if (! esThOrdenable($th)) {
                return;
            }
            if (indiceColumna($th) === colIdx) {
                $th.addClass(dir === 1 ? 'tc-th-sorted-asc' : 'tc-th-sorted-desc');
            }
        });
        actualizarBoton($tabla);
    }

    function restaurarTabla($tabla) {
        var $tbody = $tabla.children('tbody');
        if (! $tbody.length) {
            return;
        }
        var filas = $tbody.children('tr').get();
        filas.sort(function (a, b) {
            return (parseInt($(a).attr('data-tc-orden-orig') || '0', 10) || 0)
                - (parseInt($(b).attr('data-tc-orden-orig') || '0', 10) || 0);
        });
        $tbody.append(filas);
        $tabla.removeAttr('data-tc-ordenado');
        $tabla.find('thead th').removeClass('tc-th-sorted-asc tc-th-sorted-desc');
        actualizarBoton($tabla);
    }

    function prepararTabla($tabla) {
        if ($tabla.data('tc-orden-listo')) {
            return;
        }
        $tabla.data('tc-orden-listo', true);
        marcarOriginal($tabla.children('tbody'));
        asignarIndicesColumnas($tabla);

        $tabla.find('thead th').each(function () {
            var $th = $(this);
            if (! esThOrdenable($th) || $th.attr('data-tc-col') === undefined) {
                return;
            }
            $th.addClass('tc-th-ordenable');
            if (! $th.find('.tc-th-sort-icon').length) {
                $th.append(' <i class="fa fa-sort tc-th-sort-icon" aria-hidden="true"></i>');
            }
            $th.attr('title', ($th.attr('title') || $.trim($th.clone().children('.tc-th-sort-icon').remove().end().text())) + ' — clic para ordenar');
        });
    }

    $(function () {
        $('.tc-tabla-ordenable').each(function () {
            prepararTabla($(this));
        });

        $(document).on('click', '.tc-tabla-ordenable thead th.tc-th-ordenable', function (e) {
            e.preventDefault();
            var $th = $(this);
            var $tabla = $th.closest('table');
            var colIdx = indiceColumna($th);
            var dir = $th.hasClass('tc-th-sorted-asc') ? -1 : 1;
            ordenarTabla($tabla, colIdx, dir);
        });

        $(document).on('click', '.js-tc-quitar-orden', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.tc-orden-wrap');
            $wrap.find('.tc-tabla-ordenable').each(function () {
                restaurarTabla($(this));
            });
        });
    });
})(jQuery);
