<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ConciliacionBancariaExport;
use App\Exports\Contable\ConciliacionBancariaManualExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\InterbankingMovimientoPersistenciaService;
use App\Services\Caja\InterbankingService;
use App\Services\Contable\ConciliacionBancariaAlinearExcelService;
use App\Services\Contable\ConciliacionBancariaExtractoArchivoImportService;
use App\Services\Contable\ConciliacionBancariaGastoDocumentoService;
use App\Services\Contable\ConciliacionBancariaService;
use App\Support\Caja\InterbankingCuentacajaParametrosSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCruceChequesIbSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaEngancheSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaModoSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaResultadoCache;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ConciliacionBancariaController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly ConciliacionBancariaGastoDocumentoService $gastoDocumentoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresaQuery->count() === 1 ? (int) $empresaQuery->first()->id : 0);
        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));

        $cuentasCaja = collect();
        if ($empresaId > 0) {
            // Incluye cuentas sin Interbanking (extracto CSV/OFX/Excel → EXT-{codigo}).
            $cuentasCaja = Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->operativa()
                ->with('cuentacontables')
                ->orderBy('nombre')
                ->get();
        }

        $cuentacaja = $cuentacajaId > 0 ? Cuentacaja::query()->with('cuentacontables')->find($cuentacajaId) : null;
        $enganche = null;
        if ($cuentacajaId > 0 && $empresaId > 0 && $this->empresaRepository->empresaIdPermitida($empresaId)) {
            $enganche = ConciliacionBancariaEngancheSupport::datosEnganche($empresaId, $cuentacajaId);
        }

        $resultado = null;
        $error = null;

        $grabarAutomaticos = $request->boolean('grabar_automaticos');

        if ($request->boolean('consultar') && $empresaId > 0 && $cuentacajaId > 0) {
            if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
                abort(403);
            }

            try {
                $resultado = $this->service->ejecutar(
                    $empresaId,
                    $cuentacajaId,
                    $mes,
                    $anio,
                    (int) Auth::id(),
                    $grabarAutomaticos,
                );
                if ($grabarAutomaticos) {
                    $paresNuevos = (int) (($resultado['kpi']['pares_auto_nuevos']
                        ?? $resultado['caratula']['pares_nuevos']
                        ?? 0));
                    Session::flash(
                        'mensaje',
                        $paresNuevos > 0
                            ? "Corrida con grabado: se guardaron {$paresNuevos} emparejamiento(s) automático(s)."
                            : 'Corrida con grabado: no había emparejamientos nuevos para guardar.'
                    );
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Session::flash('errores', [$error]);
            }
        }

        $filtrosQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'cuentacaja_id' => $cuentacajaId > 0 ? $cuentacajaId : null,
            'mes' => $mes,
            'anio' => $anio,
            'consultar' => $request->boolean('consultar') ? 1 : null,
            'grabar_automaticos' => $grabarAutomaticos ? 1 : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('contable.conciliacion_bancaria.index', [
            'empresa_query' => $empresaQuery,
            'grabar_automaticos' => $grabarAutomaticos,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $cuentacajaId,
            'cuentacaja' => $cuentacaja,
            'enganche' => $enganche,
            'cuentas_caja' => $cuentasCaja,
            'mes' => $mes,
            'anio' => $anio,
            'resultado' => $resultado,
            'filtrosQuery' => $filtrosQuery,
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        // Quien puede ejecutar la conciliación también puede bajar el Excel.
        if (! can('exportar-conciliacion-bancaria', false) && ! can('ejecutar-conciliacion-bancaria', false)) {
            can('exportar-conciliacion-bancaria');
        }

        ini_set('memory_limit', (string) config('conciliacion_bancaria.memory_limit', '2048M'));
        set_time_limit(600);

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        $queryVolver = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'cuentacaja_id' => $cuentacajaId > 0 ? $cuentacajaId : null,
            'mes' => $mes,
            'anio' => $anio,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return redirect()
                ->route('conciliacion_bancaria', $queryVolver)
                ->with('errores', ['Faltan empresa o cuenta de caja para exportar. Volvé a conciliar e intentá de nuevo.']);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        if (strtoupper($formato) !== 'EXCEL') {
            return redirect()->route('conciliacion_bancaria', $queryVolver);
        }

        try {
            $resultado = null;
            if (! $request->boolean('recalcular')) {
                $resultado = ConciliacionBancariaResultadoCache::leer($empresaId, $cuentacajaId, $mes, $anio);
            }
            $resultado ??= $this->service->ejecutar($empresaId, $cuentacajaId, $mes, $anio, (int) Auth::id(), false);
            $resultado = ConciliacionBancariaCruceChequesIbSupport::enriquecerResultado($resultado);
            $cc = $resultado['cuentacaja'];
            $codigoCuenta = is_object($cc) ? ($cc->codigo ?? 'cuenta') : (is_array($cc) ? ($cc['codigo'] ?? 'cuenta') : 'cuenta');
            $nombre = sprintf('conciliacion_bancaria_%s_%02d_%d.xlsx', $codigoCuenta, $mes, $anio);

            return (new ConciliacionBancariaExport)->parametros($resultado)->descargar($nombre);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('conciliacion_bancaria', $queryVolver)
                ->with('errores', ['No se pudo generar el Excel: '.$e->getMessage()]);
        }
    }

    public function apiEngancheCuentacaja(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta de caja requeridas.'], 422);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $enganche = ConciliacionBancariaEngancheSupport::datosEnganche($empresaId, $cuentacajaId);

        return response()->json([
            'ok' => (bool) ($enganche['ok'] ?? false),
            'enganche' => $enganche,
            'html' => ($enganche['ok'] ?? false)
                ? view('contable.conciliacion_bancaria.partials.enganche_cuenta', ['enganche' => $enganche])->render()
                : '',
            'error' => $enganche['error'] ?? null,
        ]);
    }

    public function apiCuentacajaPorCodigo(Request $request, string $codigo)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        if ($empresaId <= 0) {
            return response()->json(['id' => 0, 'error' => 'Debe seleccionar empresa.'], 422);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $codigo = trim(urldecode($codigo));
        $variantes = array_values(array_unique(array_filter([
            $codigo,
            ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : null,
        ])));

        if ($variantes === []) {
            return response()->json(['id' => 0], 200);
        }

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->operativa()
            ->whereIn('codigo', $variantes)
            ->with('cuentacontables')
            ->get(['id', 'nombre', 'codigo', 'cuenta_interbanking', 'empresa_id']);

        $cuenta = $cuentas->first(fn ($c) => (int) $c->empresa_id === $empresaId)
            ?? $cuentas->first();

        if (! $cuenta) {
            return response()->json([
                'id' => 0,
                'error' => 'No hay cuenta de caja con Interbanking configurado para ese código.',
            ], 404);
        }

        return response()->json([
            'id' => $cuenta->id,
            'codigo' => $cuenta->codigo,
            'nombre' => $cuenta->nombre,
            'cuenta_interbanking' => $cuenta->cuenta_interbanking,
        ]);
    }

    public function manual(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return redirect()
                ->route('conciliacion_bancaria')
                ->with('errores', ['Elegí empresa y cuenta de caja antes de la conciliación manual.']);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $cuentacaja = Cuentacaja::query()->find($cuentacajaId);
        $preferenciasModo = ConciliacionBancariaModoSupport::preferenciasUi($empresaId);

        return view('contable.conciliacion_bancaria.manual', [
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $cuentacajaId,
            'cuentacaja' => $cuentacaja,
            'mes' => $mes,
            'anio' => $anio,
            'modo_prefs' => $preferenciasModo,
            'modos_opciones' => ConciliacionBancariaModoSupport::opciones(),
            'volver' => route('conciliacion_bancaria', [
                'empresa_id' => $empresaId,
                'cuentacaja_id' => $cuentacajaId,
                'mes' => $mes,
                'anio' => $anio,
            ]),
        ]);
    }

    public function exportarManual(Request $request)
    {
        if (! can('exportar-conciliacion-bancaria', false) && ! can('ejecutar-conciliacion-bancaria', false)) {
            can('exportar-conciliacion-bancaria');
        }

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));
        $fechaDia = trim((string) $request->input('fecha', ''));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return redirect()
                ->route('conciliacion_bancaria_manual', $request->only(['empresa_id', 'cuentacaja_id', 'mes', 'anio']))
                ->with('errores', ['Faltan empresa o cuenta de caja.']);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $tablero = $this->service->tableroManual(
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
                $request->boolean('incluir_anteriores'),
                $fechaDia !== '' ? $fechaDia : null,
            );
            $cc = Cuentacaja::query()->find($cuentacajaId);
            $nombre = sprintf(
                'conciliacion_manual_%s_%02d_%d.xlsx',
                $cc->codigo ?? 'cuenta',
                $mes,
                $anio,
            );

            return (new ConciliacionBancariaManualExport)
                ->parametros($tablero, [
                    'cuenta' => trim(($cc->codigo ?? '').' '.($cc->nombre ?? '')),
                    'empresa' => 'empresa '.$empresaId,
                    'mes' => $mes,
                    'anio' => $anio,
                ])
                ->descargar($nombre);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('conciliacion_bancaria_manual', [
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => $cuentacajaId,
                    'mes' => $mes,
                    'anio' => $anio,
                ])
                ->with('errores', [$e->getMessage()]);
        }
    }

    public function apiTablero(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));
        $fechaDia = trim((string) $request->input('fecha', ''));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta de caja requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $tablero = $this->service->tableroManual(
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
                $request->boolean('incluir_anteriores'),
                $fechaDia !== '' ? $fechaDia : null,
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $tablero);
    }

    public function apiSincronizarDia(
        Request $request,
        InterbankingService $interbankingService,
        InterbankingMovimientoPersistenciaService $persistenciaService,
    ) {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $fecha = trim((string) $request->input('fecha', ''));

        if ($empresaId <= 0 || $cuentacajaId <= 0 || $fecha === '') {
            return response()->json(['ok' => false, 'error' => 'Empresa, cuenta y fecha son requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $dia = Carbon::parse($fecha)->toDateString();
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'Fecha inválida.'], 422);
        }

        $cuenta = Cuentacaja::query()->with(['bancos', 'monedas'])->find($cuentacajaId);
        if (! $cuenta || ! $cuenta->perteneceAEmpresa($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Cuenta de caja inválida.'], 422);
        }

        $accountNumber = trim((string) ($cuenta->cuenta_interbanking ?? ''));
        if ($accountNumber === '') {
            return response()->json(['ok' => false, 'error' => 'La cuenta no tiene Interbanking configurado.'], 422);
        }

        $params = InterbankingCuentacajaParametrosSupport::parametrosApiDesdeCuentacaja($cuenta, $empresaId);
        $bankNumber = $params['bank_number'] ?? InterbankingCuentacajaParametrosSupport::normalizarBankNumber3(
            (string) ($cuenta->bancos?->codigo ?? '285')
        );
        if ($bankNumber === '' || $bankNumber === '000') {
            $bankNumber = '285';
        }
        $accountType = $params['account_type'] ?? 'CC';
        $currency = $params['currency'] ?? 'ARS';

        $filas = 0;
        $errores = [];
        foreach (['dia', 'anteriores'] as $movementType) {
            $res = $persistenciaService->sincronizarDesdeApi(
                $interbankingService,
                $empresaId,
                $accountNumber,
                $bankNumber,
                $accountType,
                $currency,
                $movementType,
                $dia,
                $dia,
                200,
                20,
                true,
            );
            if (! empty($res['ok'])) {
                $filas += (int) ($res['filas_guardadas'] ?? 0);
            } else {
                $errores[] = $movementType.': '.($res['error'] ?? 'error');
            }
        }

        if ($filas === 0 && $errores !== []) {
            return response()->json([
                'ok' => false,
                'error' => implode(' · ', $errores),
                'filas' => 0,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'filas' => $filas,
            'fecha' => $dia,
            'avisos' => $errores,
        ]);
    }

    public function apiConciliar(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $contables = $request->input('contables');
        $bancos = $request->input('bancos');
        $contable = $request->input('contable');
        $banco = $request->input('banco');

        if (! is_array($contables) && is_array($contable)) {
            $contables = [$contable];
        }
        if (! is_array($bancos) && is_array($banco)) {
            $bancos = [$banco];
        }

        if ($empresaId <= 0 || $cuentacajaId <= 0 || ! is_array($contables) || ! is_array($bancos) || $contables === [] || $bancos === []) {
            return response()->json(['ok' => false, 'error' => 'Faltan los movimientos a cruzar.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            if (count($contables) === 1 && count($bancos) === 1) {
                $par = $this->service->conciliarManual(
                    $empresaId,
                    $cuentacajaId,
                    $contables[0],
                    $bancos[0],
                    (int) Auth::id(),
                    $request->boolean('forzar'),
                );

                return response()->json([
                    'ok' => true,
                    'tipo' => 'par',
                    'par_id' => (int) $par->id,
                ]);
            }

            $grupo = $this->service->conciliarGrupoManual(
                $empresaId,
                $cuentacajaId,
                $contables,
                $bancos,
                (int) Auth::id(),
                $request->boolean('forzar'),
            );

            return response()->json([
                'ok' => true,
                'tipo' => 'grupo',
                'grupo_id' => (int) ($grupo->id ?? 0),
                'n_contable' => count($contables),
                'n_banco' => count($bancos),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiDesconciliar(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $parId = (int) $request->input('par_id');
        $grupoId = (int) $request->input('grupo_id');

        if ($empresaId <= 0 || $cuentacajaId <= 0 || ($parId <= 0 && $grupoId <= 0)) {
            return response()->json(['ok' => false, 'error' => 'Falta el cruce a desconciliar.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            if ($grupoId > 0) {
                $this->service->desconciliarGrupo($empresaId, $cuentacajaId, $grupoId);
            } else {
                $this->service->desconciliar($empresaId, $cuentacajaId, $parId);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiImportarExtracto(
        Request $request,
        ConciliacionBancariaExtractoArchivoImportService $importService,
    ) {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta de caja requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
        if (! $request->hasFile('archivo')) {
            return response()->json(['ok' => false, 'error' => 'Adjuntá un Excel, CSV u OFX.'], 422);
        }

        $archivo = $request->file('archivo');
        if (! $archivo || ! $archivo->isValid()) {
            return response()->json(['ok' => false, 'error' => 'Archivo inválido.'], 422);
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'ofx', 'qfx'], true)) {
            return response()->json(['ok' => false, 'error' => 'Formatos: xlsx, csv, ofx.'], 422);
        }

        $tmp = $archivo->storeAs(
            'tmp/conciliacion_extracto',
            uniqid('ext_', true).'.'.$ext,
            'local'
        );
        $ruta = storage_path('app/'.$tmp);

        try {
            $resultado = $importService->importar($ruta, $empresaId, $cuentacajaId, true);
        } catch (\Throwable $e) {
            @unlink($ruta);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        @unlink($ruta);

        return response()->json(['ok' => true] + $resultado);
    }

    public function apiCerrarPeriodo(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $ejec = $this->service->cerrarPeriodo(
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
                (int) Auth::id(),
            );
            $files = $request->file('evidencias');
            if (is_array($files) && $files !== []) {
                $this->service->adjuntarEvidencia(
                    (int) $ejec->id,
                    $files,
                    'cierre',
                    (int) Auth::id(),
                );
            } elseif ($request->hasFile('evidencia')) {
                $this->service->adjuntarEvidencia(
                    (int) $ejec->id,
                    [$request->file('evidencia')],
                    'cierre',
                    (int) Auth::id(),
                );
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'ejecucion_id' => (int) $ejec->id,
            'estado' => (string) $ejec->estado,
            'cerrado_at' => $ejec->cerrado_at?->format('d/m/Y H:i'),
            'archivos' => $ejec->archivos()->count(),
        ]);
    }

    public function apiReabrirPeriodo(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $ejec = $this->service->reabrirPeriodo($empresaId, $cuentacajaId, $mes, $anio);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'ejecucion_id' => (int) $ejec->id,
            'estado' => (string) $ejec->estado,
        ]);
    }

    public function apiAprobarPeriodo(Request $request)
    {
        can('aprobar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $ejec = $this->service->aprobarPeriodo(
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
                (int) Auth::id(),
            );
            $files = $request->file('evidencias');
            if (is_array($files) && $files !== []) {
                $this->service->adjuntarEvidencia(
                    (int) $ejec->id,
                    $files,
                    'aprobacion',
                    (int) Auth::id(),
                );
            } elseif ($request->hasFile('evidencia')) {
                $this->service->adjuntarEvidencia(
                    (int) $ejec->id,
                    [$request->file('evidencia')],
                    'aprobacion',
                    (int) Auth::id(),
                );
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'ejecucion_id' => (int) $ejec->id,
            'estado' => (string) $ejec->estado,
            'aprobado_at' => $ejec->aprobado_at?->format('d/m/Y H:i'),
            'archivos' => $ejec->archivos()->count(),
        ]);
    }

    public function apiAdjuntarEvidencia(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');
        $ejecucionId = (int) $request->input('ejecucion_id');
        $etapa = (string) $request->input('etapa', 'cierre');
        $files = $request->file('evidencias');
        if (! is_array($files) || $files === []) {
            $files = $request->hasFile('evidencia') ? [$request->file('evidencia')] : [];
        }
        if ($ejecucionId <= 0 || $files === []) {
            return response()->json(['ok' => false, 'error' => 'Falta ejecución o archivo.'], 422);
        }
        $ejec = \App\Models\Contable\ConciliacionBancariaEjecucion::query()->find($ejecucionId);
        if ($ejec === null || ! $this->empresaRepository->empresaIdPermitida((int) $ejec->empresa_id)) {
            abort(403);
        }
        try {
            $guardados = $this->service->adjuntarEvidencia(
                $ejecucionId,
                $files,
                $etapa,
                (int) Auth::id(),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'n' => count($guardados),
            'archivo' => $guardados[0]->nombre_original ?? ($guardados[0]->nombrearchivo ?? null),
            'evidencias' => $this->service->listarEvidencias($ejecucionId),
        ]);
    }

    public function apiListarEvidencia(Request $request)
    {
        $this->assertPuedeVerEvidencia();
        $ejecucionId = (int) $request->input('ejecucion_id');
        if ($ejecucionId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Falta ejecución.'], 422);
        }
        $ejec = \App\Models\Contable\ConciliacionBancariaEjecucion::query()->find($ejecucionId);
        if ($ejec === null || ! $this->empresaRepository->empresaIdPermitida((int) $ejec->empresa_id)) {
            abort(403);
        }

        return response()->json([
            'ok' => true,
            'ejecucion_id' => $ejecucionId,
            'evidencias' => $this->service->listarEvidencias($ejecucionId),
        ]);
    }

    public function apiDescargarEvidencia(int $archivoId)
    {
        $this->assertPuedeVerEvidencia();
        $archivo = \App\Models\Contable\ConciliacionBancariaEjecucionArchivo::query()->findOrFail($archivoId);
        $ejec = $archivo->ejecucion;
        if ($ejec === null || ! $this->empresaRepository->empresaIdPermitida((int) $ejec->empresa_id)) {
            abort(403);
        }
        $path = $this->service->rutaEvidencia($archivo);
        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }
        $nombre = (string) ($archivo->nombre_original ?: $archivo->nombrearchivo);

        return response()->download($path, $nombre);
    }

    private function assertPuedeVerEvidencia(): void
    {
        if (can('ejecutar-conciliacion-bancaria', false) || can('listar-conciliacion-bancaria', false)) {
            return;
        }
        can('listar-conciliacion-bancaria');
    }

    public function apiForecast(Request $request)
    {
        can('listar-conciliacion-bancaria-aging');
        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));
        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
        $forecast = \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaForecastSupport::desdeAging(
            $this->service,
            $empresaId,
            $cuentacajaId,
            $mes,
            $anio,
        );

        return response()->json(['ok' => true, 'forecast' => $forecast]);
    }

    public function apiAlinearExcel(
        Request $request,
        ConciliacionBancariaAlinearExcelService $alinearService,
    ) {
        can('alinear-conciliacion-bancaria-excel');

        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));
        $aplicar = $request->boolean('aplicar');

        if (! $request->hasFile('archivo')) {
            return response()->json(['ok' => false, 'error' => 'Adjuntá el Excel de Contaduría.'], 422);
        }
        $archivo = $request->file('archivo');
        if (! $archivo || ! $archivo->isValid()) {
            return response()->json(['ok' => false, 'error' => 'Archivo inválido.'], 422);
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls'], true)) {
            return response()->json(['ok' => false, 'error' => 'Solo Excel Contaduría (.xlsx).'], 422);
        }

        $tmp = $archivo->storeAs(
            'tmp/conciliacion_alinear',
            uniqid('alin_', true).'.'.$ext,
            'local'
        );
        $ruta = storage_path('app/'.$tmp);

        try {
            $resultado = $alinearService->alinearArchivo(
                $ruta,
                $mes,
                $anio,
                $aplicar,
                (int) Auth::id(),
            );
        } catch (\Throwable $e) {
            @unlink($ruta);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        @unlink($ruta);

        return response()->json([
            'ok' => true,
            'aplicar' => $aplicar,
            'resultado' => $resultado,
        ]);
    }

    public function apiGuardarModo(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $modo = (string) $request->input('modo', '');

        if ($empresaId <= 0 || ! ConciliacionBancariaModoSupport::esValido($modo)) {
            return response()->json(['ok' => false, 'error' => 'Empresa y modo válidos requeridos.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $resultado = ConciliacionBancariaModoSupport::guardarParaEmpresa($empresaId, $modo);
        $prefs = $resultado['persistido']
            ? ConciliacionBancariaModoSupport::preferenciasUi($empresaId)
            : ConciliacionBancariaModoSupport::preferenciasDesdeModo($resultado['modo']);

        return response()->json([
            'ok' => true,
            'modo' => $resultado['modo'],
            'preferencias' => $prefs,
            'persistido' => $resultado['persistido'],
        ]);
    }

    public function apiGenerarGastos(Request $request)
    {
        can('ejecutar-conciliacion-bancaria');
        can('crear-ingresos-egresos-caja');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa y cuenta de caja requeridas.'], 422);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        try {
            $resultado = $this->gastoDocumentoService->generar($empresaId, $cuentacajaId, $mes, $anio);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }
}
