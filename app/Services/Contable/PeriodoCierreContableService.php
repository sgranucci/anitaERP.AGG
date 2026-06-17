<?php

namespace App\Services\Contable;

use App\Models\Contable\PeriodoCierreContable;
use App\Services\Contable\CuentacontableSaldoCierreService;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PeriodoCierreContableService
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly CuentacontableSaldoCierreService $saldoCierreService,
    ) {
    }

    public function listarCierres(int $empresaId, int $perPage = 15): LengthAwarePaginator
    {
        $query = PeriodoCierreContable::query()
            ->with(['empresa:id,nombre', 'usuario:id,nombre,usuario'])
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        return $query->paginate(max(5, min(50, $perPage)));
    }

    public function registrarCierre(int $empresaId, string $fechaHasta, ?string $observacion, int $usuarioId): PeriodoCierreContable
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }

        $fecha = Carbon::parse($fechaHasta)->startOfDay();
        $cierreVigente = PeriodoContableCierreSupport::fechaCierreVigente($empresaId);

        if ($cierreVigente !== null && $fecha->lt($cierreVigente)) {
            throw new InvalidArgumentException(
                'La fecha de cierre no puede retroceder respecto al cierre vigente ('
                .$cierreVigente->format('d/m/Y').').'
            );
        }

        if ($fecha->isFuture()) {
            throw new InvalidArgumentException('No puede cerrar un período con fecha futura.');
        }

        $cierre = PeriodoCierreContable::query()->create([
            'empresa_id' => $empresaId,
            'fecha_hasta' => $fecha->format('Y-m-d'),
            'observacion' => trim((string) $observacion) ?: null,
            'usuario_id' => $usuarioId,
        ]);

        $this->saldoCierreService->congelarParaCierre($cierre);

        return $cierre;
    }

    public function obtenerUltimoCierre(int $empresaId): ?PeriodoCierreContable
    {
        if ($empresaId <= 0) {
            return null;
        }

        return PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<int, int> empresa_id => periodo_cierre.id del último cierre */
    public function mapUltimoCierreIdPorEmpresa(): array
    {
        $query = PeriodoCierreContable::query()
            ->select(['id', 'empresa_id', 'fecha_hasta'])
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        $map = [];
        foreach ($query->get() as $cierre) {
            $empresaId = (int) $cierre->empresa_id;
            if (! isset($map[$empresaId])) {
                $map[$empresaId] = (int) $cierre->id;
            }
        }

        return $map;
    }

    public function borrarUltimoCierre(int $empresaId): PeriodoCierreContable
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }

        $cierre = $this->obtenerUltimoCierre($empresaId);

        if ($cierre === null) {
            throw new InvalidArgumentException('No hay cierres registrados para esta empresa.');
        }

        DB::transaction(function () use ($cierre) {
            $cierre->delete();
        });

        Log::info('contable_periodo_cierre: último cierre eliminado', [
            'periodo_cierre_id' => $cierre->id,
            'empresa_id' => $cierre->empresa_id,
            'fecha_hasta' => $cierre->fecha_hasta?->format('Y-m-d'),
            'usuario_eliminacion_id' => auth()->id(),
        ]);

        return $cierre;
    }

    /** @return array{empresa_id: int, fecha_hasta: string|null, observacion: string|null}|null */
    public function resumenCierreVigente(int $empresaId): ?array
    {
        $fecha = PeriodoContableCierreSupport::fechaCierreVigente($empresaId);
        if ($fecha === null) {
            return null;
        }

        $ultimo = PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_hasta', $fecha->format('Y-m-d'))
            ->orderByDesc('id')
            ->first();

        return [
            'empresa_id' => $empresaId,
            'fecha_hasta' => $fecha->format('Y-m-d'),
            'observacion' => $ultimo?->observacion,
        ];
    }
}
