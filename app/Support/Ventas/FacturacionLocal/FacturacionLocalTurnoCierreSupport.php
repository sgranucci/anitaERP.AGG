<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Models\Ventas\Venta;
use Illuminate\Support\Carbon;

/**
 * Totales por medio de pago y comprobantes de un turno operativo de Facturación Local.
 */
final class FacturacionLocalTurnoCierreSupport
{
    /** @var array<int, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array{
     *   cantidad_facturas: int,
     *   total_facturado: float,
     *   cantidad_nc: int,
     *   total_nc: float,
     *   neto_medios: float,
     *   por_medio: list<array{
     *     cuentacaja_id: int,
     *     codigo: string,
     *     nombre: string,
     *     cantidad: int,
     *     cobrado: float,
     *     devuelto: float,
     *     neto: float
     *   }>
     * }
     */
    public static function resumen(TurnoOperativoLocal $turno): array
    {
        $armado = self::armar((int) $turno->id);

        return [
            'cantidad_facturas' => $armado['cantidad_facturas'],
            'total_facturado' => $armado['total_facturado'],
            'cantidad_nc' => $armado['cantidad_nc'],
            'total_nc' => $armado['total_nc'],
            'neto_medios' => $armado['neto_medios'],
            'por_medio' => $armado['por_medio'],
        ];
    }

