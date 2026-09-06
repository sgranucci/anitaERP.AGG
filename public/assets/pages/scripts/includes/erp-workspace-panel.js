/**
 * Panel split reutilizable: lista a la izquierda, PDF o edición embebida a la derecha.
 *
 * Uso: enlaces .js-erp-workspace con data-ws-url / data-ws-edit-url / data-ws-titulo / …
 * Mensaje desde iframe embed: postMessage({ type: 'erp-workspace-close' })
 */
(function ($) {
    'use strict';

    var MSG_CERRAR = 'erp-workspace-close';
    var $panel;
    var $iframe;
    var $titulo;
    var $meta;
    var $btnPdf;
    var $btnEdit;
    var $loading;
    var estado = { urlPdf: '', urlEdit: '', id: null, modo: null };

    function withEmbed(url) {
        if (!url) {
            return '';
        }
        if (/([?&])embed=/.test(url)) {
            return url;
        }
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
    }

    function asegurarPanel() {
        if ($panel && $panel.length) {
            return;
        }

        $('body').append(
            '<aside class="erp-workspace" id="erp-workspace" hidden aria-hidden="true">' +
                '<div class="erp-workspace-cabeza">' +
                    '<div class="erp-workspace-cabeza-txt">' +
                        '<div class="erp-workspace-kicker">Solapa de trabajo</div>' +
                        '<div class="erp-workspace-titulo" id="erp-workspace-titulo"></div>' +
                        '<div class="erp-workspace-meta" id="erp-workspace-meta"></div>' +
                    '</div>' +
                    '<div class="erp-workspace-acciones">' +
                        '<button type="button" class="erp-workspace-btn js-erp-ws-pdf" hidden title="Ver el PDF del comprobante">' +
                            '<i class="fa fa-file-pdf-o"></i><span>PDF</span></button>' +
                        '<button type="button" class="erp-workspace-btn js-erp-ws-edit" hidden title="Abrir el formulario (sin menú)">' +
                            '<i class="fa fa-pencil"></i><span>Formulario</span></button>' +
                        '<a class="erp-workspace-btn js-erp-ws-nueva" target="_blank" rel="noopener noreferrer" hidden title="Abrir esta vista a pantalla completa (sin menú)">' +
                            '<i class="fa fa-expand"></i><span>Ampliar</span></a>' +
                        '<button type="button" class="erp-workspace-btn erp-workspace-btn-cerrar js-erp-ws-cerrar" aria-label="Cerrar solapa">' +
                            '<i class="fa fa-times"></i><span>Cerrar</span></button>' +
                    '</div>' +
                '</div>' +
                '<div class="erp-workspace-cuerpo">' +
                    '<div class="erp-workspace-loading" id="erp-workspace-loading" hidden>' +
                        '<i class="fa fa-spinner fa-spin"></i> Cargando…' +
                    '</div>' +
                    '<iframe class="erp-workspace-frame" id="erp-workspace-frame" title="Solapa de trabajo"></iframe>' +
                '</div>' +
            '</aside>'
        );

        $panel = $('#erp-workspace');
        $iframe = $('#erp-workspace-frame');
        $titulo = $('#erp-workspace-titulo');
        $meta = $('#erp-workspace-meta');
        $btnPdf = $panel.find('.js-erp-ws-pdf');
        $btnEdit = $panel.find('.js-erp-ws-edit');
        $loading = $('#erp-workspace-loading');

        $iframe.on('load', function () {
            $loading.attr('hidden', true);
        });
    }

    function marcarFila(id) {
        $('[data-ws-id]').removeClass('erp-ws-fila-activa');
        $('.tf-grilla tbody tr').removeClass('tf-fila-activa');
        if (!id) {
            return;
        }
        $('[data-ws-id="' + id + '"]').addClass('erp-ws-fila-activa');
        $('.tf-grilla tbody tr[data-tf-id="' + id + '"]').addClass('tf-fila-activa');
    }

    function cargar(url, modo) {
        if (!url) {
            return;
        }
        $loading.removeAttr('hidden');
        $iframe.attr('src', url);
        // Pantalla completa también sin menú (embed=1).
        var urlAmpliar = modo === 'edit' ? withEmbed(url) : url;
        $panel.find('.js-erp-ws-nueva').attr('href', urlAmpliar);
        estado.modo = modo || estado.modo || 'pdf';
    }

    function syncBotones(modo) {
        if (estado.urlPdf) {
            $btnPdf.removeAttr('hidden');
        } else {
            $btnPdf.attr('hidden', true);
        }
        if (estado.urlEdit) {
            $btnEdit.removeAttr('hidden');
        } else {
            $btnEdit.attr('hidden', true);
        }
        $btnPdf.toggleClass('erp-workspace-btn-activo', modo === 'pdf');
        $btnEdit.toggleClass('erp-workspace-btn-activo', modo === 'edit');
        $btnPdf.prop('disabled', modo === 'pdf');
        $btnEdit.prop('disabled', modo === 'edit');
        $panel.find('.js-erp-ws-nueva').removeAttr('hidden');
        estado.modo = modo;
    }

    function abrir(opts) {
        asegurarPanel();

        estado.urlPdf = opts.urlPdf || '';
        estado.urlEdit = opts.urlEdit ? withEmbed(opts.urlEdit) : '';
        estado.id = opts.id || null;

        var modo = opts.modo || (estado.urlPdf ? 'pdf' : 'edit');
        var url = modo === 'edit' ? estado.urlEdit : estado.urlPdf;
        if (!url) {
            return;
        }

        $titulo.text(opts.titulo || 'Documento');
        $meta.text(opts.meta || '');
        syncBotones(modo);
        cargar(url, modo);

        $panel.removeAttr('hidden').attr('aria-hidden', 'false');
        $('body').addClass('erp-workspace-abierto');
        marcarFila(estado.id);
    }

    function cerrar() {
        if (! $panel || ! $panel.length) {
            return;
        }
        $panel.attr('hidden', true).attr('aria-hidden', 'true');
        $iframe.attr('src', 'about:blank');
        $loading.attr('hidden', true);
        $('body').removeClass('erp-workspace-abierto');
        marcarFila(null);
        estado = { urlPdf: '', urlEdit: '', id: null, modo: null };
    }

    $(document).on('click', '.js-erp-workspace', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $a = $(this);
        var urlPdf = $a.data('ws-pdf') || $a.data('pdf-url') || '';
        var urlEdit = $a.data('ws-edit') || $a.data('edit-url') || '';
        var modo = $a.data('ws-modo') || '';
        if (!urlPdf && !urlEdit) {
            urlPdf = $a.attr('href') || '';
        }
        abrir({
            urlPdf: urlPdf,
            urlEdit: urlEdit,
            titulo: $a.data('ws-titulo') || $a.data('pdf-titulo') || 'Documento',
            meta: $a.data('ws-meta') || $a.data('pdf-origen') || '',
            id: $a.data('ws-id') || $a.data('tf-id') || $a.closest('[data-ws-id]').data('ws-id') || $a.closest('tr').data('tf-id') || null,
            modo: modo || undefined,
        });
    });

    // Compat con la clase del tracking
    $(document).on('click', '.js-tf-visor', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $a = $(this);
        abrir({
            urlPdf: $a.data('pdf-url') || $a.attr('href'),
            urlEdit: $a.data('edit-url') || '',
            titulo: $a.data('pdf-titulo') || 'Comprobante',
            meta: $a.data('pdf-origen') || '',
            id: $a.data('tf-id') || $a.closest('tr').data('tf-id') || null,
            modo: 'pdf',
        });
    });

    $(document).on('click', '.js-erp-ws-cerrar, .js-tf-workspace-cerrar', function (e) {
        e.preventDefault();
        cerrar();
    });

    $(document).on('click', '.js-erp-ws-pdf', function (e) {
        e.preventDefault();
        if (!estado.urlPdf || estado.modo === 'pdf') {
            return;
        }
        syncBotones('pdf');
        cargar(estado.urlPdf, 'pdf');
    });

    $(document).on('click', '.js-erp-ws-edit', function (e) {
        e.preventDefault();
        if (!estado.urlEdit || estado.modo === 'edit') {
            return;
        }
        syncBotones('edit');
        cargar(estado.urlEdit, 'edit');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('body').hasClass('erp-workspace-abierto')) {
            cerrar();
        }
    });

    window.addEventListener('message', function (ev) {
        if (!ev.data || ev.data.type !== MSG_CERRAR) {
            return;
        }
        cerrar();
    });

    window.ErpWorkspacePanel = { abrir: abrir, cerrar: cerrar, withEmbed: withEmbed };
}(jQuery));
