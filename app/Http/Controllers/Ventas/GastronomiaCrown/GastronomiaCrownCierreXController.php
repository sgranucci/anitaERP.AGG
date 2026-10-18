<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaCrown\GastronomiaCrownCierreXService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class GastronomiaCrownCierreXController extends Controller
{
    public function __construct(
        private readonly GastronomiaCrownCierreXService $cierreXService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertCrown();
        can('listar-cierre-x-gastronomia-crown');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $this->assertEmpresaPermitida($empresaId);

        $salas = $this->salasParaFiltro();
        $salaId = (int) $request->input('sala_id', $salas->count() === 1 ? ($salas->first()->id ?? 0) : 0);
        $this->assertSalaPermitida($salaId);
        $salaFiltro = $salaId > 0 ? $salaId : null;

        $jornadas = $empresaId > 0 && $salaFiltro !== null
            ? $this->cierreXService->jornadasParaElegir($empresaId, $salaFiltro)
            : collect();

        $jornadaId = (int) $request->input('jornada_gastronomia_id', 0);
        $jornada = $jornadaId > 0
            ? $jornadas->firstWhere('id', $jornadaId)
            : $jornadas->first();

        $totales = $jornada !== null
            ? $this->cierreXService->totalesPorMozo($empresaId, $salaFiltro, $jornada)
            : [];

        return view('ventas.gastronomia_crown.cierre_x.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'salas' => $salas,
            'sala_id' => $salaId,
            'jornadas' => $jornadas,
            'jornada' => $jornada,
            'totales' => $totales,
            'total_general' => array_sum(array_column($totales, 'total_x')),
            'puede_crear' => can('crear-cierre-x-gastronomia-crown', false),
        ]);
    }

    public function crearRendicion(Request $request)
    {
        $this->assertCrown();
        can('crear-cierre-x-gastronomia-crown');

        $jornadaId = (int) $request->input('jornada_gastronomia_id', 0);
        $mozoId = (int) $request->input('mozo_gastronomia_id', 0);

        $volver = [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'sala_id' => (int) $request->input('sala_id', 0),
            'jornada_gastronomia_id' => $jornadaId,
        ];

        if ($jornadaId <= 0 || $mozoId <= 0) {
            return redirect()
                ->route('gastronomia_crown_cierre_x', $volver)
                ->with('errores', ['Debe indicar la jornada y el mozo a rendir.']);
        }

        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $this->assertEmpresaPermitida((int) $jornada->empresa_id);
        $this->assertSalaPermitida((int) ($jornada->sala_id ?? 0));

        try {
            $rendicion = $this->cierreXService->crearRendicion(
                $jornada,
                $mozoId,
                $jornada->sala_id !== null ? (int) $jornada->sala_id : null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('gastronomia_crown_cierre_x', $volver)
                ->with('errores', [$e->getMessage()]);
        }

        return redirect()
            ->route('editar_rendiciongastronomia', ['id' => $rendicion->id])
            ->with('mensaje', 'Cierre X creado. Complete los medios de pago para rendir en tesorería.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Sala>
     */
    private function salasParaFiltro()
    {
        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    private function assertSalaPermitida(int $salaId): void
    {
        if ($salaId > 0 && ! UsuarioSalaAsignadaSupport::salaIdPermitida($salaId)) {
            abort(403, 'Sala no permitida para su usuario.');
        }
    }
}
