/* global carpetaBase */
(function ($) {
    'use strict';

    var $campoActivo = $();
    var filasActuales = [];

    function endpoint(nombre) {
        var urls = window.perdidaPersonalCatalogosUrls || {};
        if (urls[nombre]) {
            return urls[nombre];
        }
        var base = (typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '');
        return base + '/caja/perdida-personal/catalogos/' + nombre;
    }

    function empresaId() {
        return parseInt($('#empresa_id').val() || '0', 10) || 0;
    }

    function tipoCampo($campo) {
        return String($campo.data('tipo') || '');
    }

    function requiereEmpresa($campo) {
        return String($campo.data('requiere-empresa') || '0') === '1';
    }

    function tituloTipo(tipo) {
        if (tipo === 'imputacion') {
            return 'Imputaciones de p\u00e9rdida';
        }
        if (tipo === 'empleado') {
            return 'Empleados';
        }
        return 'Conceptos de p\u00e9rdida';
    }

    function limpiarCampo($campo, mantenerCodigo) {
        if (!$campo || !$campo.length) {
            return;
        }
        var $id = $campo.find('.perdida-catalogo-id');
        $id.val('').attr('data-codigo', '').data('codigo', '').trigger('change');
        if (!mantenerCodigo) {
            $campo.find('.perdida-catalogo-codigo').val('');
        }
        $campo.find('.perdida-catalogo-descripcion').val('');
        $campo.find('.btn-link-editar-perdida-catalogo').attr('href', '#').addClass('d-none');
    }

    function aplicarCampo($campo, fila) {
        if (!$campo || !$campo.length || !fila || !fila.id) {
            return;
        }
        var codigo = String(fila.codigo == null ? '' : fila.codigo);
        $campo.find('.perdida-catalogo-id')
            .val(fila.id)
            .attr('data-codigo', codigo)
            .data('codigo', codigo)
            .trigger('change');
        $campo.find('.perdida-catalogo-codigo').val(codigo);
        $campo.find('.perdida-catalogo-descripcion').val(fila.nombre || '');

        var $link = $campo.find('.btn-link-editar-perdida-catalogo');
        if ($link.length && fila.consultar_url) {
            $link.attr('href', fila.consultar_url).removeClass('d-none');
        } else {
            $link.attr('href', '#').addClass('d-none');
        }
    }

    function textoSeguro(valor) {
        return $('<div>').text(valor == null ? '' : String(valor)).html();
    }

    function renderFilas(filas) {
        filasActuales = Array.isArray(filas) ? filas : [];
        var html = '';
        filasActuales.forEach(function (fila, indice) {
            html += '<tr>';
            html += '<td>' + textoSeguro(fila.codigo) + '</td>';
            html += '<td>' + textoSeguro(fila.nombre) + '</td>';
            html += '<td class="text-nowrap">';
            html += '<button type="button" class="btn btn-warning btn-sm elegir-catalogo-perdida" data-indice="'
                + indice + '">Elegir</button>';
            if (fila.consultar_url) {
                html += ' <a class="btn btn-info btn-sm" href="' + textoSeguro(fila.consultar_url)
                    + '" target="_blank" rel="noopener">Consultar</a>';
            }
            html += '</td></tr>';
        });
        if (!html) {
            html = '<tr><td colspan="3" class="text-muted">No se encontraron registros.</td></tr>';
        }
        $('#datosCatalogoPerdidaPersonal').html(html);
    }

    function buscar(consulta) {
        if (!$campoActivo.length) {
            return;
        }
        $.getJSON(endpoint('consulta'), {
            tipo: tipoCampo($campoActivo),
            empresa_id: empresaId(),
            consulta: consulta || ''
        }).done(function (respuesta) {
            renderFilas(respuesta && respuesta.data ? respuesta.data : []);
        }).fail(function () {
            renderFilas([]);
        });
    }

    function abrir($campo) {
        if (!$campo || !$campo.length) {
            return;
        }
        if (requiereEmpresa($campo) && empresaId() <= 0) {
            alert('Primero seleccione la empresa.');
            return;
        }
        $campoActivo = $campo;
        var tipo = tipoCampo($campo);
        $('#consultaCatalogoPerdidaPersonalModalLabel').text(tituloTipo(tipo));
        $('#consultaCatalogoPerdidaPersonal').val('');
        buscar('');
        $('#consultaCatalogoPerdidaPersonalModal').modal('show');
    }

    function avanzarFocoSiCorresponde($desde, avanzar) {
        if (!avanzar) {
            return;
        }
        if (typeof window.focusSiguienteCampoPerdidaPersonal === 'function') {
            window.focusSiguienteCampoPerdidaPersonal($desde);
        }
    }

    /** Deja el foco en el código para corregir sin repetir el aviso. */
    function marcarValorInvalido($input, valor, mensaje) {
        $input.data('perdidaValorInvalido', valor);
        alert(mensaje);
        setTimeout(function () {
            $input.trigger('focus');
            if ($input.get(0) && typeof $input.get(0).select === 'function') {
                $input.get(0).select();
            }
        }, 0);
    }

    function resolverCodigo($input, opciones) {
        opciones = opciones || {};
        var avanzar = !!opciones.avanzarFoco;
        var reintentar = !!opciones.reintentar;
        var $campo = $input.closest('.tm-perdida-catalogo-campo');
        var valor = String($input.val() || '').trim();

        if ($input.data('perdidaResolviendo')) {
            return;
        }
        if (!valor) {
            $input.removeData('perdidaValorInvalido');
            limpiarCampo($campo, false);
            return;
        }
        // Sin reintento explícito no se vuelve a avisar por el mismo valor errado.
        if (!reintentar && $input.data('perdidaValorInvalido') === valor) {
            return;
        }
        $input.removeData('perdidaValorInvalido');

        if (requiereEmpresa($campo) && empresaId() <= 0) {
            limpiarCampo($campo, true);
            marcarValorInvalido($input, valor, 'Primero seleccione la empresa.');
            return;
        }

        limpiarCampo($campo, true);
        $input.data('perdidaResolviendo', true);
        $.getJSON(endpoint('resolver'), {
            tipo: tipoCampo($campo),
            empresa_id: empresaId(),
            valor: valor
        }).done(function (respuesta) {
            if (respuesta && respuesta.ok) {
                aplicarCampo($campo, respuesta);
                avanzarFocoSiCorresponde($input, avanzar);
                return;
            }
            marcarValorInvalido(
                $input,
                valor,
                respuesta && respuesta.mensaje ? respuesta.mensaje : 'No se encontr\u00f3 el registro indicado.'
            );
        }).fail(function () {
            marcarValorInvalido($input, valor, 'No se pudo resolver el registro.');
        }).always(function () {
            $input.removeData('perdidaResolviendo');
        });
    }

    window.limpiarCatalogosPerdidaPersonalPorEmpresa = function () {
        $('.tm-perdida-catalogo-campo[data-requiere-empresa="1"]').each(function () {
            limpiarCampo($(this), false);
        });
    };

    $(document)
        .on('click', '.consulta-perdida-catalogo', function (e) {
            e.preventDefault();
            abrir($(this).closest('.tm-perdida-catalogo-campo'));
        })
        .on('keydown', '.perdida-catalogo-codigo', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                abrir($(this).closest('.tm-perdida-catalogo-campo'));
                return;
            }
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                resolverCodigo($(this), { avanzarFoco: true, reintentar: true });
            }
        })
        .on('input', '.perdida-catalogo-codigo', function () {
            $(this).removeData('perdidaValorInvalido');
        })
        .on('blur', '.perdida-catalogo-codigo', function () {
            var $campo = $(this).closest('.tm-perdida-catalogo-campo');
            var codigoActual = String($campo.find('.perdida-catalogo-id').attr('data-codigo') || '');
            if (String($(this).val() || '').trim() !== codigoActual) {
                resolverCodigo($(this), { avanzarFoco: false });
            }
        })
        .on('keyup', '#consultaCatalogoPerdidaPersonal', function () {
            buscar(String($(this).val() || '').trim());
        })
        .on('click', '.elegir-catalogo-perdida', function () {
            var fila = filasActuales[parseInt($(this).data('indice'), 10)];
            if (fila) {
                aplicarCampo($campoActivo, fila);
                $('#consultaCatalogoPerdidaPersonalModal').modal('hide');
                var $codigo = $campoActivo.find('.perdida-catalogo-codigo');
                avanzarFocoSiCorresponde($codigo, true);
            }
        });

    $('#consultaCatalogoPerdidaPersonalModal').on('shown.bs.modal', function () {
        $('#consultaCatalogoPerdidaPersonal').focus();
    });
})(jQuery);
