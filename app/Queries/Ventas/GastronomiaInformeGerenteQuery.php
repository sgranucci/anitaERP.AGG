<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos del informe gerente gastronomía (ventas ERP en fecha de jornada).
 */
final class GastronomiaInformeGerenteQuery
{
    private const VENTA_TOTAL_EXPR = 'CASE WHEN tt.signo = -1 THEN -v.total ELSE v.total END';

    public function __construct(
        private readonly GastronomiaArticulosVendidosQuery $articulosVendidosQuery,
    ) {}

    /**
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function top10PorCantidad(int $empresaId, string $fechaJornada): array
    {
        return $this->articulosVendidosQuery->topPorJornada($empresaId, $fechaJornada, 'cantidad', 10);
    }

    /**
     * @return list<array{articulo_id:int,sku:string,descripcion:string,cantidad:float,importe:float}>
     */
    public function top10PorValor(int $empresaId, string $fechaJornada): array
    {
        return $this->articulosVendidosQuery->topPorJornada($empresaId, $fechaJornada, 'importe', 10);
    }

    /**
     * @return list<array{turno_id:int,etiqueta:string,total:float,cantidad:int}>
     */
    public function ventasPorTurno(int $empresaId, string $fechaJornada): array
    {
        $jornadaId = $this->jornadaId($empresaId, $fechaJornada);
        $ventanas = $this->ventanasTurnoOperativo($empresaId, $jornadaId);

        $emisiones = DB::table('venta_gastronomia_emision as vge')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fechajornada', $fechaJornada)
            ->select([
                'v.id as venta_id',
                'v.created_at',
                'vge.identificador_pc',
                DB::raw(self::VENTA_TOTAL_EXPR.' as total'),
            ])
            ->get();

        $porTurno = [];
        $sinTurno = ['turno_id' => 0, 'etiqueta' => 'Sin turno asignado', 'total' => 0.0, 'cantidad' => 0];

        foreach ($emisiones as $row) {
            $total = round((float) $row->total, 2);
            if (abs($total) <= 0.0001) {
                continue;
            }

            $turnoId = $this->resolverTurnoIdParaVenta($ventanas, (string) $row->identificador_pc, $row->created_at);
            if ($turnoId <= 0) {
                $sinTurno['total'] = round($sinTurno['total'] + $total, 2);
                $sinTurno['cantidad']++;

                continue;
            }

            if (! isset($porTurno[$turnoId])) {
                $porTurno[$turnoId] = [
                    'turno_id' => $turnoId,
                    'etiqueta' => $this->etiquetaTurno($turnoId),
                    'total' => 0.0,
                    'cantidad' => 0,
                ];
            }
            $porTurno[$turnoId]['total'] = round($porTurno[$turnoId]['total'] + $total, 2);
            $porTurno[$turnoId]['cantidad']++;
        }

        $filas = array_values($porTurno);
        if ($sinTurno['cantidad'] > 0) {
            $filas[] = $sinTurno;
        }

        usort($filas, fn ($a, $b) => ($b['total'] <=> $a['total']));

        return $filas;
    }

    /**
     * @return list<array{
     *   puntoventa_id:int,
     *   codigo:string,
     *   nombre:string,
     *   total:float,
     *   total_facturas:float,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int
     * }>
     */
    public function ventasPorPuntoVenta(int $empresaId, string $fechaJornada): array
    {
        $pvIds = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->get(['puntoventa_cae_id', 'puntoventa_caea_id'])
            ->flatMap(fn ($cfg) => [(int) $cfg->puntoventa_cae_id, (int) $cfg->puntoventa_caea_id])
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $pvs = $pvIds->isEmpty()
            ? collect()
            : Puntoventa::query()
                ->whereIn('id', $pvIds)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

        $filas = [];
        foreach ($pvs as $pv) {
            $totales = GastronomiaTurnoOperativoTotalesSupport::totalesDiaPorPuntoventa(
                (int) $pv->id,
                $empresaId,
                $fechaJornada,
            );
            $filas[] = [
                'puntoventa_id' => (int) $pv->id,
                'codigo' => trim((string) $pv->codigo),
                'nombre' => trim((string) $pv->nombre),
                'total' => round((float) ($totales['total_ventas'] ?? 0), 2),
                'total_facturas' => round((float) ($totales['total_facturas'] ?? 0), 2),
                'cantidad_facturas' => (int) ($totales['cantidad_facturas'] ?? 0),
                'cantidad_notas_credito' => (int) ($totales['cantidad_notas_credito'] ?? 0),
            ];
        }

        usort($filas, fn ($a, $b) => ($b['total'] <=> $a['total']));

        return $filas;
    }

