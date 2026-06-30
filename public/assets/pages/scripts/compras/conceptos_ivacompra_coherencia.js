/**
 * Coherencia neto gravado (G) ↔ IVA liquidado (I) por alícuota — validación en pantalla.
 * Espejo de App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport.
 */
(function (global) {
    'use strict';

    var TOLERANCIA = 0.90;

    function round2(n) {
        return Math.round((parseFloat(n) || 0) * 100) / 100;
    }

    function tasaKey(tasa) {
        return String(Math.round(parseFloat(tasa) * 1000) / 1000);
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

        return {
            aplica: tasasIva.length > 0,
            neto_sin_tasa: netoSinTasa,
            neto_por_tasa: netoPorTasa,
            iva_por_tasa: ivaPorTasa,
            tasas_iva: tasasIva,
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

    function validarDescomposicion(estado, gravadosPorTasa) {
        var errores = [];
        var advertencias = [];
        var netoSinTasa = estado.neto_sin_tasa;
        var ivaPorTasa = estado.iva_por_tasa;
        var netoPorTasa = estado.neto_por_tasa;
        var tasasIva = estado.tasas_iva;

        if (netoSinTasa <= 0 || tasasIva.length < 2) {
            return { errores: errores, advertencias: advertencias };
        }

        if (Object.keys(netoPorTasa).length > 0) {
            return { errores: errores, advertencias: advertencias };
        }

        var netoDescompuesto = {};
        var sumaNeto = 0;

        tasasIva.forEach(function (tasaKey) {
            var tasa = parseFloat(tasaKey);
            var iva = ivaPorTasa[tasaKey];
            if (tasa <= 0) {
                return;
            }
            var neto = round2(iva / (tasa / 100));
            netoDescompuesto[tasaKey] = neto;
            sumaNeto = round2(sumaNeto + neto);
        });

        var dif = Math.abs(sumaNeto - netoSinTasa);
        if (dif > TOLERANCIA) {
            errores.push(
                'El neto gravado único (' + formatoNumero(netoSinTasa)
                + ') no coincide con la suma descompuesta por alícuotas IVA ('
                + formatoNumero(sumaNeto) + '). Diferencia '
                + formatoNumero(dif) + ' (tolerancia $' + formatoNumero(TOLERANCIA) + ').'
            );
            return { errores: errores, advertencias: advertencias };
        }

        var partes = [];
        tasasIva.forEach(function (tasaKey) {
            if (!gravadosPorTasa[tasaKey]) {
                errores.push(
                    'No se encontró concepto de neto gravado para alícuota ' + etiquetaTasa(parseFloat(tasaKey)) + '.'
                );
            } else {
                partes.push(etiquetaTasa(parseFloat(tasaKey)) + ': ' + formatoNumero(netoDescompuesto[tasaKey] || 0));
            }
        });

        if (errores.length === 0 && partes.length) {
            advertencias.push(
                'Al guardar se descompondrá el neto gravado único (' + formatoNumero(netoSinTasa)
                + ') en: ' + partes.join('; ') + '.'
            );
        }

        return { errores: errores, advertencias: advertencias };
    }

    function validarCoherenciaIvaNeto(estado) {
        var errores = [];
        var ivaPorTasa = estado.iva_por_tasa;
        var netoPorTasa = estado.neto_por_tasa;
        var netoSinTasa = estado.neto_sin_tasa;
        var tasasIva = estado.tasas_iva;

        if (netoSinTasa > 0 && tasasIva.length >= 2 && Object.keys(netoPorTasa).length === 0) {
            return errores;
        }

        tasasIva.forEach(function (tasaKey) {
            var tasa = parseFloat(tasaKey);
            var iva = ivaPorTasa[tasaKey];
            var neto = netoPorTasa[tasaKey] || 0;

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
        var descomp = validarDescomposicion(estado, gravadosPorTasa);
        var errores = descomp.errores.slice();
        var advertencias = descomp.advertencias.slice();

        if (descomp.errores.length === 0 && estado.neto_sin_tasa > 0 && estado.tasas_iva.length >= 2
            && Object.keys(estado.neto_por_tasa).length === 0) {
            var netoSimulado = {};
            estado.tasas_iva.forEach(function (tasaKey) {
                var tasa = parseFloat(tasaKey);
                netoSimulado[tasaKey] = round2(estado.iva_por_tasa[tasaKey] / (tasa / 100));
            });
            estado = Object.assign({}, estado, { neto_por_tasa: netoSimulado, neto_sin_tasa: 0 });
        }

        errores = errores.concat(validarCoherenciaIvaNeto(estado));

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
