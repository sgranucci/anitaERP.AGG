<?php

namespace App\Support\Ai;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Ticket\Ticket;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Liquidacion_SueldosRepositoryInterface;
use App\Support\Caja\IngresoEgresoVisibilidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\UsuarioDepositoAutorizado;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use App\Support\Ventas\ClienteCuentacorrienteDeudaAlcanceSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Intents de ampliación de cobertura (Ventas AR, Sueldos, Stock, Caja, Tickets).
 * Solo lectura; agregados baratos para el chat.
 */
final class AiConsultaCoberturaModulosSupport
{
    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function arAging(array $params): array
    {
        if (! can('listar-cuentacorriente-cliente', false) && ! can('listar-clientes', false)
            && empty($params['_mcp_bridge'])) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_AR_AGING, 'Sin permiso para aging de clientes / AR.');
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        $hoy = date('Y-m-d');

        $aplicado = DB::table('cliente_cuentacorriente_aplicacion')
            ->select('cliente_cuentacorriente_id', DB::raw('SUM(total) as aplicado'))
            ->groupBy('cliente_cuentacorriente_id');

        $saldoExpr = 'CASE WHEN cc.total >= 0 THEN GREATEST(0, cc.total + IFNULL(ap.aplicado, 0))'
            .' ELSE LEAST(0, cc.total + IFNULL(ap.aplicado, 0)) END';

        $q = DB::table('cliente_cuentacorriente as cc')
            ->leftJoinSub($aplicado, 'ap', 'ap.cliente_cuentacorriente_id', '=', 'cc.id')
            ->join('cliente as c', 'c.id', '=', 'cc.cliente_id')
            ->whereNull('c.deleted_at')
            ->whereRaw(ClienteCuentacorrienteDeudaAlcanceSupport::sql('cc'))
            ->whereRaw('ABS('.$saldoExpr.') > 0.009');

        if ($empresaId && $empresaId > 0) {
            $q->where('cc.empresa_id', $empresaId);
        }

        $lineas = $q->selectRaw(
            'cc.cliente_id, c.codigo as codigo, c.nombre as nombre,'
            .' cc.fechavencimiento, ('.$saldoExpr.') as saldo'
        )->limit(800)->get();

        $buckets = [
            'vencido' => ['label' => 'Vencido', 'monto' => 0.0, 'cantidad' => 0],
            '0_7' => ['label' => '0–7 días', 'monto' => 0.0, 'cantidad' => 0],
            '8_30' => ['label' => '8–30 días', 'monto' => 0.0, 'cantidad' => 0],
            '31_mas' => ['label' => '+30 días', 'monto' => 0.0, 'cantidad' => 0],
            'sin_venc' => ['label' => 'Sin vencimiento', 'monto' => 0.0, 'cantidad' => 0],
        ];
        $porCliente = [];
        $total = 0.0;

        foreach ($lineas as $row) {
            $saldo = (float) ($row->saldo ?? 0);
            if (abs($saldo) < 0.01) {
                continue;
            }
            $total += $saldo;
            $venc = trim((string) ($row->fechavencimiento ?? ''));
            $bucket = 'sin_venc';
            if ($venc !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $venc) === 1) {
                $vencTs = strtotime(substr($venc, 0, 10));
                $hoyTs = strtotime($hoy);
                $dias = ($vencTs !== false && $hoyTs !== false)
                    ? (int) floor(($hoyTs - $vencTs) / 86400)
                    : 0;
                // positivo = vencido
                if ($dias > 0) {
                    $bucket = 'vencido';
                } elseif ($dias >= -7) {
                    $bucket = '0_7';
                } elseif ($dias >= -30) {
                    $bucket = '8_30';
                } else {
                    $bucket = '31_mas';
                }
            }
            $buckets[$bucket]['monto'] = round($buckets[$bucket]['monto'] + $saldo, 2);
            $buckets[$bucket]['cantidad']++;