    /**
     * @return array{
     *   filas:list<array{descuento_id:int,codigo:string,nombre:string,cantidad:int,importe:float}>,
     *   sin_descuento:array{cantidad:int,importe:float}
     * }
     */
    public function facturasPorDescuento(int $empresaId, string $fechaJornada): array
    {
        $rows = DB::table('venta_gastronomia_emision as vge')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->leftJoin('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id')
            ->whereNull('v.deleted_at')
            ->whereNull('vge.venta_factura_origen_id')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fechajornada', $fechaJornada)
            ->select([
                'dg.id as descuento_id',
                'dg.codigo as descuento_codigo',
                'dg.nombre as descuento_nombre',
                DB::raw('COUNT(DISTINCT v.id) as cantidad'),
                DB::raw('SUM('.self::VENTA_TOTAL_EXPR.') as importe'),
            ])
            ->groupBy('dg.id', 'dg.codigo', 'dg.nombre')
            ->get();

        $filas = [];
        $sinDescuento = ['cantidad' => 0, 'importe' => 0.0];

        foreach ($rows as $row) {
            $cantidad = (int) $row->cantidad;
            $importe = round((float) $row->importe, 2);
            $descuentoId = (int) ($row->descuento_id ?? 0);
            if ($descuentoId <= 0) {
                $sinDescuento['cantidad'] += $cantidad;
                $sinDescuento['importe'] = round($sinDescuento['importe'] + $importe, 2);

                continue;
            }

            $filas[] = [
                'descuento_id' => $descuentoId,
                'codigo' => trim((string) ($row->descuento_codigo ?? '')),
                'nombre' => trim((string) ($row->descuento_nombre ?? '')),
                'cantidad' => $cantidad,
                'importe' => $importe,
            ];
        }

        usort($filas, fn ($a, $b) => ($b['importe'] <=> $a['importe']));

        return [
            'filas' => $filas,
            'sin_descuento' => $sinDescuento,
        ];
    }

    public function totalVentasJornada(int $empresaId, string $fechaJornada): float
    {
        $row = DB::table('venta_gastronomia_emision as vge')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', $empresaId)
            ->whereDate('v.fechajornada', $fechaJornada)
            ->selectRaw('COALESCE(SUM('.self::VENTA_TOTAL_EXPR.'), 0) as total')
            ->first();

        return round((float) ($row->total ?? 0), 2);
    }

    private function jornadaId(int $empresaId, string $fechaJornada): int
    {
        return (int) (JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->value('id') ?? 0);
    }

    /**
     * @return Collection<int, object>
     */
    private function ventanasTurnoOperativo(int $empresaId, int $jornadaId): Collection
    {
        if ($jornadaId <= 0) {
            return collect();
        }

        return TurnoOperativoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('jornada_gastronomia_id', $jornadaId)
            ->whereNotNull('habilitacion_en')
            ->get([
                'turno_gastronomia_id',
                'identificador_pc',
                'habilitacion_en',
                'cierre_en',
                'estado',
            ]);
    }

    /**
     * @param  Collection<int, object>  $ventanas
     */
    private function resolverTurnoIdParaVenta(Collection $ventanas, string $identificadorPc, mixed $createdAt): int
    {
        if ($ventanas->isEmpty() || $createdAt === null) {
            return 0;
        }

        $ts = $createdAt instanceof \DateTimeInterface
            ? $createdAt
            : \Illuminate\Support\Carbon::parse((string) $createdAt);

        $pc = trim($identificadorPc);
        $candidatos = $ventanas->filter(function ($v) use ($pc, $ts) {
            if ($pc !== '' && trim((string) $v->identificador_pc) !== $pc) {
                return false;
            }
            if ($ts < $v->habilitacion_en) {
                return false;
            }
            if ($v->cierre_en !== null && $ts > $v->cierre_en) {
                return false;
            }

            return true;
        });

        if ($candidatos->isEmpty()) {
            return 0;
        }

        $elegido = $candidatos->sortByDesc(fn ($v) => $v->habilitacion_en)->first();

        return (int) ($elegido->turno_gastronomia_id ?? 0);
    }

    private function etiquetaTurno(int $turnoId): string
    {
        if ($turnoId <= 0) {
            return 'Sin turno';
        }

        $turno = TurnoGastronomia::query()->find($turnoId);
        if ($turno === null) {
            return 'Turno #'.$turnoId;
        }

        $nombre = trim((string) $turno->nombre);
        $horario = $turno->etiquetaHorario();

        return $nombre !== '' ? $nombre.' ('.$horario.')' : 'Turno #'.$turnoId;
    }
}