    /**
     * @return list<array{
     *   venta_id: int,
     *   codigo: string,
     *   cliente: string,
     *   cuando: string,
     *   total: float,
     *   monto_medio: float,
     *   es_nota_credito: bool,
     *   es_ticket_regalo: bool,
     *   puede_cambiar_medio: bool,
     *   url_ver: string
     * }>
     */
    public static function comprobantes(TurnoOperativoLocal $turno, int $cuentacajaId, bool $soloNotasCredito): array
    {
        $armado = self::armar((int) $turno->id);
        $permiteMover = $turno->estaAbierto()
            && $turno->cierre_en === null
            && can('cambiar-medio-pago-facturacion-local', false);

        $filas = $soloNotasCredito
            ? ($armado['notas_credito'] ?? [])
            : ($armado['comprobantes_por_medio'][$cuentacajaId] ?? []);

        $out = [];
        foreach ($filas as $fila) {
            $esNc = (bool) ($fila['es_nota_credito'] ?? false);
            $out[] = [
                'venta_id' => (int) $fila['venta_id'],
                'codigo' => (string) $fila['codigo'],
                'cliente' => (string) $fila['cliente'],
                'cuando' => (string) $fila['cuando'],
                'total' => (float) $fila['total'],
                'monto_medio' => (float) $fila['monto_medio'],
                'es_nota_credito' => $esNc,
                'es_ticket_regalo' => (bool) ($fila['es_ticket_regalo'] ?? false),
                'puede_cambiar_medio' => $permiteMover && ! $esNc && (bool) ($fila['tiene_cobranza'] ?? false),
                'url_ver' => route('facturacion_local_facturas_ver', [
                    'ventaId' => (int) $fila['venta_id'],
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function armar(int $turnoId): array
    {
        if (isset(self::$cache[$turnoId])) {
            return self::$cache[$turnoId];
        }

        $emisiones = FacturacionLocalEmision::query()
            ->where('turno_operativo_local_id', $turnoId)
            ->get(['id', 'venta_id', 'venta_nc_id', 'es_ticket_regalo']);

        $facIds = [];
        $ncIds = [];
        $regalo = [];
        foreach ($emisiones as $emision) {
            $ventaId = (int) $emision->venta_id;
            if ($ventaId > 0) {
                $facIds[$ventaId] = $ventaId;
            }
            $ncId = (int) ($emision->venta_nc_id ?? 0);
            if ($ncId > 0) {
                $ncIds[$ncId] = $ncId;
            }
            if ($emision->es_ticket_regalo && $ventaId > 0) {
                $regalo[$ventaId] = true;
            }
        }

        $todosIds = array_values(array_unique(array_merge(array_values($facIds), array_values($ncIds))));
        $ventas = $todosIds === []
            ? collect()
            : Venta::query()
                ->whereIn('id', $todosIds)
                ->get(['id', 'codigo', 'nombre', 'total', 'fecha', 'created_at'])
                ->keyBy('id');

        $cobranzaVenta = self::cobranzasPorVenta($todosIds);
        $lineasPorCobranza = self::lineasPorCobranza(array_keys($cobranzaVenta));

        /** @var array<int, array{cuentacaja_id:int,codigo:string,nombre:string,cobrado:float,devuelto:float,facturas:array<int,float>,notas:array<int,float>}> $medios */
        $medios = [];
        $notas = [];
        $facConCobranza = [];

        foreach ($cobranzaVenta as $cobranzaId => $ventaId) {
            $esNc = isset($ncIds[$ventaId]);
            $lineas = $lineasPorCobranza[$cobranzaId] ?? [];
            if ($lineas === []) {
                continue;
            }
            if (! $esNc) {
                $facConCobranza[$ventaId] = true;
            }
            foreach ($lineas as $linea) {
                $ccId = (int) $linea['cuentacaja_id'];
                if (! isset($medios[$ccId])) {
                    $medios[$ccId] = [
                        'cuentacaja_id' => $ccId,
                        'codigo' => (string) $linea['codigo'],
                        'nombre' => (string) $linea['nombre'],
                        'cobrado' => 0.0,
                        'devuelto' => 0.0,
                        'facturas' => [],
                        'notas' => [],
                    ];
                }
                $monto = round((float) $linea['monto'], 2);
                if ($esNc) {
                    $devuelto = abs($monto);
                    $medios[$ccId]['devuelto'] = round($medios[$ccId]['devuelto'] + $devuelto, 2);
                    $medios[$ccId]['notas'][$ventaId] = round(($medios[$ccId]['notas'][$ventaId] ?? 0) + $devuelto, 2);
                } else {
                    $medios[$ccId]['cobrado'] = round($medios[$ccId]['cobrado'] + $monto, 2);
                    $medios[$ccId]['facturas'][$ventaId] = round(($medios[$ccId]['facturas'][$ventaId] ?? 0) + $monto, 2);
                }
            }
        }

        $sinMedio = [];
        $totalFacturado = 0.0;
        foreach ($facIds as $ventaId) {
            $venta = $ventas->get($ventaId);
            $total = round((float) ($venta->total ?? 0), 2);
            $totalFacturado = round($totalFacturado + $total, 2);
            if (! isset($facConCobranza[$ventaId])) {
                $sinMedio[$ventaId] = $total;
            }
        }

        $totalNc = 0.0;
        foreach ($ncIds as $ventaId) {
            $venta = $ventas->get($ventaId);
            $total = round(abs((float) ($venta->total ?? 0)), 2);
            $totalNc = round($totalNc + $total, 2);
            $notas[] = self::filaComprobante($venta, $ventaId, $total, $total, true, false, true);
        }

        if ($sinMedio !== []) {
            if (! isset($medios[0])) {
                $medios[0] = [
                    'cuentacaja_id' => 0,
                    'codigo' => '',
                    'nombre' => 'Sin medio de pago',
                    'cobrado' => 0.0,
                    'devuelto' => 0.0,
                    'facturas' => [],
                    'notas' => [],
                ];
            }
            $medios[0]['nombre'] = 'Sin medio de pago';
            foreach ($sinMedio as $ventaId => $total) {
                if (isset($medios[0]['facturas'][$ventaId])) {
                    continue;
                }
                $medios[0]['facturas'][$ventaId] = round((float) $total, 2);
                $medios[0]['cobrado'] = round($medios[0]['cobrado'] + (float) $total, 2);
            }
        }

        uasort($medios, static function (array $a, array $b): int {
            if ((int) $a['cuentacaja_id'] === 0) {
                return 1;
            }
            if ((int) $b['cuentacaja_id'] === 0) {
                return -1;
            }

            return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
        });

        $porMedio = [];
        $comprobantesPorMedio = [];
        $neto = 0.0;
        foreach ($medios as $ccId => $medio) {
            $netoMedio = round((float) $medio['cobrado'] - (float) $medio['devuelto'], 2);
            $neto = round($neto + $netoMedio, 2);
            $porMedio[] = [
                'cuentacaja_id' => (int) $medio['cuentacaja_id'],
                'codigo' => (string) $medio['codigo'],
                'nombre' => (string) $medio['nombre'],
                'cantidad' => count($medio['facturas']),
                'cobrado' => round((float) $medio['cobrado'], 2),
                'devuelto' => round((float) $medio['devuelto'], 2),
                'neto' => $netoMedio,
            ];

            $filas = [];
            foreach ($medio['facturas'] as $ventaId => $montoMedio) {
                $venta = $ventas->get((int) $ventaId);
                $filas[] = self::filaComprobante(
                    $venta,
                    (int) $ventaId,
                    round((float) ($venta->total ?? 0), 2),
                    round((float) $montoMedio, 2),
                    false,
                    isset($regalo[(int) $ventaId]),
                    (int) $ccId > 0,
                );
            }
            foreach ($medio['notas'] as $ventaId => $montoMedio) {
                $venta = $ventas->get((int) $ventaId);
                $filas[] = self::filaComprobante(
                    $venta,
                    (int) $ventaId,
                    round(abs((float) ($venta->total ?? 0)), 2),
                    round((float) $montoMedio, 2),
                    true,
                    false,
                    true,
                );
            }
            usort($filas, static fn (array $a, array $b): int => strcmp((string) $a['cuando'], (string) $b['cuando']));
            $comprobantesPorMedio[(int) $ccId] = $filas;
        }

        usort($notas, static fn (array $a, array $b): int => strcmp((string) $a['cuando'], (string) $b['cuando']));

        self::$cache[$turnoId] = [
            'cantidad_facturas' => count($facIds),
            'total_facturado' => $totalFacturado,
            'cantidad_nc' => count($ncIds),
            'total_nc' => $totalNc,
            'neto_medios' => $neto,
            'por_medio' => $porMedio,
            'comprobantes_por_medio' => $comprobantesPorMedio,
            'notas_credito' => $notas,
        ];

        return self::$cache[$turnoId];
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, int> cobranza_id => venta_id
     */
    private static function cobranzasPorVenta(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $mapa = [];
        $directas = Cobranza::query()
            ->whereIn('venta_id', $ventaIds)
            ->get(['id', 'venta_id']);
        foreach ($directas as $cobranza) {
            $mapa[(int) $cobranza->id] = (int) $cobranza->venta_id;
        }

        $porMovimiento = Caja_Movimiento::query()
            ->whereIn('venta_id', $ventaIds)
            ->whereNotNull('cobranza_id')
            ->get(['cobranza_id', 'venta_id']);
        foreach ($porMovimiento as $mov) {
            $cobranzaId = (int) $mov->cobranza_id;
            if ($cobranzaId > 0 && ! isset($mapa[$cobranzaId])) {
                $mapa[$cobranzaId] = (int) $mov->venta_id;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $cobranzaIds
     * @return array<int, list<array{cuentacaja_id:int,codigo:string,nombre:string,monto:float}>>
     */
    private static function lineasPorCobranza(array $cobranzaIds): array
    {
        if ($cobranzaIds === []) {
            return [];
        }

        $movimientos = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzaIds)
            ->with(['caja_movimiento_cuentacajas.cuentacajas'])
            ->get();

        $out = [];
        foreach ($movimientos as $mov) {
            $cobranzaId = (int) $mov->cobranza_id;
            foreach ($mov->caja_movimiento_cuentacajas as $linea) {
                $cotizacion = (float) ($linea->cotizacion ?? 0);
                $monto = (float) $linea->monto * ($cotizacion > 0 ? $cotizacion : 1);
                $out[$cobranzaId][] = [
                    'cuentacaja_id' => (int) ($linea->cuentacaja_id ?? 0),
                    'codigo' => (string) ($linea->cuentacajas->codigo ?? ''),
                    'nombre' => (string) ($linea->cuentacajas->nombre ?? ('Cuenta '.$linea->cuentacaja_id)),
                    'monto' => round($monto, 2),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
     *   cuando:string,
     *   total:float,
     *   monto_medio:float,
     *   es_nota_credito:bool,
     *   es_ticket_regalo:bool,
     *   tiene_cobranza:bool
     * }
     */
    private static function filaComprobante(
        ?Venta $venta,
        int $ventaId,
        float $total,
        float $montoMedio,
        bool $esNotaCredito,
        bool $esTicketRegalo,
        bool $tieneCobranza,
    ): array {
        $cuando = '';
        if ($venta !== null) {
            $marca = $venta->created_at ?: $venta->fecha;
            if ($marca) {
                $cuando = Carbon::parse($marca)->format('d/m/Y H:i');
            }
        }

        return [
            'venta_id' => $ventaId,
            'codigo' => trim((string) ($venta->codigo ?? '')) !== '' ? (string) $venta->codigo : ('#'.$ventaId),
            'cliente' => trim((string) ($venta->nombre ?? '')),
            'cuando' => $cuando,
            'total' => $total,
            'monto_medio' => $montoMedio,
            'es_nota_credito' => $esNotaCredito,
            'es_ticket_regalo' => $esTicketRegalo,
            'tiene_cobranza' => $tieneCobranza,
        ];
    }
}
