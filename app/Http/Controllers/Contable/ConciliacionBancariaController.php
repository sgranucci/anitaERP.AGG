<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ConciliacionBancariaExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaEngancheSupport;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ConciliacionBancariaController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
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
            $cuentasCaja = Cuentacaja::query()
                ->paraEmpresa($empresaId)
                ->with('cuentacontables')
                ->whereNotNull('cuenta_interbanking')
                ->where('cuenta_interbanking', '!=', '')
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
                    true,
                );
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
        ], fn ($v) => $v !== null && $v !== '');

        return view('contable.conciliacion_bancaria.index', [
            'empresa_query' => $empresaQuery,
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
        can('exportar-conciliacion-bancaria');

        $empresaId = (int) $request->input('empresa_id');
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $mes = max(1, min(12, (int) $request->input('mes')));
        $anio = max(2000, (int) $request->input('anio'));

        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return redirect()->route('conciliacion_bancaria');
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        if (strtoupper($formato) !== 'EXCEL') {
            return redirect()->route('conciliacion_bancaria', $request->query());
        }

        $resultado = $this->service->ejecutar($empresaId, $cuentacajaId, $mes, $anio, (int) Auth::id(), false);
        $cc = $resultado['cuentacaja'];
        $nombre = sprintf('conciliacion_bancaria_%s_%02d_%d.xlsx', $cc->codigo ?? 'cuenta', $mes, $anio);

        return (new ConciliacionBancariaExport)->parametros($resultado)->descargar($nombre);
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
            ->whereIn('codigo', $variantes)
            ->whereNotNull('cuenta_interbanking')
            ->where('cuenta_interbanking', '!=', '')
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
}
