<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\PosicionFinancieraExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Posición financiera (solo esa solapa) — menú Caja / tesorería.
 * Distinto del EFE completo de Contable.
 */
class PosicionFinancieraController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'posicion_financiera';

    public function __construct(
        private readonly EfePosicionFinancieraSupport $posicionFinancieraSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-posicion-financiera');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        unset($filtros['solo_moneda_origen']);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
        }

        $consultado = false;
        $filas = [];
        $dias = [];
        $saldoInicial = null;
        $saldoFinal = null;
        $erroresBridge = [];

        if ($request->boolean('consultar') && EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->posicionFinancieraSupport->generar($filtros);
            $filas = $resultado['filas_ordenadas'] ?? [];
            $dias = $resultado['dias'] ?? [];
            $saldoInicial = $resultado['saldo_inicial'] ?? null;
            $saldoFinal = $resultado['saldo_final'] ?? null;
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $consultado = true;
        }

        $filtrosQuery = EfeMensualListadoFiltros::paraQueryString($filtros);
        unset($filtrosQuery['solo_moneda_origen'], $filtrosQuery['moneda_id']);

        $empresa = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? $this->empresaRepository->find((int) $filtros['empresa_id'])
            : null;

        return view('caja.posicion_financiera.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'dias' => $dias,
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $saldoFinal,
            'errores_bridge' => $erroresBridge,
            'empresa' => $empresa,
            'periodo_texto' => $this->periodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-posicion-financiera');
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        unset($filtros['solo_moneda_origen']);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('posicion_financiera');
        }

        $resultado = $this->posicionFinancieraSupport->generar($filtros);
        $filas = $resultado['filas_ordenadas'] ?? [];
        $dias = $resultado['dias'] ?? [];
        $empresa = $this->empresaRepository->find((int) ($filtros['empresa_id'] ?? 0));
        $periodo = $this->periodoTexto($filtros);
        $formato = strtoupper($formato);

        $slugEmpresa = $empresa
            ? preg_replace('/[^A-Za-z0-9]+/', '_', (string) $empresa->nombre)
            : 'empresa';
        $mes = str_pad((string) ($filtros['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
        $anio = (string) ($filtros['anio'] ?? '');
        $baseNombre = 'posicion_financiera_'.$slugEmpresa.'_'.$anio.$mes;

        return match ($formato) {
            'PDF' => $this->exportarPdf($filas, $dias, $empresa, $periodo, $baseNombre),
            'EXCEL', 'CSV' => Excel::download(
                (new PosicionFinancieraExport)->parametros($filas, $dias, $empresa, $periodo, $formato === 'CSV'),
                $baseNombre.($formato === 'CSV' ? '.csv' : '.xlsx')
            ),
            default => redirect()->route('posicion_financiera', array_merge(
                EfeMensualListadoFiltros::paraQueryString($filtros),
                ['consultar' => 1],
            )),
        };
    }

    /**
     * @param  list<array{etiqueta: string, valor: float, por_dia?: array<int, float>}>  $filas
     * @param  list<int>  $dias
     */
    private function exportarPdf(array $filas, array $dias, $empresa, string $periodo, string $baseNombre)
    {
        $pdf = Pdf::loadView('caja.posicion_financiera.listado', [
            'filas' => $filas,
            'dias' => $dias,
            'empresa' => $empresa,
            'periodo_texto' => $periodo,
        ])->setPaper('legal', 'landscape');

        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/listado_'.$baseNombre.'.pdf';
        $pdf->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $cached = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && in_array($cached, $permitidos, true)) {
                $filtros['empresa_id'] = $cached;
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function periodoTexto(array $filtros): string
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($mes <= 0 || $anio <= 0) {
            return '';
        }

        return str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$anio;
    }
}
