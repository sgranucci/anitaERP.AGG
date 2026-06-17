<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\PeriodoCierreContableService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PeriodoCierreContableController extends Controller
{
    public function __construct(
        private readonly PeriodoCierreContableService $cierreService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cierre-periodo-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);

        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $cierres = $this->cierreService->listarCierres($empresaId);
        $cierres->appends(['empresa_id' => $empresaId > 0 ? $empresaId : null]);

        $resumenVigente = $empresaId > 0 ? $this->cierreService->resumenCierreVigente($empresaId) : null;
        $ultimoCierre = $empresaId > 0 ? $this->cierreService->obtenerUltimoCierre($empresaId) : null;
        $puedeBorrarUltimo = can('borrar-ultimo-cierre-periodo-contable', false)
            || can('ejecutar-cierre-periodo-contable', false);

        return view('contable.cierre_periodo.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'cierres' => $cierres,
            'resumen_vigente' => $resumenVigente,
            'ultimo_cierre' => $ultimoCierre,
            'ultimos_cierre_ids' => $puedeBorrarUltimo ? $this->cierreService->mapUltimoCierreIdPorEmpresa() : [],
            'puede_ejecutar_cierre' => can('ejecutar-cierre-periodo-contable', false),
            'puede_borrar_ultimo_cierre' => $puedeBorrarUltimo,
        ]);
    }

    public function cerrar(Request $request)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_hasta' => 'required|date',
            'observacion' => 'nullable|string|max:2000',
        ]);

        try {
            $this->cierreService->registrarCierre(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_hasta'),
                $request->input('observacion'),
                (int) auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('cierre_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cierre_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
            ->with('mensaje', 'Cierre contable registrado correctamente.');
    }

    public function borrarUltimo(Request $request)
    {
        if (! can('borrar-ultimo-cierre-periodo-contable', false)
            && ! can('ejecutar-cierre-periodo-contable', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
        ]);

        try {
            $cierre = $this->cierreService->borrarUltimoCierre((int) $request->input('empresa_id'));
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('cierre_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
                ->with('error', $e->getMessage());
        }

        $fecha = optional($cierre->fecha_hasta)->format('d/m/Y');

        return redirect()
            ->route('cierre_periodo_contable', ['empresa_id' => $request->input('empresa_id')])
            ->with('mensaje', 'Se eliminó el último cierre contable (hasta '.$fecha.').');
    }
}
