<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\PeriodoCierreContableService;
use App\Services\Contable\PeriodoCierreProgramadoService;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PeriodoCierreContableController extends Controller
{
    public function __construct(
        private readonly PeriodoCierreContableService $cierreService,
        private readonly PeriodoCierreProgramadoService $programadoService,
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

        $mes = (int) $request->input('mes', (int) date('n'));
        $anio = (int) $request->input('anio', (int) date('Y'));
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        $anioMes = PeriodoCierreProgramadoService::anioMesDesdePartes($anio, $mes);

        $cierres = $this->cierreService->listarCierres($empresaId);
        $cierres->appends([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'mes' => $mes,
            'anio' => $anio,
        ]);

        $resumenVigente = $empresaId > 0
            ? $this->cierreService->resumenCierreVigente($empresaId, PeriodoContableCierreSupport::ALCANCE_GENERAL)
            : null;
        $ultimoCierreGeneral = $empresaId > 0
            ? $this->cierreService->obtenerUltimoCierre($empresaId, PeriodoContableCierreSupport::ALCANCE_GENERAL)
            : null;
        $puedeBorrarUltimo = can('borrar-ultimo-cierre-periodo-contable', false)
            || can('ejecutar-cierre-periodo-contable', false);

        $agendaFilas = $empresaId > 0
            ? $this->programadoService->filasAgenda($empresaId, $anioMes)
            : collect();

        $fechaHastaDefault = PeriodoCierreProgramadoService::fechaHastaDefaultParaAgenda($anioMes)
            ->format('Y-m-d');

        return view('contable.cierre_periodo.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'mes' => $mes,
            'anio' => $anio,
            'anio_mes' => $anioMes,
            'agenda_grupos' => $agendaFilas,
            'fecha_hasta_default' => $fechaHastaDefault,
            'alcances' => PeriodoContableCierreSupport::alcancesDisponibles(),
            'jerarquia_alcances' => PeriodoContableCierreSupport::jerarquiaAgenda(),
            'cierres' => $cierres,
            'resumen_vigente' => $resumenVigente,
            'ultimo_cierre' => $ultimoCierreGeneral,
            'ultimos_cierre_ids' => $puedeBorrarUltimo
                ? $this->cierreService->mapUltimoCierreIdPorEmpresaAlcance()
                : [],
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
            'alcance' => 'required|string|max:32',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        $alcance = (string) $request->input('alcance');
        if (! PeriodoContableCierreSupport::alcanceEsValido($alcance)) {
            return $this->redirectIndex($request)
                ->with('error', 'Alcance de cierre inválido.');
        }

        try {
            $this->cierreService->registrarCierre(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_hasta'),
                $request->input('observacion'),
                (int) auth()->id(),
                $alcance
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        $etiqueta = PeriodoContableCierreSupport::etiquetaAlcance($alcance);

        return $this->redirectIndex($request)
            ->with('mensaje', 'Cierre contable registrado correctamente ('.$etiqueta.').');
    }

    public function programar(Request $request)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'anio_mes' => 'required|integer',
            'alcance' => 'required|string|max:32',
            'fecha_ejecucion' => 'required|date',
            'hora_ejecucion' => 'nullable|string|max:5',
            'fecha_hasta' => 'required|date',
            'observacion' => 'nullable|string|max:2000',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        try {
            $this->programadoService->guardarFila([
                'empresa_id' => (int) $request->input('empresa_id'),
                'anio_mes' => (int) $request->input('anio_mes'),
                'alcance' => (string) $request->input('alcance'),
                'fecha_ejecucion' => (string) $request->input('fecha_ejecucion'),
                'hora_ejecucion' => $request->input('hora_ejecucion'),
                'fecha_hasta' => (string) $request->input('fecha_hasta'),
                'observacion' => $request->input('observacion'),
            ], (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        return $this->redirectIndex($request)
            ->with('mensaje', 'Cierre programado guardado correctamente.');
    }

    public function programarTodos(Request $request)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'anio_mes' => 'required|integer',
            'fecha_ejecucion' => 'required|date',
            'hora_ejecucion' => 'nullable|string|max:5',
            'fecha_hasta' => 'required|date',
            'observacion' => 'nullable|string|max:2000',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        $resultado = $this->programadoService->guardarTodosLosModulos(
            (int) $request->input('empresa_id'),
            (int) $request->input('anio_mes'),
            (string) $request->input('fecha_ejecucion'),
            (string) $request->input('fecha_hasta'),
            $request->input('observacion'),
            (int) auth()->id(),
            $request->input('hora_ejecucion')
        );

        $mensaje = 'Se programaron '.$resultado['guardados'].' alcance(s).';
        if ($resultado['errores'] !== []) {
            return $this->redirectIndex($request)
                ->with('mensaje', $mensaje)
                ->with('error', implode(' | ', $resultado['errores']));
        }

        return $this->redirectIndex($request)->with('mensaje', $mensaje);
    }

    public function ejecutarProgramado(Request $request, int $id)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'nullable|integer|min:1',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        try {
            $prog = $this->programadoService->ejecutarAhora($id, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        return $this->redirectIndex($request)
            ->with('mensaje', 'Cierre ejecutado: '.$prog->etiquetaAlcance().'.');
    }

    public function ejecutarPendientesMes(Request $request)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'anio_mes' => 'required|integer',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        $resultado = $this->programadoService->ejecutarTodosPendientesDelMes(
            (int) $request->input('empresa_id'),
            (int) $request->input('anio_mes'),
            (int) auth()->id()
        );

        $mensaje = 'Se ejecutaron '.$resultado['ejecutados'].' cierre(s) pendiente(s).';
        if ($resultado['errores'] !== []) {
            return $this->redirectIndex($request)
                ->with('mensaje', $mensaje)
                ->with('error', implode(' | ', $resultado['errores']));
        }

        return $this->redirectIndex($request)->with('mensaje', $mensaje);
    }

    public function cancelarProgramado(Request $request, int $id)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'nullable|integer|min:1',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        try {
            $this->programadoService->cancelar($id);
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        return $this->redirectIndex($request)
            ->with('mensaje', 'Programación de cierre cancelada.');
    }

    public function cerrarTodosAhora(Request $request)
    {
        can('ejecutar-cierre-periodo-contable');

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_hasta' => 'required|date',
            'observacion' => 'nullable|string|max:2000',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        try {
            $this->programadoService->cerrarTodosLosModulosAhora(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_hasta'),
                $request->input('observacion'),
                (int) auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        return $this->redirectIndex($request)
            ->with('mensaje', 'Cierre general (todos los módulos) registrado correctamente.');
    }

    public function borrarUltimo(Request $request)
    {
        if (! can('borrar-ultimo-cierre-periodo-contable', false)
            && ! can('ejecutar-cierre-periodo-contable', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'alcance' => 'nullable|string|max:32',
            'mes' => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        $alcance = $request->input('alcance');
        if ($alcance !== null && $alcance !== '' && ! PeriodoContableCierreSupport::alcanceEsValido((string) $alcance)) {
            return $this->redirectIndex($request)->with('error', 'Alcance inválido.');
        }

        try {
            $cierre = $this->cierreService->borrarUltimoCierre(
                (int) $request->input('empresa_id'),
                $alcance !== null && $alcance !== '' ? (string) $alcance : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectIndex($request)->with('error', $e->getMessage());
        }

        $fecha = optional($cierre->fecha_hasta)->format('d/m/Y');
        $etiqueta = $cierre->etiquetaAlcance();

        return $this->redirectIndex($request)
            ->with('mensaje', 'Se eliminó el último cierre ('.$etiqueta.' hasta '.$fecha.').');
    }

    private function redirectIndex(Request $request)
    {
        return redirect()->route('cierre_periodo_contable', [
            'empresa_id' => $request->input('empresa_id'),
            'mes' => $request->input('mes'),
            'anio' => $request->input('anio'),
        ]);
    }
}
