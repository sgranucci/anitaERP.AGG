(function ($) {
    'use strict';

    var cfg = window.programaPagoCfg || { columnas: [], tieneTransf: false };
    var idxActivo = -1;
    /** @type {Object.<string, boolean>} */
    var mesesMarcados = {};

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

    function columnasAplicables() {
        return (cfg.columnas || []).slice();
    }

    function clavesMarcadas() {
        return columnasAplicables()
            .map(function (c) { return c.clave; })
            .filter(function (clave) { return !!mesesMarcados[clave]; });
    }

    function etiquetaClave(clave) {
        var col = columnasAplicables().find(function (c) { return c.clave === clave; });
        return col ? col.etiqueta : clave;
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
        actualizarPreviewProrrateo();
        resaltarTheadSeleccion();
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
        var completos = 0;
        filas().forEach(function ($tr) {
            var est = estadoFila($tr);
            if (est === 'pendiente') pendientes++;
            else if (est === 'completo') completos++;
        });
        $('#pp-progreso-texto').text(completos + ' OK / ' + total + ' · ' + pendientes + ' sin marcar');
    }

    function fijarPieGrilla() {
        var filasPie = document.querySelectorAll('#tabla-programa-pago tfoot tr');
        var acumulado = 0;
        var i;
        for (i = filasPie.length - 1; i >= 0; i--) {
            filasPie[i].style.setProperty('--pp-foot-bottom', acumulado + 'px');
            acumulado += filasPie[i].getBoundingClientRect().height;
        }
    }

    function filaVisible() {
        var filasDom = document.querySelectorAll('#tbody-programa-pago tr');
        var i;
        for (i = 0; i < filasDom.length; i++) {
            if (filasDom[i].offsetHeight > 0) {
                return filasDom[i];
            }
        }
        return null;
    }

    function ajustarAltoGrilla() {
        var wrap = document.querySelector('.pp-matriz-wrap');
        var thead = document.querySelector('#tabla-programa-pago thead');
        var tfoot = document.querySelector('#tabla-programa-pago tfoot');
        var filaEl = filaVisible();
        if (!wrap || !thead || !filaEl) {
            return;
        }
        var head = thead.getBoundingClientRect().height;
        var foot = tfoot ? tfoot.getBoundingClientRect().height : 0;
        var fila = filaEl.getBoundingClientRect().height;
        if (fila < 1) {
            return;
        }
        var topDocumento = wrap.getBoundingClientRect().top + (window.scrollY || document.documentElement.scrollTop || 0);
        var reserva = 8;
        var card = wrap.closest('.card');
        var cardFooter = card ? card.querySelector('.card-footer') : null;
        var body = wrap.closest('.card-body');
        if (cardFooter) {
            reserva += cardFooter.offsetHeight;
        }
        if (body) {
            reserva += parseFloat(window.getComputedStyle(body).paddingBottom) || 0;
        }
        var disponible = window.innerHeight - topDocumento - reserva;
        var n = Math.floor((disponible - head - foot - 18) / fila);
        if (n < 2) {
            n = 2;
        }
        function aplicarAlto(cant) {
            wrap.style.height = Math.ceil(head + foot + (cant * fila) + 2) + 'px';
            wrap.style.maxHeight = wrap.style.height;
            return wrap.clientHeight - head - foot;
        }
        var cuerpo = aplicarAlto(n);
        var nReal = Math.floor((cuerpo + 0.5) / fila);
        if (nReal >= 2 && nReal !== n) {
            cuerpo = aplicarAlto(nReal);
            nReal = Math.floor((cuerpo + 0.5) / fila);
        }
        var sobra = cuerpo - (nReal * fila);
        if (sobra > 1) {
            wrap.style.height = (parseFloat(wrap.style.height) - sobra) + 'px';
            wrap.style.maxHeight = wrap.style.height;
        }
        wrap.style.setProperty('--pp-head', head + 'px');
        wrap.style.setProperty('--pp-foot', foot + 'px');
        fijarPieGrilla();
        alinearScrollAFilas();
    }

    function alinearScrollAFilas() {
        var wrap = document.querySelector('.pp-matriz-wrap');
        var filaEl = filaVisible();
        if (!wrap || !filaEl) {
            return;
        }
        var fila = filaEl.getBoundingClientRect().height;
        if (fila < 1) {
            return;
        }
        var destino = Math.round(wrap.scrollTop / fila) * fila;
        var maximo = Math.max(0, wrap.scrollHeight - wrap.clientHeight);
        if (destino > maximo) {
            destino = Math.floor(maximo / fila) * fila;
        }
        if (Math.abs(wrap.scrollTop - destino) > 0.5) {
            wrap.scrollTop = destino;
        }
    }

    function scrollAFila($tr) {
        var wrap = document.querySelector('.pp-matriz-wrap');
        var row = $tr && $tr[0];
        var thead = document.querySelector('#tabla-programa-pago thead');
        var tfoot = document.querySelector('#tabla-programa-pago tfoot');
        if (!wrap || !row || !$tr.is(':visible') || !thead) {
            return;
        }
        var fila = row.getBoundingClientRect().height;
        if (fila < 1) {
            return;
        }
        var visibles = $('#tbody-programa-pago tr:visible').toArray();
        var idx = visibles.indexOf(row);
        if (idx < 0) {
            return;
        }
        var head = thead.getBoundingClientRect().height;
        var foot = tfoot ? tfoot.getBoundingClientRect().height : 0;
        var cupo = Math.max(1, Math.floor((wrap.clientHeight - head - foot + 0.5) / fila));
        var primero = Math.round(wrap.scrollTop / fila);
        var destino = primero * fila;
        if (idx < primero) {
            destino = idx * fila;
        } else if (idx >= primero + cupo) {
            destino = (idx - cupo + 1) * fila;
        }
        wrap.scrollTop = destino;
    }

    function setActivo(i, scroll) {
        var lista = filas();
        if (lista.length === 0) {
            idxActivo = -1;
            actualizarPanelAsistente();
            actualizarPreviewProrrateo();
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

    /**
     * Reparte saldo en partes iguales; el resto de centavos va al último.
     * @param {JQuery} $tr
     * @param {string[]} claves
     */
    function aplicarProrrateoIgual($tr, claves) {
        if (!$tr || !claves || !claves.length) {
            alert('Marcá al menos un período (mes o TRANSF).');
            return false;
        }
        var saldo = saldoFila($tr);
        var n = claves.length;
        var base = redondear(Math.floor((saldo / n) * 100) / 100);
        limpiarAsignaciones($tr);
        var acumulado = 0;
        for (var i = 0; i < n; i++) {
            var monto = (i === n - 1) ? redondear(saldo - acumulado) : base;
            setAsig($tr, claves[i], monto);
            acumulado = redondear(acumulado + monto);
        }
        recalcular();
        return true;
    }

    function aplicarTodoEnClave($tr, clave) {
        if (!$tr || !clave) {
            return false;
        }
        limpiarAsignaciones($tr);
        setAsig($tr, clave, saldoFila($tr));
        recalcular();
        return true;
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
        setActivo(idxActivo >= 0 ? idxActivo : 0);
    }

    function actualizarChipsUi() {
        $('#pp-meses-chips .pp-chip-mes').each(function () {
            var clave = $(this).data('clave');
            $(this).toggleClass('pp-chip-on', !!mesesMarcados[clave]);
            $(this).attr('aria-pressed', mesesMarcados[clave] ? 'true' : 'false');
        });
        actualizarPreviewProrrateo();
        resaltarTheadSeleccion();
    }

    function resaltarTheadSeleccion() {
        $('#tabla-programa-pago thead th[data-clave]').each(function () {
            var clave = $(this).data('clave');
            $(this).toggleClass('pp-th-seleccionada', !!mesesMarcados[clave]);
        });
    }

    function actualizarPreviewProrrateo() {
        var $tr = filaActiva();
        var claves = clavesMarcadas();
        var $prev = $('#pp-prorrateo-preview');
        if (!$prev.length) {
            return;
        }
        if (!claves.length) {
            $prev.text('Seleccioná uno o más períodos');
            return;
        }
        var nombres = claves.map(etiquetaClave).join(' + ');
        if (!$tr) {
            $prev.text(claves.length + ' período(s): ' + nombres);
            return;
        }
        var saldo = saldoFila($tr);
        if (claves.length === 1) {
            $prev.text('Todo → ' + nombres + ' = ' + fmt(saldo));
            return;
        }
        var base = redondear(Math.floor((saldo / claves.length) * 100) / 100);
        var ultimo = redondear(saldo - base * (claves.length - 1));
        $prev.text(
            claves.length + ' períodos (' + nombres + '): ~' + fmt(base) +
            ' c/u' + (ultimo !== base ? ' (último ' + fmt(ultimo) + ')' : '')
        );
    }

    function poblarChipsMeses() {
        var $wrap = $('#pp-meses-chips');
        if (!$wrap.length) {
            return;
        }
        $wrap.empty();
        columnasAplicables().forEach(function (c) {
            var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary pp-chip-mes"/>')
                .attr('data-clave', c.clave)
                .attr('aria-pressed', 'false')
                .text(c.etiqueta);
            $wrap.append($btn);
            mesesMarcados[c.clave] = false;
        });
    }

    function toggleMes(clave) {
        if (!clave) {
            return;
        }
        mesesMarcados[clave] = !mesesMarcados[clave];
        actualizarChipsUi();
    }

    function limpiarChips() {
        Object.keys(mesesMarcados).forEach(function (k) {
            mesesMarcados[k] = false;
        });
        actualizarChipsUi();
    }

    $(function () {
        if (!$('#tabla-programa-pago').length) {
            return;
        }

        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }

        poblarChipsMeses();

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

        $(document).on('click', '.pp-chip-mes', function () {
            toggleMes(String($(this).data('clave')));
        });

        // Clic en cabecera de mes también marca/desmarca
        $(document).on('click', '#tabla-programa-pago thead th[data-clave]', function () {
            toggleMes(String($(this).data('clave')));
        });

        $('#pp-solo-pendientes').on('change', function () {
            aplicarFiltroVista();
            alinearScrollAFilas();
        });
        var wrapGrilla = document.querySelector('.pp-matriz-wrap');
        if (wrapGrilla) {
            wrapGrilla.addEventListener('scrollend', alinearScrollAFilas);
        }

        $('#pp-btn-limpiar-chips').on('click', limpiarChips);

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

        $('#pp-btn-partir-igual').on('click', function () {
            var $tr = filaActiva();
            if (!$tr) {
                alert('Seleccioná un proveedor.');
                return;
            }
            aplicarProrrateoIgual($tr, clavesMarcadas());
        });

        $('#pp-btn-todo-seleccion').on('click', function () {
            var $tr = filaActiva();
            var claves = clavesMarcadas();
            if (!$tr) {
                alert('Seleccioná un proveedor.');
                return;
            }
            if (!claves.length) {
                alert('Marcá el período de destino.');
                return;
            }
            if (claves.length > 1 && !confirm('Hay ' + claves.length + ' períodos marcados. ¿Poner TODO el saldo en el primero (' + etiquetaClave(claves[0]) + ')?')) {
                return;
            }
            aplicarTodoEnClave($tr, claves[0]);
        });

        $('#pp-btn-partir-y-seguir').on('click', function () {
            var $tr = filaActiva();
            if (!$tr) {
                alert('Seleccioná un proveedor.');
                return;
            }
            if (!aplicarProrrateoIgual($tr, clavesMarcadas())) {
                return;
            }
            irSiguientePendiente();
        });

        $('#pp-btn-asistente-saltar').on('click', function () {
            setActivo(idxActivo + 1);
        });

        function confirmarAsignarCheques(soloActivo) {
            var msg = soloActivo
                ? '¿Asignar cheques en cartera diferidos al proveedor activo?\n\nUsa los montos YA GUARDADOS (Guardá antes si hay cambios). Tolerancia ±30%.'
                : '¿Asignar cheques en cartera diferidos a TODOS los proveedores programados?\n\nUsa los montos YA GUARDADOS. Tolerancia ±30%. Reemplaza asignaciones previas de esas líneas.';
            if (!confirm(msg)) {
                return;
            }
            var $form = $('#form-asignar-cheques-pp');
            if (!$form.length) {
                alert('No se puede asignar cheques en este momento.');
                return;
            }
            if (soloActivo) {
                var $tr = filaActiva();
                if (!$tr) {
                    alert('Seleccioná un proveedor.');
                    return;
                }
                var lineaId = parseInt($tr.data('linea-id') || '0', 10);
                if (!lineaId) {
                    alert('Guardá el programa antes de asignar cheques a un proveedor nuevo.');
                    return;
                }
                $('#pp-asignar-linea-id').val(String(lineaId));
            } else {
                $('#pp-asignar-linea-id').val('');
            }
            $form.trigger('submit');
        }

        $('#pp-btn-asignar-cheques-activo').on('click', function () {
            confirmarAsignarCheques(true);
        });

        $('#pp-btn-asignar-cheques-todos').on('click', function () {
            confirmarAsignarCheques(false);
        });

        if (cfg.mostrarResultadoAsignacion && $('#ppAsignacionChequesModal').length) {
            $('#ppAsignacionChequesModal').modal('show');
        }

        $('#form-agregar-proveedor-pp').on('submit', function (e) {
            var id = parseInt($('#proveedor_id').val() || '0', 10);
            if (!id) {
                e.preventDefault();
                alert('Seleccione un proveedor válido (código + Enter o lupa).');
                $('#codigoproveedor').focus();
            }
        });

        recalcular();
        ajustarAltoGrilla();
        window.addEventListener('resize', ajustarAltoGrilla);
        window.addEventListener('load', ajustarAltoGrilla);
        irSiguientePendiente();
        if (idxActivo < 0 && filas().length) {
            setActivo(0);
        }
    });
})(jQuery);
