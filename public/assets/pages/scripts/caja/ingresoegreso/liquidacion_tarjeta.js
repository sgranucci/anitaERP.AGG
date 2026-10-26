/**
 * Planilla de liquidación de tarjeta: arma un DTC y deja la caja por el neto.
 */
(function ($) {
    'use strict';

    var catalogo = { filas: [], tarjetas: [], cuits: [], tipotransaccion_compra_id: 0, cuenta_comisiones: null };
    var ivaManual = {};

    function leerCatalogo() {
        var el = document.getElementById('ie-liquidacion-tarjeta');
        if (!el) {
            return;
        }
        try {
            var data = JSON.parse(el.textContent || '{}');
            if (data && typeof data === 'object') {
                catalogo = data;
            }
        } catch (err) {
            catalogo = { filas: [], tarjetas: [], cuits: [] };
        }
    }

    function parseMonto(valor) {
        if (window.AsientoMontosFormato && typeof AsientoMontosFormato.parseDecimal === 'function') {
            return AsientoMontosFormato.parseDecimal(valor);
        }
        var n = parseFloat(String(valor || '').replace(/\./g, '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function formato(n) {
        var x = Math.round((parseFloat(n) || 0) * 100) / 100;
        var partes = x.toFixed(2).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return partes.join(',');
    }

    function soloDigitos(valor) {
        return String(valor || '').replace(/\D+/g, '');
    }

    function filaPorClave(clave) {
        var $input = $('#ie-lt-conceptos input[data-clave="' + clave + '"]');
        return $input.length ? $input : null;
    }

    function pintarTabla() {
        var $netos = $('#ie-lt-tbody-netos').empty();
        var $puestos = $('#ie-lt-tbody-puestos').empty();
        (catalogo.filas || []).forEach(function (fila) {
            var falta = !fila.concepto_id;
            var $tr = $('<tr></tr>');
            var $tdNombre = $('<td></td>').text(fila.etiqueta || fila.nombre || '');
            if (falta) {
                $tdNombre.append(' <span class="badge badge-warning">falta en el maestro</span>');
            }
            var $input = $('<input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm text-right ie-lt-importe">');
            $input.attr('data-clave', fila.clave);
            $input.attr('data-concepto-id', fila.concepto_id || 0);
            $input.attr('data-base', fila.base || '');
            $input.attr('data-tasa', fila.tasa || 0);
            $input.attr('data-cuenta-id', fila.cuenta_debe_id || 0);
            if (falta) {
                $input.prop('disabled', true);
            }
            $tr.append($tdNombre);
            $tr.append($('<td></td>').append($input));
            (fila.columna === 'puestos' ? $puestos : $netos).append($tr);
        });
        [$netos, $puestos].forEach(function ($tbody) {
            if (!$tbody.children().length) {
                $tbody.append('<tr><td colspan="2" class="text-muted small">Sin conceptos</td></tr>');
            }
        });
    }

    function recalcular() {
        var bruto = parseMonto($('#ie-lt-bruto').val());
        var gastos = 0;
        var netos = 0;
        var puestos = 0;
        $('#ie-lt-conceptos .ie-lt-importe').each(function () {
            var clave = $(this).attr('data-clave');
            var base = $(this).attr('data-base') || '';
            var tasa = parseFloat($(this).attr('data-tasa') || '0') || 0;
            if (base && tasa > 0 && !ivaManual[clave]) {
                var $base = filaPorClave(base);
                var calculado = $base ? Math.round(parseMonto($base.val()) * tasa * 100) / 100 : 0;
                $(this).val(calculado > 0 ? formato(calculado) : '');
            }
            var monto = parseMonto($(this).val());
            gastos += monto;
            if ($(this).closest('#ie-lt-tbody-puestos').length) {
                puestos += monto;
            } else {
                netos += monto;
            }
        });
        gastos = Math.round(gastos * 100) / 100;
        netos = Math.round(netos * 100) / 100;
        puestos = Math.round(puestos * 100) / 100;
        var cobrado = Math.round((bruto - gastos) * 100) / 100;
        $('#ie-lt-total-netos').text(formato(netos));
        $('#ie-lt-total-puestos').text(formato(puestos));
        $('#ie-lt-gastos').text(formato(gastos));
        $('#ie-lt-cobrado').text(formato(cobrado));
        return { bruto: bruto, gastos: gastos, cobrado: cobrado };
    }

    function tarjetaPorId(id) {
        id = parseInt(id || '0', 10) || 0;
        var hallada = null;
        (catalogo.tarjetas || []).forEach(function (tarjeta) {
            if (parseInt(tarjeta.id, 10) === id) {
                hallada = tarjeta;
            }
        });
        return hallada;
    }

    function sugerirEmisor() {
        var id = parseInt($('#ie-lt-cuentacaja-id').val() || '0', 10) || 0;
        var tarjeta = tarjetaPorId(id);
        var nombre = $.trim($('#ie-lt-cuentacaja-id_nombre').val() || '');
        if (tarjeta) {
            nombre = tarjeta.nombre || nombre;
            var cuenta = tarjeta.cuentacontable_codigo
                ? (tarjeta.cuentacontable_codigo + ' ' + tarjeta.cuentacontable_nombre)
                : 'esta cuenta no tiene cuenta contable';
            $('#ie-lt-cuenta-haber').text('Los cupones se acreditan en ' + cuenta + '.');
        } else if (nombre) {
            $('#ie-lt-cuenta-haber').text('Los cupones se acreditan en la cuenta contable de ' + nombre + '.');
        } else {
            $('#ie-lt-cuenta-haber').text('');
        }
        if (!$('#ie-lt-nombre').data('tocado') && nombre) {
            $('#ie-lt-nombre').val(nombre);
        }
        var busqueda = nombre.toUpperCase();
        var emisorAplicado = false;
        (catalogo.cuits || []).forEach(function (item) {
            if (emisorAplicado) {
                return;
            }
            var emisor = String(item.nombre || '').toUpperCase();
            if (!busqueda || (emisor.indexOf(busqueda) === -1 && busqueda.indexOf(emisor) === -1)) {
                return;
            }
            emisorAplicado = true;
            if (!$('#ie-lt-cuit').data('tocado') && item.cuit) {
                $('#ie-lt-cuit').val(item.cuit);
                if (typeof formatarCUIT === 'function') {
                    formatarCUIT(document.getElementById('ie-lt-cuit'));
                }
            }
            if (!$('#ie-lt-condicioniva').data('tocado') && item.condicioniva_id) {
                $('#ie-lt-condicioniva').val(String(item.condicioniva_id));
            }
        });
    }

    function cuentaGastoDe(conceptoId, cuentaFila) {
        if (cuentaFila > 0) {
            return cuentaFila;
        }
        var metaEl = document.getElementById('ie-conceptos-cuenta-meta');
        if (metaEl) {
            try {
                var meta = JSON.parse(metaEl.textContent || '{}');
                var concepto = meta[String(conceptoId)] || {};
                var empresaId = String($('#empresa_id').val() || '');
                var porEmpresa = concepto.cuentas_por_empresa || {};
                if (empresaId && porEmpresa[empresaId]) {
                    return parseInt(porEmpresa[empresaId], 10) || 0;
                }
                if (concepto.cuenta_debe_id) {
                    return parseInt(concepto.cuenta_debe_id, 10) || 0;
                }
            } catch (err) {
                return cuentaFila;
            }
        }
        return 0;
    }

    function pasarCobradoACaja(cobrado) {
        var $fila = $('#tbody-cuenta-table tr.item-cuenta').filter(function () {
            return (parseInt($(this).find('.cuentacaja_id').val() || '0', 10) || 0) > 0
                || $.trim($(this).find('.codigocuentacaja').val() || '') !== '';
        }).first();
        if (!$fila.length) {
            $fila = $('#tbody-cuenta-table tr.item-cuenta').first();
        }
        if (!$fila.length) {
            return;
        }
        var $monto = $fila.find('.monto');
        $monto.val(formato(cobrado)).trigger('change');
    }

    function elegirCobranza() {
        var $sel = $('#tipotransaccion_caja_id');
        if (!$sel.length || $sel.is(':disabled')) {
            return;
        }
        var $cob = $sel.find('option').filter(function () {
            return String($(this).attr('data-abreviatura') || '').toUpperCase() === 'COB';
        }).first();
        if ($cob.length && String($sel.val() || '') === '') {
            $sel.val($cob.attr('value')).trigger('change');
        }
    }

    function abrir() {
        ivaManual = {};
        $('#ie-lt-nombre, #ie-lt-cuit, #ie-lt-condicioniva').removeData('tocado');
        var fecha = $('#fecha').val() || '';
        if (fecha) {
            $('#ie-lt-fecha').val(fecha);
        }
        $('#ie-lt-aviso').addClass('d-none').text('');
        sugerirEmisor();
        recalcular();
        $('#modal-ie-liquidacion-tarjeta').modal('show');
    }

    function aceptar() {
        var totales = recalcular();
        var $aviso = $('#ie-lt-aviso');
        function fallo(texto) {
            $aviso.removeClass('d-none').text(texto);
            return;
        }
        $aviso.addClass('d-none').text('');
        if (!(catalogo.tipotransaccion_compra_id > 0)) {
            fallo('No está el tipo de comprobante DTC.');
            return;
        }
        var tarjetaId = parseInt($('#ie-lt-cuentacaja-id').val() || '0', 10) || 0;
        if (tarjetaId <= 0) {
            fallo('Elegí la cuenta de la tarjeta.');
            return;
        }
        if (totales.bruto <= 0) {
            fallo('Indicá el bruto de cupones.');
            return;
        }
        if (totales.gastos <= 0) {
            fallo('Cargá al menos un gasto.');
            return;
        }
        if (totales.cobrado < -0.009) {
            fallo('Los gastos superan el bruto de cupones.');
            return;
        }
        var nombre = $.trim($('#ie-lt-nombre').val() || '');
        var cuit = soloDigitos($('#ie-lt-cuit').val());
        var condicion = parseInt($('#ie-lt-condicioniva').val() || '0', 10) || 0;
        if (nombre === '' || cuit.length !== 11 || condicion <= 0) {
            fallo('El DTC necesita emisor, CUIT de 11 dígitos y condición de IVA.');
            return;
        }
        elegirCobranza();
        var signoTipo = String($('#tipotransaccion_caja_id option:selected').attr('data-signo') || '').toUpperCase();
        if (signoTipo !== '' && signoTipo !== 'I' && signoTipo !== '1') {
            fallo('Elegí un tipo de ingreso (Cobranza). El neto tiene que entrar a la cuenta de caja.');
            return;
        }
        var conceptos = [];
        var porConcepto = {};
        var faltaConcepto = '';
        $('#ie-lt-conceptos .ie-lt-importe').each(function () {
            var monto = Math.round(parseMonto($(this).val()) * 100) / 100;
            if (Math.abs(monto) < 0.009) {
                return;
            }
            var conceptoId = parseInt($(this).attr('data-concepto-id') || '0', 10) || 0;
            if (conceptoId <= 0) {
                faltaConcepto = $(this).closest('tr').find('td').first().text();
                return false;
            }
            var cuentaFila = parseInt($(this).attr('data-cuenta-id') || '0', 10) || 0;
            var cuentaId = cuentaGastoDe(conceptoId, cuentaFila);
            if (cuentaId <= 0 && catalogo.cuenta_comisiones && catalogo.cuenta_comisiones.id) {
                cuentaId = parseInt(catalogo.cuenta_comisiones.id, 10) || 0;
            }
            if (!porConcepto[conceptoId]) {
                porConcepto[conceptoId] = {
                    concepto_ivacompra_id: conceptoId,
                    monto: 0,
                    cuentacontabledebe_id: cuentaId > 0 ? cuentaId : null,
                    centrocosto_id: null,
                };
            }
            porConcepto[conceptoId].monto = Math.round((porConcepto[conceptoId].monto + monto) * 100) / 100;
        });
        Object.keys(porConcepto).forEach(function (id) {
            conceptos.push(porConcepto[id]);
        });
        if (faltaConcepto) {
            fallo('Falta el concepto en el maestro: ' + faltaConcepto + '.');
            return;
        }
        if (!conceptos.length) {
            fallo('Cargá al menos un gasto.');
            return;
        }
        var fecha = $('#ie-lt-fecha').val() || $('#fecha').val() || '';
        var payload = {
            id: null,
            tipo_tesoreria: 'LIQUIDACION_TARJETA',
            liquidacion_tarjeta: true,
            tipotransaccion_compra_id: catalogo.tipotransaccion_compra_id,
            cuentacaja_tarjeta_id: tarjetaId,
            bruto_cupones: totales.bruto,
            proveedor_id: 0,
            proveedor_codigo: '',
            proveedor_nombre: nombre,
            proveedor_nombre_eventual: nombre,
            proveedor_documento_eventual: cuit,
            proveedor_condicioniva_id_eventual: condicion,
            letra: 'A',
            sucursal: 1,
            numerocomprobante: 0,
            fechacomprobante: fecha,
            fechaiva: fecha,
            total: totales.gastos,
            moneda_id: 1,
            cotizacion: 1,
            conceptos: conceptos,
            debitos_gasto: [],
            leyenda: '',
        };
        if (typeof window.aplicarLiquidacionTarjetaEnGrilla !== 'function') {
            fallo('No se pudo agregar el DTC a los comprobantes.');
            return;
        }
        window.aplicarLiquidacionTarjetaEnGrilla(payload);
        pasarCobradoACaja(totales.cobrado);
        elegirCobranza();
        if (typeof flModificaAsiento !== 'undefined') {
            flModificaAsiento = true;
        }
        $('#modal-ie-liquidacion-tarjeta').modal('hide');
    }

    $(function () {
        if (!$('#modal-ie-liquidacion-tarjeta').length) {
            return;
        }
        leerCatalogo();
        pintarTabla();

        $('#ie-btn-liquidacion-tarjeta').on('click', abrir);
        window.abrirLiquidacionTarjetaIe = abrir;

        function abrirConsultaTarjeta() {
            if (!$('#empresa_id').val()) {
                alert('Debe ingresar empresa');
                return;
            }
            window.consultaCuentacajaSoloTarjeta = true;
            window.ieLiquidacionTarjetaDestinoCaja = {
                id: '#ie-lt-cuentacaja-id',
                codigo: '#ie-lt-cuentacaja-id_codigo',
                nombre: '#ie-lt-cuentacaja-id_nombre'
            };
            $('#consultacuentacaja').val('');
            $('#datoscuentacaja').html('');
            $('#consultacuentacajaModal').modal('show');
        }

        $(document).on('click.ieLtTarjeta', '#modal-ie-liquidacion-tarjeta .consultacuentacaja', function (e) {
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaTarjeta();
        });

        document.addEventListener('keydown', function (e) {
            var esF1 = e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
            if (!esF1) {
                return;
            }
            var target = e.target;
            if (!target || !target.closest || !target.closest('#modal-ie-liquidacion-tarjeta')) {
                return;
            }
            if (!$(target).hasClass('codigocuentacaja') && !$(target).hasClass('descripcioncuentacaja')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirConsultaTarjeta();
        }, true);

        $(document).on('keydown.ieLtTarjeta', '#ie-lt-cuentacaja-id_codigo', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13 && e.which !== 13) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var codigo = $.trim($(this).val() || '');
            var empresaId = $('#empresa_id').val();
            if (codigo === '') {
                $('#ie-lt-cuentacaja-id').val('').trigger('change');
                $('#ie-lt-cuentacaja-id_nombre').val('');
                return;
            }
            if (!empresaId) {
                alert('Debe ingresar empresa');
                return;
            }
            $.get(carpetaBase + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo), { empresa_id: empresaId })
                .done(function (data) {
                    if (!data || !(parseInt(data.id, 10) > 0) || String(data.es_tarjeta) === '0' || data.es_tarjeta === false) {
                        alert('Elegí una cuenta de caja marcada como tarjeta.');
                        $('#ie-lt-cuentacaja-id_codigo').trigger('focus');
                        return;
                    }
                    $('#ie-lt-cuentacaja-id').val(data.id).trigger('change');
                    $('#ie-lt-cuentacaja-id_codigo').val(data.codigo || codigo);
                    $('#ie-lt-cuentacaja-id_nombre').val(data.nombre || '');
                    var $bruto = $('#ie-lt-bruto');
                    if ($bruto.length) {
                        $bruto.trigger('focus');
                    }
                })
                .fail(function () {
                    alert('No existe la cuenta de caja.');
                    $('#ie-lt-cuentacaja-id_codigo').trigger('focus');
                });
        });

        function importesConceptoHabilitados() {
            return $('#ie-lt-conceptos .ie-lt-importe').filter(function () {
                return !this.disabled;
            });
        }

        function enfocarImporte($input) {
            if (!$input || !$input.length) {
                return;
            }
            var el = $input.get(0);
            el.focus();
            if (typeof el.select === 'function') {
                el.select();
            }
        }

        function esEnter(e) {
            return e.key === 'Enter' || e.keyCode === 13 || e.which === 13;
        }

        $('#ie-lt-bruto').on('keydown', function (e) {
            if (!esEnter(e)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            enfocarImporte(importesConceptoHabilitados().first());
        });

        $('#ie-lt-conceptos').on('keydown', '.ie-lt-importe', function (e) {
            if (!esEnter(e) || this.disabled) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var $habilitados = importesConceptoHabilitados();
            var indice = $habilitados.index(this);
            if (indice >= 0 && indice < $habilitados.length - 1) {
                enfocarImporte($habilitados.eq(indice + 1));
            }
        });

        $('#ie-lt-bruto, #ie-lt-conceptos').on('input', '.ie-lt-importe, #ie-lt-bruto', function () {
            if ($(this).hasClass('ie-lt-importe') && !$(this).attr('data-base')) {
                var clave = $(this).attr('data-clave');
                $('#ie-lt-conceptos .ie-lt-importe').filter(function () {
                    return $(this).attr('data-base') === clave;
                }).each(function () {
                    ivaManual[$(this).attr('data-clave')] = false;
                });
            }
            if ($(this).attr('data-base')) {
                ivaManual[$(this).attr('data-clave')] = true;
            }
            recalcular();
        });
        $('#ie-lt-bruto').on('input', recalcular);
        $('#ie-lt-cuentacaja-id, #ie-lt-cuentacaja-id_nombre').on('change', sugerirEmisor);
        $('#ie-lt-nombre, #ie-lt-cuit, #ie-lt-condicioniva').on('input change', function () {
            $(this).data('tocado', true);
        });
        $('#ie-lt-aceptar').on('click', aceptar);

        $('#modal-ie-liquidacion-tarjeta').on('shown.bs.modal.ieLt', function () {
            var codigo = document.getElementById('ie-lt-cuentacaja-id_codigo');
            if (codigo) {
                codigo.focus();
            }
        });

        function enfocarBrutoCupones() {
            var bruto = document.getElementById('ie-lt-bruto');
            if (!bruto || !$('#modal-ie-liquidacion-tarjeta').hasClass('show')) {
                return;
            }
            bruto.focus();
            if (typeof bruto.select === 'function') {
                bruto.select();
            }
        }

        $('#consultacuentacajaModal')
            .on('shown.bs.modal.ieLt', function () {
                if (!$('#modal-ie-liquidacion-tarjeta').hasClass('show')) {
                    return;
                }
                var zParent = parseInt($('#modal-ie-liquidacion-tarjeta').css('z-index'), 10) || 1050;
                $(this).css('z-index', zParent + 20);
                setTimeout(function () {
                    $('.modal-backdrop').last().css('z-index', zParent + 10);
                }, 0);
            })
            .on('hidden.bs.modal.ieLt', function () {
                $(this).css('z-index', '');
                window.consultaCuentacajaSoloTarjeta = false;
                window.ieLiquidacionTarjetaDestinoCaja = null;
                if ($('#modal-ie-liquidacion-tarjeta').hasClass('show')) {
                    $('body').addClass('modal-open');
                    setTimeout(enfocarBrutoCupones, 0);
                }
                sugerirEmisor();
            });
    });
})(jQuery);
