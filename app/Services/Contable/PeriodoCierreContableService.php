<?php

namespace App\Services\Contable;

use App\Models\Contable\PeriodoCierreContable;
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

    public function listarCierres(int $empresaId, int $perPage = 15, ?string $alcance = null): LengthAwarePaginator
    {
        $query = PeriodoCierreContable::query()
            ->with(['empresa:id,nombre', 'usuario:id,nombre,usuario'])
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($alcance !== null && $alcance !== '') {
            $query->where('alcance', $alcance);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        return $query->paginate(max(5, min(50, $perPage)));
    }

    public function registrarCierre(
        int $empresaId,
        string $fechaHasta,
        ?string $observacion,
        int $usuarioId,
        string $alcance = PeriodoContableCierreSupport::ALCANCE_GENERAL
    ): PeriodoCierreContable {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }

        if (! PeriodoContableCierreSupport::alcanceEsValido($alcance)) {
            throw new InvalidArgumentException('Alcance de cierre inválido.');
        }

        $fecha = Carbon::parse($fechaHasta)->startOfDay();
        $cierreVigente = PeriodoContableCierreSupport::fechaCierreVigente(
            $empresaId,
            $alcance === PeriodoContableCierreSupport::ALCANCE_GENERAL
                ? PeriodoContableCierreSupport::ALCANCE_GENERAL
                : $alcance
        );

        // Para módulo: solo no retroceder respecto al cierre vigente de ese módulo (sin general).
        if ($alcance !== PeriodoContableCierreSupport::ALCANCE_GENERAL) {
            $vigenteModulo = PeriodoCierreContable::query()
                ->where('empresa_id', $empresaId)
                ->where('alcance', $alcance)
                ->max('fecha_hasta');
            $cierreVigente = $vigenteModulo !== null
                ? Carbon::parse($vigenteModulo)->startOfDay()
                : null;
        }

        if ($cierreVigente !== null && $fecha->lt($cierreVigente)) {
            throw new InvalidArgumentException(
                'La fecha de cierre no puede retroceder respecto al cierre vigente ('
                .$cierreVigente->format('d/m/Y').') para '
                .PeriodoContableCierreSupport::etiquetaAlcance($alcance).'.'
            );
        }

        if ($fecha->isFuture()) {
            throw new InvalidArgumentException('No puede cerrar un período con fecha futura.');
        }

        $cierre = PeriodoCierreContable::query()->create([
            'empresa_id' => $empresaId,
            'alcance' => $alcance,
            'fecha_hasta' => $fecha->format('Y-m-d'),
            'observacion' => trim((string) $observacion) ?: null,
            'usuario_id' => $usuarioId,
        ]);

        if ($alcance === PeriodoContableCierreSupport::ALCANCE_GENERAL) {
            $this->saldoCierreService->congelarParaCierre($cierre);
        }

        return $cierre;
    }

    public function obtenerUltimoCierre(
        int $empresaId,
        ?string $alcance = null
    ): ?PeriodoCierreContable {
        if ($empresaId <= 0) {
            return null;
        }

        $query = PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id');

        if ($alcance !== null && $alcance !== '') {
            $query->where('alcance', $alcance);
        }

        return $query->first();
    }

    /**
     * @return array<string, int> clave "empresaId|alcance" => periodo_cierre.id del último cierre
     */
    public function mapUltimoCierreIdPorEmpresaAlcance(): array
    {
        $query = PeriodoCierreContable::query()
            ->select(['id', 'empresa_id', 'alcance', 'fecha_hasta'])
            ->orderByDesc('fecha_hasta')
            ->orderByDesc('id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        $map = [];
        foreach ($query->get() as $cierre) {
            $key = (int) $cierre->empresa_id.'|'.(string) ($cierre->alcance ?? PeriodoContableCierreSupport::ALCANCE_GENERAL);
            if (! isset($map[$key])) {
                $map[$key] = (int) $cierre->id;
            }
        }

        return $map;
    }

    /** @return array<int, int> empresa_id => periodo_cierre.id del último cierre general */
    public function mapUltimoCierreIdPorEmpresa(): array
    {
        $query = PeriodoCierreContable::query()
            ->select(['id', 'empresa_id', 'fecha_hasta'])
            ->where('alcance', PeriodoContableCierreSupport::ALCANCE_GENERAL)
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

    public function borrarUltimoCierre(
        int $empresaId,
        ?string $alcance = null
    ): PeriodoCierreContable {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }

        $cierre = $this->obtenerUltimoCierre($empresaId, $alcance);

        if ($cierre === null) {
            throw new InvalidArgumentException('No hay cierres registrados para esta empresa'
                .($alcance ? ' y módulo.' : '.'));
        }

        DB::transaction(function () use ($cierre) {
            $cierre->delete();
        });

        Log::info('contable_periodo_cierre: último cierre eliminado', [
            'periodo_cierre_id' => $cierre->id,
            'empresa_id' => $cierre->empresa_id,
            'alcance' => $cierre->alcance,
            'fecha_hasta' => $cierre->fecha_hasta?->format('Y-m-d'),
            'usuario_eliminacion_id' => auth()->id(),
        ]);

        return $cierre;
    }

    /**
     * @return array{empresa_id: int, alcance: string|null, fecha_hasta: string|null, observacion: string|null}|null
     */
    public function resumenCierreVigente(int $empresaId, ?string $alcance = null): ?array
    {
        $fecha = PeriodoContableCierreSupport::fechaCierreVigente($empresaId, $alcance);
        if ($fecha === null) {
            return null;
        }

        $query = PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_hasta', $fecha->format('Y-m-d'))
            ->orderByDesc('id');

        if ($alcance !== null && $alcance !== '' && $alcance !== PeriodoContableCierreSupport::ALCANCE_GENERAL) {
            $query->whereIn('alcance', PeriodoContableCierreSupport::alcancesQueRestringen($alcance));
        } elseif ($alcance === PeriodoContableCierreSupport::ALCANCE_GENERAL) {
            $query->where('alcance', PeriodoContableCierreSupport::ALCANCE_GENERAL);
        }

        $ultimo = $query->first();

        return [
            'empresa_id' => $empresaId,
            'alcance' => $ultimo?->alcance,
            'fecha_hasta' => $fecha->format('Y-m-d'),
            'observacion' => $ultimo?->observacion,
        ];
    }
}
