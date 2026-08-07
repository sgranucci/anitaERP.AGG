<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ContratoVencimientoReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ContratoVencimientoReporteFiltros;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel;

/**
 * Reporte de contratos / OC abiertas por vencer.
 *
 * Es la bandeja de trabajo del circuito: el mail empuja, esta pantalla es donde se
 * revisa el vencimiento, el preaviso de no renovación y el consumo del tope.
 */
class ContratoVencimientoReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'contrato_vencimiento_reporte';

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-contrato-vencimiento');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && ContratoVencimientoReporteFiltros::tieneCriteriosAplicados($filtros);

        $filas = null;
        $filasVista = [];
        $totales = $this->totalesVacios();

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);

            $contratos = $this->generar($filtros);
            $totales = $this->totales($contratos);

            $perPage = max(25, min(500, (int) $request->input('per_page', 50)));
            $filas = $this->paginar($contratos, $perPage, max(1, (int) $request->input('page', 1)));
            $filasVista = $filas->items();
        }

        $filtrosQuery = ContratoVencimientoReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.contrato_vencimiento_reporte.index', [
            'empresa_query' => $empresaQuery,
            'usuario_query' => UsuarioOperativoSupport::listadoParaSelector(columnas: ['id', 'nombre']),
            'opciones_alerta' => ContratoVencimientoReporteFiltros::OPCIONES_ALERTA,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'subtitulo' => ContratoVencimientoReporteFiltros::subtitulo($filtros, $empresaQuery),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-contrato-vencimiento');

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '600');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);

        if (! ContratoVencimientoReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_contrato_vencimiento');
        }

        $filas = $this->generar($filtros);
        $totales = $this->totales($filas);
        $titulo = 'Contratos y OC abiertas por vencer';
        $subtitulo = ContratoVencimientoReporteFiltros::subtitulo($filtros, $empresaQuery);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.contrato_vencimiento_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'contratos_vencimiento_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ContratoVencimientoReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('contratos_vencimiento.xlsx');

            case 'CSV':
                return (new ContratoVencimientoReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('contratos_vencimiento.csv', Excel::CSV);
        }

        return redirect()->route(
            'reporte_contrato_vencimiento',
            ContratoVencimientoReporteFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function generar(array $filtros): array
    {
        $contratos = OrdencompraContratoVencimientoSupport::recopilar();

        foreach ($contratos as $i => $contrato) {
            $avisos = OrdencompraContratoVencimientoSupport::avisosCandidatos($contrato);
            $contratos[$i]['avisos'] = $avisos;
            $contratos[$i]['aviso_principal'] = OrdencompraContratoVencimientoSupport::avisoPrincipal($avisos);
            $contratos[$i]['motivo'] = OrdencompraContratoVencimientoSupport::motivo($contratos[$i]);
        }

        return ContratoVencimientoReporteFiltros::aplicar($contratos, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request, $empresaQuery): array
    {
        $filtros = ContratoVencimientoReporteFiltros::resolverDesdeRequest($request);
        $permitidos = $empresaQuery->pluck('id')->map(static fn ($id) => (int) $id)->all();

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $permitidos;
        }

        return $filtros;
    }

    /**
     * @param  list<array<string, mixed>>  $contratos
     * @return array<string, mixed>
     */
    private function totales(array $contratos): array
    {
        $totales = $this->totalesVacios();
        $totales['cantidad'] = count($contratos);

        foreach ($contratos as $contrato) {
            $totales['monto_tope'] += (float) $contrato['monto_tope'];
            $totales['monto_recibido'] += (float) ($contrato['monto_recibido'] ?? 0);
            $totales['monto_facturado'] += (float) $contrato['monto_facturado'];
            $totales['monto_consumido'] += (float) ($contrato['monto_consumido'] ?? 0);
            $totales['monto_disponible'] += (float) $contrato['monto_disponible'];

            if ($contrato['vigencia_hasta'] === null) {
                $totales['sin_vigencia']++;

                continue;
            }
            if ((int) $contrato['dias_para_vencer'] < 0) {
                $totales['vencidos']++;

                continue;
            }
            if ((int) $contrato['dias_para_vencer'] <= 30) {
                $totales['vencen_30']++;
            } elseif ((int) $contrato['dias_para_vencer'] <= 60) {
                $totales['vencen_60']++;
            }
        }

        return $totales;
    }

    /**
     * @return array<string, mixed>
     */
    private function totalesVacios(): array
    {
        return [
            'cantidad' => 0,
            'vencidos' => 0,
            'vencen_30' => 0,
            'vencen_60' => 0,
            'sin_vigencia' => 0,
            'monto_tope' => 0.0,
            'monto_recibido' => 0.0,
            'monto_facturado' => 0.0,
            'monto_consumido' => 0.0,
            'monto_disponible' => 0.0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $contratos
     */
    private function paginar(array $contratos, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            array_slice($contratos, ($page - 1) * $perPage, $perPage),
            count($contratos),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }
}
