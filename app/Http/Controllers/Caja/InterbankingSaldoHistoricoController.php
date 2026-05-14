<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\InterbankingSaldoHistoricoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\InterbankingSaldoDiario;
use App\Repositories\Caja\BancoRepositoryInterface;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class InterbankingSaldoHistoricoController extends Controller
{
    public function __construct(
        private BancoRepositoryInterface $bancoRepository
    ) {}

    public function index(Request $request)
    {
        can('listar-saldos-interbanking-historico');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');

        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        [$fechaDesde, $fechaHasta, $empresaId] = $this->resolverRangoYEmpresa($request, $empresaIdsPermitidas);

        $query = $this->historicoQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId);

        $registros = $query->paginate(50)->appends($request->query())->through(function (InterbankingSaldoDiario $r) {
            $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->bank_number));

            return $r;
        });

        $empresas = $user->usuario_empresas->sortBy('nombre');

        return view('caja.interbanking.saldos_historicos', compact(
            'registros',
            'empresas',
            'empresaId',
            'fechaDesde',
            'fechaHasta'
        ));
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-saldos-interbanking-historico');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        [$fechaDesde, $fechaHasta, $empresaId] = $this->resolverRangoYEmpresa($request, $empresaIdsPermitidas);

        $registros = $this->historicoQuery($request, $empresaIdsPermitidas, $fechaDesde, $fechaHasta, $empresaId)
            ->get()
            ->map(function (InterbankingSaldoDiario $r) {
                $r->setAttribute('nombrebanco', $this->resolverNombreBanco($r->bank_number));
                $r->setAttribute('nombreempresa', $r->empresa->nombre ?? '');

                return $r;
            });

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.interbanking.listado_saldos_historicos', compact('registros'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_interbanking_saldos_historicos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new InterbankingSaldoHistoricoExport($registros))
                    ->download('interbanking_saldos_historicos.xlsx');

            case 'CSV':
                return (new InterbankingSaldoHistoricoExport($registros))
                    ->download('interbanking_saldos_historicos.csv', Excel::CSV);

            default:
                abort(404);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int|null}
     */
    private function resolverRangoYEmpresa(Request $request, array $empresaIdsPermitidas): array
    {
        $empresaId = $request->integer('empresa_id') ?: null;
        if ($empresaId !== null && ! in_array($empresaId, $empresaIdsPermitidas, true)) {
            abort(403);
        }

        $fechaHasta = $request->input('fecha_hasta')
            ? Carbon::parse($request->input('fecha_hasta'))->endOfDay()
            : Carbon::now()->endOfDay();
        $fechaDesde = $request->input('fecha_desde')
            ? Carbon::parse($request->input('fecha_desde'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        return [$fechaDesde, $fechaHasta, $empresaId];
    }

    private function historicoQuery(
        Request $request,
        array $empresaIdsPermitidas,
        Carbon $fechaDesde,
        Carbon $fechaHasta,
        ?int $empresaId
    ): Builder {
        $query = InterbankingSaldoDiario::query()
            ->with('empresa:id,nombre')
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
            ->orderByDesc('fecha')
            ->orderBy('empresa_id')
            ->orderBy('bank_number')
            ->orderBy('account_number');

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->string('currency')->toString());
        }

        if ($request->filled('account_number')) {
            $q = '%'.$request->string('account_number')->toString().'%';
            $query->where('account_number', 'like', $q);
        }

        return $query;
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
}
