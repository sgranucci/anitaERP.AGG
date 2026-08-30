var ptrLocalidadCampo;

function contenedorCampoLocalidad(origen) {
    var $origen = $(origen);
    if (!$origen.length) {
        return $();
    }
    var $campo = $origen.closest('.tm-localidad-campo');
    if ($campo.length) {
        return $campo;
    }
    var $tr = $origen.closest('tr');
    if ($tr.length) {
        return $tr;
    }

    return $();
}

function provinciaIdParaConsultaLocalidad($contenedor) {
    if ($contenedor && $contenedor.length) {
        var src = $contenedor.attr('data-provincia-source');
        if (src) {
            return $.trim($(src).val() || '');
        }
        var $tr = $contenedor.closest('tr');
        if ($tr.length) {
            var $prov = $tr.find('.tm-provincia-campo').not('.tm-provincia-iibb-campo').find('.provincia_id').first();
            if ($prov.length) {
                return $.trim($prov.val() || '');
            }
        }
    }

    return $.trim($('#provincia_id').val() || '');
}

function campoCpRelacionadoLocalidad($contenedor) {
    if ($contenedor && $contenedor.length) {
        var $tr = $contenedor.closest('tr');
        if ($tr.length) {
            var $cpFila = $tr.find('.codigospostales');
            if ($cpFila.length) {
                return $cpFila;
            }
        }
        var $ambito = $contenedor.closest('form, .tab-pane');
        if ($ambito.length) {
            var $cp = $ambito.find('#codigopostal').first();
            if ($cp.length) {
                return $cp;
            }
        }
    }

    return $('#codigopostal');
}

function aplicarLocalidadEnCampo($contenedor, datos) {
    var id = datos ? datos.id : '';
    var codigo = datos ? datos.codigo : '';
    var nombre = datos ? datos.nombre : '';
    var codigopostal = datos && datos.codigopostal !== undefined ? datos.codigopostal : '';

    if ($contenedor && $contenedor.length) {
        $contenedor.find('.localidad_id').val(id);
        $contenedor.find('.codigolocalidad').val(codigo);
        $contenedor.find('.nombrelocalidad').val(nombre);
        $contenedor.find('.localidad_id_previa, .localidad_id_previas').val(id);
        $contenedor.find('.desc_localidad, .desc_localidades').val(nombre);
    }

    if (!$contenedor || !$contenedor.length || !$contenedor.find('.localidad_id').length) {
        $('#localidad_id').val(id);
        $('#codigolocalidad').val(codigo);
        $('#nombrelocalidad').val(nombre);
        $('#localidad_id_previa').val(id);
        $('#desc_localidad').val(nombre);
        $('#localidad').val(nombre);
    }

    if (codigopostal) {
        campoCpRelacionadoLocalidad($contenedor).val(codigopostal);
    }
}

function avanzarDesdeCampoLocalidad(origen) {
    var $origen = $(origen);
    var $ambito = $origen.closest('tr');
    if (!$ambito.length) {
        $ambito = $origen.closest('form');
    }
    if (!$ambito.length) {
        return;
    }

    var $campos = $ambito.find('input, select, textarea, button').filter(':visible').not('[readonly], [disabled]');
    var indice = $campos.index($origen);
    if (indice >= 0 && indice + 1 < $campos.length) {
        $campos.eq(indice + 1).trigger('focus');
    }
}

