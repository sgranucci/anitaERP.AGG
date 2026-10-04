<?php

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Artículos de OC con entrega programada en una fecha cuya recepción confirmada
 * todavía no cubre esa cantidad.
 *
 * La fecha sale de las entregas de la línea (ordencompra_articulo_entrega).
 * Si la línea no tiene ese cronograma, se usa ordencompra_articulo.fechaentrega.
 */
final class OrdencompraEntregaDiaSinRecepcionSupport
{
    /**
     * @return array{
     *   fecha: string,
     *   items: list<array<string, mixed>>,
     *   total: int,
     * }
     */
    public static function recopilar(?int $empresaId = null, ?string $fechaYmd = null, ?int $limite = null): array
    {
        $fecha = self::fecha($fechaYmd);
        $tope = max(1, min(500, (int) ($limite ?? config('compras.entrega_dia_sin_recepcion.limite', 80))));

        if (! Schema::hasTable('ordencompra') || ! Schema::hasTable('ordencompra_articulo')) {
            return self::vacio($fecha);
        }

        $filas = array_merge(
            self::filasEntregaProgramada($fecha, $empresaId),
            self::filasFechaLinea($fecha, $empresaId)
        );

        $items = [];
        foreach ($filas as $fila) {
            $item = self::mapear($fila);
            if ($item === null) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, static function (array $a, array $b): int {
            return [$a['proveedor'], $a['numero'], $a['sku']]
                <=> [$b['proveedor'], $b['numero'], $b['sku']];
        });

        return [
            'fecha' => Carbon::parse($fecha)->format('d/m/Y'),
            'items' => array_slice($items, 0, $tope),
            'total' => count($items),
        ];
    }

    public static function hayPendientes(array $resumen): bool
    {
        return ((int) ($resumen['total'] ?? 0)) > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function formatearLista(array $items, int $totalReal): string
    {
        if ($items === []) {
            return '(ninguno)';
        }

        $lineas = [];
        foreach ($items as $item) {
            $lineas[] = sprintf(
                'OC %s | %s | %s | %s %s | Hoy %s | Acumulado %s | Recibido %s | Falta %s',
                $item['numero'],
                $item['empresa'],
                $item['proveedor'],
                $item['sku'],
                $item['descripcion'],
                self::fmtCant((float) $item['cantidad_programada']),
                self::fmtCant((float) $item['cantidad_acumulada']),
                self::fmtCant((float) $item['cantidad_recibida']),
                self::fmtCant((float) $item['cantidad_faltante'])
            );
        }

        $texto = implode("\n", $lineas);
        $mostrados = count($items);
        if ($totalReal > $mostrados) {
            $texto .= "\n… y ".($totalReal - $mostrados).' más';
        }

        return $texto;
    }

    /**
     * @return list<object>
     */
    private static function filasEntregaProgramada(string $fecha, ?int $empresaId): array
    {
        if (! Schema::hasTable('ordencompra_articulo_entrega')) {
            return [];
        }

        $hoy = DB::table('ordencompra_articulo_entrega')
            ->where('fecha', $fecha)
            ->where('cantidad', '>', 0)
            ->groupBy('ordencompra_articulo_id')
            ->selectRaw('ordencompra_articulo_id, SUM(cantidad) as cantidad_hoy');

        $hasta = DB::table('ordencompra_articulo_entrega')
            ->where('fecha', '<=', $fecha)
            ->groupBy('ordencompra_articulo_id')
            ->selectRaw('ordencompra_articulo_id, SUM(cantidad) as cantidad_hasta_hoy');

        return self::consultaBase($empresaId)
            ->joinSub($hoy, 'ent_hoy', function ($join) {
                $join->on('ent_hoy.ordencompra_articulo_id', '=', 'oa.id');
            })
            ->joinSub($hasta, 'ent_hasta', function ($join) {
                $join->on('ent_hasta.ordencompra_articulo_id', '=', 'oa.id');
            })
            ->selectRaw('ent_hoy.cantidad_hoy as cantidad_programada')
            ->selectRaw('ent_hasta.cantidad_hasta_hoy as cantidad_hasta')
            ->selectRaw(self::exprRecibida().' as cantidad_recibida')
            ->selectRaw('? as fecha_aviso', [$fecha])
            ->get()
            ->all();
    }

    /**
     * Líneas sin cronograma de entregas: manda la fecha de la línea.
     *
     * @return list<object>
     */
    private static function filasFechaLinea(string $fecha, ?int $empresaId): array
    {
        $query = self::consultaBase($empresaId)
            ->where('oa.fechaentrega', $fecha);

        if (Schema::hasTable('ordencompra_articulo_entrega')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ordencompra_articulo_entrega as oae')
                    ->whereColumn('oae.ordencompra_articulo_id', 'oa.id');
            });
        }

