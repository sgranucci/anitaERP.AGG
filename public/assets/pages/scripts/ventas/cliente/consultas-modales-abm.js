/**
 * Atajos F1 (abrir modal) y Enter (validar código) solo en ABM clientes.
 * Requiere form#form-general[data-consultas-modales-abm="1"].
 */
(function ($) {
    'use strict';

    function esTeclaF1(e) {
        return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
    }

    function enClienteAbm(el) {
        var form = document.getElementById('form-general');
        return !!(form
            && form.getAttribute('data-consultas-modales-abm') === '1'
            && el
            && form.contains(el));
    }

    function modalAbierto(selector) {
        var m = document.querySelector(selector);
        return !!(m && m.classList.contains('show'));
    }

    function sincronizarProvinciaDesdeLocalidad(data, $localidadCampo) {
        if (!data) {
            return;
        }
        var provId = data.provincia_id || (data.provincias && data.provincias.id);
        if (!provId) {
            return;
        }
        var provNombre = (data.provincias && data.provincias.nombre)
            || data.nombreprovincia
            || '';
        var $ambito = $localidadCampo && $localidadCampo.length
            ? $localidadCampo.closest('tr')
            : $();
        var $campoProv = $ambito.length
            ? $ambito.find('.tm-provincia-campo').not('.tm-provincia-iibb-campo').first()
            : $('#provincia_id').closest('.tm-provincia-campo');

        if (typeof aplicarProvinciaEnCampo === 'function' && $campoProv.length) {
            aplicarProvinciaEnCampo($campoProv, {
                id: provId,
                codigo: (data.provincias && data.provincias.codigo) || data.codigo_provincia || '',
                nombre: provNombre,
                jurisdiccion: (data.provincias && data.provincias.jurisdiccion) || data.jurisdiccion_provincia || '',
            });
            return;
        }

        var $prov = $('#provincia_id');
        if (!$prov.length) {
            return;
        }
        if (String($prov.val()) !== String(provId)) {
            $prov.val(String(provId));
        }
        if (provNombre) {
            $('#desc_provincia').val(provNombre);
            $('#nombreprovincia').val(provNombre);
        }
    }

    function resolverLocalidadDesdeCodigo(codigo, $input) {
        if (typeof leerLocalidadPorCodigo === 'function') {
            leerLocalidadPorCodigo(codigo, ($input && $input[0]) || $('#codigolocalidad')[0], true);
            return;
        }
        var cod = $.trim(codigo);
        if (cod === '') {
            $('#localidad_id').val('');
            $('#nombrelocalidad').val('');
            $('#codigolocalidad').val('');
            return;
        }
        $.get(carpetaBase + '/configuracion/leerlocalidad/' + encodeURIComponent(cod), function (data) {
            if (data) {
                $('#localidad_id').val(data.id);
                $('#nombrelocalidad').val(data.nombre);
                $('#codigolocalidad').val(data.codigo);
                if (data.codigopostal) {
                    $('#codigopostal').val(data.codigopostal);
                }
                sincronizarProvinciaDesdeLocalidad(data);
            }
        });
    }

    // Expuesto para el modal de consulta de localidades
    window.sincronizarProvinciaDesdeLocalidad = sincronizarProvinciaDesdeLocalidad;

    function resolverProvinciaDesdeInput($input) {
        if (typeof leerProvinciaPorCodigo === 'function') {
            leerProvinciaPorCodigo($input.val(), $input[0], true);
            return;
        }
        var cod = $.trim($input.val());
        var $tr = $input.closest('tr');

        if (cod === '') {
            if ($tr.length) {
                $tr.find('.provincia_id').val('');
                $tr.find('.codigoprovincia').val('');
                $tr.find('.nombreprovincia').val('');
            }
            if ($input.attr('id') === 'codigoprovincia') {
                $('#provincia_id').val('');
                $('#nombreprovincia').val('');
                $('#codigoprovincia').val('');
            }
            return;
        }

        $.get(carpetaBase + '/configuracion/leerunaprovincia/' + encodeURIComponent(cod), function (data) {
            if (!data) {
                return;
            }
            if ($tr.length) {
                $tr.find('.provincia_id').val(data.id);
                $tr.find('.codigoprovincia').val(data.codigo);
                $tr.find('.nombreprovincia').val(data.nombre);
            }
            if ($input.attr('id') === 'codigoprovincia' || !$tr.length) {
                $('#provincia_id').val(data.id);
                $('#nombreprovincia').val(data.nombre);
                $('#codigoprovincia').val(data.codigo);
            }
        });
    }

    function abrirModalLocalidad($input) {
        if (typeof abrirModalConsultaLocalidad === 'function') {
            abrirModalConsultaLocalidad($input);
            return;
        }
        $('#consultalocalidadModal').modal('show');
    }

    function abrirModalZonavta($input) {
        if (typeof resolverContextoZonavta === 'function') {
            window.ptrZonavtaContext = resolverContextoZonavta($input);
        } else {
            window.ptrZonavtaContext = $input.closest('.tm-zonavta-campo');
        }
        $('#consultazonavtaModal').modal('show');
        if (typeof buscar_datos_zonavta === 'function') {
            buscar_datos_zonavta('');
        }
    }

    function abrirModalVendedor($input) {
        var $ctx = $input.closest('.tm-vendedor-campo');
        window.ptrVendedor_id = $ctx.length ? $ctx.find('.vendedor_id') : null;
        window.ptrCodigoVendedor_id = $ctx.length ? $ctx.find('.codigovendedor') : null;
        window.ptrNombreVendedor = $ctx.length ? $ctx.find('.nombrevendedor') : null;
        $('#consultavendedorModal').modal('show');
        if (typeof buscar_datos_vendedor === 'function') {
            buscar_datos_vendedor('');
        }
    }

    function abrirModalCobrador($input) {
        var $ctx = $input.closest('.tm-cobrador-campo');
        window.ptrCobrador_id = $ctx.length ? $ctx.find('.cobrador_id') : null;
        window.ptrCodigoCobrador = $ctx.length ? $ctx.find('.codigocobrador') : null;
        window.ptrNombreCobrador = $ctx.length ? $ctx.find('.nombrecobrador') : null;
        $('#consultacobradorModal').modal('show');
        if (typeof buscar_datos_cobrador === 'function') {
            buscar_datos_cobrador('');
        }
    }

    function abrirModalDistribuidor($input) {
        var $ctx = $input.closest('.tm-distribuidor-campo');
        window.ptrDistribuidor_id = $ctx.length ? $ctx.find('.distribuidor_id') : null;
        window.ptrCodigoDistribuidor = $ctx.length ? $ctx.find('.codigodistribuidor') : null;
        window.ptrNombreDistribuidor = $ctx.length ? $ctx.find('.nombredistribuidor') : null;
        $('#consultadistribuidorModal').modal('show');
        if (typeof buscar_datos_distribuidor === 'function') {
            buscar_datos_distribuidor('');
        }
    }

    function abrirModalListaprecio($input) {
        if (typeof abrirModalConsultaListaprecioDesdeInput === 'function') {
            abrirModalConsultaListaprecioDesdeInput($input);
            return;
        }
        window.ptrListaprecioContext = $input.closest('.tm-listaprecio-campo');
        $('#consultalistaprecioModal').modal('show');
        if (typeof buscar_datos_listaprecio === 'function') {
            buscar_datos_listaprecio('');
        }
    }

    function abrirModalCuentaContable($input) {
        var $ctx = $input.closest('.tm-cuentacontable-campo');
        window.ptrCuentacontableContext = $ctx.length ? $ctx : null;
        window.cuentacontablexcodigo = $ctx.length ? $ctx.find('.cuentacontable_id').first() : null;
        window.nombrexcodigo = $ctx.length ? $ctx.find('.nombrecuentacontable').first() : null;
        window.codigoxcodigo = $ctx.length ? $ctx.find('.codigocuentacontable').first() : null;

        var empresaId = typeof empresaIdParaConsultaCuentaContable === 'function'
            ? empresaIdParaConsultaCuentaContable($ctx)
            : parseInt($('#empresa_id').val(), 10) || 0;

        if (empresaId <= 0) {
            alert('Debe ingresar empresa');
            return;
        }

        $('#consultaempresa_id').val(empresaId);
        $('#consultacuentaModal').modal('show');
        if (typeof buscar_datos === 'function') {
            buscar_datos('');
        }
    }

    function abrirModalTransporte($input) {
        if (typeof abrirModalConsultaTransporteDesdeInput === 'function') {
            abrirModalConsultaTransporteDesdeInput($input);
            return;
        }
        window.ptrTransporteContext = $input.closest('.tm-transporte-campo');
        $('#consultatransporteModal').modal('show');
        if (typeof buscar_datos_transporte === 'function') {
            buscar_datos_transporte('');
        }
    }

    function abrirModalProvincia($input) {
        if (typeof abrirModalConsultaProvincia === 'function') {
            abrirModalConsultaProvincia($input);
            return;
        }
        var $tr = $input.closest('tr');
        window.ptrprovincia_id = $tr.length ? $tr.find('.provincia_id') : null;
        $('#consultaprovinciaModal').modal('show');
        if (typeof buscar_datos_provincia === 'function') {
            buscar_datos_provincia('');
        }
    }

    function abrirModalArticulo($input) {
        var $btn = $input.closest('tr, .form-group').find('.consultaarticulo').first();
        if ($btn.length) {
            $btn.trigger('click');
        }
    }

    var ATAJOS = [
        {
            match: function (t) { return t.classList.contains('codigolocalidad') || t.id === 'codigolocalidad'; },
            modal: '#consultalocalidadModal',
            abrir: abrirModalLocalidad,
            validar: function ($t) { resolverLocalidadDesdeCodigo($t.val(), $t); },
        },
        {
            match: function (t) { return t.classList.contains('codigozonavta') || t.id === 'codigozonavta'; },
            modal: '#consultazonavtaModal',
            abrir: abrirModalZonavta,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-zonavta-campo');
                if (!$ctx.length && typeof resolverContextoZonavta === 'function') {
                    $ctx = resolverContextoZonavta($t);
                }
                if (typeof resolverPorCodigoZonavta === 'function') {
                    resolverPorCodigoZonavta($t.val(), $ctx);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigovendedor') || t.id === 'codigovendedor'; },
            modal: '#consultavendedorModal',
            abrir: abrirModalVendedor,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-vendedor-campo');
                if (typeof resolverPorCodigoVendedor === 'function') {
                    resolverPorCodigoVendedor($t.val(), $ctx.length ? $ctx : null);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigocobrador') || t.id === 'codigocobrador'; },
            modal: '#consultacobradorModal',
            abrir: abrirModalCobrador,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-cobrador-campo');
                if (typeof resolverPorCodigoCobrador === 'function') {
                    resolverPorCodigoCobrador($t.val(), $ctx.length ? $ctx : null);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigodistribuidor') || t.id === 'codigodistribuidor'; },
            modal: '#consultadistribuidorModal',
            abrir: abrirModalDistribuidor,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-distribuidor-campo');
                if (typeof resolverPorCodigoDistribuidor === 'function') {
                    resolverPorCodigoDistribuidor($t.val(), $ctx.length ? $ctx : null);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigolistaprecio') || t.id === 'codigolistaprecio'; },
            modal: '#consultalistaprecioModal',
            abrir: abrirModalListaprecio,
            validar: function ($t) {
                if (typeof aceptarCodigoListaprecioDesdeInput === 'function') {
                    aceptarCodigoListaprecioDesdeInput($t);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigocuentacontable') || t.id === 'codigocuentacontable'; },
            modal: '#consultacuentaModal',
            abrir: abrirModalCuentaContable,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-cuentacontable-campo');
                if (typeof resolverPorCodigoCuentaContable === 'function') {
                    resolverPorCodigoCuentaContable($t.val(), $ctx.length ? $ctx : null);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigotransporte') || t.id === 'codigotransporte'; },
            modal: '#consultatransporteModal',
            abrir: abrirModalTransporte,
            validar: function ($t) {
                var $ctx = $t.closest('.tm-transporte-campo');
                if (typeof resolverPorCodigoTransporte === 'function') {
                    resolverPorCodigoTransporte($t.val(), $ctx.length ? $ctx : null);
                }
            },
        },
        {
            match: function (t) { return t.classList.contains('codigoprovincia') || t.id === 'codigoprovincia'; },
            modal: '#consultaprovinciaModal',
            abrir: abrirModalProvincia,
            validar: function ($t) { resolverProvinciaDesdeInput($t); },
        },
        {
            match: function (t) {
                return t.classList.contains('codigoarticulo')
                    && !!t.closest('#tab-articulos-suspendidos, #articulo-suspendido-table');
            },
            modal: '#consultaarticuloModal',
            abrir: abrirModalArticulo,
            validar: function ($t) { $t.trigger('change'); },
        },
    ];

    function registrarAtajosConsultasClienteAbm() {
        if (window.clienteAbmModalesAtajosRegistrados) {
            return;
        }
        window.clienteAbmModalesAtajosRegistrados = true;

        document.addEventListener('keydown', function (e) {
            var target = e.target;
            if (!enClienteAbm(target) || target.readOnly || target.disabled) {
                return;
            }

            for (var i = 0; i < ATAJOS.length; i++) {
                var atajo = ATAJOS[i];
                if (!atajo.match(target)) {
                    continue;
                }

                if (esTeclaF1(e)) {
                    if (modalAbierto(atajo.modal)) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    atajo.abrir($(target));
                    return;
                }

                if (e.which === 13 || e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    atajo.validar($(target));
                    return;
                }
            }
        }, true);
    }

    window.registrarAtajosConsultasClienteAbm = registrarAtajosConsultasClienteAbm;

    $(function () {
        if ($('#form-general[data-consultas-modales-abm="1"]').length) {
            registrarAtajosConsultasClienteAbm();
        }
    });
}(jQuery));
