<?php

namespace App\Support\Ai;

/**
 * Enruta lenguaje natural → intent + params (híbrido: reglas → LLM).
 */
final class AiConsultaOperativaRouterSupport
{
    /**
     * @return array{
     *   ok: bool,
     *   intent?: string,
     *   params?: array<string,mixed>,
     *   interpretacion?: string,
     *   needs_clarification?: bool,
     *   clarification?: string,
     *   fuente?: string,
     *   sugerencias?: list<string>,
     *   error?: string
     * }
     */
    public static function interpretar(string $pregunta): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $pregunta) ?? '');
        if ($texto === '') {
            return [
                'ok' => false,
                'error' => 'Escriba una consulta.',
                'fuente' => 'reglas',
                'sugerencias' => self::ejemplos(),
            ];
        }

        $porReglas = self::interpretarPorReglas($texto);
        if ($porReglas['ok'] ?? false) {
            $validado = AiConsultaOperativaSchemaSupport::validarPlan($porReglas);
            if ($validado['ok'] ?? false) {
                $validado['fuente'] = 'reglas';

                return $validado;
            }
        }

        // Ambiguo / falta dato / no matcheó → LLM si está habilitado
        if (AiConsultaOperativaLlmRouterSupport::habilitado()) {
            $porLlm = AiConsultaOperativaLlmRouterSupport::interpretar($texto);
            if (($porLlm['ok'] ?? false) || ! empty($porLlm['needs_clarification'])) {
                return $porLlm;
            }
            // LLM falló: devolver error de reglas si había, o el del LLM
            if (($porReglas['ok'] ?? false) === false && ! empty($porReglas['error'])) {
                $porReglas['fuente'] = 'reglas';
                $porReglas['error'] = ($porReglas['error'] ?? 'No se pudo interpretar.')
                    .' (LLM: '.($porLlm['error'] ?? 'sin respuesta').')';

                return $porReglas;
            }

            return $porLlm;
        }

        if (! empty($porReglas)) {
            $porReglas['fuente'] = 'reglas';

            return $porReglas;
        }

        return [
            'ok' => false,
            'error' => 'No pude interpretar la consulta. Use un ejemplo o un atajo de abajo.',
            'fuente' => 'reglas',
            'sugerencias' => self::ejemplos(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function interpretarPorReglas(string $texto): array
    {
        $norm = mb_strtolower($texto, 'UTF-8');
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        // Manuales / ayuda (RAG léxico)
        if (self::contieneAlguno($norm, [
            'manual', 'como hago', 'como cargo', 'como crear', 'como cargar',
            'ayuda sobre', 'documentacion', 'en el manual', 'instrucciones',
        ]) || (str_contains($norm, 'ayuda') && self::contieneAlguno($norm, [
            'compras', 'stock', 'gastronomia', 'ventas', 'contable', 'recepcion', 'oc', 'factura',
        ]))) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_CONSULTAR_MANUAL,
                'params' => [
                    'valor' => $texto,
                    'pregunta' => $texto,
                ],
                'interpretacion' => 'Consulta a manuales (RAG)',
            ];
        }

        // Pedido por consumo (CC + depósito) — antes de plan agente genérico
        if (self::contieneAlguno($norm, [
            'pedido consumo', 'pedido por consumo', 'planear pedido', 'planear pedidos',
            'que pedir', 'que pedimos', 'que compre', 'que compramos', 'insumos pedir',
            'consumo del sector', 'consumo centro de costo', 'consumo cc',
            'cobertura de stock', 'sugerir pedido', 'sugerir requisicion',
        ]) || (str_contains($norm, 'consumo') && self::contieneAlguno($norm, [
            'pedido', 'pedir', 'comprar', 'requisicion', 'cobertura', 'deposito',
        ]))) {
            $params = self::extraerParamsPeriodoYExtras($texto, $norm);
            $params['solo_insumo'] = true;

            if (preg_match('/\bcc\s*(\d{1,6})\b/u', $norm, $mCc)) {
                $params['centrocosto_codigo'] = $mCc[1];
                $params['codigo'] = $mCc[1];
                $params['valor'] = $mCc[1];
            } elseif (preg_match('/centro\s+de\s+costo\s+(\d{1,6})\b/u', $norm, $mCc2)) {
                $params['centrocosto_codigo'] = $mCc2[1];
                $params['codigo'] = $mCc2[1];
                $params['valor'] = $mCc2[1];
            }

            if (preg_match('/deposit[oa]\s+(?:consumo\s+)?(\d{1,6}|[a-z0-9\.\-]+)/u', $norm, $mDep)) {
                $tok = $mDep[1];
                if (ctype_digit($tok)) {
                    $params['deposito_id'] = (int) $tok;
                    $params['deposito_consumo_id'] = (int) $tok;
                } else {
                    $params['deposito_codigo'] = $tok;
                }
            }

            if (preg_match('/(?:ultimos?|últimos?)\s+(\d{1,3})\s+dias/u', $norm, $mDias)) {
                $dias = max(1, (int) $mDias[1]);
                $params['fecha_hasta'] = date('Y-m-d');
                $params['fecha_desde'] = date('Y-m-d', strtotime('-'.($dias - 1).' days'));
            } elseif (str_contains($norm, 'dos meses') || str_contains($norm, '2 meses')) {
                $params['fecha_hasta'] = date('Y-m-d');
                $params['fecha_desde'] = date('Y-m-d', strtotime('-59 days'));
            }

            if (preg_match('/cobertura\s+(\d{1,3})\s*dias/u', $norm, $mCob)) {
                $params['dias_cobertura'] = (int) $mCob[1];
            }

            if (empty($params['centrocosto_codigo']) && empty($params['centrocosto_id']) && empty($params['codigo'])) {
                return [
                    'ok' => false,
                    'needs_clarification' => true,
                    'clarification' => 'Indique el centro de costo y el depósito de consumo (ej.: «pedido consumo CC 93 depósito 12 últimos 60 días»).',
                    'error' => 'Falta centro de costo.',
                    'sugerencias' => [
                        'pedido consumo CC 93 depósito 12 últimos 60 días',
                        'qué pedimos para CC 10 depósito 5 cobertura 7 días',
                    ],
                ];
            }
            if (empty($params['deposito_id']) && empty($params['deposito_consumo_id']) && empty($params['deposito_codigo'])) {
                return [
                    'ok' => false,
                    'needs_clarification' => true,
                    'clarification' => 'Indique el depósito de consumo (obligatorio). Ejemplo: «… depósito 12».',
                    'error' => 'Falta depósito de consumo.',
                    'sugerencias' => [
                        'pedido consumo CC 93 depósito 12 últimos 60 días',
                    ],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR,
                'params' => $params,
                'interpretacion' => 'Pedido por consumo'
                    .(isset($params['centrocosto_codigo']) ? ' CC '.$params['centrocosto_codigo'] : '')
                    .(isset($params['deposito_id']) ? ' depósito '.$params['deposito_id'] : ''),
            ];
        }

        // KPIs / mediciones de Compras (antes de plan agente y OC puntual)
        $rutaComprasKpi = self::interpretarComprasKpi($texto, $norm);
        if ($rutaComprasKpi !== null) {
            return $rutaComprasKpi;
        }

        // Plan agente HITL (antes que intents puntuales)
        if (self::contieneAlguno($norm, [
            'que hago', 'que hacer', 'plan para', 'plan de', 'plan operativo',
            'recomenda', 'recomendacion', 'desvio', 'desvios', 'agente',
        ]) || (str_contains($norm, 'plan') && self::contieneAlguno($norm, ['deuda', 'conciliacion', 'firma', 'insumo', 'stock']))) {
            $evento = AiAgenteOperativoSupport::inferirEventoDesdeTexto($texto);
            $params = self::extraerParamsPeriodoYExtras($texto, $norm);
            $params['evento'] = $evento;
            $params['valor'] = $texto;
            $codigo = self::extraerTokenCodigo($texto, [
                'plan', 'para', 'deuda', 'proveedor', 'cliente', 'insumo', 'kardex', 'stock',
                'desvio', 'desvios', 'conciliacion', 'bancaria', 'agente', 'hago', 'hacer',
                'firmar', 'firma', 'orden', 'compra',
                'con', 'del', 'de', 'la', 'el', 'un', 'una', 'los', 'las',
            ]);
            $mencionaOc = preg_match('/\boc\b/u', $norm) === 1 || str_contains($norm, 'orden de compra');
            $oc = $mencionaOc ? self::extraerNumeroOc($texto) : null;
            if ($oc !== null) {
                $params['numero'] = $oc;
                $params['valor'] = $oc;
                if ($evento === '' || $evento === AiAgenteOperativoSupport::EVENTO_DESVIO_CONCILIACION) {
                    $evento = AiAgenteOperativoSupport::EVENTO_FIRMA_OC;
                    $params['evento'] = $evento;
                }
            } elseif ($codigo !== null) {
                $params['codigo'] = $codigo;
                $params['valor'] = $codigo;
                if ($evento === AiAgenteOperativoSupport::EVENTO_STOCK_INSUMO) {
                    $params['sku'] = $codigo;
                    $params['descripcion'] = $codigo;
                }
            }
            if ($evento === '') {
                return [
                    'ok' => false,
                    'error' => 'Indique la situación (desvío conciliación, deuda proveedor/cliente, firma OC, stock insumo).',
                    'sugerencias' => [
                        'qué hago con desvíos de conciliación',
                        'plan para deuda del proveedor 475',
                        'plan para firmar la OC 1234',
                        'plan stock insumo muzarella',
                    ],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'params' => $params,
                'interpretacion' => 'Plan agente: '.(AiAgenteOperativoSupport::eventosEtiquetas()[$evento] ?? $evento),
            ];
        }

        // Mayor / analitico (antes que OC genérica: «mayor de la OC …»)
        $pideMayor = self::contieneAlguno($norm, [
            'mayor de la cuenta', 'mayor de cuenta', 'mayor cuenta', 'mayor analitico', 'mayor analítico',
            'mayor de la oc', 'mayor de oc', 'mayor oc', 'mayor de la orden', 'movimientos del mayor',
            'mayor filtrado', 'mayor con centro', 'mayor centro de costo',
        ]) || (str_contains($norm, 'mayor') && self::contieneAlguno($norm, [
            'cuenta', 'oc', 'orden de compra', 'centro de costo', 'cc ',
        ]) && ! str_contains($norm, 'corriente'));

        if ($pideMayor) {
            $params = self::extraerParamsPeriodoYExtras($texto, $norm);
            $params = array_merge($params, self::extraerFiltrosMayor($texto, $norm));

            $tieneCuenta = ! empty($params['cuenta_codigo']);
            $tieneOc = ! empty($params['numero_oc']);
            $tieneCc = ! empty($params['centrocosto_codigo']) || ! empty($params['centrocosto_id']);

            if (! $tieneCuenta && ! $tieneOc && ! $tieneCc) {
                return [
                    'ok' => false,
                    'needs_clarification' => true,
                    'clarification' => 'Indique cuenta, OC o centro de costo (ej.: «mayor cuenta 214010013 CC 85 julio empresa 1» o «mayor de la OC 221022»).',
                    'error' => 'Falta criterio de mayor (cuenta / OC / CC).',
                    'sugerencias' => [
                        'mayor de la cuenta 214010013 este mes',
                        'mayor cuenta 211010004 CC 85 de julio',
                        'mayor de la OC 221022',
                        'mayor cuenta 214010013 empresa 1 2026-01-01 2026-07-31',
                    ],
                ];
            }

            $partes = [];
            if ($tieneCuenta) {
                $partes[] = 'cuenta '.$params['cuenta_codigo'];
            }
            if ($tieneCc) {
                $partes[] = 'CC '.($params['centrocosto_codigo'] ?? $params['centrocosto_id']);
            }
            if ($tieneOc) {
                $partes[] = 'OC '.$params['numero_oc'];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA,
                'params' => $params,
                'interpretacion' => 'Mayor '.implode(', ', $partes),
            ];
        }

        // Árbol / firma (antes que OC genérica)
        if (self::contieneAlguno($norm, ['arbol', 'aprobacion', 'aprobar', 'firmar', 'firma', 'quien firma', 'quien tiene que firmar', 'pendiente de firma'])) {
            $numero = self::extraerNumeroOc($texto);
            if ($numero === null) {
                return [
                    'ok' => false,
                    'error' => 'Para el árbol de aprobación indique el número de OC (ej.: «quién debe firmar la OC 1234»).',
                    'sugerencias' => ['quién debe firmar la OC 1234', 'árbol de aprobación OC 1234'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ARBOL_OC,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Árbol de aprobación de la OC '.$numero,
            ];
        }

        // Orden de compra
        if ((self::contieneAlguno($norm, ['orden de compra', 'ordencompra', ' o.c.', 'estado de la oc', 'estado oc'])
            || preg_match('/\boc\b/u', $norm) === 1)
            && ! str_contains($norm, 'mayor')) {
            $numero = self::extraerNumeroOc($texto);
            if ($numero === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique el número de la orden de compra (ej.: «estado de la OC 1234»).',
                    'sugerencias' => ['estado de la OC 1234', 'orden de compra 1234'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Estado de la orden de compra '.$numero,
            ];
        }

        // Asiento contable
        if (self::contieneAlguno($norm, ['asiento contable', 'asiento nro', 'asiento numero', 'asiento nº'])
            || preg_match('/\basiento\b/u', $norm) === 1) {
            $numero = self::extraerTokenTrasEtiqueta($texto, ['asiento', 'nro', 'numero', 'nº', 'num', 'del', 'de', 'la', 'el']);
            if ($numero === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique el número de asiento (ej.: «asiento 12500»).',
                    'sugerencias' => ['asiento 12500', 'asiento contable 12500'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ASIENTO,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Asiento contable '.$numero,
            ];
        }

        // Comprobante / factura de proveedor
        if (self::contieneAlguno($norm, [
            'factura de proveedor', 'factura proveedor', 'comprobante de proveedor', 'comprobante proveedor',
            'fc proveedor', 'factura compra', 'factura de compra',
        ])) {
            $numero = self::extraerTokenTrasEtiqueta($texto, [
                'factura', 'comprobante', 'proveedor', 'compra', 'fc', 'de', 'del', 'la', 'el', 'nro', 'numero',
            ]);
            if ($numero === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique el comprobante (ej.: «factura proveedor A-0001-12345»).',
                    'sugerencias' => ['factura proveedor 12345', 'comprobante proveedor A-1-12345'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_COMPROBANTE_PROVEEDOR,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Comprobante de proveedor '.$numero,
            ];
        }

        // Factura de venta
        if (self::contieneAlguno($norm, [
            'factura de venta', 'factura venta', 'factura de cliente', 'factura cliente',
            'comprobante de venta', 'fc venta',
        ]) || (str_contains($norm, 'factura') && ! str_contains($norm, 'proveedor') && ! str_contains($norm, 'compra'))) {
            $numero = self::extraerTokenTrasEtiqueta($texto, [
                'factura', 'venta', 'cliente', 'comprobante', 'fc', 'de', 'del', 'la', 'el', 'nro', 'numero',
            ]);
            if ($numero === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique la factura (ej.: «factura de venta 0001-12345»).',
                    'sugerencias' => ['factura de venta 12345', 'factura venta 1-12345'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_FACTURA_VENTA,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Factura de venta '.$numero,
            ];
        }

        if ((self::contieneAlguno($norm, ['saldo de la cuenta', 'saldo cuenta', 'saldo contable'])
                || (str_contains($norm, 'saldo') && str_contains($norm, 'cuenta') && ! str_contains($norm, 'corriente') && ! str_contains($norm, 'proveedor') && ! str_contains($norm, 'cliente')))
            && ! self::contieneAlguno($norm, ['articulo', 'sku', 'stock'])) {
            $cuenta = self::extraerCodigoCuenta($texto, $norm);
            if ($cuenta === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique el código de cuenta (ej.: «saldo de la cuenta 214010013»).',
                    'sugerencias' => ['saldo de la cuenta 214010013', 'saldo contable 111010000'],
                ];
            }
            $params = self::extraerParamsPeriodoYExtras($texto, $norm);
            $params['cuenta_codigo'] = $cuenta;
            $params['valor'] = $cuenta;

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
                'params' => $params,
                'interpretacion' => 'Saldo contable de la cuenta '.$cuenta,
            ];
        }

        // Proveedor (CT vs ficha)
        if (self::contieneAlguno($norm, ['proveedor', 'cuit proveedor'])) {
            $codigo = self::extraerTokenCodigo($texto, [
                'proveedor', 'codigo', 'cuit', 'saldo', 'cuenta', 'corriente', 'ficha',
                'de', 'del', 'la', 'el', 'un', 'una',
            ]);
            if ($codigo === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique el código del proveedor (ej.: «saldo del proveedor 475»).',
                    'sugerencias' => ['saldo del proveedor 475', 'ficha del proveedor 350'],
                ];
            }
            $pideCt = self::contieneAlguno($norm, [
                'saldo', 'cuenta corriente', 'ctacte', 'cta cte', 'deuda', 'movimientos', 'mayor proveedor',
            ]);
            $intent = $pideCt
                ? AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE
                : AiConsultaOperativaSupport::INTENT_PROVEEDOR;
            $params = array_merge(
                ['codigo' => $codigo, 'valor' => $codigo],
                self::extraerParamsPeriodoYExtras($texto, $norm)
            );
            if (self::contieneAlguno($norm, ['solo deuda', 'deuda pendiente', 'impagas', 'impagos'])) {
                $params['solo_deuda'] = true;
            }

            return [
                'ok' => true,
                'intent' => $intent,
                'params' => $params,
                'interpretacion' => $pideCt
                    ? 'Cuenta corriente del proveedor '.$codigo
                    : 'Ficha del proveedor '.$codigo,
            ];
        }

        if (self::contieneAlguno($norm, ['cliente', 'cuit cliente', 'documento cliente'])) {
            $codigo = self::extraerTokenCodigo($texto, [
                'cliente', 'codigo', 'cuit', 'documento', 'doc', 'saldo', 'cuenta', 'corriente', 'ficha',
                'de', 'del', 'la', 'el', 'un', 'una',
            ]);
            if ($codigo === null) {
                return [
                    'ok' => false,
                    'error' => 'Indique código o documento del cliente (ej.: «saldo del cliente 10025»).',
                    'sugerencias' => ['saldo del cliente 10025', 'cliente 10025', 'ficha del cliente 20123456789'],
                ];
            }
            $pideCt = self::contieneAlguno($norm, [
                'saldo', 'cuenta corriente', 'ctacte', 'cta cte', 'deuda', 'movimientos',
            ]);
            $intent = $pideCt
                ? AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE
                : AiConsultaOperativaSupport::INTENT_CLIENTE;
            $params = array_merge(
                ['codigo' => $codigo, 'valor' => $codigo],
                self::extraerParamsPeriodoYExtras($texto, $norm)
            );

            return [
                'ok' => true,
                'intent' => $intent,
                'params' => $params,
                'interpretacion' => $pideCt
                    ? 'Cuenta corriente del cliente '.$codigo
                    : 'Ficha del cliente '.$codigo,
            ];
        }

        // Kardex / movimientos de artículo (antes que saldo genérico)
        if (self::contieneAlguno($norm, [
            'kardex', 'ficha kardex', 'movimientos del articulo', 'movimientos articulo',
            'movimientos del insumo', 'movimientos insumo', 'kardex del', 'kardex de',
        ])) {
            $nombre = self::extraerTokenTrasEtiqueta($texto, [
                'kardex', 'ficha', 'movimientos', 'movimiento', 'articulo', 'insumo', 'stock',
                'del', 'de', 'la', 'el', 'un', 'una', 'los',
            ]);
            // Frases tipo "kardex del insumo muzarella": tomar resto tras "insumo"/"articulo"
            if (preg_match('/(?:insumo|articulo|artículo|sku)\s+(.+)$/ui', $texto, $m) === 1) {
                $nombre = trim($m[1]);
            } elseif (preg_match('/kardex(?:\s+del|\s+de|\s+)\s*(.+)$/ui', $texto, $m) === 1) {
                $cand = trim(preg_replace('/^(insumo|articulo|artículo|sku)\s+/ui', '', $m[1]) ?? $m[1]);
                if ($cand !== '') {
                    $nombre = $cand;
                }
            }
            if ($nombre === null || $nombre === '') {
                return [
                    'ok' => false,
                    'error' => 'Indique el artículo (ej.: «kardex del insumo muzarella»).',
                    'sugerencias' => ['kardex del insumo muzarella', 'kardex artículo ABC-100 este mes'],
                ];
            }
            $nombre = self::limpiarTextoEntidad($nombre);
            if ($nombre === '') {
                return [
                    'ok' => false,
                    'error' => 'Indique el artículo (ej.: «kardex del insumo muzarella este mes»).',
                    'sugerencias' => ['kardex del insumo muzarella', 'kardex artículo ABC-100 depósito 01'],
                ];
            }
            $soloInsumo = self::pideSoloInsumo($norm, $texto);
            $params = array_merge(
                [
                    'valor' => $nombre,
                    'sku' => $nombre,
                    'descripcion' => $nombre,
                    'solo_insumo' => $soloInsumo,
                ],
                self::extraerParamsPeriodoYExtras($texto, $norm),
                self::extraerParamsDeposito($texto, $norm)
            );

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX,
                'params' => $params,
                'interpretacion' => ($soloInsumo ? 'Kardex del insumo «' : 'Kardex del artículo «')
                    .$nombre.'»',
            ];
        }

        // Artículo / saldo stock
        $pideArticulo = self::contieneAlguno($norm, ['articulo', 'sku', 'existencia', 'inventario', 'insumo'])
            || (self::contieneAlguno($norm, ['saldo', 'stock']) && ! self::contieneAlguno($norm, ['proveedor', 'cliente', 'cuenta', 'kardex']));
        if ($pideArticulo) {
            $soloInsumo = self::pideSoloInsumo($norm, $texto);
            $sku = null;
            if ($soloInsumo && preg_match('/(?:insumo|insumos|materia\s+prima)\s+(.+)$/ui', $texto, $m) === 1) {
                $sku = trim($m[1]);
            }
            if ($sku === null || $sku === '') {
                $sku = self::extraerTokenCodigo($texto, [
                    'sku', 'articulo', 'insumo', 'codigo', 'saldo', 'stock', 'existencia', 'inventario',
                    'de', 'del', 'la', 'el', 'un', 'una',
                ]);
            }
            if ($sku === null || $sku === '') {
                return [
                    'ok' => false,
                    'error' => 'Indique el SKU o nombre (ej.: «saldo del insumo muzarella»).',
                    'sugerencias' => ['saldo del insumo muzarella', 'stock SKU ABC-100'],
                ];
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
                'params' => ['sku' => $sku, 'valor' => $sku, 'solo_insumo' => $soloInsumo],
                'interpretacion' => ($soloInsumo ? 'Saldo del insumo «' : 'Saldo del artículo «')
                    .$sku.'»',
            ];
        }

        if (preg_match('/\boc\s*[#:]?\s*([A-Za-z0-9\-\/]+)/ui', $texto, $m) === 1) {
            $numero = trim($m[1]);

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
                'params' => ['numero' => $numero, 'valor' => $numero],
                'interpretacion' => 'Estado de la orden de compra '.$numero,
            ];
        }

        return [
            'ok' => false,
            'error' => 'No pude interpretar la consulta. Use un ejemplo o un atajo de abajo.',
            'sugerencias' => self::ejemplos(),
        ];
    }

    /** @return list<string> */
    public static function ejemplos(): array
    {
        return [
            'kardex del insumo muzarella este mes',
            'saldo del proveedor 475 de julio',
            'saldo del cliente 10025 este mes',
            'mayor de la cuenta 211010004 de julio',
            'mayor cuenta 214010013 CC 85 este mes',
            'mayor de la OC 221022',
            'mayor cuenta 211010004 empresa 1 julio',
            'resumen operativo de compras',
            'OC pendientes de firma',
            'OC vencidas sin recepción',
            'lead time OC recepción últimos 90 días',
            'top proveedores por monto este mes',
            'requisiciones sin OC',
            'qué hago con desvíos de conciliación',
            'plan para deuda del proveedor 475',
            'plan para firmar la OC 1234',
            'estado de la OC 1234',
            'quién debe firmar la OC 1234',
            'asiento 12500',
            'factura proveedor A-1-12345',
            'cómo cargo una orden de compra',
            'manual de gastronomía cierres',
            'pedido consumo CC 93 depósito 12 últimos 60 días',
        ];
    }

    /**
     * Ejemplos cuyo intent el rol actual puede ejecutar.
     *
     * @return list<string>
     */
    public static function ejemplosPermitidos(): array
    {
        $out = [];
        foreach (self::ejemplos() as $ejemplo) {
            $ruta = self::interpretarPorReglas($ejemplo);
            if (! ($ruta['ok'] ?? false)) {
                continue;
            }
            $intent = (string) ($ruta['intent'] ?? '');
            if ($intent !== '' && AiConsultaOperativaSupport::usuarioPuedeIntent($intent)) {
                $out[] = $ejemplo;
            }
        }

        return $out !== [] ? $out : ['Escribí una consulta permitida para tu rol'];
    }

    /** Detecta pedido acotado a insumos gastronomía. */
    private static function pideSoloInsumo(string $norm, string $texto): bool
    {
        if (self::contieneAlguno($norm, ['insumo', 'insumos', 'materia prima'])) {
            return true;
        }

        return preg_match('/\b(insumo|insumos|materia\s+prima)\b/ui', $texto) === 1;
    }

    /** @param  list<string>  $needles */
    private static function contieneAlguno(string $norm, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($norm, $n)) {
                return true;
            }
        }

        return false;
    }

    private static function extraerNumeroOc(string $texto): ?string
    {
        if (preg_match('/\boc\s*[#:]?\s*([A-Za-z0-9\-\/]+)/ui', $texto, $m) === 1) {
            return trim($m[1]);
        }
        if (preg_match('/orden\s+de\s+compra\s*[#:]?\s*([A-Za-z0-9\-\/]+)/ui', $texto, $m) === 1) {
            return trim($m[1]);
        }
        if (preg_match('/([A-Za-z0-9][A-Za-z0-9\-\/]{1,30})\s*$/u', trim($texto), $m) === 1) {
            $cand = trim($m[1]);
            $candNorm = mb_strtolower($cand, 'UTF-8');
            if (! in_array($candNorm, ['oc', 'compra', 'aprobacion', 'arbol', 'firma', 'mes', 'detalle'], true)) {
                return $cand;
            }
        }

        return null;
    }

    private static function extraerCodigoCuenta(string $texto, string $norm): ?string
    {
        if (preg_match('/cuenta\s*[#:]?\s*([0-9]{4,12})/ui', $texto, $m) === 1) {
            return trim($m[1]);
        }
        if (preg_match('/\b([0-9]{6,12})\b/u', $texto, $m) === 1) {
            return trim($m[1]);
        }

        return self::extraerTokenCodigo($texto, [
            'cuenta', 'codigo', 'saldo', 'mayor', 'contable', 'analitico', 'este', 'mes',
            'sin', 'detalle', 'de', 'del', 'la', 'el', 'un', 'una',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function extraerParamsPeriodoYExtras(string $texto, string $norm): array
    {
        $params = [];
        if (str_contains($norm, 'este mes') || str_contains($norm, 'mes actual')) {
            $params['fecha_desde'] = date('Y-m-01');
            $params['fecha_hasta'] = date('Y-m-d');
        } elseif (str_contains($norm, 'mes pasado') || str_contains($norm, 'el mes anterior')) {
            $params['fecha_desde'] = date('Y-m-01', strtotime('first day of last month'));
            $params['fecha_hasta'] = date('Y-m-t', strtotime('last day of last month'));
        } elseif (preg_match('/(\d{4}-\d{2}-\d{2}).{0,20}(\d{4}-\d{2}-\d{2})/u', $texto, $m) === 1) {
            $params['fecha_desde'] = $m[1];
            $params['fecha_hasta'] = $m[2];
        } else {
            $mesNom = self::extraerMesNombrado($norm);
            if ($mesNom !== null) {
                $params['fecha_desde'] = $mesNom['desde'];
                $params['fecha_hasta'] = $mesNom['hasta'];
            }
        }

        if (self::contieneAlguno($norm, ['sin detalle', 'excluir detalle', 'sin observacion'])) {
            $params['campos_excluir'] = ['detalle'];
        }
        if (self::contieneAlguno($norm, ['cruzar con proveedor', 'con proveedor', 'solo proveedor'])) {
            $params['cruzar_con'] = 'proveedor';
        }
        if (preg_match('/\bultimos?\s+(\d{1,3})\b/u', $norm, $m) === 1) {
            $params['max_lineas'] = max(1, min(AiConsultaOperativaSchemaSupport::MAX_LINEAS, (int) $m[1]));
        }

        return $params;
    }

    /**
     * Filtros combinables del mayor: cuenta, CC, empresa, OC.
     *
     * @return array<string,mixed>
     */
    private static function extraerFiltrosMayor(string $texto, string $norm): array
    {
        $params = [];

        $numeroOc = null;
        if (self::contieneAlguno($norm, ['oc', 'orden de compra', 'orden compra'])) {
            $numeroOc = self::extraerNumeroOc($texto);
            if ($numeroOc !== null) {
                $params['numero_oc'] = $numeroOc;
            }
        }

        if (preg_match('/cuenta\s*[#:]?\s*([0-9]{4,12})/ui', $texto, $mCuenta) === 1) {
            $params['cuenta_codigo'] = trim($mCuenta[1]);
            $params['valor'] = $params['cuenta_codigo'];
        } elseif ($numeroOc === null) {
            $cuenta = self::extraerCodigoCuenta($texto, $norm);
            if ($cuenta !== null) {
                $params['cuenta_codigo'] = $cuenta;
                $params['valor'] = $cuenta;
            }
        }

        if (preg_match('/\bcc\s*(\d{1,6})\b/u', $norm, $mCc) === 1) {
            $params['centrocosto_codigo'] = $mCc[1];
        } elseif (preg_match('/centro\s+de\s+costo\s+(\d{1,6})\b/u', $norm, $mCc2) === 1) {
            $params['centrocosto_codigo'] = $mCc2[1];
        }

        if (preg_match('/\bempresa\s*[#:=]?\s*(\d{1,6})\b/ui', $texto, $mEmp) === 1) {
            $params['empresa_id'] = (int) $mEmp[1];
            $params['empresa_codigo'] = $mEmp[1];
        } elseif (preg_match('/\bempresa\s*[#:=]?\s*([A-Za-z0-9][A-Za-z0-9\.\-]{0,20})\b/ui', $texto, $mEmp2) === 1) {
            $params['empresa_codigo'] = trim($mEmp2[1]);
        }

        return $params;
    }

    /**
     * @return array{
     *   ok: bool,
     *   intent: string,
     *   params: array<string,mixed>,
     *   interpretacion: string
     * }|null
     */
    private static function interpretarComprasKpi(string $texto, string $norm): ?array
    {
        $params = self::extraerParamsPeriodoYExtras($texto, $norm);
        if (preg_match('/\bempresa\s*[#:=]?\s*(\d{1,6})\b/ui', $texto, $mEmp) === 1) {
            $params['empresa_id'] = (int) $mEmp[1];
        }

        if (self::contieneAlguno($norm, [
            'oc vencidas', 'ordenes vencidas', 'órdenes vencidas', 'vencidas sin recepcion',
            'vencidas sin recepción', 'oc sin recepcion', 'oc sin recepción',
        ])) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION,
                'params' => $params,
                'interpretacion' => 'OC vencidas sin recepción',
            ];
        }

        if (self::contieneAlguno($norm, [
            'oc pendientes de firma', 'oc pendiente de firma', 'pendientes de firma',
            'oc pendientes', 'ordenes pendientes de firma', 'órdenes pendientes de firma',
            'bandeja de firmas oc', 'firma pendiente oc',
        ]) || (str_contains($norm, 'pendiente') && str_contains($norm, 'firma') && self::contieneAlguno($norm, ['oc', 'orden']))) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA,
                'params' => $params,
                'interpretacion' => 'OC pendientes de firma',
            ];
        }

        if (self::contieneAlguno($norm, [
            'lead time', 'leadtime', 'tiempo de entrega oc', 'demora oc recepcion',
            'demora oc recepción', 'plazo oc recepcion', 'plazo oc recepción',
        ]) || (str_contains($norm, 'lead') && self::contieneAlguno($norm, ['oc', 'recepcion', 'recepción']))) {
            if (empty($params['fecha_desde'])) {
                $params['fecha_desde'] = date('Y-m-d', strtotime('-89 days'));
                $params['fecha_hasta'] = date('Y-m-d');
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION,
                'params' => $params,
                'interpretacion' => 'Lead time OC → recepción',
            ];
        }

        if (self::contieneAlguno($norm, [
            'top proveedores', 'ranking proveedores', 'proveedores por monto',
            'mayores proveedores', 'proveedores mas comprados', 'proveedores más comprados',
        ])) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO,
                'params' => $params,
                'interpretacion' => 'Top proveedores por monto',
            ];
        }

        if (self::contieneAlguno($norm, [
            'requisiciones sin oc', 'rq sin oc', 'requisicion sin oc', 'requisición sin oc',
            'rq pendientes de compra', 'requisiciones pendientes de oc', 'lineas sin oc',
            'líneas sin oc', 'rq sin orden',
        ])) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_RQ_SIN_OC,
                'params' => $params,
                'interpretacion' => 'Requisiciones con líneas sin OC',
            ];
        }

        if (self::contieneAlguno($norm, [
            'kpi compras', 'kpis compras', 'dashboard compras', 'tablero compras',
            'resumen compras', 'resumen operativo compras', 'resumen operativo de compras',
            'mediciones compras', 'indicadores compras', 'situacion de compras',
            'situación de compras',
        ]) || (self::contieneAlguno($norm, ['kpi', 'medicion', 'medición', 'indicador', 'dashboard', 'tablero'])
            && str_contains($norm, 'compra'))) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN,
                'params' => $params,
                'interpretacion' => 'KPI / resumen operativo de Compras',
            ];
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function extraerParamsDeposito(string $texto, string $norm): array
    {
        if (preg_match('/\bdeposito\s*[#:=]?\s*([A-Za-z0-9][A-Za-z0-9\-]{0,20})\b/ui', $texto, $m) === 1) {
            return ['deposito_codigo' => trim($m[1])];
        }
        if (preg_match('/\bdep\.?\s*([A-Za-z0-9][A-Za-z0-9\-]{0,20})\b/ui', $norm, $m) === 1) {
            return ['deposito_codigo' => trim($m[1])];
        }

        return [];
    }

    private static function limpiarTextoEntidad(string $texto): string
    {
        $t = trim($texto);
        $t = preg_replace(
            '/\b(este mes|mes actual|mes pasado|mes anterior|solo deuda|deuda pendiente|ultimos?\s+\d+)\b/ui',
            '',
            $t
        ) ?? $t;
        $t = preg_replace(
            '/\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b(?:\s+(?:de\s+)?\d{4})?/ui',
            '',
            $t
        ) ?? $t;
        $t = preg_replace('/\bdeposito\s+[A-Za-z0-9\-]+\b/ui', '', $t) ?? $t;
        $t = preg_replace('/\bdep\.?\s*[A-Za-z0-9\-]+\b/ui', '', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t, " \t.,;:");
    }

    /**
     * @return array{desde: string, hasta: string}|null
     */
    private static function extraerMesNombrado(string $norm): ?array
    {
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];
        if (preg_match(
            '/\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b(?:\s+(?:de\s+)?(\d{4}))?/u',
            $norm,
            $m
        ) !== 1) {
            return null;
        }
        $mes = $meses[$m[1]] ?? null;
        if ($mes === null) {
            return null;
        }
        $anio = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : (int) date('Y');
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimo = date('Y-m-t', strtotime($desde));
        $hasta = ($anio === (int) date('Y') && $mes === (int) date('n'))
            ? date('Y-m-d')
            : $ultimo;

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * Extrae el último token útil (número o A-0001-123) ignorando stopwords.
     *
     * @param  list<string>  $stop
     */
    private static function extraerTokenTrasEtiqueta(string $texto, array $stop): ?string
    {
        // Patrón letra-sucursal-número o pv-número pegado
        if (preg_match('/\b([A-Za-z]\s*[-\/]\s*\d{1,5}\s*[-\/]\s*\d{1,12})\b/u', $texto, $m) === 1) {
            return preg_replace('/\s+/', '', $m[1]) ?? trim($m[1]);
        }
        if (preg_match('/\b(\d{1,5}\s*[-\/]\s*\d{1,12})\b/u', $texto, $m) === 1) {
            return preg_replace('/\s+/', '', $m[1]) ?? trim($m[1]);
        }

        return self::extraerTokenCodigo($texto, $stop);
    }

    /** @param  list<string>  $stop */
    private static function extraerTokenCodigo(string $texto, array $stop): ?string
    {
        if (preg_match('/(?:sku|articulo|artículo|codigo|código|cliente|proveedor|cuenta)\s*[#:=]?\s*([A-Za-z0-9][A-Za-z0-9\.\-\/]{1,40})/ui', $texto, $m) === 1) {
            return trim($m[1]);
        }

        if (preg_match('/\b(\d{7,13})\b/u', $texto, $m) === 1) {
            return trim($m[1]);
        }

        $stopPeriodo = [
            'este', 'mes', 'actual', 'pasado', 'anterior', 'de', 'del',
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'setiembre', 'octubre', 'noviembre', 'diciembre',
        ];
        $stop = array_values(array_unique(array_merge($stop, $stopPeriodo)));

        $parts = preg_split('/\s+/u', trim($texto)) ?: [];
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $tok = trim($parts[$i], " \t.,;:¿?¡!'\"()[]");
            if ($tok === '' || mb_strlen($tok) < 1) {
                continue;
            }
            $tokNorm = mb_strtolower(strtr($tok, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            ]), 'UTF-8');
            if (in_array($tokNorm, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d{4}$/u', $tok) === 1) {
                // año suelto (ej. 2026) no es código
                continue;
            }
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9\.\-\/]{0,40}$/u', $tok) === 1) {
                return $tok;
            }
        }

        return null;
    }
}
