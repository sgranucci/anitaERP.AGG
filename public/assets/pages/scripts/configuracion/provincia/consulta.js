var ptrprovincia_id;

function buscar_datos_provincia(consulta) {

    $.ajax({
        url: carpetaBase+'/configuracion/provincia/consultaprovincia',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosprovincia").html("");
        $("#datosprovincia").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué tecla ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      e.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consultaprovincia', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_provincia(valor);
    } else {
        buscar_datos_provincia();
    }
});

/**
 * Contenedor del campo: bloque .tm-provincia-campo (varios por fila) o tr de grilla.
 */
function contenedorCampoProvincia(origen) {
    var $origen = $(origen);
    if (!$origen.length) {
        return $();
    }
    var $campo = $origen.closest('.tm-provincia-campo');
    if ($campo.length) {
        return $campo;
    }
    var $tr = $origen.closest('tr');
    if ($tr.length) {
        return $tr;
    }

    return $();
}

function abrirModalConsultaProvincia($origen) {
    var $contenedor = contenedorCampoProvincia($origen);
    ptrprovincia_id = $contenedor.length ? $contenedor.find('.provincia_id') : $('#provincia_id');
    $('#consultaprovinciaModal').modal('show');
    if (typeof buscar_datos_provincia === 'function') {
        buscar_datos_provincia('');
    }
}

/**
 * Escribe la provincia elegida en el trío ID / código / nombre (+ jurisdicción).
 */
function aplicarProvinciaEnCampo($contenedor, datos) {
    var id = datos ? datos.id : '';
    var codigo = datos ? datos.codigo : '';
    var nombre = datos ? datos.nombre : '';
    var jurisdiccion = datos && datos.jurisdiccion !== undefined ? datos.jurisdiccion : '';

    if ($contenedor && $contenedor.length) {
        $contenedor.find('.provincia_id').val(id);
        $contenedor.find('.codigoprovincia').val(codigo);
        $contenedor.find('.nombreprovincia').val(nombre);
        $contenedor.find('.jurisdiccionprovincia').val(jurisdiccion);
        $contenedor.find('.desc_provincia').val(nombre);
    }

    // Compatibilidad con pantallas que usan los ids globales.
    if (!$contenedor || !$contenedor.length || !$contenedor.find('.provincia_id').length) {
        $('#provincia_id').val(id);
        $('#codigoprovincia').val(codigo);
        $('#nombreprovincia').val(nombre);
        $('#jurisdiccionprovincia').val(jurisdiccion);
        $('#desc_provincia').val(nombre);
        $('#provincia').val(nombre);
    }
}

/**
 * Enfoca el campo siguiente del formulario (o de la fila) tras resolver el código.
 */
