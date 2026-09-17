(function ($) {
    'use strict';

    var cfg = window.programaPagoCfg || { columnas: [], tieneTransf: false };
    var idxActivo = -1;

    function parseMonto(val) {
        var n = parseFloat(String(val || '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function fmt(n) {
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function redondear(n) {
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    function filas() {
        return $('#tbody-programa-pago tr').toArray().map(function (el) {
            return $(el);
        });
    }

    function saldoFila($tr) {
        return parseMonto($tr.find('input[name$="[saldo_adeudado]"]').val());
    }

    function totalFila($tr) {
        var suma = 0;
        $tr.find('.pp-asig').each(function () {
            suma += parseMonto($(this).val());
        });
        return redondear(suma);
    }

    function setAsig($tr, clave, monto) {
        $tr.find('.pp-asig[data-clave="' + clave + '"]').val(redondear(monto).toFixed(2));
    }

    function limpiarAsignaciones($tr) {
        $tr.find('.pp-asig').val('0');
    }

    function estadoFila($tr) {
        var saldo = saldoFila($tr);
        var tot = totalFila($tr);
        if (Math.abs(tot) < 0.005) {
            return 'pendiente';
        }
        if (Math.abs(tot - saldo) < 0.05) {
            return 'completo';
        }
        return 'parcial';
    }

    function aplicarFiltroVista() {
        var soloPendientes = $('#pp-solo-pendientes').is(':checked');
        filas().forEach(function ($tr) {
            if (!soloPendientes) {
                $tr.show();
                return;
            }
            // Mantener visible la fila activa aunque esté completa
            var i = $tr.index();
            if (i === idxActivo || estadoFila($tr) !== 'completo') {
                $tr.show();
            } else {
                $tr.hide();
            }
        });
    }

    function actualizarEstilosFilas() {
        filas().forEach(function ($tr, i) {
            var est = estadoFila($tr);
            $tr.removeClass('pp-fila-pendiente pp-fila-parcial pp-fila-completo pp-fila-activa');
            $tr.addClass('pp-fila-' + est);
            if (i === idxActivo) {
                $tr.addClass('pp-fila-activa');
            }
            var $badge = $tr.find('.pp-badge-estado');
            if ($badge.length) {
                $badge
                    .removeClass('badge-secondary badge-warning badge-success')
                    .addClass(est === 'completo' ? 'badge-success' : (est === 'parcial' ? 'badge-warning' : 'badge-secondary'))
                    .text(est === 'completo' ? 'OK' : (est === 'parcial' ? 'Parcial' : 'Pendiente'));
            }
        });
        aplicarFiltroVista();
        actualizarProgreso();
        actualizarPanelAsistente();
    }

    function recalcular() {
        var totales = {};
        var totalPrograma = 0;
        var totalSaldo = 0;

        filas().forEach(function ($tr) {
            var suma = 0;
            $tr.find('.pp-asig').each(function () {
                var clave = $(this).data('clave');
                var monto = parseMonto($(this).val());
                suma += monto;
                totales[clave] = (totales[clave] || 0) + monto;
            });
            $tr.find('.pp-total-fila').text(fmt(suma));
            totalPrograma += suma;
            totalSaldo += saldoFila($tr);
        });

        $('.pp-total-col').each(function () {
            var clave = $(this).data('clave');
            $(this).text(fmt(totales[clave] || 0));
        });
        $('#pp-total-programa').text(fmt(totalPrograma));
        $('#pp-total-saldo').text(fmt(totalSaldo));
        actualizarEstilosFilas();
    }

    function reindexarLineas() {
        $('#tbody-programa-pago tr').each(function (idx) {
            $(this).find('input[name^="lineas["]').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                $(this).attr('name', name.replace(/^lineas\[\d+]/, 'lineas[' + idx + ']'));
            });
        });
    }

    function actualizarProgreso() {
        var total = filas().length;
        var pendientes = 0;
        var parciales = 0;
        var completos = 0;
        filas().forEach(function ($tr) {
            var est = estadoFila($tr);
            if (est === 'pendiente') pendientes++;
            else if (est === 'parcial') parciales++;
            else completos++;
        });
        $('#pp-progreso-texto').text(completos + ' OK / ' + total + ' · ' + pendientes + ' sin marcar');
        var pct = total ? Math.round((completos / total) * 100) : 0;
        $('#pp-progreso-barra').css('width', pct + '%').attr('aria-valuenow', pct).text(pct + '%');
    }

    function scrollAFila($tr) {
        if (!$tr || !$tr.length || !$tr[0].scrollIntoView) {
            return;
        }
        $tr[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function setActivo(i, scroll) {
        var lista = filas();
        if (lista.length === 0) {
            idxActivo = -1;
            actualizarPanelAsistente();
            return;
        }
        if (i < 0) {
            i = lista.length - 1;
        }
        if (i >= lista.length) {
            i = 0;
        }
        idxActivo = i;
        actualizarEstilosFilas();
        if (scroll !== false) {
            scrollAFila(lista[idxActivo]);
        }
    }

    function filaActiva() {
        var lista = filas();
        if (idxActivo < 0 || idxActivo >= lista.length) {
            return null;
        }
        return lista[idxActivo];
    }

    function nombreFila($tr) {
        return $.trim($tr.find('.pp-nombre-proveedor').text()) || '—';
    }

    function actualizarPanelAsistente() {
        var $tr = filaActiva();
        if (!$tr) {
            $('#pp-asistente-proveedor').text('—');
            $('#pp-asistente-saldo').text('—');
            $('#pp-asistente-asignado').text('—');
            $('#pp-asistente-resto').text('—');
            $('#pp-asistente-pos').text('—');
            return;
        }
        var saldo = saldoFila($tr);
        var tot = totalFila($tr);
        $('#pp-asistente-proveedor').text(nombreFila($tr));
        $('#pp-asistente-saldo').text(fmt(saldo));
        $('#pp-asistente-asignado').text(fmt(tot));
        $('#pp-asistente-resto').text(fmt(redondear(saldo - tot)));
        $('#pp-asistente-pos').text('Proveedor ' + (idxActivo + 1) + ' de ' + filas().length);
    }

    function aplicarTodoEnClave($tr, clave) {
        if (!$tr || !clave) {
            return;
        }
        limpiarAsignaciones($tr);
        setAsig($tr, clave, saldoFila($tr));
        recalcular();
    }

    function aplicar5050($tr, claveA, claveB) {
        if (!$tr || !claveA || !claveB || claveA === claveB) {
            alert('Elija dos meses distintos para el 50/50.');
            return;
        }
        var saldo = saldoFila($tr);
        var mitad = redondear(saldo / 2);
        limpiarAsignaciones($tr);
        setAsig($tr, claveA, mitad);
        setAsig($tr, claveB, redondear(saldo - mitad));
        recalcular();
    }

    function irSiguientePendiente() {
        var lista = filas();
        if (!lista.length) {
            return;
        }
        var start = idxActivo < 0 ? 0 : idxActivo + 1;
        for (var i = 0; i < lista.length; i++) {
            var j = (start + i) % lista.length;
            if (estadoFila(lista[j]) !== 'completo') {
                setActivo(j);
                return;
            }
        }
        // Todos OK: quedarse en el actual o el primero
        setActivo(idxActivo >= 0 ? idxActivo : 0);
    }

    function marcarYSeguir(fn) {
        var $tr = filaActiva();
        if (!$tr) {
            alert('No hay proveedor seleccionado.');
            return;
        }
        fn($tr);
        irSiguientePendiente();
    }

    function poblarSelectsMeses() {
        var meses = (cfg.columnas || []).filter(function (c) { return c.anio_mes; });
        var $a = $('#pp-mes-a');
        var $b = $('#pp-mes-b');
        var $todo = $('#pp-mes-todo');
        [$a, $b, $todo].forEach(function ($sel) {
            $sel.empty();
            meses.forEach(function (c) {
                $sel.append($('<option/>').val(c.clave).text(c.etiqueta));
            });
        });
        if (meses.length >= 2) {
            $a.val(meses[0].clave);
            $b.val(meses[1].clave);
            $todo.val(meses[0].clave);
        } else if (meses.length === 1) {
            $a.val(meses[0].clave);
            $b.val(meses[0].clave);
            $todo.val(meses[0].clave);
        }
        if (cfg.tieneTransf) {
            $todo.prepend($('<option/>').val('transf').text('TRANSF'));
            $todo.val('transf');
        }
    }

    $(function () {
        if (!$('#tabla-programa-pago').length) {
            return;
        }

        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }

        poblarSelectsMeses();

        $(document).on('input change', '.pp-asig, .pp-obs', recalcular);

        $(document).on('click', '#tbody-programa-pago tr', function () {
            setActivo($(this).index(), false);
        });

        $(document).on('focus', '#tbody-programa-pago input', function () {
            setActivo($(this).closest('tr').index(), false);
        });

        $(document).on('click', '.pp-quitar-fila', function (e) {
            e.stopPropagation();
            if (!confirm('¿Quitar este proveedor del programa?')) {
                return;
            }
            $(this).closest('tr').remove();
            reindexarLineas();
            idxActivo = -1;
            recalcular();
            if (filas().length) {
                setActivo(0);
            }
        });

        $('#pp-solo-pendientes').on('change', aplicarFiltroVista);

        $('#pp-btn-limpiar').on('click', function () {
            var $tr = filaActiva();
            if (!$tr) {
                return;
            }
            limpiarAsignaciones($tr);
            recalcular();
        });

        $('#pp-btn-prev').on('click', function () {
            setActivo(idxActivo - 1);
        });

        $('#pp-btn-next').on('click', function () {
            setActivo(idxActivo + 1);
        });

        $('#pp-btn-asistente-transf').on('click', function () {
            if (!cfg.tieneTransf) {
                return;
            }
            marcarYSeguir(function ($tr) {
                aplicarTodoEnClave($tr, 'transf');
            });
        });

        $('#pp-btn-asistente-mes').on('click', function () {
            var clave = $('#pp-mes-todo').val();
            marcarYSeguir(function ($tr) {
                aplicarTodoEnClave($tr, clave);
            });
        });

        $('#pp-btn-asistente-5050').on('click', function () {
            var a = $('#pp-mes-a').val();
            var b = $('#pp-mes-b').val();
            marcarYSeguir(function ($tr) {
                aplicar5050($tr, a, b);
            });
        });

        $('#pp-btn-asistente-saltar').on('click', function () {
            setActivo(idxActivo + 1);
        });

        $('#form-agregar-proveedor-pp').on('submit', function (e) {
            var id = parseInt($('#proveedor_id').val() || '0', 10);
            if (!id) {
                e.preventDefault();
                alert('Seleccione un proveedor válido (código + Enter o lupa).');
                $('#codigoproveedor').focus();
            }
        });

        recalcular();
        irSiguientePendiente();
        if (idxActivo < 0 && filas().length) {
            setActivo(0);
        }
    });
})(jQuery);