        return $query
            ->selectRaw('oa.cantidad as cantidad_programada')
            ->selectRaw('oa.cantidad as cantidad_hasta')
            ->selectRaw(self::exprRecibida().' as cantidad_recibida')
            ->selectRaw('? as fecha_aviso', [$fecha])
            ->get()
            ->all();
    }

    private static function consultaBase(?int $empresaId)
    {
        $query = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as oc', 'oc.id', '=', 'oa.ordencompra_id')
            ->join('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'oa.articulo_id')
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', OrdencompraLineaEstados::CERRADA);
            })
            ->whereIn('oc.estadoordencompra', [
                OrdencompraEstados::PENDIENTE,
                OrdencompraEstados::APROBADA,
                OrdencompraEstados::CUMPLIDA,
            ])
            ->when($empresaId !== null && $empresaId > 0, function ($q) use ($empresaId) {
                $q->where('oc.empresa_id', $empresaId);
            })
            ->select([
                'oc.id',
                'oc.numeroordencompra',
                'oc.empresa_id',
                'e.nombre as empresa_nombre',
                'p.nombre as proveedor_nombre',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'oa.fechaentrega',
            ]);

        if (Schema::hasTable('recepcion_proveedor') && Schema::hasTable('recepcion_proveedor_articulo')) {
            $query->leftJoin(
                DB::raw(OrdencompraReporteEntregaSql::subqueryCantidadEntregada().' AS rec'),
                'rec.ordencompra_articulo_id',
                '=',
                'oa.id'
            );
        }

        return $query;
    }

    private static function exprRecibida(): string
    {
        if (! Schema::hasTable('recepcion_proveedor') || ! Schema::hasTable('recepcion_proveedor_articulo')) {
            return '0';
        }

        return SqlDialectSupport::coalesce('rec.cantidad_entregada', '0');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mapear(object $fila): ?array
    {
        $programada = max(0.0, (float) ($fila->cantidad_programada ?? 0));
        $hasta = max(0.0, (float) ($fila->cantidad_hasta ?? $programada));
        $recibida = max(0.0, (float) ($fila->cantidad_recibida ?? 0));
        $descubierto = $hasta - $recibida;
        if ($programada <= 0.000001 || $descubierto <= 0.000001) {
            return null;
        }

        $faltante = min($programada, $descubierto);

        return [
            'id' => (int) $fila->id,
            'numero' => (string) ((int) $fila->numeroordencompra),
            'empresa_id' => (int) $fila->empresa_id,
            'empresa' => (string) ($fila->empresa_nombre ?? ''),
            'proveedor' => (string) ($fila->proveedor_nombre ?? ''),
            'sku' => trim((string) ($fila->sku ?? '')) !== '' ? (string) $fila->sku : '—',
            'descripcion' => trim((string) ($fila->articulo_descripcion ?? '')) !== ''
                ? (string) $fila->articulo_descripcion
                : '—',
            'fecha_entrega' => self::fmtFecha($fila->fecha_aviso ?? $fila->fechaentrega ?? null),
            'cantidad_programada' => $programada,
            'cantidad_acumulada' => $hasta,
            'cantidad_recibida' => $recibida,
            'cantidad_faltante' => $faltante,
        ];
    }

    /**
     * @return array{fecha: string, items: list<array<string, mixed>>, total: int}
     */
    private static function vacio(string $fecha): array
    {
        return [
            'fecha' => Carbon::parse($fecha)->format('d/m/Y'),
            'items' => [],
            'total' => 0,
        ];
    }

    private static function fecha(?string $fechaYmd): string
    {
        $valor = trim((string) $fechaYmd);
        if ($valor === '') {
            return Carbon::today()->toDateString();
        }

        return Carbon::parse($valor)->toDateString();
    }

    private static function fmtFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function fmtCant(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',');
    }
}