function sincronizarProvinciaTrasLocalidad(datos, $contenedor) {
    if (!datos) {
        return;
    }

    window.__sincronizandoProvinciaDesdeLocalidad = true;
    try {
        if (typeof window.sincronizarProvinciaDesdeLocalidad === 'function') {
            window.sincronizarProvinciaDesdeLocalidad(datos, $contenedor);
            return;
        }

        var provId = datos.provincia_id || (datos.provincias && datos.provincias.id);
        if (!provId) {
            return;
        }
        var $ambito = $contenedor && $contenedor.length ? $contenedor.closest('tr') : $();
        var $campoProv = $ambito.length
            ? $ambito.find('.tm-provincia-campo').not('.tm-provincia-iibb-campo').first()
            : $('#provincia_id').closest('.tm-provincia-campo');
        if (typeof aplicarProvinciaEnCampo === 'function' && $campoProv.length) {
            aplicarProvinciaEnCampo($campoProv, {
                id: provId,
                codigo: (datos.provincias && datos.provincias.codigo) || datos.codigo_provincia || '',
                nombre: (datos.provincias && datos.provincias.nombre) || datos.nombreprovincia || '',
                jurisdiccion: (datos.provincias && datos.provincias.jurisdiccion) || datos.jurisdiccion_provincia || '',
            });
            return;
        }
        if ($('#provincia_id').length) {
            $('#provincia_id').val(String(provId));
            var nombreProv = (datos.provincias && datos.provincias.nombre) || datos.nombreprovincia || '';
            if (nombreProv) {
                $('#desc_provincia').val(nombreProv);
                $('#nombreprovincia').val(nombreProv);
            }
        }
    } finally {
        window.__sincronizandoProvinciaDesdeLocalidad = false;
    }
}

function buscar_datos_localidad(consulta) {
    var provincia_id = provinciaIdParaConsultaLocalidad(ptrLocalidadCampo);

    $.ajax({
        url: carpetaBase + '/configuracion/localidad/consultalocalidad',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta,
            provincia_id: provincia_id
        },
    })
    .done(function (respuesta) {
        var resp = respuesta.replace(/\\/g, '');
        $('#datoslocalidad').html('');
        $('#datoslocalidad').html(resp);
    })
    .fail(function () {
        console.log('error');
    });
}

function abrirModalConsultaLocalidad($origen) {
    ptrLocalidadCampo = contenedorCampoLocalidad($origen);
    $('#consultalocalidadModal').modal('show');
    if (typeof buscar_datos_localidad === 'function') {
        buscar_datos_localidad('');
    }
}

