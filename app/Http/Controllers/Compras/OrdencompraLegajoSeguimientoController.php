<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\OrdencompraLegajoBandejaPaqueteService;
use App\Services\Compras\OrdencompraLegajoBandejaService;
use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraListadoFiltros;
use Illuminate\Http\Request;

/**
 * Consulta transversal de legajos (solo lectura): ¿dónde está esta OC/factura/COM/OP?
 */
class OrdencompraLegajoSeguimientoController extends Controller
{
    public function __construct(
        private OrdencompraLegajoBandejaService $service,
        private OrdencompraLegajoBandejaPaqueteService $paqueteService,
        private OrdencompraGestionService $gestionService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-seguimiento-legajo-compra');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = OrdencompraLegajoBandejaFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );
        // Seguimiento no usa pestañas de bandeja.
        $filtros['vista'] = OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO;
        $filtros['tab'] = OrdencompraLegajoBandejaFiltros::TAB_TODOS;
        $filtros['atajo'] = '';

        $busco = $this->service->tieneCriterioSeguimiento($filtros);
        $filas = $busco ? $this->service->buscarSeguimiento($filtros) : collect();

        return view('compras.legajo_seguimiento.index', [
            'filas' => $filas,
            'busco' => $busco,
            'filtros' => $filtros,
            'filtrosQuery' => OrdencompraLegajoBandejaFiltros::paraQueryString($filtros),
            'camposFiltro' => OrdencompraListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_pago' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
        ]);
    }

    public function ficha(int $id)
    {
        can('listar-seguimiento-legajo-compra');
        $oc = $this->paqueteService->encontrarOcConsulta($id);

        return response()->json([
            'paquete' => $this->paqueteService->paquete($oc),
            'historia' => $this->gestionService->leerHistoriaLegajo((int) $oc->id),
        ]);
    }
}
