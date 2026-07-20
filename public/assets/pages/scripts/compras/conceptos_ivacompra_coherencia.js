/**
 * Coherencia neto gravado (G) ↔ IVA liquidado (I) por alícuota — validación en pantalla.
 * Espejo de App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport.
 *
 * Al abrir gravados desde IVA, las diferencias se reparte entre gravados (nunca se ajusta IVA).
 */
(function (global) {
    'use strict';

    var TOLERANCIA = 0.90;

    function round2(n) {
        return Math.round((parseFloat(n) || 0) * 100) / 100;
    }

    function tasaKey(tasa) {
        // Misma idea que PHP number_format(..., 3): evita claves "21" ambiguas.
        var n = Math.round((parseFloat(tasa) || 0) * 1000) / 1000;
        return n.toFixed(3);
    }

    function etiquetaTasa(tasa) {
        return String(parseFloat(tasa).toFixed(3)).replace(/\.?0+$/, '') + '%';
    }

    function formatoNumero(n) {
        var v = parseFloat(n) || 0;
        return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function metaConcepto(conceptosMeta, id) {
        var key = String(id);
        return conceptosMeta[key] || conceptosMeta[id] || {};
    }

    function tasaDesdeMeta(meta) {
        return round2(meta.impuesto_tasa || 0);
    }

    function analizar(lineas, conceptosMeta) {
        var netoSinTasa = 0;
        var netoPorTasa = {};
        var ivaPorTasa = {};

        lineas.forEach(function (linea) {
            var conceptoId = parseInt(linea.concepto_ivacompra_id || '0', 10);
            var meta = metaConcepto(conceptosMeta, conceptoId);
            var monto = round2(Math.abs(parseFloat(linea.monto || '0')));
            if (conceptoId <= 0 || monto <= 0) {
                return;
            }

            var tipo = String(meta.tipoconcepto || '').toUpperCase();
            var tasa = tasaDesdeMeta(meta);
            var key = tasaKey(tasa);

            if (tipo === 'G') {
                if (tasa > 0) {
                    netoPorTasa[key] = round2((netoPorTasa[key] || 0) + monto);
                } else {
                    netoSinTasa = round2(netoSinTasa + monto);
                }
            } else if (tipo === 'I' && tasa > 0) {
                ivaPorTasa[key] = round2((ivaPorTasa[key] || 0) + monto);
            }
        });

        var tasasIva = Object.keys(ivaPorTasa);
        var netoTotal = round2(netoSinTasa + Object.keys(netoPorTasa).reduce(function (acc, k) {
            return acc + (netoPorTasa[k] || 0);
        }, 0));

        return {
            aplica: tasasIva.length > 0,
            neto_sin_tasa: netoSinTasa,
            neto_por_tasa: netoPorTasa,
            iva_por_tasa: ivaPorTasa,
            tasas_iva: tasasIva,
            neto_total: netoTotal,
        };
    }

    function buildGravadosPorTasa(conceptosMeta) {
        var map = {};
        Object.keys(conceptosMeta || {}).forEach(function (key) {
            var meta = conceptosMeta[key];
            if (String(meta.tipoconcepto || '').toUpperCase() !== 'G') {
                return;
            }
            var tasa = tasaDesdeMeta(meta);
            if (tasa <= 0) {
                return;
            }
            var tKey = tasaKey(tasa);
            if (!map[tKey]) {
                map[tKey] = parseInt(key, 10);
            }
        });
        return map;
    }

    function necesitaApertura(estado) {
        var ivaPorTasa = estado.iva_por_tasa;
        var tasasIva = estado.tasas_iva;
        if (tasasIva.length < 2 || estado.neto_total <= 0) {
            return false;
        }

        var buckets = (estado.neto_sin_tasa > 0 ? 1 : 0) + Object.keys(estado.neto_por_tasa).length;
        if (buckets <= 1) {
            return true;
        }

        for (var i = 0; i < tasasIva.length; i++) {
            var tKey = tasasIva[i];
            var neto = estado.neto_por_tasa[tKey] || 0;
            if (neto <= 0) {
                return true;
            }
            var esperado = round2(neto * parseFloat(tKey) / 100);
            if (Math.abs(esperado - ivaPorTasa[tKey]) > TOLERANCIA) {
                return true;
            }
        }

        return false;
    }

    function repartirDiferencia(netoTeorico, sumaTeorica, netoOriginal) {
        var delta = round2(netoOriginal - sumaTeorica);
        var keys = Object.keys(netoTeorico);
        if (Math.abs(delta) < 0.005 || sumaTeorica <= 0 || keys.length === 0) {
            return netoTeorico;
        }

        var ajustados = {};
        var repartido = 0;
        var ultimo = keys[keys.length - 1];

        keys.forEach(function (tKey) {
            if (tKey === ultimo) {
                return;
            }
            var share = round2(delta * (netoTeorico[tKey] / sumaTeorica));
            ajustados[tKey] = round2(netoTeorico[tKey] + share);
            repartido = round2(repartido + share);
        });
        ajustados[ultimo] = round2(netoTeorico[ultimo] + (delta - repartido));

        return ajustados;
    }

    function simularApertura(estado, gravadosPorTasa) {
        var errores = [];
        var advertencias = [];
        var ivaPorTasa = estado.iva_por_tasa;
        var tasasIva = estado.tasas_iva;
        var netoOriginal = estado.neto_total;

        var netoTeorico = {};
        var sumaTeorica = 0;

        tasasIva.forEach(function (tKey) {
            var tasa = parseFloat(tKey);
            if (tasa <= 0) {
                return;
            }
            var neto = round2(ivaPorTasa[tKey] / (tasa / 100));
            netoTeorico[tKey] = neto;
            sumaTeorica = round2(sumaTeorica + neto);
        });

        var netoDescompuesto = repartirDiferencia(netoTeorico, sumaTeorica, netoOriginal);
        var delta = round2(netoOriginal - sumaTeorica);
        var partes = [];

        tasasIva.forEach(function (tKey) {
            if (!gravadosPorTasa[tKey]) {
                errores.push(
                    'No se encontró concepto de neto gravado para alícuota ' + etiquetaTasa(parseFloat(tKey)) + '.'
                );
            } else {
                partes.push(etiquetaTasa(parseFloat(tKey)) + ': ' + formatoNumero(netoDescompuesto[tKey] || 0));
            }
        });

        if (errores.length === 0 && partes.length) {
            var msg = 'Al guardar se abrirá el neto gravado (' + formatoNumero(netoOriginal)
                + ') en: ' + partes.join('; ') + '.';
            if (Math.abs(delta) >= 0.01) {
                msg += ' Diferencia de ' + formatoNumero(delta)
                    + ' repartida entre los gravados (los IVA no se ajustan).';
            }
            advertencias.push(msg);
        }

        return {
            errores: errores,
            advertencias: advertencias,
            neto_por_tasa: netoDescompuesto,
        };
    }

    function validarCoherenciaIvaNeto(estado) {
        var errores = [];
        var ivaPorTasa = estado.iva_por_tasa;
        var netoPorTasa = estado.neto_por_tasa;
        var netoSinTasa = estado.neto_sin_tasa;
        var tasasIva = estado.tasas_iva;

        if (netoSinTasa > 0 && tasasIva.length >= 2 && Object.keys(netoPorTasa).length === 0) {
            errores.push(
                'Hay neto gravado sin alícuota y múltiples tasas de IVA: no se pudo descomponer. Revise los importes.'
            );
            return errores;
        }

        tasasIva.forEach(function (tKey) {
            var tasa = parseFloat(tKey);
            var iva = ivaPorTasa[tKey];
            var neto = netoPorTasa[tKey] || 0;

            if (neto <= 0) {
                errores.push(
                    'Falta neto gravado para alícuota IVA ' + etiquetaTasa(tasa)
                    + ' (IVA cargado: ' + formatoNumero(iva) + ').'
                );
                return;
            }

            var esperado = round2(neto * tasa / 100);
            var diferencia = Math.abs(esperado - iva);
            if (diferencia > TOLERANCIA) {
                errores.push(
                    'IVA ' + etiquetaTasa(tasa) + ' (' + formatoNumero(iva)
                    + ') no coincide con neto gravado ' + formatoNumero(neto)
                    + ' × ' + etiquetaTasa(tasa) + ' = ' + formatoNumero(esperado)
                    + '. Diferencia ' + formatoNumero(diferencia)
                    + ' (tolerancia $' + formatoNumero(TOLERANCIA) + ').'
                );
            }
        });

        return errores;
    }

    /**
     * @param {Array<{concepto_ivacompra_id: number, monto: number}>} lineas
     * @param {Object} conceptosMeta
     * @returns {{valido: boolean, errores: string[], advertencias: string[]}}
     */
    function validar(lineas, conceptosMeta) {
        if (!lineas || lineas.length === 0) {
            return { valido: true, errores: [], advertencias: [] };
        }

        var estado = analizar(lineas, conceptosMeta || {});
        if (!estado.aplica) {
            return { valido: true, errores: [], advertencias: [] };
        }

        var gravadosPorTasa = buildGravadosPorTasa(conceptosMeta || {});
        var errores = [];
        var advertencias = [];

        if (necesitaApertura(estado)) {
            var sim = simularApertura(estado, gravadosPorTasa);
            errores = sim.errores.slice();
            advertencias = sim.advertencias.slice();
            if (sim.errores.length === 0) {
                estado = Object.assign({}, estado, {
                    neto_por_tasa: sim.neto_por_tasa,
                    neto_sin_tasa: 0,
                });
            }
        }

        if (errores.length === 0) {
            errores = errores.concat(validarCoherenciaIvaNeto(estado));
        }

        return {
            valido: errores.length === 0,
            errores: errores,
            advertencias: advertencias,
        };
    }

    global.ConceptosIvacompraCoherencia = {
        TOLERANCIA: TOLERANCIA,
        validar: validar,
        formatoNumero: formatoNumero,
    };
})(window);
