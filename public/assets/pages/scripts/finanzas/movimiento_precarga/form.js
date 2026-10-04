(function ($) {
    var campoActivo = null;

    function esTransferencia() {
        return $('#tipo').val() === 'transferencia';
    }

    function sincronizarTipo() {
        var tra = esTransferencia();
        $('#bloque-transferencia').toggleClass('d-none', !tra);
        $('#bloque-cuenta-unica').toggleClass('d-none', tra);
        $('#bloque-contrapartida').toggleClass('d-none', tra);
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

    function resolverCodigo($campo) {
        var codigo = $.trim($campo.find('.codigocuentacaja').val() || '');
        if (codigo === '') {
            aplicarCuenta($campo, { id: '', codigo: '', nombre: '', moneda_id: 1, moneda: '', cotizacion: 1, hoja: '' });
            return;
        }
        var empresa = rolDe($campo) === 'hasta' ? '' : ($('#empresa_id').val() || '');
        var url = (window.carpetaBase || '') + '/caja/cuentacaja/leercuentacajaporcodigo/' + encodeURIComponent(codigo);
        $.getJSON(url, { empresa_id: empresa }).done(function (cuenta) {
            if (!cuenta || !cuenta.id) return;
            $campo.find('.cuentacaja_id').val(cuenta.id);
            $campo.find('.descripcioncuentacaja').val(cuenta.nombre || '');
            leerCuenta($campo);
        }).fail(function () {
            $campo.find('.cuentacaja_id').val('');
            $campo.find('.descripcioncuentacaja').val('');
        });
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

    $(document).on('click', '#form-precarga .consultacuentacaja', function () {
        campoActivo = campoDe(this);
    });
    $(document).on('keydown', '#form-precarga .codigocuentacaja', function (e) {
        campoActivo = campoDe(this);
        if (e.key === 'F1' || e.keyCode === 112) {
            e.preventDefault();
            $(this).closest('.tm-cuentacaja-campo').find('.consultacuentacaja').trigger('click');
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            resolverCodigo(campoActivo);
        }
    });
    $(document).on('blur', '#form-precarga .codigocuentacaja', function () {
        var $campo = campoDe(this);
        if ($('#consultacuentacajaModal').hasClass('show')) return;
        resolverCodigo($campo);
    });
    $(document).on('click', '.eligeconsultacuentacaja', function () {
        if (!campoActivo || !campoActivo.length) return;
        var tr = $(this).closest('tr');
        campoActivo.find('.cuentacaja_id').val(tr.find('.cuentacaja_id').text().trim());
        campoActivo.find('.codigocuentacaja').val(tr.find('.codigo').text().trim());
        campoActivo.find('.descripcioncuentacaja').val(tr.find('.nombre').text().trim());
        $('#consultacuentacajaModal').modal('hide');
        leerCuenta(campoActivo);
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
    $('#form-precarga .tm-cuentacaja-campo').each(function () {
        if (parseInt($(this).find('.cuentacaja_id').val() || '0', 10) > 0) {
            leerCuenta($(this));
        }
    });
})(jQuery);
