<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\CierreDescuentoFallo_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\DescuentoFalloProcesoService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Proceso de generación de descuentos por fallos (Anita: p-dtofallo.c).
 */
class DescuentoFallo_SueldosController extends Controller
{
    public function __construct(
        private readonly DescuentoFalloProcesoService $proceso,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-descuento-fallo-sueldos');

        $cierres = CierreDescuentoFallo_Sueldos::query()
            ->with(['empresa', 'usuario'])
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        $conceptoCodigo = (int) config('sueldos.concepto_descuento_fallo_codigo', 192);
        $concepto = $conceptoCodigo > 0
            ? Concepto_Sueldos::query()->where('codigo', $conceptoCodigo)->first()
            : null;

        return view('sueldos.descuento_fallo.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'cierres' => $cierres,
            'concepto' => $concepto,
            'mesesPlan' => (int) config('sueldos.meses_descuento_fallo', 10),
            'defaults' => [
                'empresa_id' => (int) optional($this->empresaRepository->allFiltrado()->first())->id,
                'periodo_descuento' => (int) date('Ym'),
                'fecha_fallo_desde' => date('Y-m-01'),
                'fecha_fallo_hasta' => date('Y-m-d'),
                'legajo_desde' => '',
                'legajo_hasta' => '',
                'generar_novedades' => 1,
            ],
        ]);
    }

    public function generar(Request $request)
    {
        can('crear-descuento-fallo-sueldos');

        $datos = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'periodo_descuento' => ['required', 'integer', 'min:190001', 'max:299912'],
            'fecha_fallo_desde' => ['required', 'date'],
            'fecha_fallo_hasta' => ['required', 'date', 'after_or_equal:fecha_fallo_desde'],
            'legajo_desde' => ['nullable', 'integer', 'min:1'],
            'legajo_hasta' => ['nullable', 'integer', 'min:1'],
            'generar_novedades' => ['nullable', 'boolean'],
        ]);

        $datos['generar_novedades'] = $request->boolean('generar_novedades', true);

        try {
            $resultado = $this->proceso->generar($datos);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $resumen = $resultado['resumen'];
        $cierre = $resultado['cierre'];

        return redirect()
            ->route('consultar_descuento_fallo_sueldos')
            ->with(
                'mensaje',
                sprintf(
                    'Cierre #%d generado: %d empleado(s), %d movimiento(s), %d novedad(es). Descuento $ %s · Sanción $ %s · Pérdida $ %s.',
                    $cierre->numero_cierre,
                    $resumen['empleados_procesados'],
                    $resumen['movimientos'],
                    $resumen['novedades'],
                    number_format($resumen['total_descuento'], 2, ',', '.'),
                    number_format($resumen['total_sancion'], 2, ',', '.'),
                    number_format($resumen['total_perdida'], 2, ',', '.')
                )
            );
    }

    public function anular(Request $request, $id)
    {
        can('anular-descuento-fallo-sueldos');

        try {
            $cierre = $this->proceso->anularCierre((int) $id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('consultar_descuento_fallo_sueldos')
            ->with('mensaje', 'Cierre #'.$cierre->numero_cierre.' anulado. Movimientos borrados y novedades asociadas anuladas.');
    }

    public function ver($id)
    {
        can('listar-descuento-fallo-sueldos');
        $cierre = CierreDescuentoFallo_Sueldos::query()
            ->with([
                'empresa',
                'usuario',
                'movimientos.empleado:id,legajo,nombre',
                'movimientos.novedad:id,estado,periodo,concepto_codigo,valor1',
            ])
            ->findOrFail($id);

        return view('sueldos.descuento_fallo.ver', compact('cierre'));
    }
}
