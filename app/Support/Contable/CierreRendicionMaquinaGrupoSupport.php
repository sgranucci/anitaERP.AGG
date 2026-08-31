<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use App\Models\Contable\Asiento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Agrupa rendiciones máquinas: empresa + fecha (un cierre diario, turno C).
 */
final class CierreRendicionMaquinaGrupoSupport
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_PARCIAL = 'parcial';

    public const GRUPOS_POR_PAGINA = 10;

    public const TURNO_CIERRE = 'C';

    public static function fechaDiaDesdeRendicion(RendicionMaquina $rendicion): string
    {
        return $rendicion->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    public static function claveGrupo(RendicionMaquina $rendicion): string
    {
        return (int) ($rendicion->empresa_id ?? 0).'|'.self::fechaDiaDesdeRendicion($rendicion);
    }

    public static function tieneCierreContable(RendicionMaquina $rendicion): bool
    {
        if (method_exists($rendicion, 'tieneCierreContable')) {
            return $rendicion->tieneCierreContable();
        }

        return (int) ($rendicion->asiento_id ?? 0) > 0 && $rendicion->cierre_contable_en !== null;
    }

    public static function puedeCerrarContablemente(RendicionMaquina $rendicion): bool
    {
        if (method_exists($rendicion, 'puedeCerrarContablemente')) {
            return $rendicion->puedeCerrarContablemente();
        }

        return (string) ($rendicion->turno ?? '') === self::TURNO_CIERRE
            && (string) ($rendicion->estado ?? '') === RendicionMaquina::ESTADO_CONFIRMADA
            && ! self::tieneCierreContable($rendicion);
    }

    /**
     * @param  Collection<int, RendicionMaquina>  $rendiciones
     * @return list<array<string, mixed>>
     */
    public static function agrupar(Collection $rendiciones): array
    {
        /** @var array<string, list<RendicionMaquina>> $buckets */
        $buckets = [];

        foreach ($rendiciones as $rendicion) {
            if ((string) ($rendicion->turno ?? '') !== self::TURNO_CIERRE) {
                continue;
            }
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
     * @param  Builder<RendicionMaquina>  $query
     */
    public static function aplicarFiltroGrupo(Builder $query, int $empresaId, string $fechaDia): void
    {
        if ($empresaId <= 0 || trim($fechaDia) === '') {
            throw new InvalidArgumentException('Grupo inválido (empresa o fecha).');
        }

        $fecha = Carbon::parse($fechaDia)->toDateString();
        $query->where('rendicion_maquina.empresa_id', $empresaId)
            ->whereDate('rendicion_maquina.fecha', $fecha)
            ->where('rendicion_maquina.turno', self::TURNO_CIERRE);
    }

    public static function fechaAsientoDesdeGrupo(string $fechaDia): string
    {
        return Carbon::parse($fechaDia)->toDateString();
    }

    /**
     * @param  list<RendicionMaquina>  $items
     * @return array<string, mixed>
     */
    private static function armarGrupo(string $clave, Collection $items): array
    {
        $items = $items->sortBy([
            ['fecha', 'asc'],
            ['id', 'asc'],
        ])->values();

        /** @var RendicionMaquina $primera */
        $primera = $items->first();
        $empresaId = (int) $primera->empresa_id;
        $pvFsl = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);

        $totalResultado = 0.0;
        $pendientes = 0;
        $cerradas = 0;
        /** @var list<int> $asientoIds */
        $asientoIds = [];

        foreach ($items as $row) {
            $calc = is_array($row->calc_json['variables'] ?? null) ? $row->calc_json['variables'] : [];
            $resultado = round(
                (float) ($calc['calc.resultado_rodillo'] ?? $calc['resultado_rodillo'] ?? 0)
                + (float) ($calc['calc.resultado_ruleta'] ?? $calc['resultado_ruleta'] ?? 0),
                2,
            );
            if (abs($resultado) <= 0.0001) {
                $resultado = round((float) ($row->resultado_turno ?? 0), 2);
            }
            $totalResultado = round($totalResultado + $resultado, 2);

            if (self::tieneCierreContable($row)) {
                $cerradas++;
                foreach (self::asientoIdsDeRendicion($row) as $asientoIdFila) {
                    $asientoIds[] = $asientoIdFila;
                }
            } else {
                $pendientes++;
            }
        }

        $asientoIds = array_values(array_unique($asientoIds));
        $estado = self::resolverEstadoGrupo($pendientes, $cerradas, count($items));
        $asientosVista = self::asientosParaVista($asientoIds);
        $asientoId = $asientoIds[0] ?? null;
        $asientoNumero = $asientosVista[0]['numero'] ?? null;
        $asientoFecha = $asientosVista[0]['fecha'] ?? null;

        $fechaDia = self::fechaDiaDesdeRendicion($primera);
        $facturaLabel = null;
        $conFactura = $items->first(function (RendicionMaquina $r) {
            $nro = (int) ($r->factura_nro ?? $r->getAttribute('factura_nro') ?? 0);

            return $nro > 0;
        });
        if ($conFactura !== null) {
            $letra = (string) ($conFactura->factura_letra ?? $conFactura->getAttribute('factura_letra') ?? 'B');
            $sucursal = (int) ($conFactura->factura_sucursal ?? $conFactura->getAttribute('factura_sucursal') ?? $pvFsl);
            $nro = (int) ($conFactura->factura_nro ?? $conFactura->getAttribute('factura_nro') ?? 0);
            $facturaLabel = trim(
                $letra
                .str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) $nro, 8, '0', STR_PAD_LEFT),
            );
        }

        return [
            'clave' => $clave,
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($primera->empresa?->nombre ?? ''),
            'fecha_dia' => $fechaDia,
            'fecha_dia_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'puntoventa_fsl' => $pvFsl,
            'cantidad_rendiciones' => $items->count(),
            'cantidad_pendiente' => $pendientes,
            'cantidad_cerrada' => $cerradas,
            'total_resultado' => $totalResultado,
            'estado_grupo' => $estado,
            'asiento_id' => $asientoId,
            'asiento_numero' => $asientoNumero,
            'asiento_fecha' => $asientoFecha,
            'asientos' => $asientosVista,
            'asiento_ids_distintos' => count($asientoIds),
            'factura_label' => $facturaLabel,
            'puede_cerrar' => $pendientes > 0,
            'puede_anular' => $asientoIds !== [] && $pendientes === 0,
            'fecha_asiento' => self::fechaAsientoDesdeGrupo($fechaDia),
            'rendiciones' => $items,
            'rendicion_ids_pendientes' => $items
                ->filter(fn (RendicionMaquina $r) => self::puedeCerrarContablemente($r))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private static function asientoIdsDeRendicion(RendicionMaquina $row): array
    {
        $ids = [];
        $json = $row->asientos_cierre_ids_json;
        if (is_array($json)) {
            foreach ($json as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $principal = (int) ($row->asiento_id ?? 0);
        if ($principal > 0) {
            $ids[] = $principal;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $asientoIds
     * @return list<array{id: int, numero: string, fecha: mixed}>
     */
    private static function asientosParaVista(array $asientoIds): array
    {
        if ($asientoIds === []) {
            return [];
        }

        $porId = Asiento::query()
            ->whereIn('id', $asientoIds)
            ->get(['id', 'numeroasiento', 'fecha'])
            ->keyBy('id');

        $vista = [];
        foreach ($asientoIds as $id) {
            $asiento = $porId->get($id);
            $vista[] = [
                'id' => $id,
                'numero' => (string) ($asiento?->numeroasiento ?? ('#'.$id)),
                'fecha' => $asiento?->fecha,
            ];
        }

        return $vista;
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