function leerLocalidadPorCodigo(codigo, origen, avisar) {
    var $contenedor = contenedorCampoLocalidad(origen);
    var valor = $.trim(codigo || '');

    if (valor === '') {
        aplicarLocalidadEnCampo($contenedor, null);
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/leerlocalidad/' + encodeURIComponent(valor))
        .done(function (data) {
            if (data && data.id) {
                aplicarLocalidadEnCampo($contenedor, data);
                $(origen).removeAttr('data-localidad-invalida');
                sincronizarProvinciaTrasLocalidad(data, $contenedor);
                if (avisar) {
                    avanzarDesdeCampoLocalidad(origen);
                }
                return;
            }

            aplicarLocalidadEnCampo($contenedor, null);
            $(origen).val(valor).attr('data-localidad-invalida', valor);
            if (avisar) {
                $('#consultalocalidadModal').modal('hide');
                setTimeout(function () {
                    alert('No existe una localidad con el código ' + valor + '.');
                    $(origen).trigger('focus').trigger('select');
                }, 0);
            }
        })
        .fail(function () {
            aplicarLocalidadEnCampo($contenedor, null);
            if (avisar) {
                $('#consultalocalidadModal').modal('hide');
                setTimeout(function () {
                    alert('No se pudo consultar la localidad con el código ' + valor + '.');
                    $(origen).trigger('focus');
                }, 0);
            }
        });
}

function activa_eventos_consultalocalidad() {
    function esTeclaF1Localidad(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function esCampoCodigoLocalidad($target) {
        return $target.hasClass('codigolocalidad') || $target.is('#codigolocalidad');
    }

    $(document)
        .off('click.consultaLocalidad', '.consultalocalidad')
        .on('click.consultaLocalidad', '.consultalocalidad', function (event) {
            event.preventDefault();
            abrirModalConsultaLocalidad($(this));
        });

    document.removeEventListener('keydown', window.__localidadF1Capture, true);
    window.__localidadF1Capture = function (e) {
        if (!esTeclaF1Localidad(e)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled) {
            return;
        }
        var $target = $(target);
        var esCampoLocalidad = esCampoCodigoLocalidad($target)
            || $target.hasClass('nombrelocalidad')
            || $target.is('#nombrelocalidad')
            || $target.hasClass('consultalocalidad')
            || $target.closest('.consultalocalidad').length > 0;
        if (!esCampoLocalidad) {
            return;
        }
        if ($('#consultalocalidadModal').hasClass('show') || $('#consultalocalidadModal').is(':visible')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaLocalidad($target);
    };
    document.addEventListener('keydown', window.__localidadF1Capture, true);

    document.removeEventListener('keydown', window.__localidadEnterCapture, true);
    window.__localidadEnterCapture = function (e) {
        if (!e || (e.key !== 'Enter' && e.keyCode !== 13)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled || target.readOnly) {
            return;
        }
        var $target = $(target);

        if ($target.is('#consultalocalidad')) {
            e.preventDefault();
            e.stopPropagation();
            $('#datoslocalidad').find('.eligeconsultalocalidad').first().trigger('click');
            return;
        }

        if (!esCampoCodigoLocalidad($target)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        leerLocalidadPorCodigo($target.val(), target, true);
    };
    document.addEventListener('keydown', window.__localidadEnterCapture, true);

    $(document)
        .off('input.consultaLocalidad', '.codigolocalidad')
        .on('input.consultaLocalidad', '.codigolocalidad', function () {
            $(this).removeAttr('data-localidad-invalida');
        });

    $('#consultalocalidadModal').off('shown.bs.modal.consultaLocalidad').on('shown.bs.modal.consultaLocalidad', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultalocalidadModal').off('click.consultaLocalidad').on('click.consultaLocalidad', function () {
        $('#consultalocalidadModal').modal('hide');
    });

    $(document).off('click.eligeconsultalocalidad').on('click.eligeconsultalocalidad', '.eligeconsultalocalidad', function () {
        var $fila = $(this).parents('tr');
        var datos = {
            id: $fila.children().first().html(),
            nombre: $fila.find('.nombre').html(),
            codigo: $fila.find('.codigo').html(),
            codigopostal: $fila.find('.codigopostal').html(),
            provincia_id: $fila.find('.provincia_id').html(),
            nombreprovincia: $fila.find('.nombreprovincia').html(),
            codigo_provincia: $fila.find('.codigo_provincia').html(),
            jurisdiccion_provincia: $fila.find('.jurisdiccion_provincia').html(),
            provincias: {
                id: $fila.find('.provincia_id').html(),
                nombre: $fila.find('.nombreprovincia').html(),
                codigo: $fila.find('.codigo_provincia').html(),
                jurisdiccion: $fila.find('.jurisdiccion_provincia').html(),
            }
        };

        var $contenedor = ptrLocalidadCampo && ptrLocalidadCampo.length
            ? contenedorCampoLocalidad(ptrLocalidadCampo)
            : $();
        aplicarLocalidadEnCampo($contenedor, datos);
        sincronizarProvinciaTrasLocalidad(datos, $contenedor);

        $('#consultalocalidadModal').modal('hide');
    });

    $(document).off('click.consultaunalocalidad').on('click.consultaunalocalidad', '.consultaunalocalidad', function () {
        var id = $(this).parents('tr').children().html();
        if (id > 0) {
            var urlConsultaLocalidad = route('editar_localidad', ':id');
            var url = urlConsultaLocalidad.replace(':id', id);
            window.open(url, '_blank', 'noopener');
        }
    });

    $(document)
        .off('change.consultaLocalidad', '.codigolocalidad, #codigolocalidad')
        .on('change.consultaLocalidad', '.codigolocalidad, #codigolocalidad', function (event) {
            event.preventDefault();
            leerLocalidadPorCodigo($(this).val(), this, false);
        });
}

$(document).off('keyup.consultaLocalidad', '#consultalocalidad').on('keyup.consultaLocalidad', '#consultalocalidad', function () {
    var valor = $(this).val();
    if (valor !== '') {
        buscar_datos_localidad(valor);
    } else {
        buscar_datos_localidad();
    }
});

$(function () {
    activa_eventos_consultalocalidad();
});
