(function () {
    'use strict';

    var $tablaRepartos = $('#tabla-repartos-cot tbody');
    var $filaRepartoActiva = null;
    var arcaConstanciaUrl = $('#form-cot-electronico').data('arca-constancia-url') || '';

    function soloDigitosCuit(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function formatearInputCuit(input) {
        if (input && typeof window.formatarCUIT === 'function') {
            window.formatarCUIT(input);
        }
    }

    function esCuitValido(valor) {
        var cuit = soloDigitosCuit(valor);
        if (cuit.length !== 11) {
            return false;
        }

        var codes = '6789456789';
        var resultado = 0;
        var x;

        for (x = 0; x < 10; x++) {
            resultado += parseInt(codes.charAt(x), 10) * parseInt(cuit.charAt(x), 10);
        }

        resultado = resultado % 11;

        return resultado === parseInt(cuit.charAt(10), 10);
    }

    function marcarEstadoCuit($fila, estado) {
        var $input = $fila.find('.input-cuit-reparto');
        var $error = $fila.find('.cot-cuit-error');
        var $titular = $fila.find('.input-titular-cuit');

        $input.removeClass('is-valid is-invalid');

        if (estado === 'valido') {
            $input.addClass('is-valid');
            $error.addClass('d-none');
            return;
        }

        if (estado === 'invalido') {
            $input.addClass('is-invalid');
            $error.removeClass('d-none');
            $titular.val('').attr('title', 'CUIT inválida');
            return;
        }

        $error.addClass('d-none');
        $titular.val('').attr('title', 'Se completa al validar la CUIT en padrón ARCA');
    }

    function nombreDesdePadronArca(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }

        if (data.nombre) {
            return String(data.nombre).trim();
        }

        if (data.razonSocial) {
            return String(data.razonSocial).trim();
        }

        return String((data.apellido || '') + ' ' + (data.nombre || '')).trim();
    }

    function consultarTitularCuit($fila) {
        var $input = $fila.find('.input-cuit-reparto');
        var cuit = soloDigitosCuit($input.val());
        var $titular = $fila.find('.input-titular-cuit');
        var $loading = $fila.find('.cot-cuit-loading');
        var xhrAnterior = $fila.data('cotConsultaCuitXhr');

        if (xhrAnterior && xhrAnterior.readyState !== 4) {
            xhrAnterior.abort();
        }

        if (cuit === '') {
            marcarEstadoCuit($fila, 'vacio');
            return $.Deferred().resolve().promise();
        }

        formatearInputCuit($input[0]);
        cuit = soloDigitosCuit($input.val());

        if (!esCuitValido(cuit)) {
            marcarEstadoCuit($fila, 'invalido');
            return $.Deferred().resolve().promise();
        }

        marcarEstadoCuit($fila, 'valido');

        if (!arcaConstanciaUrl) {
            $titular.val('').attr('title', 'Consulta padrón no configurada');
            return $.Deferred().resolve().promise();
        }

        $loading.removeClass('d-none');

        var xhr = $.ajax({
            url: arcaConstanciaUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify({ cuit: cuit }),
        });

        $fila.data('cotConsultaCuitXhr', xhr);

        return xhr
            .done(function (resp) {
                var nombre = '';

                if (resp && resp.ok && resp.data) {
                    nombre = nombreDesdePadronArca(resp.data);
                }

                if (nombre !== '') {
                    $titular.val(nombre).attr('title', nombre);
                    return;
                }

                var mensaje = (resp && resp.message) ? resp.message : 'Sin datos en padrón ARCA';
                $titular.val(mensaje).attr('title', mensaje);
            })
            .fail(function (jqXHR, status) {
                if (status === 'abort') {
                    return;
                }

                var mensaje = 'No se pudo consultar padrón ARCA';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    mensaje = jqXHR.responseJSON.message;
                }
                $titular.val(mensaje).attr('title', mensaje);
            })
            .always(function () {
                $loading.addClass('d-none');
            });
    }

    function inicializarCuitEnFila($fila) {
        var $input = $fila.find('.input-cuit-reparto');
        if (!$input.length) {
            return;
        }

        formatearInputCuit($input[0]);
        consultarTitularCuit($fila);
    }

    function esTeclaEnter(e) {
        return e && (e.key === 'Enter' || e.which === 13 || e.keyCode === 13);
    }

    function enfocarInput($input) {
        if (!$input || !$input.length) {
            return;
        }

        $input.trigger('focus');
        if ($input[0] && typeof $input[0].select === 'function') {
            $input[0].select();
        }
    }

    function focoCodigoReparto($fila) {
        var $input = ($fila && $fila.length)
            ? $fila.find('.input-codigo-reparto').first()
            : $tablaRepartos.find('tr.fila-reparto:first .input-codigo-reparto');

        enfocarInput($input);
    }

    function focoDominioCamion($fila) {
        enfocarInput($fila.find('.input-patente-reparto').first());
    }

    function focoCuitChofer($fila) {
        enfocarInput($fila.find('.input-cuit-reparto').first());
    }

    function filaRepartoVacia() {
        return ''
            + '<tr class="fila-reparto">'
            + '<td>'
            + '<input type="hidden" name="reparto_transporte_id[]" class="input-transporte-id" value="">'
            + '<div class="input-group input-group-sm">'
            + '<input type="text" name="reparto_codigo[]" class="form-control input-codigo-reparto" maxlength="10">'
            + '<div class="input-group-append">'
            + '<button type="button" class="btn btn-outline-primary btn-consulta-reparto-cot" title="Consulta repartos">'
            + '<i class="fa fa-search"></i></button>'
            + '</div></div>'
            + '</td>'
            + '<td><input type="text" name="reparto_nombre[]" class="form-control form-control-sm input-nombre-reparto" readonly></td>'
            + '<td><input type="text" name="reparto_patente[]" class="form-control form-control-sm input-patente-reparto" maxlength="20"></td>'
            + '<td>'
            + '<div class="input-group input-group-sm">'
            + '<input type="text" name="reparto_cuit_chofer[]" class="form-control input-cuit-reparto" placeholder="XX-XXXXXXXX-X" maxlength="13">'
            + '<div class="input-group-append">'
            + '<span class="input-group-text cot-cuit-loading d-none" title="Consultando padrón">'
            + '<i class="fa fa-spinner fa-spin"></i></span>'
            + '</div></div>'
            + '<small class="text-danger cot-cuit-error d-none">CUIT inválida</small>'
            + '</td>'
            + '<td><input type="text" class="form-control form-control-sm input-titular-cuit" readonly title="Se completa al validar la CUIT en padrón ARCA"></td>'
            + '<td class="text-center">'
            + '<button type="button" class="btn btn-outline-danger btn-sm btn-quitar-reparto" title="Quitar fila">'
            + '<i class="fa fa-trash"></i></button></td>'
            + '</tr>';
    }

    function repartoDuplicadoEnGrilla(transporteId, codigo, $filaActual) {
        var idBuscado = parseInt(transporteId, 10) || 0;
        var codigoClave = String(codigo || '').trim().toUpperCase();
        var duplicado = false;

        $tablaRepartos.find('tr.fila-reparto').each(function () {
            if ($filaActual && $filaActual.length && $(this).is($filaActual)) {
                return;
            }

            var id = parseInt($(this).find('.input-transporte-id').val(), 10) || 0;
            var codigoFila = String($(this).find('.input-codigo-reparto').val() || '').trim().toUpperCase();

            if (idBuscado > 0 && id === idBuscado) {
                duplicado = true;
                return false;
            }

            if (idBuscado < 1 && codigoClave !== '' && codigoFila === codigoClave) {
                duplicado = true;
                return false;
            }
        });

        return duplicado;
    }

    function avisarRepartoDuplicado(codigo) {
        alert('El reparto ' + (codigo || '') + ' ya está cargado en la grilla.');
    }

    function aplicarTransporteEnFila($fila, data) {
        if (!data || !data.id) {
            return false;
        }

        if (repartoDuplicadoEnGrilla(data.id, data.codigo || '', $fila)) {
            avisarRepartoDuplicado(data.codigo || data.id);
            $fila.find('.input-transporte-id').val('');
            $fila.find('.input-codigo-reparto').val('');
            $fila.find('.input-nombre-reparto').val('');
            $fila.find('.input-patente-reparto').val('');
            $fila.find('.input-cuit-reparto').val('');
            $fila.find('.input-titular-cuit').val('');
            marcarEstadoCuit($fila, 'vacio');
            focoCodigoReparto($fila);
            return false;
        }

        $fila.find('.input-transporte-id').val(data.id);
        $fila.find('.input-codigo-reparto').val(data.codigo || '');
        $fila.find('.input-nombre-reparto').val(data.nombre || '');
        $fila.find('.input-patente-reparto').val(data.patentevehiculo || '');

        var $cuit = $fila.find('.input-cuit-reparto');
        $cuit.val(data.cuit_chofer || '');
        formatearInputCuit($cuit[0]);
        consultarTitularCuit($fila);

        return true;
    }

    function resolverTransportePorCodigo($fila, codigo, onDone) {
        var listo = function (ok) {
            if (typeof onDone === 'function') {
                onDone($fila, !!ok);
            }
        };

        if (!codigo) {
            listo(false);
            return;
        }

        $.getJSON(carpetaBase + '/ventas/leertransporte/' + encodeURIComponent(codigo))
            .done(function (data) {
                listo(aplicarTransporteEnFila($fila, data));
            })
            .fail(function () {
                listo(false);
            });
    }

    function buscarDatosTransporte(consulta) {
        $.ajax({
            url: carpetaBase + '/ventas/transporte/consultatransporte',
            type: 'POST',
            dataType: 'HTML',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: {
                consulta: consulta || '',
            },
        })
            .done(function (respuesta) {
                var resp = respuesta.replace(/\\/g, '');
                $('#datostransporte').html(resp);
            })
            .fail(function () {
                console.log('error consulta transporte');
            });
    }

    function validarRepartosDuplicadosAntesEnviar() {
        var vistos = {};
        var duplicado = '';

        $tablaRepartos.find('tr.fila-reparto').each(function () {
            var $fila = $(this);
            var transporteId = parseInt($fila.find('.input-transporte-id').val(), 10) || 0;
            var codigo = ($fila.find('.input-codigo-reparto').val() || '').trim();

            if (transporteId < 1 && codigo === '') {
                return;
            }

            var clave = transporteId > 0 ? 'id:' + transporteId : 'cod:' + codigo.toUpperCase();
            if (vistos[clave]) {
                duplicado = codigo !== '' ? codigo : ('ID ' + transporteId);
                return false;
            }
            vistos[clave] = true;
        });

        if (duplicado !== '') {
            alert('Hay repartos repetidos en la grilla: ' + duplicado);
            return false;
        }

        return true;
    }

    function validarCuitsRepartosAntesEnviar() {
        var invalidas = [];

        $tablaRepartos.find('tr.fila-reparto').each(function () {
            var $fila = $(this);
            var codigo = ($fila.find('.input-codigo-reparto').val() || '').trim();
            var transporteId = parseInt($fila.find('.input-transporte-id').val(), 10) || 0;
            var cuit = ($fila.find('.input-cuit-reparto').val() || '').trim();

            if (codigo === '' && transporteId < 1) {
                return;
            }

            if (cuit === '') {
                return;
            }

            if (!esCuitValido(cuit)) {
                invalidas.push(codigo !== '' ? codigo : ('ID ' + transporteId));
                marcarEstadoCuit($fila, 'invalido');
            }
        });

        if (invalidas.length) {
            alert('Revise CUIT chofer inválida en reparto(s): ' + invalidas.join(', '));
            return false;
        }

        return true;
    }

    function limpiarGrillaRepartos() {
        var fecha = ($('#fecha').val() || '').trim();
        var url = carpetaBase + '/ventas/cot-electronico';

        if (fecha !== '') {
            url += '?fecha=' + encodeURIComponent(fecha);
        }

        window.location.href = url;
    }

    $('#btn-agregar-reparto').on('click', function () {
        $tablaRepartos.append(filaRepartoVacia());
        focoCodigoReparto($tablaRepartos.find('tr.fila-reparto:last'));
    });

    $(document).on('click', '.btn-quitar-reparto', function () {
        var $fila = $(this).closest('tr.fila-reparto');
        var $filas = $tablaRepartos.find('tr.fila-reparto');

        if ($filas.length <= 1) {
            $fila.find('input').val('');
            marcarEstadoCuit($fila, 'vacio');
            focoCodigoReparto($fila);
            return;
        }

        var $destino = $fila.prev('tr.fila-reparto');
        if (!$destino.length) {
            $destino = $fila.next('tr.fila-reparto');
        }

        $fila.remove();
        focoCodigoReparto($destino);
    });

    $(document).on('click', '.btn-limpiar-sesion-cot', function () {
        limpiarGrillaRepartos();
    });

    $(document).on('input', '.input-cuit-reparto', function () {
        formatearInputCuit(this);
        marcarEstadoCuit($(this).closest('tr'), 'vacio');
        $(this).closest('tr').find('.input-titular-cuit').val('');
    });

    $(document).on('blur', '.input-cuit-reparto', function () {
        consultarTitularCuit($(this).closest('tr'));
    });

    $(document).on('keydown', '.input-cuit-reparto', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }

        e.preventDefault();
        consultarTitularCuit($(this).closest('tr'));
    });

    $(document).on('change', '.input-codigo-reparto', function () {
        var $fila = $(this).closest('tr');
        if ($fila.data('cotResolviendoEnter')) {
            return;
        }

        resolverTransportePorCodigo($fila, ($(this).val() || '').trim());
    });

    $(document).on('keydown', '.input-codigo-reparto', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }

        e.preventDefault();
        var $fila = $(this).closest('tr');
        var codigo = ($(this).val() || '').trim();

        if (codigo === '') {
            focoDominioCamion($fila);
            return;
        }

        $fila.data('cotResolviendoEnter', true);
        resolverTransportePorCodigo($fila, codigo, function ($f, ok) {
            $f.data('cotResolviendoEnter', false);
            if (ok) {
                focoDominioCamion($f);
                return;
            }

            focoCodigoReparto($f);
        });
    });

    $(document).on('keydown', '.input-patente-reparto', function (e) {
        if (!esTeclaEnter(e)) {
            return;
        }

        e.preventDefault();
        focoCuitChofer($(this).closest('tr'));
    });

    $(document).on('click', '.btn-consulta-reparto-cot', function () {
        $filaRepartoActiva = $(this).closest('tr.fila-reparto');
        $('#consultatransporte').val('');
        buscarDatosTransporte('');
        $('#consultatransporteModal').modal('show');
    });

    $('#consultatransporteModal').on('shown.bs.modal', function () {
        $(this).find('#consultatransporte').trigger('focus');
    });

    $(document).on('keyup', '#consultatransporte', function () {
        buscarDatosTransporte($(this).val());
    });

    $(document).on('click', '#consultatransporteModal .eligeconsultatransporte', function (e) {
        e.preventDefault();
        if (!$filaRepartoActiva || !$filaRepartoActiva.length) {
            return;
        }

        var $tr = $(this).closest('tr');
        var codigo = ($tr.find('.codigo').text() || '').trim();
        $('#consultatransporteModal').modal('hide');

        if (codigo === '') {
            return;
        }

        resolverTransportePorCodigo($filaRepartoActiva, codigo, function ($f, ok) {
            if (ok) {
                focoDominioCamion($f);
            }
        });
        $filaRepartoActiva = null;
    });

    $('#btn-consultar-remitos').on('click', function (e) {
        if (!validarRepartosDuplicadosAntesEnviar()) {
            e.preventDefault();
            return false;
        }

        if (!validarCuitsRepartosAntesEnviar()) {
            e.preventDefault();
            return false;
        }

        $('#input-consultar').val('1');
        $('#input-procesar').val('');
    });

    $('#check-todos-remitos').on('change', function () {
        var marcado = $(this).is(':checked');
        $('#tabla-remitos-cot tbody .check-remito-cot-pendiente').prop('checked', marcado);
    });

    $('#btn-procesar-cot').on('click', function (e) {
        if (!validarRepartosDuplicadosAntesEnviar()) {
            e.preventDefault();
            return false;
        }

        if (!validarCuitsRepartosAntesEnviar()) {
            e.preventDefault();
            return false;
        }

        var $invalidos = $('#tabla-remitos-cot tbody input[data-importe-ok="0"]:checked');
        if ($invalidos.length) {
            e.preventDefault();
            alert('Hay remitos seleccionados sin el neto gravado + exento de la factura. No se genera el COT. Desmarcalos o corregí la factura.');
            return false;
        }

        var seleccionados = $('#tabla-remitos-cot tbody .check-remito-cot-pendiente:checked').length;
        if (seleccionados < 1) {
            e.preventDefault();
            alert('Seleccione al menos un remito para procesar.');
            return false;
        }

        if (!confirm('¿Confirma el envío de los remitos seleccionados a ARBA?')) {
            e.preventDefault();
            return false;
        }

        $('#input-consultar').val('1');
        $('#input-procesar').val('1');
    });

    $tablaRepartos.find('tr.fila-reparto').each(function () {
        inicializarCuitEnFila($(this));
    });

    var $primerCodigoReparto = $tablaRepartos.find('.input-codigo-reparto').first();
    if ($primerCodigoReparto.length) {
        focoCodigoReparto($tablaRepartos.find('tr.fila-reparto:first'));
    }
})();
