(function ($) {
    var campoActivo = null;

    function esTransferencia() {
        return $('#tipo').val() === 'transferencia';
    }

    function sincronizarTipo() {
        var tra = esTransferencia();
        $('#bloque-transferencia').toggleClass('d-none', !tra);
        $('#bloque-cuenta-unica').toggleClass('d-none', tra);
    }

    function campoDe(el) {
        return $(el).closest('.tm-cuentacaja-campo');
    }

    function rolDe($campo) {
        var id = $campo.find('.cuentacaja_id').attr('id') || '';
        if (id.indexOf('hasta') >= 0) return 'hasta';
        if (id.indexOf('desde') >= 0) return 'desde';
        return 'unica';
    }

    window.empresaIdConsultaCuentacajaOverride = function () {
        if (campoActivo && rolDe(campoActivo) === 'hasta') {
            return '';
        }
        var v = parseInt($('#empresa_id').val() || '0', 10);
        return v > 0 ? String(v) : '';
    };

    function aplicarCuenta($campo, data) {
        $campo.find('.cuentacaja_id').val(data.id || '');
        $campo.find('.codigocuentacaja').val(data.codigo || '');
        $campo.find('.descripcioncuentacaja').val(data.nombre || '');
        if (data.moneda) {
            $('#precarga_moneda').val(data.moneda);
        }
        if (data.cotizacion && parseInt(data.moneda_id || '0', 10) > 1) {
            $('#cotizacion').val(data.cotizacion);
        }
        if (parseInt(data.moneda_id || '1', 10) <= 1) {
            $('#cotizacion').val('1');
        }
        $('#precarga_moneda').data('moneda-id', parseInt(data.moneda_id || '1', 10) || 1);
        var hoja = data.hoja ? ('Hoja de posición: ' + data.hoja) : 'Esta cuenta no mapea a una hoja de la posición.';
        $('#precarga_hoja').text(hoja);
    }

    function leerCuenta($campo) {
        var id = parseInt($campo.find('.cuentacaja_id').val() || '0', 10);
        if (id <= 0 || !window.PRECARG_CUENTA_URL) return;
        var rol = rolDe($campo);
        $.getJSON(window.PRECARG_CUENTA_URL + '/' + id, {
            empresa_id: $('#empresa_id').val() || '',
            fecha: $('#fecha').val() || '',
            rol: rol
        }).done(function (data) {
            if (data && data.id) aplicarCuenta($campo, data);
        });
    }

    function avisarCuenta(mensaje) {
        if ($('#consultacuentacajaModal').hasClass('show')) {
            $('#consultacuentacajaModal').modal('hide');
        }
        window.setTimeout(function () {
            window.alert(mensaje);
        }, 0);
    }

    function resolverCodigo($campo, avisar, alTerminar) {
        var codigo = $.trim($campo.find('.codigocuentacaja').val() || '');
        var input = $campo.find('.codigocuentacaja').get(0);
        if (codigo === '') {
            aplicarCuenta($campo, { id: '', codigo: '', nombre: '', moneda_id: 1, moneda: '', cotizacion: 1, hoja: '' });
            if (avisar) {
                avisarCuenta('Ingresá el código de la cuenta de caja.');
                if (input) input.focus();
            }
            if (typeof alTerminar === 'function') alTerminar(false);
            return;
        }
        if (parseInt($campo.find('.cuentacaja_id').val() || '0', 10) > 0) {
            if (typeof alTerminar === 'function') alTerminar(true);
            return;
        }
        var empresa = rolDe($campo) === 'hasta' ? '' : ($('#empresa_id').val() || '');
        if (rolDe($campo) !== 'hasta' && !(parseInt(empresa, 10) > 0)) {
            if (avisar) {
                avisarCuenta('Elegí la empresa antes de la cuenta de caja.');
                if (input) input.focus();
            }
            if (typeof alTerminar === 'function') alTerminar(false);
            return;
        }
        var base = window.PRECARG_CUENTA_POR_CODIGO_URL || ((window.carpetaBase || '') + '/caja/cuentacaja/leercuentacajaporcodigo');
        $.getJSON(base + '/' + encodeURIComponent(codigo), { empresa_id: empresa }).done(function (cuenta) {
            if (!cuenta || !cuenta.id) {
                if (avisar) {
                    avisarCuenta('No se encontró la cuenta de caja.');
                    if (input) input.focus();
                }
                $campo.find('.cuentacaja_id').val('');
                $campo.find('.descripcioncuentacaja').val('');
                if (typeof alTerminar === 'function') alTerminar(false);
                return;
            }
            $campo.find('.cuentacaja_id').val(cuenta.id);
            $campo.find('.codigocuentacaja').val(cuenta.codigo || codigo);
            $campo.find('.descripcioncuentacaja').val(cuenta.nombre || '');
            leerCuenta($campo);
            if (typeof alTerminar === 'function') alTerminar(true);
        }).fail(function (xhr) {
            $campo.find('.cuentacaja_id').val('');
            $campo.find('.descripcioncuentacaja').val('');
            if (avisar) {
                var msg = 'No se encontró la cuenta de caja.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                avisarCuenta(msg);
                if (input) input.focus();
            }
            if (typeof alTerminar === 'function') alTerminar(false);
        });
    }

    function abrirModalCuenta($campo) {
        campoActivo = $campo;
        if (rolDe($campo) !== 'hasta' && !(parseInt($('#empresa_id').val() || '0', 10) > 0)) {
            avisarCuenta('Elegí la empresa antes de consultar cuentas de caja.');
            return;
        }
        $('#consultacuentacaja').val('');
        $('#consultacuentacajaModal').modal('show');
    }

    function dinero(valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        return Number(valor).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function pintarTablero(data) {
        var body = document.getElementById('precarga-board-body');
        if (!body) return;
        var html = '';
        if (data.sin_hoja > 0) {
            html += '<div class="alert alert-warning py-2">' + data.sin_hoja + ' movimiento(s) sin banco de la posición.</div>';
        }
        (data.hojas || []).forEach(function (hoja) {
            html += '<div class="precarga-hoja"><div class="precarga-hoja-titulo">' + $('<div>').text(hoja.nombre).html() + '</div>';
            html += '<table class="table table-sm precarga-grid mb-3"><thead><tr><th>Concepto</th><th class="text-right">Biyemas</th><th class="text-right">Kandiko</th><th class="text-right">Rebisco</th><th>Nota</th></tr></thead><tbody>';
            (hoja.filas || []).forEach(function (fila) {
                var vacia = fila.biy === null && fila.kan === null && fila.reb === null;
                html += '<tr class="tono-' + (fila.tono || '') + (vacia ? ' fila-vacia' : '') + '">';
                html += '<td>' + $('<div>').text(fila.etiqueta || '').html() + '</td>';
                html += '<td class="text-right">' + dinero(fila.biy) + '</td>';
                html += '<td class="text-right">' + dinero(fila.kan) + '</td>';
                html += '<td class="text-right">' + dinero(fila.reb) + '</td>';
                html += '<td>' + $('<div>').text(fila.nota || '').html() + '</td></tr>';
            });
            html += '</tbody></table></div>';
        });
        if ((data.movimientos || []).length) {
            html += '<div class="precarga-lista"><div class="precarga-hoja-titulo">Movimientos del día</div><ul class="list-unstyled mb-0">';
            data.movimientos.forEach(function (mov) {
                html += '<li><strong>' + $('<div>').text(mov.tipo || '').html() + '</strong> · '
                    + $('<div>').text(mov.rubro || '').html() + ' · '
                    + $('<div>').text(mov.empresa || '').html() + ' · '
                    + dinero(mov.monto) + ' <span class="text-muted">' + $('<div>').text(mov.detalle || '').html() + '</span>'
                    + ' <span class="badge badge-light">' + $('<div>').text(mov.estado || '').html() + '</span></li>';
            });
            html += '</ul></div>';
        }
        if (!html) {
            html = '<p class="text-muted mb-0">Todavía no hay importes de precarga para esta fecha.</p>';
        }
        body.innerHTML = html;
    }

    function refrescarTablero() {
        if (!window.PRECARG_IMPACTO_URL) return;
        var fecha = $('#fecha').val();
        $('#precarga-board-fecha').text(fecha ? fecha.split('-').reverse().join('/') : '');
        $.getJSON(window.PRECARG_IMPACTO_URL, { fecha: fecha }).done(pintarTablero);
    }

    function campoVisible(el) {
        if (!el || el.disabled || el.readOnly) return false;
        if (el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio') return false;
        if (!el.closest || !el.closest('#form-precarga')) return false;
        if ($(el).closest('.d-none').length) return false;
        return true;
    }

    function secuenciaFoco() {
        var lista = [];
        $('#form-precarga').find('input, select, textarea').each(function () {
            if (campoVisible(this)) lista.push(this);
        });
        var guardar = document.querySelector('button[form="form-precarga"]');
        if (guardar) lista.push(guardar);
        return lista;
    }

    function focoSiguiente(actual) {
        var lista = secuenciaFoco();
        var i = lista.indexOf(actual);
        if (i < 0) return;
        var next = lista[i + 1];
        if (!next) return;
        next.focus();
        if (next.tagName === 'INPUT' && next.type !== 'date' && typeof next.select === 'function') {
            try { next.select(); } catch (err) { /* date y algunos tipos no seleccionan */ }
        }
    }

    function avisarCampo(el, mensaje) {
        window.setTimeout(function () {
            window.alert(mensaje);
            if (el && el.focus) el.focus();
        }, 0);
    }

    function validarYAvanzar(el) {
        var $el = $(el);
        if ($el.is('#empresa_id')) {
            if (!(parseInt($el.val() || '0', 10) > 0)) {
                avisarCampo(el, 'Elegí la empresa.');
                return;
            }
            focoSiguiente(el);
            return;
        }
        if ($el.is('#fecha')) {
            if (!$el.val()) {
                avisarCampo(el, 'Ingresá la fecha.');
                return;
            }
            focoSiguiente(el);
            return;
        }
        if ($el.is('#detalle')) {
            if (!$.trim($el.val() || '')) {
                avisarCampo(el, 'Ingresá el detalle.');
                return;
            }
            focoSiguiente(el);
            return;
        }
        if ($el.is('#monto')) {
            var monto = parseFloat(String($el.val() || '').replace(',', '.'));
            if (!(monto > 0)) {
                avisarCampo(el, 'Ingresá un monto mayor a cero.');
                return;
            }
            focoSiguiente(el);
            return;
        }
        if ($el.is('#cotizacion')) {
            var cot = parseFloat(String($el.val() || '').replace(',', '.'));
            var monedaId = parseInt($('#precarga_moneda').data('moneda-id') || '1', 10);
            if (!(cot > 0)) {
                avisarCampo(el, 'Ingresá la cotización.');
                return;
            }
            if (monedaId > 1 && cot <= 1.0001) {
                avisarCampo(el, 'En moneda extranjera la cotización tiene que ser mayor a 1.');
                return;
            }
            focoSiguiente(el);
            return;
        }
        if ($el.hasClass('codigocuentacaja')) {
            var $campo = campoDe(el);
            campoActivo = $campo;
            resolverCodigo($campo, true, function (ok) {
                if (ok) focoSiguiente(el);
            });
            return;
        }
        if ($el.hasClass('codigocuentacontable')) {
            if (!$.trim($el.val() || '')) {
                focoSiguiente(el);
            }
            return;
        }
        focoSiguiente(el);
    }

    function focoInicial() {
        if ($('#form-precarga').hasClass('pe-none')) return;
        var $emp = $('#empresa_id');
        var preelegida = parseInt($emp.val() || '0', 10) > 0;
        if ($emp.is('select') && !preelegida) {
            $emp.trigger('focus');
            return;
        }
        var fecha = document.getElementById('fecha');
        if (fecha) fecha.focus();
    }

    document.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!target || !target.closest || !target.closest('#form-precarga')) {
            return;
        }
        var esF1 = e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
        if (esF1 && target.classList && target.classList.contains('codigocuentacaja')) {
            e.preventDefault();
            e.stopPropagation();
            var $campoF1 = campoDe(target);
            campoActivo = $campoF1;
            if (!$('#consultacuentacajaModal').hasClass('show')) {
                abrirModalCuenta($campoF1);
            }
            return;
        }
        var esEnter = e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13 || e.which === 13;
        if (!esEnter) return;
        if (target.tagName === 'TEXTAREA') return;
        if (target.readOnly) return;
        e.preventDefault();
        e.stopPropagation();
        validarYAvanzar(target);
    }, true);

    if (typeof window.resolverPorCodigoCuentaContable === 'function') {
        var resolverCuentaContableOriginal = window.resolverPorCodigoCuentaContable;
        window.resolverPorCodigoCuentaContable = function (codigo, $ctx, onDone) {
            resolverCuentaContableOriginal(codigo, $ctx, function (ok, ctx) {
                if (typeof onDone === 'function') onDone(ok, ctx);
                if (!ctx || !ctx.length || !ctx.closest('#form-precarga').length) return;
                var input = ctx.find('.codigocuentacontable').get(0);
                if (!input || !$.trim(codigo || '')) return;
                if (ok) focoSiguiente(input);
                else input.focus();
            });
        };
    }

    $(document).on('click', '#form-precarga .consultacuentacaja', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalCuenta(campoDe(this));
    });
    $(document).on('blur', '#form-precarga .codigocuentacaja', function () {
        var $campo = campoDe(this);
        if ($('#consultacuentacajaModal').hasClass('show')) return;
        if (parseInt($campo.find('.cuentacaja_id').val() || '0', 10) > 0) return;
        resolverCodigo($campo, false);
    });
    $(document).on('input', '#form-precarga .codigocuentacaja', function () {
        var $campo = campoDe(this);
        $campo.find('.cuentacaja_id').val('');
        $campo.find('.descripcioncuentacaja').val('');
    });
    $(document).on('click', '.eligeconsultacuentacaja', function (e) {
        if (!campoActivo || !campoActivo.length || !campoActivo.closest('#form-precarga').length) return;
        e.preventDefault();
        e.stopPropagation();
        var tr = $(this).closest('tr');
        campoActivo.find('.cuentacaja_id').val(tr.find('.cuentacaja_id').text().trim());
        campoActivo.find('.codigocuentacaja').val(tr.find('.codigo').text().trim());
        campoActivo.find('.descripcioncuentacaja').val(tr.find('.nombre').text().trim());
        $('#consultacuentacajaModal').modal('hide');
        leerCuenta(campoActivo);
        var codigoElegido = campoActivo.find('.codigocuentacaja').get(0);
        if (codigoElegido) focoSiguiente(codigoElegido);
    });

    $('#tipo').on('change', sincronizarTipo);
    $('#fecha').on('change', function () {
        refrescarTablero();
        $('#form-precarga .tm-cuentacaja-campo').each(function () {
            if (parseInt($(this).find('.cuentacaja_id').val() || '0', 10) > 0) {
                leerCuenta($(this));
            }
        });
    });
    $('#empresa_id').on('change', function () {
        $('#bloque-cuenta-unica .tm-cuentacaja-campo, #bloque-transferencia .tm-cuentacaja-campo').each(function () {
            var $campo = $(this);
            if (rolDe($campo) === 'hasta') return;
            aplicarCuenta($campo, { id: '', codigo: '', nombre: '', moneda_id: 1 });
        });
    });

    sincronizarTipo();
    focoInicial();
    $('#form-precarga .tm-cuentacaja-campo').each(function () {
        if (parseInt($(this).find('.cuentacaja_id').val() || '0', 10) > 0) {
            leerCuenta($(this));
        }
    });
})(jQuery);
