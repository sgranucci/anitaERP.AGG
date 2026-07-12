<?php

namespace App\Support\Contable;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Agrupa rendiciones bingo: empresa + fecha jornada (un cierre diario).
 */
final class CierreRendicionBingoGrupoSupport
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_PARCIAL = 'parcial';

    public const GRUPOS_POR_PAGINA = 10;

    public static function fechaDiaDesdeRendicion(RendicionBingoCaja $rendicion): string
    {
        if ($rendicion->fecha_jornada !== null) {
            return Carbon::parse($rendicion->fecha_jornada)->toDateString();
        }

        return $rendicion->fecharendicion?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    public static function claveGrupo(RendicionBingoCaja $rendicion): string
    {
        return (int) ($rendicion->empresa_id ?? 0).'|'.self::fechaDiaDesdeRendicion($rendicion);
    }

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     * @return list<array<string, mixed>>
     */
    public static function agrupar(Collection $rendiciones): array
    {
        /** @var array<string, list<RendicionBingoCaja>> $buckets */
        $buckets = [];

        foreach ($rendiciones as $rendicion) {
            $buckets[self::claveGrupo($rendicion)][] = $rendicion;
        }

        $grupos = [];
        foreach ($buckets as $clave => $items) {
            $grupos[] = self::armarGrupo($clave, new Collection($items));
        }

        usort($grupos, static function (array $a, array $b): int {
            $cmpFecha = strcmp((string) ($b['fecha_dia'] ?? ''), (string) ($a['fecha_dia'] ?? ''));
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }

            return strcmp((string) ($a['empresa_nombre'] ?? ''), (string) ($b['empresa_nombre'] ?? ''));
        });

        return $grupos;
    }

    /**
     * @param  list<array<string, mixed>>  $grupos
     */
    public static function paginarGrupos(array $grupos, int $perPage, ?string $path = null): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = count($grupos);
        $items = array_slice($grupos, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $path ?? request()->url()],
        );
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    public static function aplicarFiltroGrupo(Builder $query, int $empresaId, string $fechaDia): void
    {
        if ($empresaId <= 0 || trim($fechaDia) === '') {
            throw new InvalidArgumentException('Grupo inválido (empresa o fecha).');
        }

        $fecha = Carbon::parse($fechaDia)->toDateString();
        $query->where('rendicion_bingo_caja.empresa_id', $empresaId)
            ->whereDate('rendicion_bingo_caja.fecha_jornada', $fecha);
    }

    public static function fechaAsientoDesdeGrupo(string $fechaDia): string
    {
        return Carbon::parse($fechaDia)->toDateString();
    }

    /**
     * @param  list<RendicionBingoCaja>  $items
     * @return array<string, mixed>
     */
    private static function armarGrupo(string $clave, Collection $items): array
    {
        $items = $items->sortBy([
            ['fecharendicion', 'asc'],
            ['id', 'asc'],
        ])->values();

        /** @var RendicionBingoCaja $primera */
        $primera = $items->first();
        $empresaId = (int) $primera->empresa_id;
        $pvFbi = CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId);

        $totalRecaudacion = 0.0;
        $pendientes = 0;
        $cerradas = 0;
        /** @var list<int> $asientoIds */
        $asientoIds = [];

        foreach ($items as $row) {
            $totalRecaudacion = round($totalRecaudacion + (float) ($row->total_cartones ?? 0), 2);
            if ($row->tieneCierreContable()) {
                $cerradas++;
                $asientoId = (int) ($row->asiento_id ?? 0);
                if ($asientoId > 0) {
                    $asientoIds[] = $asientoId;
                }
            } else {
                $pendientes++;
            }
        }

        $asientoIds = array_values(array_unique($asientoIds));
        $estado = self::resolverEstadoGrupo($pendientes, $cerradas, count($items));
        $asientoId = count($asientoIds) === 1 ? $asientoIds[0] : null;
        $asientoNumero = null;
        $asientoFecha = null;
        if ($asientoId !== null) {
            $conAsiento = $items->first(fn (RendicionBingoCaja $r) => (int) ($r->asiento_id ?? 0) === $asientoId);
            $asientoNumero = $conAsiento?->asiento?->numeroasiento;
            $asientoFecha = $conAsiento?->asiento?->fecha;
        }

        $fechaDia = self::fechaDiaDesdeRendicion($primera);
        $facturaLabel = null;
        $conFactura = $items->first(fn (RendicionBingoCaja $r) => (int) ($r->factura_nro ?? 0) > 0);
        if ($conFactura !== null) {
            $facturaLabel = trim(
                ($conFactura->factura_letra ?? 'B')
                .str_pad((string) ($conFactura->factura_sucursal ?? $pvFbi), 4, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) ($conFactura->factura_nro ?? 0), 8, '0', STR_PAD_LEFT),
            );
        }

        return [
            'clave' => $clave,
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($primera->empresa?->nombre ?? ''),
            'fecha_dia' => $fechaDia,
            'fecha_dia_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'puntoventa_fbi' => $pvFbi,
            'cantidad_rendiciones' => $items->count(),
            'cantidad_pendiente' => $pendientes,
            'cantidad_cerrada' => $cerradas,
            'total_recaudacion' => $totalRecaudacion,
            'estado_grupo' => $estado,
            'asiento_id' => $asientoId,
            'asiento_numero' => $asientoNumero,
            'asiento_fecha' => $asientoFecha,
            'asiento_ids_distintos' => count($asientoIds),
            'factura_label' => $facturaLabel,
            'puede_cerrar' => $pendientes > 0,
            'puede_anular' => $asientoId !== null && $pendientes === 0,
            'fecha_asiento' => self::fechaAsientoDesdeGrupo($fechaDia),
            'rendiciones' => $items,
            'rendicion_ids_pendientes' => $items
                ->filter(fn (RendicionBingoCaja $r) => $r->puedeCerrarContablemente())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    private static function resolverEstadoGrupo(int $pendientes, int $cerradas, int $total): string
    {
        if ($pendientes === 0) {
            return self::ESTADO_CERRADA;
        }
        if ($cerradas === 0) {
            return self::ESTADO_PENDIENTE;
        }

        return self::ESTADO_PARCIAL;
    }
}
