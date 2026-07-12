<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquinavendingCaja;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Agrupa rendiciones vending para cierre contable: fecha jornada (día) + punto de venta CAE.
 */
final class CierreRendicionMaquinavendingGrupoSupport
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_LEGACY = 'legacy';

    public const GRUPOS_POR_PAGINA = 10;

    public static function fechaDiaDesdeRendicion(RendicionMaquinavendingCaja $rendicion): string
    {
        $rendicion->loadMissing(['maquinavendingRendicion']);

        $fechaJornada = $rendicion->maquinavendingRendicion?->fecha_jornada?->format('Y-m-d');
        if ($fechaJornada !== null && $fechaJornada !== '') {
            return $fechaJornada;
        }

        return $rendicion->fecharendicion?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    public static function claveGrupo(RendicionMaquinavendingCaja $rendicion): string
    {
        $empresaId = (int) ($rendicion->empresa_id ?? 0);
        $pvId = (int) ($rendicion->puntoventa_cae_id ?? 0);
        $fechaDia = self::fechaDiaDesdeRendicion($rendicion);

        return $empresaId.'|'.$fechaDia.'|'.$pvId;
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return list<array<string, mixed>>
     */
    public static function agrupar(Collection $rendiciones): array
    {
        /** @var array<string, list<RendicionMaquinavendingCaja>> $buckets */
        $buckets = [];

        foreach ($rendiciones as $rendicion) {
            $clave = self::claveGrupo($rendicion);
            $buckets[$clave][] = $rendicion;
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

            return strcmp((string) ($a['puntoventa_label'] ?? ''), (string) ($b['puntoventa_label'] ?? ''));
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
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    public static function aplicarFiltroGrupo(
        Builder $query,
        int $empresaId,
        string $fechaDia,
        int $puntoventaCaeId,
    ): void {
        if ($empresaId <= 0 || $puntoventaCaeId <= 0 || trim($fechaDia) === '') {
            throw new InvalidArgumentException('Grupo inválido (empresa, fecha o punto de venta).');
        }

        $fecha = Carbon::parse($fechaDia)->toDateString();

        $query->where('rendicion_maquinavending_caja.empresa_id', $empresaId)
            ->where('rendicion_maquinavending_caja.puntoventa_cae_id', $puntoventaCaeId)
            ->where(function ($w) use ($fecha) {
                $w->whereHas('maquinavendingRendicion', function ($mr) use ($fecha) {
                    $mr->whereDate('fecha_jornada', $fecha);
                })->orWhere(function ($q) use ($fecha) {
                    $q->whereDoesntHave('maquinavendingRendicion')
                        ->whereDate('fecharendicion', $fecha);
                });
            });
    }

    public static function fechaAsientoDesdeGrupo(string $fechaDia): string
    {
        return Carbon::parse($fechaDia)->toDateString();
    }

    /**
     * @param  list<RendicionMaquinavendingCaja>  $items
     * @return array<string, mixed>
     */
    private static function armarGrupo(string $clave, Collection $items): array
    {
        $items = $items->sortBy([
            ['fecharendicion', 'asc'],
            ['id', 'asc'],
        ])->values();

        /** @var RendicionMaquinavendingCaja $primera */
        $primera = $items->first();
        $pv = $primera->puntoventaCae;
        $etiquetaPv = $pv
            ? trim(($pv->codigo ?? '').' — '.($pv->nombre ?? ''))
            : '—';

        $totalCobrado = 0.0;
        $totalVentas = 0.0;
        $totalInvitaciones = 0.0;
        $pendientes = 0;
        $cerradas = 0;
        $legacy = 0;
        /** @var list<int> $asientoIds */
        $asientoIds = [];

        foreach ($items as $row) {
            $totalCobrado = round($totalCobrado + (float) ($row->totalcobrado ?? 0), 2);
            $totalVentas = round($totalVentas + (float) ($row->totalfactura ?? 0), 2);
            $totalInvitaciones = round($totalInvitaciones + (float) ($row->totalinvitacion ?? 0), 2);
            if ($row->esCierreContableLegacy()) {
                $legacy++;
                $cerradas++;
            } elseif ($row->tieneCierreContable()) {
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
        $estado = self::resolverEstadoGrupo($pendientes, $cerradas, $legacy, count($items));

        $asientoId = count($asientoIds) === 1 ? $asientoIds[0] : null;
        $asientoNumero = null;
        $asientoFecha = null;
        if ($asientoId !== null) {
            $conAsiento = $items->first(fn (RendicionMaquinavendingCaja $r) => (int) ($r->asiento_id ?? 0) === $asientoId);
            $asientoNumero = $conAsiento?->asiento?->numeroasiento;
            $asientoFecha = $conAsiento?->asiento?->fecha;
        }

        $fechaDia = self::fechaDiaDesdeRendicion($primera);

        return [
            'clave' => $clave,
            'empresa_id' => (int) $primera->empresa_id,
            'empresa_nombre' => (string) ($primera->empresa?->nombre ?? ''),
            'fecha_dia' => $fechaDia,
            'fecha_dia_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'puntoventa_cae_id' => (int) ($primera->puntoventa_cae_id ?? 0),
            'puntoventa_label' => $etiquetaPv,
            'cantidad_rendiciones' => $items->count(),
            'cantidad_pendiente' => $pendientes,
            'cantidad_cerrada' => $cerradas,
            'cantidad_legacy' => $legacy,
            'total_cobrado' => $totalCobrado,
            'total_ventas' => $totalVentas,
            'total_invitaciones' => $totalInvitaciones,
            'estado_grupo' => $estado,
            'asiento_id' => $asientoId,
            'asiento_numero' => $asientoNumero,
            'asiento_fecha' => $asientoFecha,
            'asiento_ids_distintos' => count($asientoIds),
            'puede_cerrar' => $pendientes > 0,
            'puede_anular' => $asientoId !== null && $pendientes === 0 && $legacy === 0,
            'fecha_asiento' => self::fechaAsientoDesdeGrupo($fechaDia),
            'rendiciones' => $items,
            'rendicion_ids_pendientes' => $items
                ->filter(fn (RendicionMaquinavendingCaja $r) => $r->puedeCerrarContablemente())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    private static function resolverEstadoGrupo(int $pendientes, int $cerradas, int $legacy, int $total): string
    {
        if ($legacy > 0 && $legacy === $total) {
            return self::ESTADO_LEGACY;
        }
        if ($pendientes === 0) {
            return self::ESTADO_CERRADA;
        }
        if ($cerradas === 0) {
            return self::ESTADO_PENDIENTE;
        }

        return self::ESTADO_PARCIAL;
    }
}
