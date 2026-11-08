(function () {
    function pesos(importe) {
        var n = Number(importe);
        if (!isFinite(n) || Math.abs(n) < 0.001) {
            return '0,00';
        }
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bind() {
        var modal = document.getElementById('modal-anexo3-auditoria');
        if (!modal || typeof $ === 'undefined') {
            return;
        }
        var titulo = document.getElementById('anexo3-auditoria-titulo');
        var subtitulo = document.getElementById('anexo3-auditoria-subtitulo');
        var formula = document.getElementById('anexo3-auditoria-formula');
        var avisos = document.getElementById('anexo3-auditoria-avisos');
        var lista = document.getElementById('anexo3-auditoria-lista');
        var vacio = document.getElementById('anexo3-auditoria-vacio');
        var cargando = document.getElementById('anexo3-auditoria-cargando');
        var linkCierre = document.getElementById('anexo3-auditoria-cierre');
        var urlBase = modal.getAttribute('data-url');

        document.querySelectorAll('.js-anexo3-audit').forEach(function (boton) {
            boton.addEventListener('click', function () {
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

                titulo.textContent = 'Auditoría';
                subtitulo.textContent = '';
                formula.hidden = true;
                formula.textContent = '';
                avisos.innerHTML = '';
                lista.innerHTML = '';
                vacio.hidden = true;
                cargando.hidden = false;
                linkCierre.hidden = true;
                linkCierre.removeAttribute('href');
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
                        cargando.hidden = true;
                        titulo.textContent = (data.etiqueta_columna || 'Importe') + ' · ' + pesos(data.total);
                        subtitulo.textContent = data.titulo || '';
                        if (data.formula) {
                            formula.hidden = false;
                            formula.textContent = data.formula;
                        }
                        (data.avisos || []).forEach(function (aviso) {
                            var div = document.createElement('div');
                            div.className = 'alert alert-warning py-2 mb-2';
                            div.textContent = aviso;
                            avisos.appendChild(div);
                        });
                        if (data.cierre_url) {
                            linkCierre.hidden = false;
                            linkCierre.href = data.cierre_url;
                        }
                        var movimientos = data.movimientos || [];
                        if (movimientos.length === 0) {
                            vacio.hidden = false;
                            return;
                        }
                        movimientos.forEach(function (mov) {
                            var article = document.createElement('article');
                            article.className = 'tc-anexo3-doc border rounded p-2 mb-2';
                            var header = document.createElement('header');
                            header.className = 'd-flex justify-content-between align-items-start';
                            var left = document.createElement('div');
                            if (mov.caja) {
                                var caja = document.createElement('div');
                                caja.className = 'small text-muted';
                                caja.textContent = mov.caja + (mov.grupo_columna ? ' · ' + mov.grupo_columna : '');
                                left.appendChild(caja);
                            } else if (mov.grupo_columna) {
                                var grupo = document.createElement('div');
                                grupo.className = 'small text-muted';
                                grupo.textContent = mov.grupo_columna;
                                left.appendChild(grupo);
                            }
                            var link = document.createElement('a');
                            link.className = 'text-primary';
                            link.target = '_blank';
                            link.rel = 'noopener';
                            link.href = mov.url;
                            link.textContent = mov.etiqueta;
                            left.appendChild(link);
                            var estado = document.createElement('div');
                            estado.className = 'small text-muted';
                            estado.textContent = mov.estado || '';
                            left.appendChild(estado);
                            header.appendChild(left);
                            var importe = document.createElement('strong');
                            importe.textContent = pesos(mov.aporte);
                            header.appendChild(importe);
                            article.appendChild(header);
                            var renglones = mov.renglones || [];
                            if (renglones.length) {
                                var ul = document.createElement('ul');
                                ul.className = 'list-unstyled mb-0 mt-2 small';
                                renglones.forEach(function (renglon) {
                                    if (Math.abs(Number(renglon.importe) || 0) < 0.001 && !renglon.etiqueta) {
                                        return;
                                    }
                                    var li = document.createElement('li');
                                    li.className = 'd-flex justify-content-between';
                                    var et = document.createElement('span');
                                    et.textContent = renglon.etiqueta || '';
                                    var im = document.createElement('span');
                                    im.textContent = pesos(renglon.importe);
                                    li.appendChild(et);
                                    li.appendChild(im);
                                    ul.appendChild(li);
                                });
                                article.appendChild(ul);
                            }
                            lista.appendChild(article);
                        });
                    })
                    .catch(function (error) {
                        cargando.hidden = true;
                        vacio.hidden = false;
                        vacio.textContent = error.message || 'Error al auditar.';
                    });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