            $cid = (int) ($row->cliente_id ?? 0);
            $key = (string) $cid;
            if (! isset($porCliente[$key])) {
                $porCliente[$key] = [
                    'codigo' => (string) ($row->codigo ?? $cid),
                    'nombre' => (string) ($row->nombre ?? ''),
                    'saldo' => 0.0,
                    'lineas' => 0,
                ];
            }
            $porCliente[$key]['saldo'] += $saldo;
            $porCliente[$key]['lineas']++;
        }

        uasort($porCliente, static fn ($a, $b) => (abs($b['saldo']) <=> abs($a['saldo'])));
        $top = array_slice(array_values($porCliente), 0, $max);

        $parrafos = [
            'Aging clientes (AR) · deuda CT pendiente',
            'Total pendiente: $ '.number_format($total, 2, ',', '.'),
            'Clientes en top: '.count($top).' · importes en moneda del comprobante',
        ];
        foreach ($buckets as $b) {
            if ((float) $b['monto'] == 0.0 && (int) $b['cantidad'] === 0) {
                continue;
            }
            $parrafos[] = $b['label'].': $ '.number_format((float) $b['monto'], 2, ',', '.')
                .' ('.(int) $b['cantidad'].')';
        }

        $filas = [];
        $pasos = [];
        foreach ($top as $c) {
            $filas[] = [
                'codigo' => (string) $c['codigo'],
                'cliente' => mb_substr((string) $c['nombre'], 0, 40),
                'lineas' => (string) (int) $c['lineas'],
                'saldo' => number_format((float) $c['saldo'], 2, ',', '.'),
            ];
            $pasos[] = [
                'etiqueta' => 'CT cliente '.$c['codigo'],
                'frase' => 'saldo del cliente '.$c['codigo'].' solo deuda',
                'url' => route('cliente_cuentacorriente_reporte', [
                    'consultar' => 1,
                    'cliente_codigo_desde' => $c['codigo'],
                    'cliente_codigo_hasta' => $c['codigo'],
                ]),
            ];
        }
        if ($filas === []) {
            $parrafos[] = 'No hay deuda pendiente de clientes en el filtro.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_AR_AGING,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Reporte CT clientes',
                    'url' => route('cliente_cuentacorriente_reporte'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Código'],
                    ['key' => 'cliente', 'label' => 'Cliente'],
                    ['key' => 'lineas', 'label' => 'Lín.'],
                    ['key' => 'saldo', 'label' => 'Saldo'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total_deuda' => $total,
                'pasos' => array_slice($pasos, 0, 8),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function empleadoLegajo(array $params): array
    {
        if (! can('listar-empleado-sueldos', false)) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO, 'Sin permiso para empleados de sueldos.');
        }

        $legajo = isset($params['legajo']) && is_numeric($params['legajo'])
            ? (int) $params['legajo']
            : 0;
        if ($legajo <= 0 && isset($params['valor']) && is_numeric($params['valor'])) {
            $legajo = (int) $params['valor'];
        }
        if ($legajo <= 0 && isset($params['numero']) && is_numeric($params['numero'])) {
            $legajo = (int) $params['numero'];
        }
        if ($legajo <= 0) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO,
                'Indique el legajo (ej.: empleado legajo 1234).'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        /** @var Empleado_SueldosRepositoryInterface $repo */
        $repo = app(Empleado_SueldosRepositoryInterface::class);
        $emp = $repo->findOperativoPorLegajo($legajo, $empresaId);
        if (! $emp) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO,
                'No se encontró empleado operativo con legajo '.$legajo.'.'
            );
        }

        $parrafos = [
            'Empleado legajo '.(int) $emp->legajo,
            (string) ($emp->nombre ?? ''),
            'CUIL: '.(string) ($emp->cuil ?? '—').' · Doc: '.(string) ($emp->documento ?? '—'),
            'Estado: '.(string) ($emp->estado ?? '—'),
            'Ingreso: '.(string) ($emp->fecha_ingreso ?? '—'),
        ];

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir ficha',
                    'url' => route('editar_empleado_sueldos', $emp->id).'?origen=modal_consulta&vista=consulta',
                ],
                [
                    'etiqueta' => 'Listado empleados',
                    'url' => route('consultar_empleado_sueldos'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'campo', 'label' => 'Campo'],
                    ['key' => 'valor', 'label' => 'Valor'],
                ],
                'filas' => [
                    ['campo' => 'Legajo', 'valor' => (string) (int) $emp->legajo],
                    ['campo' => 'Nombre', 'valor' => (string) ($emp->nombre ?? '')],
                    ['campo' => 'CUIL', 'valor' => (string) ($emp->cuil ?? '')],
                    ['campo' => 'Estado', 'valor' => (string) ($emp->estado ?? '')],
                ],
            ],
            'datos' => [
                'empleado_id' => (int) $emp->id,
                'legajo' => (int) $emp->legajo,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function liquidacionResumen(array $params): array
    {
        if (! can('listar-liquidacion-sueldos', false)) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
                'Sin permiso para liquidaciones de sueldos.'
            );
        }

        $numero = isset($params['numero']) && is_numeric($params['numero'])
            ? (int) $params['numero']
            : 0;
        if ($numero <= 0 && isset($params['valor']) && is_numeric($params['valor'])) {
            $numero = (int) $params['valor'];
        }
        if ($numero <= 0) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
                'Indique el número de liquidación (ej.: liquidación 45).'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        /** @var Liquidacion_SueldosRepositoryInterface $repo */
        $repo = app(Liquidacion_SueldosRepositoryInterface::class);
        $liq = $repo->findPorNumero($numero, $empresaId > 0 ? $empresaId : null);
        if (! $liq) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
                'No se encontró liquidación nro '.$numero.'.'
            );
        }

        try {
            LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        } catch (Throwable) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
                'Liquidación no visible para su empresa asignada.'
            );
        }

        $totales = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);
        $periodo = trim((string) ($liq->periodo ?? ''));
        if ($periodo === '' && $liq->periodo_anio && $liq->periodo_mes) {
            $periodo = sprintf('%04d-%02d', (int) $liq->periodo_anio, (int) $liq->periodo_mes);
        }

        $parrafos = [
            'Liquidación nro '.(int) $liq->numero,
            'Período: '.($periodo !== '' ? $periodo : '—').' · Estado: '.(string) ($liq->estado ?? '—'),
            'Recibos visibles: '.(int) $totales['cantidad'],
            'Neto a pagar (visible): $ '.number_format((float) $totales['neto'], 2, ',', '.'),
            'Remunerativo: $ '.number_format((float) $totales['rem'], 2, ',', '.')
                .' · Descuentos: $ '.number_format((float) $totales['desc'], 2, ',', '.'),
        ];
        if (! LiquidacionConfidencialSeguridadSupport::puedeVerConfidencial()) {
            $parrafos[] = 'Totales sin nómina confidencial (su rol no tiene ver-confidencial-recibo-sueldos).';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir liquidación',
                    'url' => route('editar_liquidacion_sueldos', $liq->id).'?origen=modal_consulta&vista=consulta',
                ],
                [
                    'etiqueta' => 'Listado liquidaciones',
                    'url' => route('consultar_liquidacion_sueldos'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'concepto', 'label' => 'Concepto'],
                    ['key' => 'importe', 'label' => 'Importe'],
                ],
                'filas' => [
                    ['concepto' => 'Recibos', 'importe' => (string) (int) $totales['cantidad']],
                    ['concepto' => 'Remunerativo', 'importe' => number_format((float) $totales['rem'], 2, ',', '.')],
                    ['concepto' => 'No remunerativo', 'importe' => number_format((float) $totales['norem'], 2, ',', '.')],
                    ['concepto' => 'Descuentos', 'importe' => number_format((float) $totales['desc'], 2, ',', '.')],
                    ['concepto' => 'Neto', 'importe' => number_format((float) $totales['neto'], 2, ',', '.')],
                ],
            ],
            'datos' => [
                'liquidacion_id' => (int) $liq->id,
                'numero' => (int) $liq->numero,
                'totales' => $totales,
            ],
        ];
    }

    /**
     * Corridas de liquidación de un período (AAAAMM).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function liquidacionesPeriodo(array $params): array
    {
        if (! can('listar-liquidacion-sueldos', false) && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PERIODO,
                'Sin permiso para liquidaciones de sueldos.'
            );
        }

        $periodo = self::resolverPeriodoAaaamm($params);
        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 20;

        $q = Liquidacion_Sueldos::query()
            ->where('periodo', $periodo)
            ->orderBy('numero');
        try {
            /** @var EmpresaRepositoryInterface $empRepo */
            $empRepo = app(EmpresaRepositoryInterface::class);
            $empRepo->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
        } catch (Throwable) {
            // MCP / sin repo: seguir
        }
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $filas = [];
        $netoVisible = 0.0;
        $recibos = 0;
        foreach ($q->limit($max)->get() as $liq) {
            try {
                LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
            } catch (Throwable) {
                continue;
            }
            $tot = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);
            $netoVisible += (float) ($tot['neto'] ?? 0);
            $recibos += (int) ($tot['cantidad'] ?? 0);
            $tipo = method_exists($liq, 'tipoLabel') ? (string) $liq->tipoLabel() : (string) ($liq->tipo ?? '');
            $filas[] = [
                'numero' => (string) (int) $liq->numero,
                'tipo' => $tipo !== '' ? $tipo : '—',
                'estado' => (string) ($liq->estado ?? ''),
                'recibos' => (string) (int) ($tot['cantidad'] ?? 0),
                'neto' => number_format((float) ($tot['neto'] ?? 0), 2, ',', '.'),
            ];
        }

        $parrafos = [
            'Liquidaciones período '.$periodo,
            'Corridas visibles: '.count($filas)
                .' · Recibos: '.$recibos
                .' · Neto visible: $ '.number_format($netoVisible, 2, ',', '.'),
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay liquidaciones visibles en ese período.';
        }
        if (! LiquidacionConfidencialSeguridadSupport::puedeVerConfidencial()) {
            $parrafos[] = 'Totales sin nómina confidencial (faltante ver-confidencial-recibo-sueldos).';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PERIODO,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Listado liquidaciones · '.$periodo,
                    'url' => route('consultar_liquidacion_sueldos', [
                        'filtro_modo' => 'campo',
                        'filtro_campo' => 'periodo',
                        'filtro_operador' => 'igual',
                        'filtro_valor' => $periodo,
                    ]),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'Nro'],
                    ['key' => 'tipo', 'label' => 'Tipo'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'recibos', 'label' => 'Recibos'],
                    ['key' => 'neto', 'label' => 'Neto visible'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'periodo' => $periodo,
                'cantidad' => count($filas),
                'recibos' => $recibos,
                'neto_visible' => $netoVisible,
            ],
        ];
    }

    /**
     * Corridas del período aún editables (borrador / calculada / revisada).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function liquidacionesPendientesCierre(array $params): array
    {
        if (! can('listar-liquidacion-sueldos', false) && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PENDIENTES_CIERRE,
                'Sin permiso para liquidaciones de sueldos.'
            );
        }

        $periodo = self::resolverPeriodoAaaamm($params);
        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 20;

        $q = Liquidacion_Sueldos::query()
            ->where('periodo', $periodo)
            ->whereIn('estado', Liquidacion_Sueldos::ESTADOS_EDITABLES)
            ->orderBy('numero');
        try {
            /** @var EmpresaRepositoryInterface $empRepo */
            $empRepo = app(EmpresaRepositoryInterface::class);
            $empRepo->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
        } catch (Throwable) {
            // MCP / sin repo
        }
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $filas = [];
        $netoVisible = 0.0;
        $recibos = 0;
        foreach ($q->limit($max)->get() as $liq) {
            try {
                LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
            } catch (Throwable) {
                continue;
            }
            $tot = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);
            $netoVisible += (float) ($tot['neto'] ?? 0);
            $recibos += (int) ($tot['cantidad'] ?? 0);
            $tipo = method_exists($liq, 'tipoLabel') ? (string) $liq->tipoLabel() : (string) ($liq->tipo ?? '');
            $filas[] = [
                'numero' => (string) (int) $liq->numero,
                'tipo' => $tipo !== '' ? $tipo : '—',
                'estado' => (string) ($liq->estado ?? ''),
                'recibos' => (string) (int) ($tot['cantidad'] ?? 0),
                'neto' => number_format((float) ($tot['neto'] ?? 0), 2, ',', '.'),
            ];
        }

        $parrafos = [
            'Liquidaciones pendientes de cierre · período '.$periodo,
            'Estados: borrador / calculada / revisada',
            'Corridas visibles: '.count($filas)
                .' · Recibos: '.$recibos
                .' · Neto visible: $ '.number_format($netoVisible, 2, ',', '.'),
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay corridas pendientes de cierre en ese período.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PENDIENTES_CIERRE,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Listado liquidaciones · '.$periodo,
                    'url' => route('consultar_liquidacion_sueldos', [
                        'filtro_modo' => 'campo',
                        'filtro_campo' => 'periodo',
                        'filtro_operador' => 'igual',
                        'filtro_valor' => $periodo,
                    ]),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'Nro'],
                    ['key' => 'tipo', 'label' => 'Tipo'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'recibos', 'label' => 'Recibos'],
                    ['key' => 'neto', 'label' => 'Neto visible'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'periodo' => $periodo,
                'cantidad' => count($filas),
                'recibos' => $recibos,
                'neto_visible' => $netoVisible,
                'estados' => Liquidacion_Sueldos::ESTADOS_EDITABLES,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function resolverPeriodoAaaamm(array $params): string
    {
        $raw = trim((string) ($params['periodo'] ?? $params['valor'] ?? ''));
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 6) {
            return $digits;
        }
        if (strlen($digits) === 8 && str_starts_with($digits, '20')) {
            // YYYYMMDD → YYYYMM
            return substr($digits, 0, 6);
        }

        $norm = mb_strtolower($raw, 'UTF-8');
        $norm = strtr($norm, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if ($norm === '' || str_contains($norm, 'este mes') || str_contains($norm, 'mes actual')) {
            return date('Ym');
        }
        if (str_contains($norm, 'mes pasado') || str_contains($norm, 'mes anterior')) {
            return date('Ym', strtotime('first day of last month'));
        }

        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];
        $anio = (int) date('Y');
        if (preg_match('/\b(20\d{2})\b/', $norm, $mAnio) === 1) {
            $anio = (int) $mAnio[1];
        }
        foreach ($meses as $nombre => $num) {
            if (str_contains($norm, $nombre)) {
                return sprintf('%04d%02d', $anio, $num);
            }
        }

        return date('Ym');
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function recepcionBorradores(array $params): array
    {
        if (! can('listar-recepcion-proveedor', false)) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_RECEPCION_BORRADORES,
                'Sin permiso para recepciones de proveedor.'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;

        $q = Recepcion_Proveedor::query()
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->where('estado', Recepcion_Proveedor::ESTADO_BORRADOR)
            ->orderByDesc('fecha')
            ->orderByDesc('id');
        if ($empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $total = (clone $q)->count();
        $filasDb = $q->limit($max)->get();

        $filas = [];
        $preview = [];
        foreach ($filasDb as $r) {
            $id = (int) $r->id;
            $nro = (string) (int) ($r->numerorecepcion ?? $r->id);
            $proveedor = mb_substr((string) ($r->proveedores->nombre ?? ''), 0, 36);
            $filas[] = [
                'id' => (string) $id,
                'nro' => $nro,
                'fecha' => (string) ($r->fecha ?? ''),
                'proveedor' => $proveedor,
                'empresa' => mb_substr((string) ($r->empresas->nombre ?? ''), 0, 20),
            ];
            if ($id > 0) {
                $preview[] = [
                    'id' => $id,
                    'nro' => $nro,
                    'proveedor' => $proveedor,
                    'empresa_id' => (int) ($r->empresa_id ?? 0),
                    'fecha' => (string) ($r->fecha ?? ''),
                ];
            }
        }
        $preview = array_slice($preview, 0, 10);

        $parrafos = [
            'Recepciones COM en borrador (pendientes de confirmar)',
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay borradores en el filtro.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_RECEPCION_BORRADORES,
            'score' => 0.87,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Recepciones proveedor',
                    'url' => route('recepcion_proveedor', ['filtro_estado' => 'BORRADOR']),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'nro', 'label' => 'Nro'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total' => $total,
                'confirmar' => [
                    'recepcion_ids' => array_column($preview, 'id'),
                    'preview' => $preview,
                    'puede_confirmar' => $preview !== [] && can('confirmar-recepcion-proveedor', false),
                ],
                'eliminar' => [
                    'recepcion_ids' => array_column($preview, 'id'),
                    'preview' => $preview,
                    'puede_eliminar' => $preview !== [] && can('borrar-recepcion-proveedor', false),
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function tmPendientes(array $params): array
    {
        if (! can('listar-transferencias-pendientes', false)
            && ! can('crear-transferencia-mercaderia', false)
            && ! can('listar-movimientos-de-stock', false)) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_TM_PENDIENTES,
                'Sin permiso para transferencias de mercadería pendientes.'
            );
        }

        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        $q = Transferencia_Mercaderia::query()
            ->with(['depositoOrigen:id,codigo,nombre', 'depositoDestino:id,codigo,nombre'])
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (UsuarioDepositoAutorizado::tieneRestriccion()) {
            $ids = UsuarioDepositoAutorizado::idsRestringidos() ?? [];
            $usuarioId = (int) (Auth::id() ?? 0);
            if ($ids !== [] || $usuarioId > 0) {
                $q->where(function ($w) use ($ids, $usuarioId) {
                    if ($ids !== []) {
                        $w->whereIn('deposito_destino_id', $ids);
                    }
                    if ($usuarioId > 0) {
                        $w->orWhere('usuario_destino_id', $usuarioId);
                    }
                });
            }
        }

        $total = (clone $q)->count();
        $rows = $q->limit($max)->get();
        $filas = [];
        $preview = [];
        foreach ($rows as $t) {
            $id = (int) $t->id;
            $codigo = (string) ($t->codigo ?? $t->id);
            $filas[] = [
                'codigo' => $codigo,
                'fecha' => (string) ($t->fecha ?? ''),
                'origen' => (string) ($t->depositoOrigen->codigo ?? ''),
                'destino' => (string) ($t->depositoDestino->codigo ?? ''),
            ];
            if ($id > 0) {
                $preview[] = [
                    'id' => $id,
                    'codigo' => $codigo,
                    'origen' => (string) ($t->depositoOrigen->codigo ?? ''),
                    'destino' => (string) ($t->depositoDestino->codigo ?? ''),
                ];
            }
        }
        $preview = array_slice($preview, 0, 10);

        $parrafos = [
            'Transferencias pendientes de recepción',
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay TM pendientes en su alcance.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_TM_PENDIENTES,
            'score' => 0.87,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'TM pendientes',
                    'url' => route('transferencia_mercaderia_pendientes'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Código'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'origen', 'label' => 'Origen'],
                    ['key' => 'destino', 'label' => 'Destino'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total' => $total,
                'confirmar_tm' => [
                    'transferencia_ids' => array_column($preview, 'id'),
                    'preview' => $preview,
                    'puede_confirmar' => $preview !== [] && can('aprobar-transferencia-mercaderia', false),
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function ingresoEgresoPeriodo(array $params): array
    {
        if (! can('listar-ingresos-egresos-caja', false)) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_INGRESO_EGRESO_PERIODO,
                'Sin permiso para ingresos/egresos de caja.'
            );
        }

        $desde = ! empty($params['fecha_desde']) ? (string) $params['fecha_desde'] : date('Y-m-01');
        $hasta = ! empty($params['fecha_hasta']) ? (string) $params['fecha_hasta'] : date('Y-m-d');
        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;

        $q = Caja_Movimiento::query()
            ->from('caja_movimiento')
            ->whereBetween('caja_movimiento.fecha', [$desde, $hasta]);
        IngresoEgresoVisibilidadSupport::aplicarFiltroAlcance($q, 'caja_movimiento');
        if ($empresaId > 0) {
            $q->where('caja_movimiento.empresa_id', $empresaId);
        }

        $cantidad = (clone $q)->count();

        // Sumar montos por movimiento (evita multiplicar si hay N cuentas)
        $porMov = (clone $q)
            ->join('tipotransaccion_caja as ttc', 'ttc.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
            ->leftJoin('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'caja_movimiento.id')
            ->groupBy('caja_movimiento.id', 'ttc.signo')
            ->selectRaw(
                'caja_movimiento.id as mov_id, ttc.signo as signo, COALESCE(SUM(ABS(cmc.monto)),0) as monto'
            )
            ->get();

        $ingresos = 0.0;
        $egresos = 0.0;
        foreach ($porMov as $row) {
            $monto = (float) ($row->monto ?? 0);
            if ((int) ($row->signo ?? 0) >= 0) {
                $ingresos += $monto;
            } else {
                $egresos += $monto;
            }
        }

        $parrafos = [
            'Ingresos / egresos de caja · '.$desde.' → '.$hasta,
            'Movimientos: '.$cantidad,
            'Ingresos (signo +): $ '.number_format($ingresos, 2, ',', '.'),
            'Egresos (signo −): $ '.number_format($egresos, 2, ',', '.'),
            'Neto aproximado: $ '.number_format($ingresos - $egresos, 2, ',', '.'),
        ];

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_INGRESO_EGRESO_PERIODO,
            'score' => 0.86,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Ingresos / egresos',
                    'url' => route('ingresoegreso'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'concepto', 'label' => 'Concepto'],
                    ['key' => 'valor', 'label' => 'Valor'],
                ],
                'filas' => [
                    ['concepto' => 'Movimientos', 'valor' => (string) $cantidad],
                    ['concepto' => 'Ingresos', 'valor' => number_format($ingresos, 2, ',', '.')],
                    ['concepto' => 'Egresos', 'valor' => number_format($egresos, 2, ',', '.')],
                    ['concepto' => 'Neto', 'valor' => number_format($ingresos - $egresos, 2, ',', '.')],
                ],
            ],
            'datos' => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'ingresos' => $ingresos,
                'egresos' => $egresos,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function ticketsKpi(array $params): array
    {
        if (! can('listar-ticket', false) && ! can('listar-administracion-ticket', false)
            && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_TICKETS_KPI,
                'Sin permiso para tickets.'
            );
        }

        $rows = Ticket::query()
            ->selectRaw('estado_ticket, COUNT(*) as cantidad')
            ->groupBy('estado_ticket')
            ->orderByDesc('cantidad')
            ->get();

        $total = 0;
        $enCurso = 0;
        $filas = [];
        foreach ($rows as $r) {
            $est = trim((string) ($r->estado_ticket ?? '(vacío)'));
            $cant = (int) ($r->cantidad ?? 0);
            $total += $cant;
            if (in_array($est, ['Pendiente', 'En ejecución'], true)) {
                $enCurso += $cant;
            }
            $filas[] = [
                'estado' => $est !== '' ? $est : '(vacío)',
                'cantidad' => (string) $cant,
            ];
        }

        $parrafos = [
            'Resumen de tickets por estado',
            'Total: '.$total.' · En curso (Pendiente / En ejecución): '.$enCurso,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay tickets.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_TICKETS_KPI,
            'score' => 0.85,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Administración de tickets',
                    'url' => route('consulta_administracion_ticket'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'cantidad', 'label' => 'Cant.'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total' => $total,
                'en_curso' => $enCurso,
            ],
        ];
    }

    /**
     * Tickets abiertos (Pendiente / En ejecución) ordenados por antigüedad.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function ticketsAbiertos(array $params): array
    {
        if (! can('listar-ticket', false) && ! can('listar-administracion-ticket', false)
            && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_TICKETS_ABIERTOS,
                'Sin permiso para tickets.'
            );
        }

        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        $diasMin = isset($params['dias_min']) && is_numeric($params['dias_min'])
            ? max(0, (int) $params['dias_min'])
            : 0;
        $hoy = strtotime(date('Y-m-d'));

        $q = Ticket::query()
            ->whereIn('estado_ticket', AdministracionTicketListadoFiltros::ESTADOS_EN_CURSO)
            ->orderBy('fecha')
            ->orderBy('id');
        if ($diasMin > 0) {
            $corte = date('Y-m-d', strtotime('-'.$diasMin.' days'));
            $q->whereDate('fecha', '<=', $corte);
        }

        $totalAbiertos = (int) (clone $q)->count();
        $rows = $q->limit($max)->get(['id', 'fecha', 'estado_ticket', 'titulo']);

        $filas = [];
        $parrafos = [
            $diasMin > 0
                ? 'Tickets abiertos con ≥ '.$diasMin.' días (proxy antigüedad / SLA operativo)'
                : 'Tickets abiertos (Pendiente / En ejecución)',
            'Total: '.$totalAbiertos.' · mostrando top '.$rows->count().' por antigüedad',
        ];
        foreach ($rows as $t) {
            $fecha = $t->fecha ? (is_string($t->fecha) ? $t->fecha : $t->fecha->format('Y-m-d')) : null;
            $dias = 0;
            if ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) {
                $ts = strtotime(substr($fecha, 0, 10));
                if ($ts !== false) {
                    $dias = max(0, (int) floor(($hoy - $ts) / 86400));
                }
            }
            $titulo = mb_substr(trim((string) ($t->titulo ?? '')), 0, 60);
            $filas[] = [
                'id' => (string) $t->id,
                'fecha' => $fecha ? date('d/m/Y', strtotime(substr($fecha, 0, 10))) : '',
                'dias' => (string) $dias,
                'estado' => (string) ($t->estado_ticket ?? ''),
                'titulo' => $titulo !== '' ? $titulo : '(sin título)',
            ];
        }
        if ($filas === []) {
            $parrafos[] = $diasMin > 0
                ? 'No hay tickets abiertos con esa antigüedad.'
                : 'No hay tickets abiertos.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_TICKETS_ABIERTOS,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Administración de tickets (en curso)',
                    'url' => route('consulta_administracion_ticket'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'dias', 'label' => 'Días'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'titulo', 'label' => 'Título'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total_abiertos' => $totalAbiertos,
                'mostrados' => count($filas),
                'dias_min' => $diasMin,
            ],
        ];
    }

    /**
     * Precargas de factura proveedor en estado PENDIENTE.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function precargaPendientes(array $params): array
    {
        if (! can('listar-precarga-proveedores', false) && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_PRECARGA_PENDIENTES,
                'Sin permiso para precargas de factura proveedor.'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;

        $q = Precarga_Comprobante_Proveedor::query()
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->where('estado', PrecargaComprobanteEstados::PENDIENTE)
            ->orderByDesc('fechafactura')
            ->orderByDesc('id');
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $total = (clone $q)->count();
        $filas = [];
        foreach ($q->limit($max)->get() as $p) {
            $filas[] = [
                'id' => (string) (int) $p->id,
                'fecha' => $p->fechafactura ? $p->fechafactura->format('d/m/Y') : '',
                'proveedor' => mb_substr((string) ($p->proveedores->nombre ?? $p->identificacion_proveedor_cuit ?? ''), 0, 36),
                'nro' => trim(($p->letra ?? '').'-'.($p->sucursal ?? '').'-'.($p->numerocomprobante ?? '')),
                'total' => number_format((float) ($p->total ?? 0), 2, ',', '.'),
            ];
        }

        $parrafos = [
            'Precargas de factura en PENDIENTE',
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay precargas pendientes.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_PRECARGA_PENDIENTES,
            'score' => 0.87,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Precargas pendientes',
                    'url' => route('precarga_comprobante_proveedor'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'nro', 'label' => 'Comprobante'],
                    ['key' => 'total', 'label' => 'Total'],
                ],
                'filas' => $filas,
            ],
            'datos' => ['total' => $total],
        ];
    }

    /**
     * Empleados con fecha_ingreso en el período (default mes actual).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function empleadosIngresados(array $params): array
    {
        if (! can('listar-empleado-sueldos', false) && empty($params['_mcp_bridge'])) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_EMPLEADOS_INGRESADOS,
                'Sin permiso para empleados de sueldos.'
            );
        }

        $periodo = self::resolverPeriodoAaaamm($params);
        $anio = (int) substr($periodo, 0, 4);
        $mes = (int) substr($periodo, 4, 2);
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde) ?: time());
        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 20;

        $q = Empleado_Sueldos::query()
            ->whereDate('fecha_ingreso', '>=', $desde)
            ->whereDate('fecha_ingreso', '<=', $hasta)
            ->orderBy('fecha_ingreso')
            ->orderBy('legajo');
        try {
            /** @var EmpresaRepositoryInterface $empRepo */
            $empRepo = app(EmpresaRepositoryInterface::class);
            $empRepo->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
        } catch (Throwable) {
            // MCP
        }
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $total = (clone $q)->count();
        $filas = [];
        foreach ($q->limit($max)->get(['id', 'legajo', 'nombre', 'fecha_ingreso', 'empresa_id']) as $e) {
            $filas[] = [
                'legajo' => (string) (int) ($e->legajo ?? 0),
                'nombre' => mb_substr((string) ($e->nombre ?? ''), 0, 40),
                'ingreso' => $e->fecha_ingreso ? $e->fecha_ingreso->format('d/m/Y') : '',
            ];
        }

        $parrafos = [
            'Empleados ingresados · período '.$periodo.' ('.$desde.' → '.$hasta.')',
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay ingresos en ese período.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_EMPLEADOS_INGRESADOS,
            'score' => 0.87,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Empleados sueldos',
                    'url' => route('consultar_empleado_sueldos'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'legajo', 'label' => 'Legajo'],
                    ['key' => 'nombre', 'label' => 'Nombre'],
                    ['key' => 'ingreso', 'label' => 'Ingreso'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'periodo' => $periodo,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'total' => $total,
            ],
        ];
    }

    /**
     * Bandeja unificada de pendientes (árbol + indumentaria + asientos + TM + …).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function misAprobaciones(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_MIS_APROBACIONES;
        if (! can('aprobar-mis-aprobaciones-arbol', false)
            && ! can('listar-aprobacion-asiento', false)
            && ! can('aprobar-solicitud-indumentaria', false)
            && ! can('aprobar-transferencia-mercaderia', false)
            && empty($params['_mcp_bridge'])) {
            return self::fallo($intent, 'Sin permiso para la bandeja de aprobaciones.');
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId < 1 && empty($params['_mcp_bridge'])) {
            return self::fallo($intent, 'Sesión requerida para ver pendientes de aprobación.');
        }
        if ($usuarioId < 1) {
            $usuarioId = (int) ($params['usuario_id'] ?? 0);
        }
        if ($usuarioId < 1) {
            return self::fallo($intent, 'Indique el usuario para listar pendientes.');
        }

        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        /** @var \App\Services\Configuracion\UserTaskBandejaService $bandeja */
        $bandeja = app(\App\Services\Configuracion\UserTaskBandejaService::class);
        $items = $bandeja->listarPendientesParaUsuario($usuarioId, [
            'fuente' => $params['fuente'] ?? null,
            'tipo' => $params['tipo'] ?? null,
        ]);
        $total = $items->count();
        $filas = [];
        foreach ($items->take($max) as $item) {
            $detalle = (string) ($item['titulo'] ?? $item['detalle'] ?? '');
            if ($detalle === '' && ! empty($item['tipo'])) {
                $detalle = trim((string) $item['tipo'].' '.($item['numero'] ?? $item['comprobante_id'] ?? ''));
            }
            $desde = (string) ($item['fecha_envio'] ?? $item['fecha'] ?? '');
            if ($desde === '' && isset($item['dias'])) {
                $desde = ((int) $item['dias']).' d';
            }
            $filas[] = [
                'fuente' => mb_substr((string) ($item['fuente_label'] ?? $item['fuente'] ?? ''), 0, 24),
                'detalle' => mb_substr($detalle, 0, 48),
                'empresa' => mb_substr((string) ($item['empresa'] ?? $item['nombreempresa'] ?? ''), 0, 24),
                'desde' => mb_substr($desde, 0, 16),
            ];
        }

        $parrafos = [
            'Mis aprobaciones pendientes',
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay pendientes en tu bandeja.';
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.92,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir mis aprobaciones',
                    'url' => route('mis_aprobaciones_arbol'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'fuente', 'label' => 'Fuente'],
                    ['key' => 'detalle', 'label' => 'Detalle'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                    ['key' => 'desde', 'label' => 'Desde'],
                ],
                'filas' => $filas,
            ],
            'datos' => ['total' => $total, 'usuario_id' => $usuarioId],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function fallo(string $intent, string $mensaje): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $mensaje,
        ];
    }
}
