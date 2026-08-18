<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\PosicionFinancieraExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\PosicionFinancieraSaldo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
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
        $filas = [];
        $dias = [];
        $saldoInicial = null;
        $saldoFinal = null;
        $saldoInicialOrigen = null;
        $saldoConfirmado = null;
        $erroresBridge = [];

        if ($request->boolean('consultar') && EfeMensualListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->posicionFinancieraSupport->generar($filtros);
            $filas = $resultado['filas_ordenadas'] ?? [];
            $dias = $resultado['dias'] ?? [];
            $saldoInicial = $resultado['saldo_inicial'] ?? null;
            $saldoFinal = $resultado['saldo_final'] ?? null;
            $saldoInicialOrigen = $resultado['saldo_inicial_origen'] ?? null;
            $erroresBridge = $resultado['errores_bridge'] ?? [];
            $saldoConfirmado = PosicionFinancieraSaldo::query()
                ->with(['confirmadoPor:id,nombre', 'anuladoPor:id,nombre'])
                ->where('empresa_id', (int) ($filtros['empresa_id'] ?? 0))
                ->whereDate('fecha_cierre', Carbon::createFromDate(
                    (int) ($filtros['anio'] ?? 0),
                    (int) ($filtros['mes'] ?? 0),
                    1,
                )->endOfMonth()->toDateString())
                ->whereNull('anulado_at')
                ->latest('id')
                ->first();
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
            'saldo_inicial_origen' => $saldoInicialOrigen,
            'saldo_confirmado' => $saldoConfirmado,
            'errores_bridge' => $erroresBridge,
            'empresa' => $empresa,
            'periodo_texto' => $this->periodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
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
