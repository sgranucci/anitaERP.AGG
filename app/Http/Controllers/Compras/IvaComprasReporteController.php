<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\IvaComprasListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\IvaComprasReporteService;
use App\Support\Compras\IvaComprasListadoFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class IvaComprasReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'iva_compras';

    public function __construct(
        private readonly IvaComprasReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-iva-compras');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();
        $filtros = IvaComprasListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
        }

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($request->boolean('consultar') && IvaComprasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generarDesdeFiltros($filtros);
            $filasVistaFuente = $resultado['filas_display'] ?? $resultado['filas'];
            $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
            $totalFilas = count($filasVistaFuente);
            $maxPage = max(1, (int) ceil($totalFilas / $perPage));
            $page = max(1, min($maxPage, (int) $request->input('page', 1)));

            $filas = $this->reporteService->paginarFilas($filasVistaFuente, $perPage, $page);
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = IvaComprasListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.iva_compras.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'orden_enum' => IvaComprasListadoFiltros::ORDENES,
            'subdiario_enum' => IvaComprasListadoFiltros::SUBDIARIOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => IvaComprasListadoFiltros::formatearPeriodoTexto($filtros),
            'orden_texto' => IvaComprasListadoFiltros::formatearOrdenTexto($filtros),
            'subdiario_texto' => IvaComprasListadoFiltros::formatearSubdiarioTexto($filtros),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_tipotransaccion' => can('editar-tipo-transaccion-compra', false) || can('listar-tipo-transaccion-compra', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-iva-compras');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IvaComprasListadoFiltros::resolverDesdeRequest($request);

        if (! IvaComprasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('iva_compras');
        }

        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $filas = $resultado['filas_display'] ?? $resultado['filas'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('compras.iva_compras.listado', [
                    'resultado' => $resultado,
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'para_pdf' => true,
                    'puede_ver_comprobante' => false,
                ])->render();

                $dir = storage_path('pdf/listados');
                if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    abort(500, 'No se pudo crear el directorio para el PDF.');
                }
                $ruta = $dir.'/iva_compras_'.date('Ymd_His').'_'.uniqid('', true).'.pdf';
                DompdfListadoSupport::guardarLegalLandscape($view, $ruta, [
                    'titulo_corto' => 'IVA compras',
                ]);

                return response()->download($ruta)->deleteFileAfterSend(true);

            case 'EXCEL':
                return (new IvaComprasListadoExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download('iva_compras.xlsx');

            case 'CSV':
                return (new IvaComprasListadoExport($this->reporteService))
                    ->parametros($filtros, $resultado, true)
                    ->download('iva_compras.csv', Excel::CSV);
        }

        return redirect()->route('iva_compras', IvaComprasListadoFiltros::paraQueryString($filtros));
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
}
