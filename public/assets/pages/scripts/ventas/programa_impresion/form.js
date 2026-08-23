(function ($) {
    'use strict';

    function nextIndex($items, attr) {
        var max = -1;
        $items.each(function () {
            var n = parseInt($(this).attr(attr), 10);
            if (!isNaN(n) && n > max) {
                max = n;
            }
        });
        return max + 1;
    }

    function etiquetasFormulario() {
        try {
            return JSON.parse($('#programa-formularios').attr('data-etiquetas') || '{}');
        } catch (e) {
            return {};
        }
    }

    function presetsCopia() {
        try {
            return JSON.parse($('#programa-agregar-comprobantes').attr('data-presets') || '[]');
        } catch (e) {
            return [];
        }
    }

    function presetPorCodigo(codigo) {
        var lista = presetsCopia();
        var i;
        codigo = String(codigo || '').toUpperCase();
        for (i = 0; i < lista.length; i += 1) {
            if (String(lista[i].codigo).toUpperCase() === codigo) {
                return lista[i];
            }
        }
        return null;
    }

    function tiposEnRuta() {
        var tipos = {};
        $('#programa-formularios .programa-formulario-bloque').each(function () {
            tipos[$(this).find('.formulario-tipo').val()] = true;
        });
        return tipos;
    }

    function syncValorRegla($row) {
        var clave = $row.find('.regla-clave').val();
        var id = '';
        if (clave === 'EMPRESA') {
            id = $row.find('.empresa_id').val();
        } else if (clave === 'TRANSPORTE') {
            id = $row.find('.transporte_id').val();
        } else if (clave === 'PROVINCIA_ENTREGA') {
            id = $row.find('.provincia_id').val();
        }
        $row.find('.regla-valor-id').val(id || '');
    }

    function limpiarValorRegla($row) {
        $row.find('.empresa_id, .transporte_id, .provincia_id, .regla-valor-id').val('');
        $row.find('.codigoempresa, .nombreempresa, .codigotransporte, .nombretransporte, .codigoprovincia, .nombreprovincia').val('');
    }

    function filtrarValorRegla($row) {
        var clave = $row.find('.regla-clave').val();
        $row.find('.regla-valor-vacio').toggle(clave === 'DEFAULT');
        $row.find('.regla-valor-empresa').toggle(clave === 'EMPRESA');
        $row.find('.regla-valor-transporte').toggle(clave === 'TRANSPORTE');
        $row.find('.regla-valor-provincia').toggle(clave === 'PROVINCIA_ENTREGA');
        if (clave === 'DEFAULT') {
            limpiarValorRegla($row);
        } else {
            syncValorRegla($row);
        }
    }

    function aplicarEmpresa($ctx, data) {
        if (!$ctx || !$ctx.length) {
            return;
        }
        $ctx.find('.empresa_id').val(data && data.id ? data.id : '');
        $ctx.find('.codigoempresa').val(data && data.codigo ? data.codigo : '');
        $ctx.find('.nombreempresa').val(data && data.nombre ? data.nombre : '');
        syncValorRegla($ctx.closest('tr'));
    }

    function empresasLista() {
        var filas = [];
        $('#programa-consulta-empresa-modal .programa-empresa-fila').each(function () {
            filas.push({
                id: $(this).data('id'),
                codigo: String($(this).data('codigo') || ''),
                nombre: String($(this).data('nombre') || '')
            });
        });
        return filas;
    }

    function resolverEmpresa($ctx, codigo, avisar) {
        var cod = $.trim(codigo);
        if (cod === '') {
            aplicarEmpresa($ctx, {});
            return;
        }
        var hit = null;
        empresasLista().forEach(function (emp) {
            if (String(emp.codigo).toUpperCase() === cod.toUpperCase() || String(emp.id) === cod) {
                hit = emp;
            }
        });
        if (hit) {
            aplicarEmpresa($ctx, hit);
            return;
        }
        aplicarEmpresa($ctx, {});
        $ctx.find('.codigoempresa').val(cod);
        if (avisar) {
            $('#programa-consulta-empresa-modal').modal('hide');
            setTimeout(function () {
                alert('No existe una empresa con el código ' + cod + '.');
                $ctx.find('.codigoempresa').trigger('focus').trigger('select');
            }, 0);
        }
    }

    function activaConsultaEmpresaPrograma() {
        var $ctxEmpresa = null;
        $(document).off('click.progEmp', '.consultaempresa-programa').on('click.progEmp', '.consultaempresa-programa', function () {
            $ctxEmpresa = $(this).closest('.tm-empresa-campo');
            $('#programa-consulta-empresa-texto').val('');
            $('#programa-consulta-empresa-modal .programa-empresa-fila').show();
            $('#programa-consulta-empresa-modal').modal('show');
        });
        $(document).off('input.progEmp', '#programa-consulta-empresa-texto').on('input.progEmp', '#programa-consulta-empresa-texto', function () {
            var q = $.trim($(this).val()).toLowerCase();
            $('#programa-consulta-empresa-modal .programa-empresa-fila').each(function () {
                var t = (String($(this).data('codigo') || '') + ' ' + String($(this).data('nombre') || '')).toLowerCase();
                $(this).toggle(!q || t.indexOf(q) !== -1);
            });
        });
        $(document).off('click.progEmpElige', '.programa-elige-empresa').on('click.progEmpElige', '.programa-elige-empresa', function () {
            var $fila = $(this).closest('.programa-empresa-fila');
            aplicarEmpresa($ctxEmpresa, {
                id: $fila.data('id'),
                codigo: $fila.data('codigo'),
                nombre: $fila.data('nombre')
            });
            $('#programa-consulta-empresa-modal').modal('hide');
        });
        $(document).off('keydown.progEmpF1').on('keydown.progEmpF1', '.codigoempresa', function (e) {
            if (e.key !== 'F1' && e.keyCode !== 112) {
                return;
            }
            e.preventDefault();
            $ctxEmpresa = $(this).closest('.tm-empresa-campo');
            $('#programa-consulta-empresa-modal').modal('show');
        });
        $(document).off('keydown.progEmpEnter').on('keydown.progEmpEnter', '.codigoempresa', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13) {
                return;
            }
            e.preventDefault();
            resolverEmpresa($(this).closest('.tm-empresa-campo'), $(this).val(), true);
        });
        $(document).off('change.progEmpBlur', '.codigoempresa').on('change.progEmpBlur', '.codigoempresa', function () {
            resolverEmpresa($(this).closest('.tm-empresa-campo'), $(this).val(), false);
        });
    }

    function activaConsultasRegla() {
        if (typeof activa_eventos_consultatransporte === 'function') {
            activa_eventos_consultatransporte();
        }
        if (typeof activa_eventos_consultaprovincia === 'function') {
            activa_eventos_consultaprovincia();
        }
        activaConsultaEmpresaPrograma();
    }

    function reindexFormularios() {
        $('#programa-formularios .programa-formulario-bloque').each(function (orden) {
            $(this).find('.formulario-orden').val((orden + 1) * 10);
            $(this).find('.programa-copia-hoja').each(function (ci) {
                $(this).find('.copia-orden').val((ci + 1) * 10);
            });
        });
    }

    function syncChips($bloque) {
        var usados = {};
        $bloque.find('.programa-copia-hoja .copia-codigo').each(function () {
            usados[String($(this).val() || '').toUpperCase()] = true;
        });
        $bloque.find('.toggle-copia-preset').each(function () {
            var on = !!usados[String($(this).data('codigo') || '').toUpperCase()];
            $(this)
                .toggleClass('btn-primary', on)
                .toggleClass('btn-outline-secondary', !on)
                .attr('aria-pressed', on ? 'true' : 'false');
        });
    }

    function aplicarPreset($hoja, preset) {
        if (!preset) {
            return;
        }
        $hoja.attr('data-codigo', preset.codigo);
        $hoja.find('.copia-codigo').val(preset.codigo);
        $hoja.find('.copia-leyenda').val(preset.leyenda);
        $hoja.find('.copia-preset').val(preset.codigo);
        $hoja.find('.copia-otra-wrap').hide();
        var $dest = $hoja.find('.copia-destinatario');
        if (!$.trim($dest.val()) || $dest.data('manual') !== 1) {
            $dest.val(preset.destinatario || '');
        }
        if (String(preset.codigo).toUpperCase() === 'NAS') {
            var $sel = $hoja.find('.copia-salida');
            $sel.find('option').each(function () {
                if (/NAS/i.test($(this).text())) {
                    $sel.val($(this).val());
                    return false;
                }
            });
        }
    }

    function agregarCopia($bloque, preset) {
        var fi = $bloque.attr('data-fi');
        var ci = nextIndex($bloque.find('.programa-copia-hoja'), 'data-ci');
        var html = $('#tpl-copia').html()
            .replace(/__FI__/g, fi)
            .replace(/__CI__/g, ci);
        var $hoja = $(html);
        aplicarPreset($hoja, preset || presetPorCodigo('ORI') || {
            codigo: 'ORI',
            leyenda: 'ORIGINAL',
            destinatario: 'Cliente'
        });
        $bloque.find('.programa-copias').append($hoja);
        return $hoja;
    }

    function refrescarBotonesAgregar() {
        var tipos = tiposEnRuta();
        $('.agrega-comprobante').each(function () {
            var tipo = String($(this).data('formulario'));
            var etiqueta = String($(this).data('etiqueta'));
            var enRuta = !!tipos[tipo];
            $(this)
                .prop('disabled', enRuta)
                .toggleClass('btn-primary', !enRuta)
                .toggleClass('btn-outline-secondary', enRuta);
            if (enRuta) {
                $(this).html('<i class="fa fa-check"></i> ' + etiqueta + ' en la ruta');
            } else {
                $(this).html('<i class="fa fa-plus"></i> ' + etiqueta);
            }
        });
        var hay = $('#programa-formularios .programa-formulario-bloque').length > 0;
        $('#programa-ruta-vacia').toggle(!hay);
        $('#programa-formularios').toggle(hay);
    }

    function textoNodo(texto) {
        return $('<div>').text(texto).html();
    }

    function refrescarRuta() {
        var $preview = $('#programa-ruta-preview');
        if (!$preview.length) {
            return;
        }
        var etiquetas = etiquetasFormulario();
        var html = [];
        $('#programa-formularios .programa-formulario-bloque').each(function () {
            var tipo = $(this).find('.formulario-tipo').val();
            var doc = etiquetas[tipo] || tipo || 'Comprobante';
            if (html.length) {
                html.push('<span class="programa-ruta-flecha" aria-hidden="true">&rarr;</span>');
            }
            html.push('<span class="programa-ruta-nodo es-doc">' + textoNodo(doc) + '</span>');
            $(this).find('.programa-copia-hoja').each(function () {
                var $hoja = $(this);
                var preset = $hoja.find('.copia-preset option:selected').text();
                var leyenda = $.trim($hoja.find('.copia-leyenda').val());
                var etiquetaCopia = '';
                if ($hoja.find('.copia-preset').val() && $hoja.find('.copia-preset').val() !== 'OTRA') {
                    etiquetaCopia = preset.split('—')[0].trim() || leyenda;
                } else {
                    etiquetaCopia = leyenda || 'Copia';
                }
                var dest = $.trim($hoja.find('.copia-destinatario').val());
                var salida = $.trim($hoja.find('.copia-salida option:selected').text());
                var esNas = /NAS|archivo/i.test(salida) || $hoja.find('.copia-codigo').val() === 'NAS';
                var texto = etiquetaCopia;
                if (dest) {
                    texto += ' · ' + dest;
                }
                html.push('<span class="programa-ruta-flecha" aria-hidden="true">&rarr;</span>');
                html.push('<span class="programa-ruta-nodo' + (esNas ? ' es-nas' : '') + '">' + textoNodo(texto) + '</span>');
            });
        });
        if (!html.length) {
            $preview.html('<span class="programa-ruta-vacia">La ruta está vacía. Sumá Factura, Remito o Pedido con los botones de arriba.</span>');
            return;
        }
        $preview.html(html.join(''));
    }

    function refrescarTodo() {
        $('#programa-formularios .programa-formulario-bloque').each(function () {
            syncChips($(this));
        });
        reindexFormularios();
        refrescarBotonesAgregar();
        refrescarRuta();
    }

    $(function () {
        if (!$('#form-general').length || !$('#programa-formularios').length) {
            return;
        }

        $('#tabla-reglas tbody tr').each(function () {
            filtrarValorRegla($(this));
        });

        $(document).on('click', '.agrega-comprobante', function () {
            var tipo = String($(this).data('formulario'));
            var etiqueta = String($(this).data('etiqueta'));
            if (tiposEnRuta()[tipo]) {
                return;
            }
            var fi = nextIndex($('#programa-formularios .programa-formulario-bloque'), 'data-fi');
            var $bloque = $($('#tpl-formulario').html().replace(/__FI__/g, fi));
            $bloque.attr('data-fi', fi);
            $bloque.attr('data-formulario', tipo);
            $bloque.find('.formulario-tipo').val(tipo);
            $bloque.find('.formulario-titulo').text(etiqueta);
            $bloque.find('.programa-copia-presets .small.font-weight-bold')
                .text('Elegí las copias de este ' + etiqueta.toLowerCase());
            $('#programa-formularios').append($bloque);
            if ($bloque.find('.programa-copia-hoja').length === 0) {
                agregarCopia($bloque, presetPorCodigo('ORI'));
            }
            refrescarTodo();
        });

        $('#programa-formularios').on('click', '.quita-formulario', function () {
            $(this).closest('.programa-formulario-bloque').remove();
            refrescarTodo();
        });

        $('#programa-formularios').on('click', '.mueve-formulario-izq', function () {
            var $bloque = $(this).closest('.programa-formulario-bloque');
            var $prev = $bloque.prev('.programa-formulario-bloque');
            if ($prev.length) {
                $bloque.insertBefore($prev);
                refrescarTodo();
            }
        });

        $('#programa-formularios').on('click', '.mueve-formulario-der', function () {
            var $bloque = $(this).closest('.programa-formulario-bloque');
            var $next = $bloque.next('.programa-formulario-bloque');
            if ($next.length) {
                $bloque.insertAfter($next);
                refrescarTodo();
            }
        });

        $('#programa-formularios').on('click', '.toggle-copia-preset', function () {
            var $bloque = $(this).closest('.programa-formulario-bloque');
            var codigo = String($(this).data('codigo') || '').toUpperCase();
            var $existente = $bloque.find('.programa-copia-hoja').filter(function () {
                return String($(this).find('.copia-codigo').val() || '').toUpperCase() === codigo;
            });
            if ($existente.length) {
                if ($bloque.find('.programa-copia-hoja').length <= 1) {
                    return;
                }
                $existente.remove();
            } else {
                agregarCopia($bloque, {
                    codigo: codigo,
                    leyenda: $(this).data('leyenda'),
                    destinatario: $(this).data('destinatario')
                });
            }
            refrescarTodo();
        });

        $('#programa-formularios').on('change', '.copia-preset', function () {
            var $hoja = $(this).closest('.programa-copia-hoja');
            var $bloque = $hoja.closest('.programa-formulario-bloque');
            var valor = $(this).val();
            var $opt = $(this).find('option:selected');
            if (valor === 'OTRA') {
                $hoja.attr('data-codigo', 'OTRA');
                $hoja.find('.copia-codigo').val('OTRA');
                $hoja.find('.copia-otra-wrap').show();
                $hoja.find('.copia-leyenda').val($.trim($hoja.find('.copia-leyenda-otra').val()) || 'COPIA');
            } else {
                aplicarPreset($hoja, {
                    codigo: valor,
                    leyenda: $opt.data('leyenda'),
                    destinatario: $opt.data('destinatario')
                });
            }
            syncChips($bloque);
            reindexFormularios();
            refrescarRuta();
        });

        $('#programa-formularios').on('input', '.copia-leyenda-otra', function () {
            var $hoja = $(this).closest('.programa-copia-hoja');
            $hoja.find('.copia-leyenda').val($.trim($(this).val()) || 'COPIA');
            refrescarRuta();
        });

        $('#programa-formularios').on('input', '.copia-destinatario', function () {
            $(this).data('manual', 1);
            refrescarRuta();
        });

        $('#programa-formularios').on('click', '.quita-copia', function () {
            var $lista = $(this).closest('.programa-copias');
            if ($lista.find('.programa-copia-hoja').length <= 1) {
                return;
            }
            $(this).closest('.programa-copia-hoja').remove();
            refrescarTodo();
        });

        $('#programa-formularios').on('change', '.copia-salida', refrescarRuta);

        $('#agrega-regla').on('click', function () {
            var ri = nextIndex($('#tabla-reglas tbody tr'), 'data-ri');
            var html = $('#tpl-regla').html().replace(/__RI__/g, ri);
            $('#tabla-reglas tbody').append(html);
            filtrarValorRegla($('#tabla-reglas tbody tr').last());
            activaConsultasRegla();
        });

        $('#tabla-reglas').on('click', '.quita-regla', function () {
            var $rows = $('#tabla-reglas tbody tr');
            if ($rows.length <= 1) {
                return;
            }
            $(this).closest('tr').remove();
        });

        $('#tabla-reglas').on('change', '.regla-clave', function () {
            var $row = $(this).closest('tr');
            limpiarValorRegla($row);
            filtrarValorRegla($row);
        });

        $(document).on('click', '.eligeconsultatransporte, .eligeconsultaprovincia', function () {
            setTimeout(function () {
                $('#tabla-reglas tr.fila-regla').each(function () {
                    syncValorRegla($(this));
                });
            }, 0);
        });

        activaConsultasRegla();
        refrescarTodo();
    });
})(jQuery);
