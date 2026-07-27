<?php

namespace App\Support\Ai;

/**
 * Fase C — agente selectivo: ante un evento operaivo propone un plan HITL
 * (pasos tipados = intents de consulta), sin ejecutar escritura.
 */
final class AiAgenteOperativoSupport
{
    public const EVENTO_DESVIO_CONCILIACION = 'desvio_conciliacion';

    public const EVENTO_DEUDA_PROVEEDOR = 'deuda_proveedor';

    public const EVENTO_DEUDA_CLIENTE = 'deuda_cliente';

    public const EVENTO_FIRMA_OC = 'firma_oc';

    public const EVENTO_STOCK_INSUMO = 'stock_insumo';

    /** Emitido por auditor ARCA WSAPOC (no reemplaza la suspensión automática). */
    public const EVENTO_FACTURA_APOCRIFA = 'factura_apocrifa';

    /** Emitido por verificación Z transmision faltante gastronomía. */
    public const EVENTO_Z_TRANSMISION_FALTANTE = 'z_transmision_faltante';

    /** @return array<string, string> */
    public static function eventosEtiquetas(): array
    {
        return [
            self::EVENTO_DESVIO_CONCILIACION => 'Desvíos de conciliación bancaria',
            self::EVENTO_DEUDA_PROVEEDOR => 'Deuda / CT de proveedor',
            self::EVENTO_DEUDA_CLIENTE => 'Deuda / CT de cliente',
            self::EVENTO_FIRMA_OC => 'Firma / árbol de OC',
            self::EVENTO_STOCK_INSUMO => 'Stock / kardex de insumo',
            self::EVENTO_FACTURA_APOCRIFA => 'Facturas / CUIT apócrifos (ARCA)',
            self::EVENTO_Z_TRANSMISION_FALTANTE => 'Z con transmisión faltante (gastronomía)',
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{
     *   ok: bool,
     *   intent: string,
     *   score: float,
     *   parrafos: list<string>,
     *   links: list<array{etiqueta: string, url: string}>,
     *   tabla?: array{columnas: list<array{key: string, label: string}>, filas: list<array<string,string>>},
     *   datos: array<string,mixed>,
     *   error?: string
     * }
     */
    public static function proponerPlan(string $evento, array $params = []): array
    {
        $evento = strtolower(trim($evento));
        if (! array_key_exists($evento, self::eventosEtiquetas())) {
            $evento = self::inferirEventoDesdeTexto((string) ($params['valor'] ?? $params['pregunta'] ?? ''));
        }
        if ($evento === '' || ! array_key_exists($evento, self::eventosEtiquetas())) {
            return [
                'ok' => false,
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'score' => 0.0,
                'parrafos' => [],
                'links' => [],
                'datos' => [],
                'error' => 'Indique el tipo de situación (ej.: «qué hago con desvíos de conciliación», «plan para deuda del proveedor 475»).',
            ];
        }

        $pasos = match ($evento) {
            self::EVENTO_DESVIO_CONCILIACION => self::planDesvioConciliacion($params),
            self::EVENTO_DEUDA_PROVEEDOR => self::planDeudaProveedor($params),
            self::EVENTO_DEUDA_CLIENTE => self::planDeudaCliente($params),
            self::EVENTO_FIRMA_OC => self::planFirmaOc($params),
            self::EVENTO_STOCK_INSUMO => self::planStockInsumo($params),
            self::EVENTO_FACTURA_APOCRIFA => self::planFacturaApocrifa($params),
            self::EVENTO_Z_TRANSMISION_FALTANTE => self::planZTransmisionFaltante($params),
            default => [],
        };

        if ($pasos === []) {
            return [
                'ok' => false,
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'score' => 0.0,
                'parrafos' => [],
                'links' => [],
                'datos' => [],
                'error' => 'No pude armar un plan para «'.$evento.'». Falta un dato (código, OC, etc.).',
            ];
        }

        $columnas = [
            ['key' => 'paso', 'label' => '#'],
            ['key' => 'accion', 'label' => 'Acción sugerida'],
            ['key' => 'consulta', 'label' => 'Consulta tipada'],
            ['key' => 'motivo', 'label' => 'Motivo'],
        ];
        $filas = [];
        foreach ($pasos as $i => $paso) {
            $filas[] = [
                'paso' => (string) ($i + 1),
                'accion' => (string) ($paso['etiqueta'] ?? ''),
                'consulta' => (string) ($paso['frase'] ?? $paso['intent'] ?? ''),
                'motivo' => (string) ($paso['motivo'] ?? ''),
            ];
        }

        $parrafos = [
            'Plan operativo (HITL): '.self::eventosEtiquetas()[$evento],
            'La IA solo propone; usted confirma cada consulta o acción en el ERP.',
            'Pasos sugeridos: '.count($pasos),
        ];

        $links = [];
        if ($evento === self::EVENTO_DESVIO_CONCILIACION && can('ejecutar-conciliacion-bancaria', false)) {
            $links[] = [
                'etiqueta' => 'Abrir conciliación bancaria',
                'url' => route('conciliacion_bancaria'),
            ];
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
            'score' => 0.86,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => $columnas,
                'filas' => $filas,
            ],
            'datos' => [
                'evento' => $evento,
                'pasos' => $pasos,
            ],
        ];
    }

    /**
     * Plan adjunto a una corrida de conciliación con anomalías (evento → pasos).
     *
     * @param  list<array<string,mixed>>  $anomalias
     * @param  array<string,mixed>  $contexto
     * @return array{evento: string, resumen: string, pasos: list<array<string,mixed>>}
     */
    public static function planDesdeAnomaliasConciliacion(array $anomalias, array $contexto = []): array
    {
        $cuenta = trim((string) ($contexto['cuenta_codigo'] ?? $contexto['cuentacaja'] ?? ''));
        $mes = (int) ($contexto['mes'] ?? date('n'));
        $anio = (int) ($contexto['anio'] ?? date('Y'));
        $params = [
            'cuenta_codigo' => $cuenta,
            'fecha_desde' => sprintf('%04d-%02d-01', $anio, max(1, min(12, $mes))),
            'fecha_hasta' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, max(1, min(12, $mes))))),
        ];
        $plan = self::planDesvioConciliacion($params);
        $altas = count(array_filter($anomalias, static fn ($a) => ($a['severidad'] ?? '') === 'alta'));

        return [
            'evento' => self::EVENTO_DESVIO_CONCILIACION,
            'resumen' => count($anomalias).' anomalía(s) detectada(s)'
                .($altas > 0 ? ' ('.$altas.' alta)' : '')
                .'. Plan sugerido de '.count($plan).' paso(s).',
            'pasos' => $plan,
        ];
    }

    public static function inferirEventoDesdeTexto(string $texto): string
    {
        $norm = mb_strtolower($texto, 'UTF-8');
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        if (str_contains($norm, 'apocrif') || str_contains($norm, 'wsapoc')) {
            return self::EVENTO_FACTURA_APOCRIFA;
        }
        if (str_contains($norm, 'transmision faltante') || str_contains($norm, 'z faltante') || (str_contains($norm, 'informe z') && str_contains($norm, 'falt'))) {
            return self::EVENTO_Z_TRANSMISION_FALTANTE;
        }
        if (str_contains($norm, 'conciliacion') || str_contains($norm, 'desvio') || str_contains($norm, 'interbanking')) {
            return self::EVENTO_DESVIO_CONCILIACION;
        }
        if (str_contains($norm, 'firmar') || str_contains($norm, 'firma') || str_contains($norm, 'arbol')) {
            return self::EVENTO_FIRMA_OC;
        }
        if (str_contains($norm, 'insumo') || str_contains($norm, 'kardex') || str_contains($norm, 'stock')) {
            return self::EVENTO_STOCK_INSUMO;
        }
        if (str_contains($norm, 'cliente')) {
            return self::EVENTO_DEUDA_CLIENTE;
        }
        if (str_contains($norm, 'proveedor') || str_contains($norm, 'deuda')) {
            return self::EVENTO_DEUDA_PROVEEDOR;
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planDesvioConciliacion(array $params): array
    {
        $cuenta = trim((string) ($params['cuenta_codigo'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $pasos = [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'etiqueta' => 'Revisar panel de anomalías IA en la conciliación',
                'frase' => 'Abrir conciliación bancaria y revisar ai_anomalias',
                'motivo' => 'Priorizar pendientes de severidad alta y pares con score bajo.',
                'params' => [],
            ],
        ];
        if ($cuenta !== '') {
            $pasos[] = [
                'intent' => AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA,
                'etiqueta' => 'Mayor de la cuenta '.$cuenta.' del período',
                'frase' => 'mayor de la cuenta '.$cuenta.' '.$desde.' '.$hasta,
                'motivo' => 'Contrastar movimientos contables vs extracto banco.',
                'params' => [
                    'cuenta_codigo' => $cuenta,
                    'fecha_desde' => $desde,
                    'fecha_hasta' => $hasta,
                ],
            ];
        } else {
            $pasos[] = [
                'intent' => AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA,
                'etiqueta' => 'Mayor de la cuenta de caja del período',
                'frase' => 'mayor de la cuenta [código] este mes',
                'motivo' => 'Indique el código de la cuenta de caja vinculada.',
                'params' => ['fecha_desde' => $desde, 'fecha_hasta' => $hasta],
            ];
        }
        $pasos[] = [
            'intent' => AiConsultaOperativaSupport::INTENT_ASIENTO,
            'etiqueta' => 'Abrir asientos dudosos del mayor',
            'frase' => 'asiento [nro]',
            'motivo' => 'Validar imputación y contrapartida de cada desvío.',
            'params' => [],
        ];

        return $pasos;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planDeudaProveedor(array $params): array
    {
        $codigo = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));
        if ($codigo === '') {
            return [];
        }

        return [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PROVEEDOR,
                'etiqueta' => 'Ficha del proveedor '.$codigo,
                'frase' => 'proveedor '.$codigo,
                'motivo' => 'Confirmar datos maestros y condición.',
                'params' => ['codigo' => $codigo],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE,
                'etiqueta' => 'Cuenta corriente del proveedor '.$codigo,
                'frase' => 'saldo del proveedor '.$codigo.' este mes',
                'motivo' => 'Ver deuda, vencimientos y comprobantes (join OC).',
                'params' => ['codigo' => $codigo, 'fecha_desde' => date('Y-m-01'), 'fecha_hasta' => date('Y-m-d')],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
                'etiqueta' => 'Revisar OC abiertas vinculadas',
                'frase' => 'estado de la OC [nro]',
                'motivo' => 'Cruzar facturas CT con órdenes pendientes.',
                'params' => [],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planDeudaCliente(array $params): array
    {
        $codigo = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));
        if ($codigo === '') {
            return [];
        }

        return [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_CLIENTE,
                'etiqueta' => 'Ficha del cliente '.$codigo,
                'frase' => 'cliente '.$codigo,
                'motivo' => 'Confirmar datos y estado comercial.',
                'params' => ['codigo' => $codigo],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE,
                'etiqueta' => 'Cuenta corriente del cliente '.$codigo,
                'frase' => 'saldo del cliente '.$codigo.' este mes',
                'motivo' => 'Ver facturas, cobranzas y vencimientos.',
                'params' => ['codigo' => $codigo, 'fecha_desde' => date('Y-m-01'), 'fecha_hasta' => date('Y-m-d')],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planFirmaOc(array $params): array
    {
        $numero = trim((string) ($params['numero'] ?? $params['valor'] ?? ''));
        if ($numero === '') {
            return [];
        }

        return [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
                'etiqueta' => 'Estado de la OC '.$numero,
                'frase' => 'estado de la OC '.$numero,
                'motivo' => 'Ver totales, estado y proveedor.',
                'params' => ['numero' => $numero],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_ARBOL_OC,
                'etiqueta' => 'Quién debe firmar la OC '.$numero,
                'frase' => 'quién debe firmar la OC '.$numero,
                'motivo' => 'Árbol de aprobación y nivel pendiente.',
                'params' => ['numero' => $numero],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planStockInsumo(array $params): array
    {
        $valor = trim((string) ($params['valor'] ?? $params['sku'] ?? $params['descripcion'] ?? ''));
        if ($valor === '') {
            return [];
        }

        return [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
                'etiqueta' => 'Saldo del insumo «'.$valor.'»',
                'frase' => 'saldo del insumo '.$valor,
                'motivo' => 'Existencia por depósito (solo insumos gastronomía).',
                'params' => ['valor' => $valor, 'solo_insumo' => true],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX,
                'etiqueta' => 'Kardex del insumo «'.$valor.'»',
                'frase' => 'kardex del insumo '.$valor.' este mes',
                'motivo' => 'Entradas/salidas con depósito y comprobante.',
                'params' => [
                    'valor' => $valor,
                    'solo_insumo' => true,
                    'fecha_desde' => date('Y-m-01'),
                    'fecha_hasta' => date('Y-m-d'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planFacturaApocrifa(array $params): array
    {
        $codigo = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));

        $pasos = [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'etiqueta' => 'Revisar mail / listado de suspensiones WSAPOC del día',
                'frase' => 'Abrir mail de auditoría ARCA apócrifas',
                'motivo' => 'El auditor ya evaluó y pudo suspender; confirmar impacto operativo.',
                'params' => [],
            ],
        ];
        if ($codigo !== '') {
            $pasos[] = [
                'intent' => AiConsultaOperativaSupport::INTENT_PROVEEDOR,
                'etiqueta' => 'Ficha del proveedor '.$codigo,
                'frase' => 'proveedor '.$codigo,
                'motivo' => 'Ver estado de suspensión y datos fiscales.',
                'params' => ['codigo' => $codigo],
            ];
            $pasos[] = [
                'intent' => AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE,
                'etiqueta' => 'CT del proveedor '.$codigo,
                'frase' => 'saldo del proveedor '.$codigo,
                'motivo' => 'Detectar comprobantes abiertos post-suspensión.',
                'params' => ['codigo' => $codigo],
            ];
        } else {
            $pasos[] = [
                'intent' => AiConsultaOperativaSupport::INTENT_PROVEEDOR,
                'etiqueta' => 'Abrir ficha de cada CUIT suspendido',
                'frase' => 'proveedor [codigo]',
                'motivo' => 'Validar uno por uno desde el listado del auditor.',
                'params' => [],
            ];
        }

        return $pasos;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<array<string,mixed>>
     */
    private static function planZTransmisionFaltante(array $params): array
    {
        $jornadaId = (int) ($params['jornada_id'] ?? $params['entidad_id'] ?? 0);

        return [
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'etiqueta' => 'Abrir detalle del cierre / Z de la jornada'
                    .($jornadaId > 0 ? ' #'.$jornadaId : ''),
                'frase' => 'Revisar transmisión faltante Z en jornada',
                'motivo' => 'El auditor ya midió el delta Waitry vs Z histórico.',
                'params' => ['jornada_id' => $jornadaId],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'etiqueta' => 'Contrastar conciliación de turno gastronomía (filas DIF)',
                'frase' => 'Abrir conciliación turno gastronomía',
                'motivo' => 'Cruzar comandas faltantes con medios y totales del día.',
                'params' => [],
            ],
            [
                'intent' => AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
                'etiqueta' => 'Evaluar regeneración Z desde proceso (si aplica política)',
                'frase' => 'gastronomia:regenerar-z-desde-proceso (operador)',
                'motivo' => 'Solo si el procedimiento del local lo autoriza; no auto-aplica la IA.',
                'params' => [],
            ],
        ];
    }
}
