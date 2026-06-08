<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaInformeGerenteService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GastronomiaInformeGerenteController extends Controller
{
    public function __construct(
        private readonly GastronomiaInformeGerenteService $informeService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
        private readonly GastronomiaJornadaService $jornadaService,
    ) {}

    public function index(Request $request)
    {
        can('ver-informe-gerente-gastronomia');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $this->assertAccesoEmpresa($empresaId);

        $fechaJornada = trim((string) $request->input('fecha_jornada', ''));
        if ($fechaJornada === '' && $empresaId > 0) {
            $jornadaAbierta = $this->jornadaService->estadoParaEmpresa($empresaId);
            if (! empty($jornadaAbierta['fecha_jornada'])) {
                $fechaJornada = (string) $jornadaAbierta['fecha_jornada'];
            }
        }
        if ($fechaJornada === '') {
            $fechaJornada = Carbon::today()->format('Y-m-d');
        } else {
            $fechaJornada = Carbon::parse($fechaJornada)->format('Y-m-d');
        }

        $informe = null;
        if ($empresaId > 0) {
            $informe = $this->informeService->generar($empresaId, $fechaJornada);
        }

        $jornadas = $empresaId > 0
            ? $this->jornadaRepository->historialPorEmpresa($empresaId, 40)
            : collect();

        $jornadaRegistro = $empresaId > 0
            ? JornadaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_jornada', $fechaJornada)
                ->orderByDesc('id')
                ->first()
            : null;

        return view('ventas.gastronomia.informe_gerente.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'informe' => $informe,
            'jornadas' => $jornadas,
            'jornada_registro' => $jornadaRegistro,
        ]);
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }
}
