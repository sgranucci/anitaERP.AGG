(function ($) {
    'use strict';

    function pesos(importe) {
        var n = Number(importe);
        if (!isFinite(n) || Math.abs(n) < 0.001) {
            return '0,00';
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function el(modal, sufijo) {
        return document.getElementById(modal.id + '-' + sufijo);
    }

    function limpiar(modal) {
        var mapa = {
            titulo: 'Auditoría del importe',
            subtitulo: '',
            'total-valor': '0,00',
            formula: '',
            pista: '',
            vacio: 'Ningún movimiento aporta a este importe.'
        };
        Object.keys(mapa).forEach(function (sufijo) {
            var nodo = el(modal, sufijo);
            if (nodo) {
                nodo.textContent = mapa[sufijo];
            }
        });
        ['total', 'bloque-formula', 'bloque-pista', 'vacio'].forEach(function (sufijo) {
            var nodo = el(modal, sufijo);
            if (nodo) {
                nodo.hidden = true;
            }
        });
        var avisos = el(modal, 'avisos');
        if (avisos) {
            avisos.innerHTML = '';
        }
        var lista = el(modal, 'lista');
        if (lista) {
            lista.innerHTML = '';
        }
        var cargando = el(modal, 'cargando');
        if (cargando) {
            cargando.hidden = false;
        }
        var linkCierre = el(modal, 'cierre');
        if (linkCierre) {
            linkCierre.hidden = true;
            linkCierre.removeAttribute('href');
        }
        var docsCount = el(modal, 'docs-count');
        if (docsCount) {
            docsCount.hidden = true;
            docsCount.textContent = '';
        }
    }

    function renderMovimiento(mov) {
        var article = document.createElement('article');
        article.className = 'tc-audit-doc';

        var header = document.createElement('header');
        header.className = 'tc-audit-doc-header';

        var left = document.createElement('div');
        left.className = 'tc-audit-doc-meta';

        if (mov.caja || mov.grupo_columna) {
            var meta = document.createElement('div');
            meta.className = 'tc-audit-doc-grupo';
            meta.textContent = [mov.caja, mov.grupo_columna].filter(Boolean).join(' · ');
            left.appendChild(meta);
        }

        var link = document.createElement('a');
        link.className = 'tc-audit-doc-link';
        link.target = '_blank';
        link.rel = 'noopener';
        link.href = mov.url || '#';
        var icon = document.createElement('i');
        icon.className = 'fa fa-external-link';
        icon.setAttribute('aria-hidden', 'true');
        link.appendChild(icon);
        link.appendChild(document.createTextNode(' ' + (mov.etiqueta || 'Documento')));
        left.appendChild(link);

        if (mov.estado) {
            var estado = document.createElement('span');
            estado.className = 'badge badge-light border tc-audit-doc-estado';
            estado.textContent = mov.estado;
            left.appendChild(estado);
        }

        header.appendChild(left);

        var importe = document.createElement('strong');
        importe.className = 'tc-audit-doc-aporte';
        importe.textContent = pesos(mov.aporte);
        header.appendChild(importe);
        article.appendChild(header);

        var renglones = mov.renglones || [];
        if (renglones.length) {
            var ul = document.createElement('ul');
            ul.className = 'tc-audit-doc-renglones';
            renglones.forEach(function (renglon) {
                if (Math.abs(Number(renglon.importe) || 0) < 0.001 && !renglon.etiqueta) {
                    return;
                }
                var li = document.createElement('li');
                var et = document.createElement('span');
                et.textContent = renglon.etiqueta || '';
                var im = document.createElement('span');
                im.textContent = pesos(renglon.importe);
                li.appendChild(et);
                li.appendChild(im);
                ul.appendChild(li);
            });
            if (ul.children.length) {
                article.appendChild(ul);
            }
        }

        return article;
    }

    function modalAuditoria() {
        var modal = document.getElementById('modal-tc-auditoria');
        if (!modal) {
            return null;
        }
        // Bootstrap: el modal no puede vivir dentro de .card / overflow o queda invisible.
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    function abrirDesdeBoton(boton) {
        var modal = modalAuditoria();
        if (!modal) {
            return;
        }
        var urlBase = modal.getAttribute('data-url');
        if (!urlBase) {
            return;
        }

        var params = new URLSearchParams();
        params.set('columna', boton.getAttribute('data-columna') || '');
        params.set('scope', boton.getAttribute('data-scope') || 'fila');
        var asignacionId = boton.getAttribute('data-asignacion-id');
        var tipo = boton.getAttribute('data-tipo');
        if (asignacionId) {
            params.set('asignacion_id', asignacionId);
        }
        if (tipo) {
            params.set('tipo', tipo);
        }

        limpiar(modal);
        $(modal).modal('show');

        fetch(urlBase + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('No se pudo cargar el detalle.');
                }
                return respuesta.json();
            })
            .then(function (data) {
                var cargando = el(modal, 'cargando');
                if (cargando) {
                    cargando.hidden = true;
                }
                var titulo = el(modal, 'titulo');
                if (titulo) {
                    titulo.textContent = data.etiqueta_columna || 'Importe';
                }
                var subtitulo = el(modal, 'subtitulo');
                if (subtitulo) {
                    subtitulo.textContent = data.titulo || '';
                }
                var total = el(modal, 'total');
                var totalValor = el(modal, 'total-valor');
                if (total && totalValor) {
                    total.hidden = false;
                    totalValor.textContent = pesos(data.total);
                }
                if (data.formula) {
                    var bloqueFormula = el(modal, 'bloque-formula');
                    var formula = el(modal, 'formula');
                    if (bloqueFormula && formula) {
                        bloqueFormula.hidden = false;
                        formula.textContent = data.formula;
                    }
                }
                if (data.como_auditar) {
                    var bloquePista = el(modal, 'bloque-pista');
                    var pista = el(modal, 'pista');
                    if (bloquePista && pista) {
                        bloquePista.hidden = false;
                        pista.textContent = data.como_auditar;
                    }
                }
                var avisos = el(modal, 'avisos');
                (data.avisos || []).forEach(function (aviso) {
                    if (!avisos) {
                        return;
                    }
                    var div = document.createElement('div');
                    div.className = 'alert alert-warning py-2 mb-2';
                    div.textContent = aviso;
                    avisos.appendChild(div);
                });
                var linkCierre = el(modal, 'cierre');
                if (data.cierre_url && linkCierre) {
                    linkCierre.hidden = false;
                    linkCierre.href = data.cierre_url;
                }
                var movimientos = data.movimientos || [];
                var docsCount = el(modal, 'docs-count');
                if (docsCount) {
                    docsCount.hidden = false;
                    docsCount.textContent = movimientos.length === 1
                        ? '1 documento'
                        : (movimientos.length + ' documentos');
                }
                if (movimientos.length === 0) {
                    var vacio = el(modal, 'vacio');
                    if (vacio) {
                        vacio.hidden = false;
                    }
                    return;
                }
                var lista = el(modal, 'lista');
                movimientos.forEach(function (mov) {
                    if (lista) {
                        lista.appendChild(renderMovimiento(mov));
                    }
                });
            })
            .catch(function (error) {
                var cargando = el(modal, 'cargando');
                if (cargando) {
                    cargando.hidden = true;
                }
                var vacio = el(modal, 'vacio');
                if (vacio) {
                    vacio.hidden = false;
                    vacio.textContent = error.message || 'Error al auditar.';
                }
            });
    }

    $(document).on('click', '.js-tc-audit', function (e) {
        e.preventDefault();
        abrirDesdeBoton(this);
    });

    $(function () {
        modalAuditoria();
    });
})(window.jQuery);