function avanzarDesdeCampoProvincia(origen) {
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

/**
 * Resuelve una provincia por código Anita.
 *
 * @param {string} codigo
 * @param {HTMLElement} origen input .codigoprovincia
 * @param {boolean} avisar true solo en Enter / elección explícita (nunca en blur)
 */
function leerProvinciaPorCodigo(codigo, origen, avisar) {
    var $contenedor = contenedorCampoProvincia(origen);
    var valor = $.trim(codigo || '');
    var idAnterior = $contenedor.length ? String($contenedor.find('.provincia_id').val() || '') : '';

    if (valor === '') {
        aplicarProvinciaEnCampo($contenedor, null);
        if ($contenedor.length && !$contenedor.hasClass('tm-provincia-iibb-campo') && idAnterior !== '') {
            $contenedor.find('.provincia_id').trigger('change');
        }
        return;
    }

    $.getJSON(carpetaBase + '/configuracion/leerunaprovincia/' + encodeURIComponent(valor))
        .done(function (data) {
            if (data && data.id) {
                aplicarProvinciaEnCampo($contenedor, data);
                $(origen).removeAttr('data-provincia-invalida');
                if ($contenedor.length && !$contenedor.hasClass('tm-provincia-iibb-campo')
                    && idAnterior !== String(data.id)) {
                    $contenedor.find('.provincia_id').trigger('change');
                }
                if (avisar) {
                    avanzarDesdeCampoProvincia(origen);
                }
                return;
            }

            aplicarProvinciaEnCampo($contenedor, null);
            $(origen).val(valor).attr('data-provincia-invalida', valor);
            if (avisar) {
                // El alert sobre la transición del modal deja el backdrop huérfano.
                $('#consultaprovinciaModal').modal('hide');
                setTimeout(function () {
                    alert('No existe una provincia con el código ' + valor + '.');
                    $(origen).trigger('focus').trigger('select');
                }, 0);
            }
        })
        .fail(function () {
            aplicarProvinciaEnCampo($contenedor, null);
            if (avisar) {
                $('#consultaprovinciaModal').modal('hide');
                setTimeout(function () {
                    alert('No se pudo consultar la provincia con el código ' + valor + '.');
                    $(origen).trigger('focus');
                }, 0);
            }
        });
}

function activa_eventos_consultaprovincia()
{
    function esTeclaF1Provincia(e) {
        return e && (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112);
    }

    function esCampoCodigoProvincia($target) {
        return $target.hasClass('codigoprovincia') || $target.is('#codigoprovincia');
    }

    // Consulta de provincias
    $(document)
        .off('click.consultaProvincia', '.consultaprovincia')
        .on('click.consultaProvincia', '.consultaprovincia', function (event) {
            event.preventDefault();
            abrirModalConsultaProvincia($(this));
        });

    document.removeEventListener('keydown', window.__provinciaF1Capture, true);
    window.__provinciaF1Capture = function (e) {
        if (!esTeclaF1Provincia(e)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled) {
            return;
        }
        var $target = $(target);
        var esCampoProvincia = esCampoCodigoProvincia($target)
            || $target.hasClass('nombreprovincia')
            || $target.is('#nombreprovincia')
            || $target.hasClass('consultaprovincia')
            || $target.closest('.consultaprovincia').length > 0;
        if (!esCampoProvincia) {
            return;
        }
        if ($('#consultaprovinciaModal').hasClass('show') || $('#consultaprovinciaModal').is(':visible')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaProvincia($target);
    };
    document.addEventListener('keydown', window.__provinciaF1Capture, true);

    // Enter en el código: se captura antes del bloqueo global de Enter de esta pantalla.
    document.removeEventListener('keydown', window.__provinciaEnterCapture, true);
    window.__provinciaEnterCapture = function (e) {
        if (!e || (e.key !== 'Enter' && e.keyCode !== 13)) {
            return;
        }
        var target = e.target;
        if (!target || target.disabled || target.readOnly) {
            return;
        }
        var $target = $(target);

        // Enter en el buscador del modal: elige la primera fila.
        if ($target.is('#consultaprovincia')) {
            e.preventDefault();
            e.stopPropagation();
            $('#datosprovincia').find('.eligeconsultaprovincia').first().trigger('click');
            return;
        }

        if (!esCampoCodigoProvincia($target)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        leerProvinciaPorCodigo($target.val(), target, true);
    };
    document.addEventListener('keydown', window.__provinciaEnterCapture, true);

    // Al reescribir el código se limpia la marca de inválido para no repetir el aviso.
    $(document)
        .off('input.consultaProvincia', '.codigoprovincia')
        .on('input.consultaProvincia', '.codigoprovincia', function () {
            $(this).removeAttr('data-provincia-invalida');
        });

    $('#consultaprovinciaModal').off('shown.bs.modal.consultaProvincia').on('shown.bs.modal.consultaProvincia', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultaprovinciaModal').off('click.consultaProvincia').on('click.consultaProvincia', function () {
        $('#consultaprovinciaModal').modal('hide');
    });

    $(document).off('click.eligeconsultaprovincia').on('click.eligeconsultaprovincia', '.eligeconsultaprovincia', function () {
        let $fila = $(this).parents('tr');
        let datos = {
            id: $fila.children().first().html(),
            nombre: $fila.find('.nombre').html(),
            codigo: $fila.find('.codigo').html(),
            jurisdiccion: $fila.find('.jurisdiccion').html(),
        };

        var $contenedor = ptrprovincia_id && ptrprovincia_id.length
            ? contenedorCampoProvincia(ptrprovincia_id)
            : $();
        aplicarProvinciaEnCampo($contenedor, datos);

        if (ptrprovincia_id && ptrprovincia_id.length) {
            ptrprovincia_id.val(datos.id);
        }

        if ($contenedor.length && !$contenedor.hasClass('tm-provincia-iibb-campo')) {
            $contenedor.find('.provincia_id').trigger('change');
        }

        $('#consultaprovinciaModal').modal('hide');
    });

    // Blur / cambio de código: resuelve sin alertar (el aviso queda para Enter).
    $(document)
        .off('change.consultaProvincia', '.codigoprovincia, #codigoprovincia')
        .on('change.consultaProvincia', '.codigoprovincia, #codigoprovincia', function (event) {
            event.preventDefault();
            leerProvinciaPorCodigo($(this).val(), this, false);
        });
}

$(function () {
    activa_eventos_consultaprovincia();
});
