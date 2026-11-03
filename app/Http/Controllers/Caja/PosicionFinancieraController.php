<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\PosicionFinancieraExport;
use App\Exports\Caja\PosicionFinancieraMultiExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPosicionFinancieraOrdenConceptos;
use App\Models\Caja\PosicionFinancieraSaldo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\PosicionFinancieraOrdenConceptoSupport;
use App\Support\Caja\PosicionFinancieraSaldoSupport;
use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
        $resultados = [];
        $erroresBridge = [];
        $saldoConfirmado = null;
        $esRango = false;

        if ($request->boolean('consultar') && EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $esRango = EfeMensualListadoFiltros::esRango($filtros);
            $resultados = $this->generarPeriodos($filtros);
            foreach ($resultados as $resultado) {
                foreach ($resultado['errores_bridge'] ?? [] as $err) {
                    $erroresBridge[] = $err;
                }
            }
            $erroresBridge = array_values(array_unique($erroresBridge));

            if (! $esRango && $resultados !== []) {
                $unico = $resultados[0];
                $saldoConfirmado = PosicionFinancieraSaldo::query()
                    ->with(['confirmadoPor:id,nombre', 'anuladoPor:id,nombre'])
                    ->where('empresa_id', (int) ($filtros['empresa_id'] ?? 0))
                    ->whereDate('fecha_cierre', Carbon::createFromDate(
                        (int) ($unico['anio'] ?? $filtros['anio'] ?? 0),
                        (int) ($unico['mes'] ?? $filtros['mes'] ?? 0),
                        1,
                    )->endOfMonth()->toDateString())
                    ->whereNull('anulado_at')
                    ->latest('id')
                    ->first();
            }
            $consultado = true;
        }

        $filtrosQuery = EfeMensualListadoFiltros::paraQueryString($filtros);
        unset($filtrosQuery['solo_moneda_origen'], $filtrosQuery['moneda_id']);

        $empresa = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? $this->empresaRepository->find((int) $filtros['empresa_id'])
            : null;

        $primero = $resultados[0] ?? null;

        return view('caja.posicion_financiera.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultados' => $resultados,
            'es_rango' => $esRango,
            // Compat vista/export de un solo mes:
            'filas' => $primero['filas_ordenadas'] ?? [],
            'dias' => $primero['dias'] ?? [],
            'saldo_inicial' => $primero['saldo_inicial'] ?? null,
            'saldo_final' => $primero['saldo_final'] ?? null,
            'saldo_inicial_origen' => $primero['saldo_inicial_origen'] ?? null,
            'fuente' => $primero['fuente'] ?? null,
            'fuente_etiqueta' => $primero['fuente_etiqueta'] ?? null,
            'saldo_confirmado' => $saldoConfirmado,
            'errores_bridge' => $erroresBridge,
            'empresa' => $empresa,
            'periodo_texto' => $this->periodoTexto($filtros),
            'corte_fuente_erp_ym' => EfePosicionFinancieraSupport::CORTE_FUENTE_ERP_YM,
            'fuente_corte_texto' => $this->fuenteCorteTexto(),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function ordenConceptos()
    {
        can('configurar-orden-posicion-financiera');

        $empresas = PosicionFinancieraOrdenConceptoSupport::empresasParaPreview();
        $bloques = PosicionFinancieraOrdenConceptoSupport::armarPreview($empresas);

        return view('caja.posicion_financiera.orden_conceptos', [
            'empresas' => $empresas,
            'bloques' => $bloques,
        ]);
    }

    public function guardarOrdenConceptos(ValidacionPosicionFinancieraOrdenConceptos $request)
    {
        can('configurar-orden-posicion-financiera');

        $actualizados = PosicionFinancieraOrdenConceptoSupport::guardarFilas(
            $request->validated('filas')
        );

        return redirect()->route('posicion_financiera_orden_conceptos')
            ->with('mensaje', $actualizados === 0
                ? 'El orden ya estaba guardado.'
                : 'Orden de conceptos actualizado ('.$actualizados.' cuentas).');
    }

    public function aplicarOrdenBiyemas()
    {
        can('configurar-orden-posicion-financiera');

        $actualizados = PosicionFinancieraOrdenConceptoSupport::aplicarOrdenBiyemas();

        return redirect()->route('posicion_financiera_orden_conceptos')
            ->with('mensaje', $actualizados === 0
                ? 'Las cuentas ya tenían el orden de Biyemas.'
                : 'Se aplicó el orden de Biyemas ('.$actualizados.' cuentas).');
    }

    public function confirmarSaldo(Request $request)
    {
        can('confirmar-saldo-posicion-financiera');

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        unset($filtros['solo_moneda_origen']);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        if (! EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('posicion_financiera')
                ->with('error', 'Debe indicar empresa y período.');
        }

        if (EfeMensualListadoFiltros::esRango($filtros)) {
            return redirect()->route('posicion_financiera', array_merge(
                EfeMensualListadoFiltros::paraQueryString($filtros),
                ['consultar' => 1],
            ))->with('error', 'El cierre de saldo solo aplica a un mes (deje Hasta igual a Desde).');
        }

        try {
            $resultado = $this->posicionFinancieraSupport->generar($filtros);
            $fechaCierre = Carbon::createFromDate(
                (int) $filtros['anio'],
                (int) $filtros['mes'],
                1,
            )->endOfMonth();

            PosicionFinancieraSaldoSupport::confirmar(
                $empresaId,
                $fechaCierre,
                (float) ($resultado['saldo_inicial'] ?? 0),
                (float) ($resultado['saldo_final'] ?? 0),
                EfeMensualListadoFiltros::paraQueryString($filtros),
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('posicion_financiera', array_merge(
                EfeMensualListadoFiltros::paraQueryString($filtros),
                ['consultar' => 1],
            ))->with('error', $e->getMessage());
        }

        return redirect()->route('posicion_financiera', array_merge(
            EfeMensualListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ))->with('mensaje', 'Saldo final del período confirmado.');
    }

    public function anularSaldo(Request $request, int $id)
    {
        can('anular-saldo-posicion-financiera');

        $saldo = PosicionFinancieraSaldo::query()->findOrFail($id);
        $this->assertAccesoEmpresa((int) $saldo->empresa_id);

        try {
            PosicionFinancieraSaldoSupport::anular(
                $id,
                (int) auth()->id(),
                (string) $request->input('motivo'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Saldo confirmado anulado. El período puede volver a confirmarse.');
    }

    public function auditoria(Request $request)
    {
        can('listar-posicion-financiera');

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        unset($filtros['solo_moneda_origen']);
        // Auditoría de celda: mes puntual (ignora rango; usa mes/anio del link).
        if ($request->filled('mes') || $request->filled('anio')) {
            $filtros['mes'] = max(1, min(12, (int) $request->input('mes', $filtros['mes'])));
            $filtros['anio'] = max(2000, min(2100, (int) $request->input('anio', $filtros['anio'])));
            $filtros['mes_hasta'] = $filtros['mes'];
            $filtros['anio_hasta'] = $filtros['anio'];
        }
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $dia = (int) $request->input('dia');
        $bloque = trim((string) $request->input('bloque'));
        $etiqueta = trim((string) $request->input('etiqueta'));

        try {
            $auditoria = $this->posicionFinancieraSupport->auditarDato(
                $filtros,
                $dia,
                $bloque,
                $etiqueta,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return view('caja.posicion_financiera.partials.auditoria_dato', compact('auditoria'));
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-posicion-financiera');
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $filtros = EfeMensualListadoFiltros::resolverDesdeRequest($request);
        unset($filtros['solo_moneda_origen']);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('posicion_financiera');
        }

        $resultados = $this->generarPeriodos($filtros);
        $empresa = $this->empresaRepository->find((int) ($filtros['empresa_id'] ?? 0));
        $periodo = $this->periodoTexto($filtros);
        $formato = strtoupper($formato);
        $esRango = count($resultados) > 1;

        $slugEmpresa = $empresa
            ? preg_replace('/[^A-Za-z0-9]+/', '_', (string) $empresa->nombre)
            : 'empresa';
        $mes = str_pad((string) ($filtros['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
        $anio = (string) ($filtros['anio'] ?? '');
        $sufijoRango = '';
        if ($esRango) {
            $mesHasta = str_pad((string) ($filtros['mes_hasta'] ?? $filtros['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
            $anioHasta = (string) ($filtros['anio_hasta'] ?? $filtros['anio'] ?? '');
            $sufijoRango = '_a_'.$anioHasta.$mesHasta;
        }
        $baseNombre = 'posicion_financiera_'.$slugEmpresa.'_'.$anio.$mes.$sufijoRango;

        if ($formato === 'PDF') {
            return $this->exportarPdf($resultados, $empresa, $periodo, $baseNombre);
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            if ($formato === 'CSV' || ! $esRango) {
                $primero = $resultados[0] ?? [
                    'filas_ordenadas' => [],
                    'dias' => [],
                ];

                return Excel::download(
                    (new PosicionFinancieraExport)->parametros(
                        $primero['filas_ordenadas'] ?? [],
                        $primero['dias'] ?? [],
                        $empresa,
                        $this->periodoMesTexto(
                            (int) ($primero['mes'] ?? $filtros['mes'] ?? 0),
                            (int) ($primero['anio'] ?? $filtros['anio'] ?? 0),
                        ),
                        $formato === 'CSV',
                    ),
                    $baseNombre.($formato === 'CSV' ? '.csv' : '.xlsx')
                );
            }

            $periodosExport = [];
            foreach ($resultados as $resultado) {
                $texto = $this->periodoMesTexto(
                    (int) ($resultado['mes'] ?? 0),
                    (int) ($resultado['anio'] ?? 0),
                );
                $periodosExport[] = [
                    'filas' => $resultado['filas_ordenadas'] ?? [],
                    'dias' => $resultado['dias'] ?? [],
                    'periodo_texto' => $texto,
                    'titulo_hoja' => $texto !== '' ? $texto : 'Posición financiera',
                ];
            }

            return Excel::download(
                new PosicionFinancieraMultiExport($periodosExport, $empresa),
                $baseNombre.'.xlsx'
            );
        }

        return redirect()->route('posicion_financiera', array_merge(
            EfeMensualListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function generarPeriodos(array $filtros): array
    {
        $periodos = EfeMensualListadoFiltros::periodosMensuales($filtros);
        $resultados = [];
        foreach ($periodos as $periodo) {
            $filtrosMes = $filtros;
            $filtrosMes['mes'] = $periodo['mes'];
            $filtrosMes['anio'] = $periodo['anio'];
            $filtrosMes['mes_hasta'] = $periodo['mes'];
            $filtrosMes['anio_hasta'] = $periodo['anio'];
            $resultados[] = $this->posicionFinancieraSupport->generar($filtrosMes);
        }

        return $resultados;
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function exportarPdf(array $resultados, $empresa, string $periodo, string $baseNombre)
    {
        $pdf = Pdf::loadView('caja.posicion_financiera.listado', [
            'resultados' => $resultados,
            'filas' => $resultados[0]['filas_ordenadas'] ?? [],
            'dias' => $resultados[0]['dias'] ?? [],
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
        $desde = $this->periodoMesTexto(
            (int) ($filtros['mes'] ?? 0),
            (int) ($filtros['anio'] ?? 0),
        );
        if ($desde === '') {
            return '';
        }

        if (! EfeMensualListadoFiltros::esRango($filtros)) {
            return $desde;
        }

        $hasta = $this->periodoMesTexto(
            (int) ($filtros['mes_hasta'] ?? 0),
            (int) ($filtros['anio_hasta'] ?? 0),
        );

        return $hasta !== '' ? $desde.' a '.$hasta : $desde;
    }

    private function periodoMesTexto(int $mes, int $anio): string
    {
        if ($mes <= 0 || $anio <= 0) {
            return '';
        }

        return str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$anio;
    }

    private function fuenteCorteTexto(): string
    {
        $corte = EfePosicionFinancieraSupport::CORTE_FUENTE_ERP_YM;
        $mesErp = $corte % 100;
        $anioErp = intdiv($corte, 100);
        $mesAnitaHasta = $mesErp === 1 ? 12 : $mesErp - 1;
        $anioAnitaHasta = $mesErp === 1 ? $anioErp - 1 : $anioErp;

        return 'Anita hasta '.$this->periodoMesTexto($mesAnitaHasta, $anioAnitaHasta)
            .'; ERP desde '.$this->periodoMesTexto($mesErp, $anioErp);
    }
}
