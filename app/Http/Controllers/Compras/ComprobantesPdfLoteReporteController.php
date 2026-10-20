<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ComprobantesPdfLoteReporteService;
use App\Support\Compras\ComprobantesPdfLoteFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComprobantesPdfLoteReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'comprobantes_pdf_lote';

    public function __construct(
        private readonly ComprobantesPdfLoteReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-comprobantes-pdf-lote');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ComprobantesPdfLoteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if ((int) ($filtros['empresa_id'] ?? 0) > 0
            && ! $this->empresaRepository->empresaIdPermitida((int) $filtros['empresa_id'])) {
            $filtros['empresa_id'] = 0;
        }

        if ($request->boolean('consultar') && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) $filtros['empresa_id'],
            ]);
        }

        $consultado = false;
        $filas = null;
        $proveedoresIniciales = $this->proveedoresIniciales($filtros['proveedor_ids'] ?? []);

        if ($request->boolean('consultar') && ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $page = max(1, (int) $request->input('page', 1));
            $filas = $this->reporteService->paginar($filtros, $perPage, $page);
            $consultado = true;
        }

        $filtrosQuery = ComprobantesPdfLoteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.comprobantes_pdf_lote.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'proveedores_iniciales' => $proveedoresIniciales,
            'consultado' => $consultado,
            'filas' => $filas,
            'puede_editar' => true,
        ]);
    }

    public function descargarZip(Request $request): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        can('listar-comprobantes-pdf-lote');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ComprobantesPdfLoteFiltros::resolverDesdeRequest($request);
        if ((int) ($filtros['empresa_id'] ?? 0) > 0
            && ! $this->empresaRepository->empresaIdPermitida((int) $filtros['empresa_id'])) {
            abort(403);
        }

        if (! ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('comprobantes_pdf_lote');
        }

        $claves = ComprobantesPdfLoteFiltros::clavesDesdeRequest($request);
        $zip = $this->reporteService->armarZip($filtros, $claves);
        $nombre = 'comprobantes_'.$filtros['fecha_desde'].'_'.$filtros['fecha_hasta'].'.zip';

        return response()->download($zip['ruta'], $nombre)->deleteFileAfterSend(true);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $preferida = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($preferida !== null && $this->empresaRepository->empresaIdPermitida($preferida)) {
                $filtros['empresa_id'] = $preferida;
            } elseif ($empresaQuery->count() === 1) {
                $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
            }
        }

        return $filtros;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, codigo: string, nombre: string}>
     */
    private function proveedoresIniciales(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $porId = Proveedor::query()
            ->whereIn('id', $ids)
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $lista = [];
        foreach ($ids as $id) {
            $prov = $porId->get($id);
            if ($prov === null) {
                continue;
            }
            $lista[] = [
                'id' => (int) $prov->id,
                'codigo' => (string) ($prov->codigo ?? ''),
                'nombre' => (string) ($prov->nombre ?? ''),
            ];
        }

        return $lista;
    }
}
