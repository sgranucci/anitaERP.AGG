<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Interpreta preguntas cortas en español → entidad + QBE del explorador.
 * Primero diccionario/patrones (+ builder QBE); si no hay entidad y hay LLM, consulta el modelo.
 */
final class ConsultaInteligenteNlSupport
{
    /**
     * @return array{
     *   ok: bool,
     *   mensaje: string,
     *   entidad?: string,
     *   url?: string,
     *   explicacion?: string,
     *   query?: array<string, mixed>,
     *   qbe_legible?: list<string>,
     *   criterios?: list<array<string, string>>,
     *   via?: string
     * }
     */
    public static function interpretar(string $texto): array
    {
        $raw = trim($texto);
        if ($raw === '') {
            return ['ok' => false, 'mensaje' => 'Escribí una pregunta, por ejemplo: «cheques para depositar» o «pedidos pendientes este mes».'];
        }

        $t = self::normalizar($raw);
        $entidad = ConsultaInteligenteNlDiccionarioSupport::detectarEntidad($t)
            ?? self::detectarEntidad($t);

        $hintsLlm = null;
        $via = 'patrones';
        if ($entidad === null) {
            $llm = ConsultaInteligenteNlLlmSupport::interpretar($raw);
            if (is_array($llm) && ($llm['ok'] ?? false) && ! empty($llm['entidad'])) {
                $entidad = (string) $llm['entidad'];
                $hintsLlm = $llm;
                $via = 'llm';
            } elseif (is_array($llm) && isset($llm['mensaje']) && ($llm['ok'] ?? true) === false) {
                return ['ok' => false, 'mensaje' => (string) $llm['mensaje']];
            } else {
                return [
                    'ok' => false,
                    'mensaje' => 'No reconocí la entidad. Probá con cheques, remitos, facturas de venta, pedidos, pagos, proveedores, clientes o mayor.',
                ];
            }
        }

        if (! EntidadSemanticaCatalogo::existe($entidad)) {
            return ['ok' => false, 'mensaje' => 'Entidad no publicada en el catálogo.'];
        }

        $ent = EntidadSemanticaCatalogo::porClave($entidad);
        if (! can($ent->permisoListar(), false)) {
            return ['ok' => false, 'mensaje' => 'No tenés permiso para consultar «'.$ent->nombre().'».'];
        }

        $query = ['consultar' => 1];
        $partes = ['Entidad: '.$ent->nombre()];
        if ($via === 'llm') {
            $partes[] = 'vía LLM';
        }

        if ($ent instanceof EntidadSemanticaPuenteReporte) {
            $periodo = is_string($hintsLlm['periodo'] ?? null) ? $hintsLlm['periodo'] : self::detectarPeriodo($t);
            if ($periodo === 'mes_anterior') {
                $mes = (int) date('n');
                $anio = (int) date('Y');
                $query['modo_periodo'] = 'mes';
                $query['mes'] = $mes === 1 ? 12 : $mes - 1;
                $query['anio'] = $mes === 1 ? $anio - 1 : $anio;
                $partes[] = 'Período: mes anterior';
            } else {
                $query['modo_periodo'] = 'mes';
                $query['mes'] = (int) date('n');
                $query['anio'] = (int) date('Y');
                $partes[] = 'Período: este mes';
            }

            return [
                'ok' => true,
                'mensaje' => 'Listo',
                'entidad' => $entidad,
                'url' => $ent->urlReporte($query),
                'explicacion' => implode(' · ', $partes),
                'query' => $query,
                'qbe_legible' => $partes,
                'criterios' => [],
                'via' => $via,
            ];
        }

        if ($entidad === 'cheque') {
            $paraDepositar = (bool) ($hintsLlm['para_depositar'] ?? false)
                || self::contiene($t, ['para depositar', 'a depositar', 'depositar', 'pendientes de deposito']);
            $cartera = (bool) ($hintsLlm['cartera'] ?? false)
                || self::contiene($t, ['cartera', 'en cartera', 'recibidos en cartera']);
            $rechazados = (bool) ($hintsLlm['rechazados'] ?? false)
                || self::contiene($t, ['rechazado', 'rechazos', 'nd cheque']);
            $origenLlm = isset($hintsLlm['origen']) && is_string($hintsLlm['origen']) ? $hintsLlm['origen'] : null;

            if ($paraDepositar) {
                $query['para_depositar'] = 1;
                $partes[] = 'Atajo: para depositar';
            } elseif ($cartera) {
                $query['cartera'] = 1;
                $partes[] = 'Atajo: en cartera';
            } elseif ($origenLlm === 'E' || self::contiene($t, ['emitido', 'propios', 'propio', 'cheques propios'])) {
                $query['origen'] = 'E';
                $partes[] = 'Origen: emitidos';
            } elseif ($rechazados) {
                $query['rechazados'] = 1;
                $partes[] = 'Atajo: rechazados';
            } elseif ($origenLlm === 'R' || self::contiene($t, ['recibido', 'de terceros'])) {
                $query['origen'] = 'R';
                $partes[] = 'Origen: recibidos';
            }
        }

        if ($entidad === 'factura') {
            $query['empresa_todas'] = 1;
            if (self::contiene($t, ['sin remito', 'pendiente de remito', 'no remitado'])) {
                $query['solo_sin_remito'] = 1;
                $partes[] = 'Sin remito';
            }
        }

        $periodo = is_string($hintsLlm['periodo'] ?? null) ? $hintsLlm['periodo'] : self::detectarPeriodo($t);

        // Builder QBE: período + estado + importe + campo texto
        $criterios = ConsultaInteligenteNlQbeSupport::criteriosDesdeTexto($entidad, $t, $periodo);

        // Criterios extra del LLM (campo/op/valor)
        if (is_array($hintsLlm['criterios'] ?? null)) {
            foreach ($hintsLlm['criterios'] as $c) {
                if (! is_array($c) || empty($c['campo'])) {
                    continue;
                }
                $criterios[] = [
                    'campo' => (string) $c['campo'],
                    'op' => (string) ($c['op'] ?? 'contiene'),
                    'valor' => (string) ($c['valor'] ?? ''),
                    'valor_hasta' => (string) ($c['valor_hasta'] ?? ''),
                    'label' => (string) ($c['label'] ?? $c['campo']),
                ];
            }
        }

        $empresa = self::detectarEmpresa($t);
        if ($empresa !== null) {
            $query['empresa_id'] = $empresa['id'];
            unset($query['empresa_todas']);
            $partes[] = 'Empresa: '.$empresa['nombre'];
        }

        $textoLibre = '';
        if ($hintsLlm !== null && ! empty($hintsLlm['filtro_valor'])) {
            $textoLibre = trim((string) $hintsLlm['filtro_valor']);
        } else {
            // Si ya hay criterio de campo texto, no duplicar en filtro_valor
            $yaCampoTexto = false;
            foreach ($criterios as $c) {
                if (! in_array((string) ($c['op'] ?? ''), ['hoy', 'ayer', 'esta_semana', 'este_mes', 'mes_anterior', 'este_anio', 'mayor', 'menor', 'entre'], true)
                    && (string) ($c['valor'] ?? '') !== ''
                    && ! in_array((string) ($c['campo'] ?? ''), ['estadopedido', 'estadoremito', 'estado', 'fechapago', 'fecha', 'fechacomprobante', 'fecha_apertura', 'fecha_ingreso', 'monto', 'total'], true)
                ) {
                    $yaCampoTexto = true;
                    break;
                }
            }
            if (! $yaCampoTexto) {
                $textoLibre = self::extraerTextoLibre($t, $entidad, $periodo !== null, $empresa !== null);
            }
        }
        if ($textoLibre !== '') {
            $query['filtro_valor'] = $textoLibre;
            $partes[] = 'Texto: '.$textoLibre;
        }

        if ($criterios !== []) {
            $query['filtro_modo'] = 'qbe';
            $query['qbe'] = ConsultaInteligenteNlQbeSupport::aEstructuraQbe($criterios);
        }

        $qbeLegible = ConsultaInteligenteNlQbeSupport::legible($criterios, $entidad);
        foreach ($qbeLegible as $linea) {
            if (! in_array($linea, $partes, true)) {
                $partes[] = $linea;
            }
        }

        if ($hintsLlm !== null && ! empty($hintsLlm['explicacion'])) {
            $partes[] = (string) $hintsLlm['explicacion'];
        }

        $metrica = ConsultaInteligenteNlMetricasDiccionarioSupport::detectar($t);
        if ($metrica !== null) {
            $partes[] = 'Métrica: '.$metrica;
            if (in_array($metrica, ['suma', 'promedio'], true)) {
                $query['_ci_metrica'] = $metrica;
            }
        }

        return [
            'ok' => true,
            'mensaje' => 'Listo',
            'entidad' => $entidad,
            'url' => EntidadSemanticaCatalogo::urlExplorar($entidad, $query),
            'explicacion' => implode(' · ', $partes),
            'query' => $query,
            'qbe_legible' => $qbeLegible !== [] ? $qbeLegible : $partes,
            'criterios' => $criterios,
            'via' => $via,
            'metrica' => $metrica,
        ];
    }

