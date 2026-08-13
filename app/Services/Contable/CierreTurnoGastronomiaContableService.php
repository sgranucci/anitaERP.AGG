<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Contable\CierreTurnoGastronomiaContableConciliacionSupport;
use App\Support\Contable\CierreTurnoGastronomiaContableListadoFiltros;
use App\Support\Contable\GastronomiaDiarioPuntoventaReporteSupport;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reporte Contable gastronomía: consulta cierres de turno y conciliación flash/mayor (sin grabar asientos).
 */
final class CierreTurnoGastronomiaContableService
{
    public function __construct(
        private readonly GastronomiaCierreTurnoReporteSupport $reporteSupport,
        private readonly CierreTurnoGastronomiaContableConciliacionSupport $conciliacionSupport,
        private readonly GastronomiaDiarioPuntoventaReporteSupport $diarioPuntoventaSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>|LengthAwarePaginator
     */
    public function listar(array $filtros, bool $paginar = false): Collection|LengthAwarePaginator
    {
        $filtros['todas_terminales'] = true;
        $filtros['identificador_pc'] = '';

        $filas = $this->reporteSupport->listadoConFiltros($filtros);
        $this->enriquecerRendicionesCaja($filas);

        if (! $paginar) {
            return $filas;
        }

        $page = max(1, (int) request()->input('page', 1));
        $perPage = 10;
        $slice = $filas->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $filas->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => CierreTurnoGastronomiaContableListadoFiltros::paraQueryString($filtros),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function conciliarFlash(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->conciliacionSupport->conciliar($empresaId, $fechaDesde, $fechaHasta);
    }

    /**
     * @return array<string, mixed>
     */
    public function reporteDiarioPuntoventa(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        ?int $puntoventaId = null,
    ): array {
        return $this->diarioPuntoventaSupport->generar($empresaId, $fechaDesde, $fechaHasta, $puntoventaId);
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public function resolverRangoConciliacionDefault(int $empresaId): array
    {
        $hasta = Carbon::today()->toDateString();
        $desde = Carbon::today()->startOfMonth()->toDateString();

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    private function resolverUltimaJornadaConCierre(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }

        $fecha = TurnoOperativoGastronomia::query()
            ->where('turno_operativo_gastronomia.empresa_id', $empresaId)
            ->where('turno_operativo_gastronomia.estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->join(
                'jornada_gastronomia',
                'jornada_gastronomia.id',
                '=',
                'turno_operativo_gastronomia.jornada_gastronomia_id',
            )
            ->orderByDesc('jornada_gastronomia.fecha_jornada')
            ->value('jornada_gastronomia.fecha_jornada');

        if ($fecha === null) {
            return null;
        }

        return Carbon::parse($fecha)->toDateString();
    }

    /**
     * @param  Collection<int, object>  $filas
     */
    private function enriquecerRendicionesCaja(Collection $filas): void
    {
        $turnoIds = $filas
            ->filter(static fn ($f) => ($f->tipo ?? '') === 'cierre')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($turnoIds === []) {
            return;
        }

        $porTurno = RendicionGastronomiaCaja::query()
            ->whereIn('turno_operativo_gastronomia_id', $turnoIds)
            ->orderByDesc('id')
            ->get(['id', 'codigo', 'turno_operativo_gastronomia_id'])
            ->unique('turno_operativo_gastronomia_id')
            ->keyBy('turno_operativo_gastronomia_id');

        foreach ($filas as $fila) {
            if (($fila->tipo ?? '') !== 'cierre') {
                continue;
            }
            $rend = $porTurno->get((int) $fila->id);
            $fila->rendicion_caja_id = $rend !== null ? (int) $rend->id : null;
            $fila->rendicion_caja_codigo = $rend !== null ? (string) $rend->codigo : null;
        }
    }
}
