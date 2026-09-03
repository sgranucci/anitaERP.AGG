(function ($) {
    'use strict';

    function nombreFirmante(f) {
        if (!f) {
            return '';
        }
        return f.nombre || f.usuario || ('Usuario ' + f.id);
    }

    function detalleFirmante(f) {
        var partes = [];
        if (f && f.usuario) {
            partes.push(f.usuario);
        }
        if (f && f.email) {
            partes.push(f.email);
        }
        return partes.join(' \u00b7 ');
    }

    function renderFirmantes(firmantes) {
        var html = '<div class="list-group">';
        (firmantes || []).forEach(function (f, idx) {
            var detalle = detalleFirmante(f);
            html += '<label class="list-group-item list-group-item-action mb-0">';
            html += '<input type="radio" name="oc_firmante_gastronomia_arbol" class="mr-2" value="' + f.id + '"' + (idx === 0 ? ' checked' : '') + '>';
            html += '<strong>' + $('<div>').text(nombreFirmante(f)).html() + '</strong>';
            if (detalle) {
                html += '<br><small class="text-muted">' + $('<div>').text(detalle).html() + '</small>';
            }
            html += '</label>';
        });
        html += '</div>';
        $('#ocFirmanteGastronomiaArbolLista').html(html);
    }

    function asegurarHidden($form) {
        var $hidden = $form.find('input[name="destinatario_usuario_id"]');
        if (!$hidden.length) {
            $hidden = $('<input type="hidden" name="destinatario_usuario_id" value="">');
            $form.append($hidden);
        }
        return $hidden;
    }

    function enviarConFirmante($form, destinatarioId) {
        asegurarHidden($form).val(destinatarioId || '');
        $form.data('oc-gastro-skip-preview', true);
        $form[0].submit();
    }

    function initForm($form) {
        if (!$form || !$form.length || $form.data('oc-gastro-firmante-bound')) {
            return;
        }
        $form.data('oc-gastro-firmante-bound', true);

        $form.on('submit', function (e) {
            if ($form.data('oc-gastro-skip-preview')) {
                return true;
            }

            var previewUrl = $form.attr('data-firmantes-url') || '';
            var ocId = $form.attr('data-ordencompra-id') || $form.data('ordencompra-id') || '';
            if (!previewUrl && ocId) {
                var base = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase) ? window.carpetaBase : '';
                previewUrl = base + '/compras/ordencompra/' + ocId + '/firmantes-gastronomia-arbol';
            }
            if (!previewUrl) {
                return true;
            }

            e.preventDefault();
            var $btn = $form.find('[type=submit]');
            $btn.prop('disabled', true);

            $.getJSON(previewUrl)
                .done(function (data) {
                    $btn.prop('disabled', false);
                    if (!data || data.mensaje !== 'ok') {
                        alert((data && data.errores) ? data.errores : 'No se pudieron consultar los firmantes del árbol.');
                        return;
                    }
                    if (!data.requiere_seleccion) {
                        enviarConFirmante($form, '');
                        return;
                    }
                    var firmantes = data.firmantes || [];
                    var nivel = data.nivel || '';
                    var texto = 'Hay más de un firmante';
                    if (nivel) {
                        texto += ' en el nivel ' + nivel + ' del árbol';
                    }
                    texto += ' que puede recibir la orden de compra. Elija a quién enviarla.';
                    $('#ocFirmanteGastronomiaArbolTexto').text(texto);
                    $('#ocFirmanteGastronomiaArbolError').addClass('d-none').text('');
                    renderFirmantes(firmantes);
                    $form.data('pending-gastro-form', true);
                    $('#modalOcFirmanteGastronomiaArbol').data('source-form', $form).modal('show');
                })
                .fail(function (xhr) {
                    $btn.prop('disabled', false);
                    var msg = 'No se pudieron consultar los firmantes del árbol.';
                    if (xhr.responseJSON && xhr.responseJSON.errores) {
                        msg = xhr.responseJSON.errores;
                    }
                    alert(msg);
                });
        });
    }

    $(function () {
        initForm($('#formOcEnviarGastronomia'));
        initForm($('#formBandejaEnviarGastro'));

        $('#ocFirmanteGastronomiaArbolConfirmar').on('click', function () {
            var $modal = $('#modalOcFirmanteGastronomiaArbol');
            var $form = $modal.data('source-form');
            var seleccionado = parseInt($('#ocFirmanteGastronomiaArbolLista').find('input[name="oc_firmante_gastronomia_arbol"]:checked').val(), 10) || 0;
            if (!$form || !$form.length) {
                $modal.modal('hide');
                return;
            }
            if (seleccionado <= 0) {
                $('#ocFirmanteGastronomiaArbolError').removeClass('d-none').text('Debe elegir un firmante.');
                return;
            }
            $modal.modal('hide');
            enviarConFirmante($form, seleccionado);
        });
    });

    window.OcEnviarGastronomiaFirmante = {
        initForm: initForm,
        setOrdencompraId: function ($form, id, previewUrl) {
            if (!$form || !$form.length) {
                return;
            }
            $form.attr('data-ordencompra-id', id || '');
            if (previewUrl) {
                $form.attr('data-firmantes-url', previewUrl);
            }
            initForm($form);
        }
    };
})(jQuery);