    private static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return trim($t);
    }

    private static function detectarEntidad(string $t): ?string
    {
        $map = [
            'cheque' => ['cheque', 'cheques', 'echeq', 'e-cheq', 'valores', 'cartera de cheques'],
            'factura' => [
                'factura de venta', 'facturas de venta', 'comprobante de venta', 'comprobantes de venta',
                'fc venta', 'ventas facturadas', 'facturacion', 'facturación',
            ],
            'remito' => ['remito', 'remitos', 'remito de venta', 'remitos de venta', 'guia de remito'],
            'pedido' => ['pedido', 'pedidos', 'pedido de venta', 'pedidos de venta', 'orden de venta'],
            'comprobante_proveedor' => [
                'factura de compra', 'facturas de compra', 'factura proveedor', 'facturas proveedor',
                'factura', 'facturas', 'comprobante proveedor', 'comprobantes de proveedor', 'fc proveedor', 'compras facturadas',
            ],
            'pagoproveedor' => [
                'pago a proveedor', 'pagos a proveedor', 'orden de pago', 'ordenes de pago', 'ops',
                'pago proveedor', 'pagos proveedor', 'pagos', 'pago', 'tesoreria pagos',
            ],
            'proveedor' => ['proveedor', 'proveedores', 'maestro proveedores'],
            'cliente' => ['cliente', 'clientes', 'maestro clientes', 'deudores'],
            'ingresoegreso' => [
                'ingreso y egreso', 'ingresos y egresos', 'movimiento de caja', 'movimientos de caja',
                'ingresoegreso', 'caja movimiento', 'rendicion de fondos',
            ],
            'cobranza' => ['cobranza', 'cobranzas', 'recibo', 'recibos', 'cobro', 'cobros'],
            'ordencompra' => [
                'orden de compra', 'ordenes de compra', 'órdenes de compra', 'oc ', ' ocs', 'compra oc',
            ],
            'requisicion' => ['requisicion', 'requisiciones', 'req de compra', 'pedido interno'],
            'articulo' => ['articulo', 'articulos', 'sku', 'stock', 'saldo deposito', 'saldos'],
            'movimientostock' => [
                'movimiento de stock', 'movimientos de stock', 'movimientostock', 'kardex',
                'entrada de stock', 'salida de stock', 'ajuste de stock',
            ],
            'transferencia_mercaderia' => [
                'transferencia de mercaderia', 'transferencias de mercaderia',
                'transferencia mercaderia', 'transferencias mercaderia',
                'transferencia de stock', 'tm entre depositos', 'tm entre depósitos',
            ],
            'capex' => ['capex', 'proyecto capex', 'proyectos capex', 'inversion capex', 'inversión capex'],
            'importacion' => [
                'importacion', 'importaciones', 'carpeta de importacion', 'nacionalizacion',
                'despacho importacion', 'embarque',
            ],
            'solicitudpago' => [
                'solicitud de pago', 'solicitudes de pago', 'solicitudpago', 'anticipo de pago',
                'rendicion de gastos solicitud',
            ],
            'empleado_sueldos' => [
                'empleado', 'empleados', 'legajo', 'legajos', 'sueldos', 'personal', 'nomina',
            ],
            'ticket' => ['ticket', 'tickets', 'incidencia', 'incidencias', 'mesa de ayuda'],
            'recepcion_proveedor' => [
                'recepcion', 'recepciones', 'com de compra', 'com proveedor', 'recepcion de proveedor',
                'mercaderia recibida',
            ],
            'asiento' => ['asiento', 'asientos', 'asiento contable', 'asientos contables', 'diario'],
            'mayor_concepto' => ['mayor por concepto', 'mayor concepto', 'mayor de concepto'],
            'mayor_plano_cuenta' => ['mayor plano', 'mayor por cuenta', 'mayor de cuenta', 'mayor analitico', 'mayor analítico'],
        ];

        foreach ([
            'mayor_plano_cuenta', 'mayor_concepto',
            'factura', 'remito', 'pedido',
            'transferencia_mercaderia', 'movimientostock', 'capex',
            'comprobante_proveedor', 'pagoproveedor', 'ordencompra', 'requisicion',
            'recepcion_proveedor', 'solicitudpago', 'importacion', 'ingresoegreso', 'cobranza', 'cheque',
            'empleado_sueldos', 'ticket', 'asiento', 'articulo', 'proveedor', 'cliente',
        ] as $clave) {
            foreach ($map[$clave] as $needle) {
                // No trim: 'oc ' debe conservar el espacio (si no, “oc” matchea dentro de “stock”).
                $needle = (string) $needle;
                if ($needle !== '' && str_contains($t, $needle)) {
                    return $clave;
                }
            }
        }

        return null;
    }

    private static function detectarPeriodo(string $t): ?string
    {
        if (self::contiene($t, ['mes anterior', 'mes pasado'])) {
            return 'mes_anterior';
        }
        if (self::contiene($t, ['este mes', 'mes actual', 'del mes'])) {
            return 'este_mes';
        }
        if (self::contiene($t, ['este anio', 'este año', 'del anio', 'del año'])) {
            return 'este_anio';
        }
        if (self::contiene($t, ['esta semana'])) {
            return 'esta_semana';
        }
        if (preg_match('/\bhoy\b/', $t)) {
            return 'hoy';
        }
        if (preg_match('/\bayer\b/', $t)) {
            return 'ayer';
        }

        return null;
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private static function detectarEmpresa(string $t): ?array
    {
        $empresas = \Illuminate\Support\Facades\DB::table('empresa')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        foreach ($empresas as $e) {
            $nombre = self::normalizar((string) $e->nombre);
            if ($nombre !== '' && str_contains($t, $nombre)) {
                return ['id' => (int) $e->id, 'nombre' => (string) $e->nombre];
            }
        }

        return null;
    }

    private static function extraerTextoLibre(string $t, string $entidad, bool $hayPeriodo, bool $hayEmpresa): string
    {
        $quitar = [
            'mostrame', 'mostrar', 'dame', 'quiero', 'ver', 'listar', 'consulta', 'consultar',
            'los', 'las', 'de', 'del', 'la', 'el', 'un', 'una', 'para', 'con',
            'cheque', 'cheques', 'factura', 'facturas', 'pago', 'pagos', 'proveedor', 'proveedores',
            'cliente', 'clientes', 'ingreso', 'ingresos', 'egreso', 'egresos', 'caja',
            'orden', 'ordenes', 'comprobante', 'comprobantes', 'este', 'mes', 'anterior', 'pasado',
            'hoy', 'ayer', 'semana', 'anio', 'año', 'actual', 'depositar', 'cartera', 'emitido',
            'emitidos', 'rechazado', 'rechazados', 'propios', 'propio', 'a',
            'remito', 'remitos', 'venta', 'ventas', 'facturacion', 'facturación',
            'pedido', 'pedidos', 'pendiente', 'pendientes', 'facturado', 'facturados',
            'mayor', 'menor', 'mas', 'más', 'menos', 'entre', 'y',
        ];
        if ($hayPeriodo) {
            $quitar = array_merge($quitar, ['mes', 'anterior', 'pasado', 'este', 'hoy', 'ayer']);
        }
        $palabras = preg_split('/\s+/', $t) ?: [];
        $out = [];
        foreach ($palabras as $p) {
            if ($p === '' || in_array($p, $quitar, true)) {
                continue;
            }
            if ($hayEmpresa) {
                continue;
            }
            if (preg_match('/^\d[\d\.]*$/', $p)) {
                continue;
            }
            $out[] = $p;
        }
        $texto = trim(implode(' ', $out));
        if (mb_strlen($texto) < 3) {
            return '';
        }

        return $texto;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function contiene(string $t, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($t, self::normalizar($n))) {
                return true;
            }
        }

        return false;
    }
}
