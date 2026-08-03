<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\GananciasCalculadorService;
use Illuminate\Http\Request;

/**
 * Consulta / debug de la planilla anual de Ganancias 4ta categoria.
 */
class Ganancias_SueldosController extends Controller
{
    public function __construct(private EmpresaRepositoryInterface $empresaRepository)
    {
    }

    public function index(Request $request, GananciasCalculadorService $calculador)
    {
        can('listar-ganancias-sueldos');

        $anio = (int) $request->input('anio', now()->year);
        $hastaMes = (int) $request->input('hasta_mes', now()->month);
        $legajo = trim((string) $request->input('legajo', ''));
        $empresaId = $request->input('empresa_id') ? (int) $request->input('empresa_id') : null;
        if ($empresaId && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $empleado = null;
        $resultado = null;

        if ($request->boolean('consultar') && $legajo !== '') {
            $q = Empleado_Sueldos::query()->where('legajo', (int) $legajo);
            if ($empresaId) {
                $q->where('empresa_id', $empresaId);
            } else {
                $this->empresaRepository->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
            }
            $empleado = $q->first();

            if ($empleado) {
                can('calcular-ganancias-sueldos');
                $resultado = $calculador->calcularYPersistir(
                    (int) $empleado->id,
                    (int) $empleado->empresa_id,
                    $anio,
                    max(1, min(12, $hastaMes)),
                );
            }
        }

        return view('sueldos.ganancias.index', [
            'anio' => $anio,
            'hastaMes' => $hastaMes,
            'legajo' => $legajo,
            'empresaId' => $empresaId,
            'empleado' => $empleado,
            'resultado' => $resultado,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * Endpoint de prueba: calcula con entradas JSON (para validar vs CSV Anita).
     */
    public function simular(Request $request, GananciasCalculadorService $calculador)
    {
        can('calcular-ganancias-sueldos');

        $anio = (int) $request->input('anio', 2026);
        $hastaMes = (int) $request->input('hasta_mes', 1);
        $entradas = $request->input('entradas', []);
        $cantidades = $request->input('cantidades', []);

        $resultado = $calculador->calcularAnio($anio, $hastaMes, $entradas, $cantidades);

        return response()->json($resultado);
    }
}
