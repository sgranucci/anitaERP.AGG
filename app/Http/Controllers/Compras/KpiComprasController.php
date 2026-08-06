<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ComprasKpisProcesoProductividadSupport;
use App\Support\Compras\OrdencompraListadoFiltros;
use Illuminate\Http\Request;

/**
 * Tablero de KPIs de proceso y productividad de Compras (Enc-compras).
 */
class KpiComprasController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can(ComprasKpisProcesoProductividadSupport::PERMISO);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresaQuery->first())->id;

        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );

        if (! empty($filtros['empresa_id']) && ! $this->empresaRepository->empresaIdPermitida((int) $filtros['empresa_id'])) {
            $filtros['empresa_id'] = $empresaDefault ? (int) $empresaDefault : null;
            $filtros['empresa_scope'] = $filtros['empresa_id'] ? 'una' : 'todas';
        }

        $empresaIdFiltro = (($filtros['empresa_scope'] ?? 'una') === 'todas')
            ? null
            : ($filtros['empresa_id'] ?? null);

        $desde = (string) $request->input('fecha_desde', date('Y-m-d', strtotime('-89 days')));
        $hasta = (string) $request->input('fecha_hasta', date('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = date('Y-m-d', strtotime('-89 days'));
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = date('Y-m-d');
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tablero = ComprasKpisProcesoProductividadSupport::tablero(
            $empresaIdFiltro ? (int) $empresaIdFiltro : null,
            $desde,
            $hasta
        );

        $filtrosQuery = OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros);
        $filtrosQuery['fecha_desde'] = $desde;
        $filtrosQuery['fecha_hasta'] = $hasta;

        return view('compras.kpi.index', [
            'tablero' => $tablero,
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ]);
    }
}
