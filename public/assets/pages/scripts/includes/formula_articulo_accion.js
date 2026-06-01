(function () {
    'use strict';

    function config() {
        return window.FORMULA_ARTICULO_ACCION || window.ARTICULOS_VENDIDOS_GASTRONOMIA || {};
    }

    function urlFormulaEditar(formulaId) {
        var cfg = config();
        var base = (cfg.urlFormulaBase || '').replace(/\/$/, '');
        var fid = parseInt(formulaId, 10) || 0;
        if (!base || fid <= 0) {
            return '';
        }
        var url = base + '/' + fid + '/editar?origen=modal_consulta';
        if (window.ModoConsulta && typeof window.ModoConsulta.url === 'function') {
            url = window.ModoConsulta.url(url);
        }
        return url;
    }

    function abrirFormula(formulaId, mensaje) {
        var fid = parseInt(formulaId, 10) || 0;
        if (fid <= 0) {
            window.alert(
                mensaje || 'No hay fórmula vinculada a este artículo. Verifique el CRUD de fórmulas o ejecute "Vincular con artículos".'
            );
            return;
        }
        var url = urlFormulaEditar(fid);
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    }

    function resolverFormulaArticulo(articuloId, callback) {
        var cfg = config();
        var aid = parseInt(articuloId, 10) || 0;
        var base = (cfg.urlResolverFormulaBase || '').replace(/\/$/, '');
        if (!cfg.puedeVerFormula || !base || aid <= 0) {
            if (typeof callback === 'function') {
                callback(null, null);
            }
            return;
        }

        fetch(base + '/' + aid, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, body: j };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.body) {
                    if (typeof callback === 'function') {
                        callback(null, (res.body && res.body.message) || 'No se pudo resolver la fórmula.');
                    }
                    return;
                }
                var fid = res.body.formula_id && parseInt(res.body.formula_id, 10) > 0
                    ? parseInt(res.body.formula_id, 10)
                    : null;
                if (typeof callback === 'function') {
                    callback(fid, res.body.mensaje || null);
                }
            })
            .catch(function () {
                if (typeof callback === 'function') {
                    callback(null, 'Error de comunicación al consultar la fórmula.');
                }
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-ver-formula-articulo');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        resolverFormulaArticulo(btn.getAttribute('data-articulo-id'), function (formulaId, mensaje) {
            abrirFormula(formulaId, mensaje);
        });
    });

    window.FormulaArticuloAccion = {
        resolver: resolverFormulaArticulo,
        abrir: abrirFormula,
        urlEditar: urlFormulaEditar,
    };
})();
