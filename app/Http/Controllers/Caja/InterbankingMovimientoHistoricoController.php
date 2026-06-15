<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\InterbankingMovimientoHistoricoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\InterbankingMovimiento;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Services\Caja\InterbankingMovimientoPersistenciaService;
use App\Services\Caja\InterbankingService;
use App\Exceptions\Contable\PeriodoContableCerradoException;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel;

class InterbankingMovimientoHistoricoController extends Controller
{
    public function __construct(
        private BancoRepositoryInterface $bancoRepository,
        private InterbankingService $interbankingService,
        private InterbankingMovimientoPersistenciaService $movimientoPersistenciaService
    ) {}

    public function index(Request $request): View
    {
        can('listar-interbanking-movimientos-persistidos');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $empresaId = $request->integer('empresa_id') ?: null;
        if ($empresaId !== null && ! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        [$fechaDesde, $fechaHasta] = $this->resolverRangoFechas($request);

        $query = $this->movimientosQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId);

        $registros = $query->paginate(50)->appends($request->query())->through(function (InterbankingMovimiento $r) {
            $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->bank_number));

            return $r;
        });

        $empresas = $user->usuario_empresas->sortBy('nombre');

        $prefill = [
            'empresa_id' => $request->filled('empresa_id') ? $request->integer('empresa_id') : null,
            'account_number' => $request->string('account_number')->toString(),
            'bank_number' => $request->string('bank_number')->toString(),
            'currency' => $request->string('currency')->toString() ?: 'ARS',
            'account_type' => $request->string('account_type')->toString() ?: 'CC',
        ];

        return view('caja.interbanking.movimientos_historicos', compact(
            'registros',
            'empresas',
            'empresaId',
            'fechaDesde',
            'fechaHasta',
            'prefill'
        ));
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-interbanking-movimientos-persistidos');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $empresaId = $request->integer('empresa_id') ?: null;
        if ($empresaId !== null && ! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        [$fechaDesde, $fechaHasta] = $this->resolverRangoFechas($request);

        $registros = $this->movimientosQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId)
            ->get()
            ->map(function (InterbankingMovimiento $r) {
                $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->bank_number));
                $r->setAttribute('nombreempresa', $r->empresa->nombre ?? '');

                return $r;
            });

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.interbanking.listado_movimientos_historicos', compact('registros'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_interbanking_movimientos_historicos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new InterbankingMovimientoHistoricoExport($registros))
                    ->download('interbanking_movimientos_historicos.xlsx');

            case 'CSV':
                return (new InterbankingMovimientoHistoricoExport($registros))
                    ->download('interbanking_movimientos_historicos.csv', Excel::CSV);

            default:
                abort(404);
        }
    }

    public function sincronizar(Request $request): RedirectResponse
    {
        can('sincronizar-interbanking-movimientos');

        $validated = $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'account_number' => 'required|string|max:64',
            'bank_number' => 'required|string|max:8',
            'account_type' => 'nullable|string|in:CC,CA',
            'currency' => 'nullable|string|in:ARS,USD',
            'movement_type' => 'required|string|in:dia,diferidos,anteriores,zughus',
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

        $bankNumber = $this->normalizarBankNumber3($validated['bank_number']);
        if (! preg_match('/^[0-9]{3}$/', $bankNumber)) {
            Session::flash('errores', ['El código de banco debe poder normalizarse a 3 dígitos numéricos.']);

            return redirect()->route('interbanking_movimientos_persistidos', array_merge(
                $request->only([
                    'empresa_id', 'fecha_desde', 'fecha_hasta', 'currency', 'account_number', 'bank_number', 'movement_type',
                ]),
                ['abrir_sincronizacion' => 1]
            ));
        }

        try {
            $resultado = $this->movimientoPersistenciaService->sincronizarDesdeApi(
                $this->interbankingService,
                $empresaId,
                trim($validated['account_number']),
                $bankNumber,
                $validated['account_type'] ?? 'CC',
                $validated['currency'] ?? 'ARS',
                $validated['movement_type'],
                $validated['date_since'] ?? null,
                $validated['date_until'] ?? null,
                200,
                80
            );
        } catch (PeriodoContableCerradoException $e) {
            Session::flash('errores', [$e->getMessage()]);

            return redirect()->route('interbanking_movimientos_persistidos', array_merge(
                $request->only([
                    'empresa_id', 'fecha_desde', 'fecha_hasta', 'currency', 'account_number', 'bank_number', 'movement_type',
                ]),
                ['abrir_sincronizacion' => 1]
            ));
        }

        $abrirSincronizacion = false;
        if (! $resultado['ok']) {
            Session::flash('errores', ['Interbanking: '.($resultado['error'] ?? 'Error al sincronizar.')]);
            $abrirSincronizacion = true;
        } else {
            Session::flash('mensaje', 'Sincronización finalizada: '.$resultado['filas_guardadas'].' movimiento(s) procesados en '.$resultado['paginas'].' página(s) de API.');
        }

        return redirect()->route('interbanking_movimientos_persistidos', array_filter([
            'empresa_id' => $empresaId,
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            'currency' => $validated['currency'] ?? null,
            'account_number' => $validated['account_number'],
            'bank_number' => $bankNumber,
            'movement_type' => $validated['movement_type'],
            'abrir_sincronizacion' => $abrirSincronizacion ? 1 : null,
        ], fn ($v) => $v !== null && $v !== ''));
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
            : Carbon::now()->subDays(30)->startOfDay();

        return [$fechaDesde, $fechaHasta];
    }

    private function movimientosQuery(
        Request $request,
        array $empresaIdsPermitidas,
        Carbon $fechaDesde,
        Carbon $fechaHasta,
        ?int $empresaId
    ): Builder {
        $query = InterbankingMovimiento::query()
            ->with('empresa:id,nombre')
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->whereBetween('process_date', [$fechaDesde, $fechaHasta])
            ->orderByDesc('process_date')
            ->orderByDesc('id');

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->string('currency')->toString());
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->string('movement_type')->toString());
        }

        if ($request->filled('account_number')) {
            $q = '%'.$request->string('account_number')->toString().'%';
            $query->where('account_number', 'like', $q);
        }

        if ($request->filled('bank_number')) {
            $query->where('bank_number', $this->normalizarBankNumber3($request->string('bank_number')->toString()));
        }

        return $query;
    }
}
