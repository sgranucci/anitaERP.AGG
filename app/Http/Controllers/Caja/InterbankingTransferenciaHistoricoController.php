<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\InterbankingTransferenciaHistoricoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\InterbankingTransferencia;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Services\Caja\InterbankingService;
use App\Services\Caja\InterbankingTransferenciaPersistenciaService;
use App\Support\Caja\InterbankingTransferenciaComprobanteSupport;
use App\Exceptions\Contable\PeriodoContableCerradoException;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel;

class InterbankingTransferenciaHistoricoController extends Controller
{
    public function __construct(
        private BancoRepositoryInterface $bancoRepository,
        private InterbankingService $interbankingService,
        private InterbankingTransferenciaPersistenciaService $transferenciaPersistenciaService,
        private InterbankingTransferenciaComprobanteSupport $comprobanteSupport
    ) {}

    public function index(Request $request): View
    {
        can('listar-interbanking-transferencias-persistidas');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $empresaId = $request->integer('empresa_id') ?: null;
        if ($empresaId !== null && ! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        [$fechaDesde, $fechaHasta] = $this->resolverRangoFechas($request);

        $query = $this->transferenciasQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId);

        $registros = $query->paginate(10)->appends($request->query())->through(function (InterbankingTransferencia $r) {
            $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->debit_bank_number));

            $credito = $this->comprobanteSupport->cuentaResumen(
                $r->credit_account_json,
                $r->credit_account,
                null
            );
            $r->setAttribute('credito_banco', $credito['banco']);
            $r->setAttribute('credito_denominacion', $credito['denominacion']);
            $r->setAttribute('credito_cuit', $credito['cuit']);
            $r->setAttribute('credito_cbu', $credito['cbu']);
            $r->setAttribute('debito_cbu', $this->comprobanteSupport->cbuCuenta(
                $r->debit_account_json,
                $r->debit_account,
                $r->debit_bank_number
            ));

            return $r;
        });

        $empresas = $user->usuario_empresas->sortBy('nombre');

        $prefill = [
            'empresa_id' => $request->filled('empresa_id') ? $request->integer('empresa_id') : null,
            'debit_account_number' => $request->string('debit_account_number')->toString(),
            'debit_bank_number' => $request->string('debit_bank_number')->toString(),
            'debit_currency' => $request->string('debit_currency')->toString() ?: 'ARS',
            'debit_account_type' => $request->string('debit_account_type')->toString() ?: 'CC',
        ];

        return view('caja.interbanking.transferencias_historicas', compact(
            'registros',
            'empresas',
            'empresaId',
            'fechaDesde',
            'fechaHasta',
            'prefill'
        ));
    }

    public function detalle(int $id)
    {
        can('listar-interbanking-transferencias-persistidas');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($i) => (int) $i)->all();

        $registro = InterbankingTransferencia::query()->findOrFail($id);
        if (! in_array((int) $registro->empresa_id, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        $secciones = $this->comprobanteSupport->seccionesDetalleModal($registro);
        $titulo = 'Transferencia #'.($registro->transfer_id ?? $registro->id);

        return response()->json([
            'ok' => true,
            'titulo' => $titulo,
            'html' => view('caja.interbanking.partials.detalle_transferencia_contenido', compact('secciones'))->render(),
        ]);
    }

    public function comprobante(Request $request, int $id)
    {
        $this->canVerComprobanteTransferencia();

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($i) => (int) $i)->all();

        $registro = InterbankingTransferencia::query()
            ->with('empresa:id,nombre')
            ->findOrFail($id);

        if (! in_array((int) $registro->empresa_id, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        $datos = $this->comprobanteSupport->datosDesdeModelo($registro);
        $nombre = $this->comprobanteSupport->nombreArchivoPdf($registro);

        $html = view('caja.interbanking.comprobante_transferencia', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $request->boolean('inline')
            ? $pdf->stream($nombre)
            : $pdf->download($nombre);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-interbanking-transferencias-persistidas');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $empresaId = $request->integer('empresa_id') ?: null;
        if ($empresaId !== null && ! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        [$fechaDesde, $fechaHasta] = $this->resolverRangoFechas($request);

        $registros = $this->transferenciasQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId)
            ->get()
            ->map(function (InterbankingTransferencia $r) {
                $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->debit_bank_number));
                $r->setAttribute('nombreempresa', $r->empresa->nombre ?? '');

                return $r;
            });

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.interbanking.listado_transferencias_historicas', compact('registros'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_interbanking_transferencias_historicas';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new InterbankingTransferenciaHistoricoExport($registros))
                    ->download('interbanking_transferencias_historicas.xlsx');

            case 'CSV':
                return (new InterbankingTransferenciaHistoricoExport($registros, true))
                    ->download('interbanking_transferencias_historicas.csv', Excel::CSV);

            default:
                abort(404);
        }
    }

    public function sincronizar(Request $request): RedirectResponse
    {
        can('sincronizar-interbanking-transferencias');

        $validated = $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'debit_account_number' => 'nullable|string|max:64',
            'debit_account_type' => 'nullable|string|in:CC,CA',
            'debit_bank_number' => 'nullable|string|max:8',
            'debit_currency' => 'nullable|string|in:ARS,USD',
            'credit_account_number' => 'nullable|string|max:64',
            'credit_account_type' => 'nullable|string|in:CC,CA',
            'credit_bank_number' => 'nullable|string|regex:/^[0-9]{3}$/',
            'credit_currency' => 'nullable|string|in:ARS,USD',
            'date_since' => 'nullable|date_format:Y-m-d',
            'date_until' => 'nullable|date_format:Y-m-d',
        ]);

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $empresaId = (int) $validated['empresa_id'];
        if (! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        $debitBank = null;
        if (! empty($validated['debit_bank_number'])) {
            $debitBank = $this->normalizarBankNumber3($validated['debit_bank_number']);
            if (! preg_match('/^[0-9]{3}$/', $debitBank)) {
                Session::flash('errores', ['El código de banco débito debe poder normalizarse a 3 dígitos numéricos.']);

                return redirect()->route('interbanking_transferencias_persistidas', array_merge(
                    $request->only([
                        'empresa_id', 'fecha_desde', 'fecha_hasta', 'debit_currency', 'debit_account_number', 'debit_bank_number',
                    ]),
                    ['abrir_sincronizacion' => 1]
                ));
            }
        }

        $filtrosApi = array_filter([
            'debit_account_number' => $validated['debit_account_number'] ?? null,
            'debit_account_type' => $validated['debit_account_type'] ?? null,
            'debit_bank_number' => $debitBank,
            'debit_currency' => $validated['debit_currency'] ?? null,
            'credit_account_number' => $validated['credit_account_number'] ?? null,
            'credit_account_type' => $validated['credit_account_type'] ?? null,
            'credit_bank_number' => $validated['credit_bank_number'] ?? null,
            'credit_currency' => $validated['credit_currency'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $resultado = $this->transferenciaPersistenciaService->sincronizarDesdeApi(
                $this->interbankingService,
                $empresaId,
                $filtrosApi,
                $validated['date_since'] ?? null,
                $validated['date_until'] ?? null,
                100,
                80
            );
        } catch (PeriodoContableCerradoException $e) {
            Session::flash('errores', [$e->getMessage()]);

            return redirect()->route('interbanking_transferencias_persistidas', array_merge(
                $request->only([
                    'empresa_id', 'fecha_desde', 'fecha_hasta', 'debit_currency', 'debit_account_number', 'debit_bank_number',
                ]),
                ['abrir_sincronizacion' => 1]
            ));
        }

        $abrirSincronizacion = false;
        if (! $resultado['ok']) {
            Session::flash('errores', ['Interbanking: '.($resultado['error'] ?? 'Error al sincronizar.')]);
            $abrirSincronizacion = true;
        } else {
            Session::flash('mensaje', 'Sincronización finalizada: '.$resultado['filas_guardadas'].' transferencia(s) procesadas en '.$resultado['paginas'].' página(s) de API.');
        }

        $fechaDesdeListado = $validated['date_since']
            ?? $request->input('fecha_desde')
            ?? Carbon::now()->subDays(60)->format('Y-m-d');
        $fechaHastaListado = $validated['date_until']
            ?? $request->input('fecha_hasta')
            ?? Carbon::now()->format('Y-m-d');

        return redirect()->route('interbanking_transferencias_persistidas', array_filter([
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesdeListado,
            'fecha_hasta' => $fechaHastaListado,
            'debit_currency' => $validated['debit_currency'] ?? null,
            'debit_account_number' => $validated['debit_account_number'] ?? null,
            'debit_bank_number' => $debitBank,
            'abrir_sincronizacion' => $abrirSincronizacion ? 1 : null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function canVerComprobanteTransferencia(): void
    {
        if (can('listar-interbanking-transferencias-persistidas', false)) {
            return;
        }
        if (can('ver-transferencias-cuenta-interbanking', false)) {
            return;
        }

        can('listar-interbanking-transferencias-persistidas');
    }

    private function normalizarBankNumber3(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        $d = ltrim($d, '0');
        if ($d === '') {
            $d = '0';
        }

        return str_pad($d, 3, '0', STR_PAD_LEFT);
    }

    private function resolverNombreBanco(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return 'Banco no encontrado';
        }

        $str = (string) $codigo;
        $sinCerosIzq = ltrim($str, '0');
        $sinCerosIzq = $sinCerosIzq === '' ? '0' : $sinCerosIzq;

        $candidatos = array_unique(array_filter([
            $str,
            str_pad($sinCerosIzq, 3, '0', STR_PAD_LEFT),
            str_pad($sinCerosIzq, 4, '0', STR_PAD_LEFT),
        ]));

        foreach ($candidatos as $c) {
            $banco = $this->bancoRepository->findPorCodigo($c);
            if ($banco) {
                return $banco->nombre;
            }
        }

        return 'Banco no encontrado';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRangoFechas(Request $request): array
    {
        $fechaHasta = $request->input('fecha_hasta')
            ? Carbon::parse($request->input('fecha_hasta'))->endOfDay()
            : Carbon::now()->endOfDay();
        $fechaDesde = $request->input('fecha_desde')
            ? Carbon::parse($request->input('fecha_desde'))->startOfDay()
            : Carbon::now()->subDays(60)->startOfDay();

        return [$fechaDesde, $fechaHasta];
    }

    private function transferenciasQuery(
        Request $request,
        array $empresaIdsPermitidas,
        Carbon $fechaDesde,
        Carbon $fechaHasta,
        ?int $empresaId
    ): Builder {
        $query = InterbankingTransferencia::query()
            ->with('empresa:id,nombre')
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->whereBetween('request_date', [$fechaDesde, $fechaHasta])
            ->orderByDesc('request_date')
            ->orderByDesc('id');

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        if ($request->filled('debit_currency')) {
            $query->where('debit_currency', $request->string('debit_currency')->toString());
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->string('currency')->toString());
        }

        if ($request->filled('debit_account_number')) {
            $q = '%'.$request->string('debit_account_number')->toString().'%';
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('debit_account_number', 'like', $q)
                    ->orWhere('debit_account', 'like', $q);
            });
        }

        if ($request->filled('debit_bank_number')) {
            $query->where('debit_bank_number', $this->normalizarBankNumber3($request->string('debit_bank_number')->toString()));
        }

        return $query;
    }
}
