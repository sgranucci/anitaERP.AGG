<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\MayorConceptoReporteService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MayorConceptoController extends Controller
{
    public function __construct(
        private readonly MayorConceptoReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
    }

    public function index()
    {
        can('listar-asiento');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();

        return view('contable.mayor_concepto.index', [
            'empresa_query' => $empresa_query,
            'moneda_query' => $moneda_query,
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function generar(Request $request)
    {
        can('listar-asiento');

        $rules = [
            'empresa_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'modo_periodo' => 'required|in:rango,mes',
            'solo_moneda_origen' => 'nullable|boolean',
        ];

        if ($request->modo_periodo === 'mes') {
            $rules['mes'] = 'required|integer|min:1|max:12';
            $rules['anio'] = 'required|integer|min:2000|max:2100';
        } else {
            $rules['fecha_desde'] = 'required|date';
            $rules['fecha_hasta'] = 'required|date|after_or_equal:fecha_desde';
        }

        $request->validate($rules);

        if (! $this->empresaRepository->empresaIdPermitida((int) $request->empresa_id)) {
            abort(403);
        }

        $usarMesCompleto = $request->modo_periodo === 'mes';
        $soloMonedaOrigen = (bool) $request->boolean('solo_moneda_origen');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '600');

        $resultado = $this->reporteService->generar(
            (int) $request->empresa_id,
            $request->fecha_desde,
            $request->fecha_hasta,
            $usarMesCompleto ? (int) $request->mes : null,
            $usarMesCompleto ? (int) $request->anio : null,
            $usarMesCompleto,
            (int) $request->moneda_id,
            $soloMonedaOrigen,
        );

        $empresa = $this->empresaRepository->find((int) $request->empresa_id);
        $moneda = $this->monedaRepository->find((int) $request->moneda_id);

        $fd = $resultado['parametros']['fecha_desde'];
        $fh = $resultado['parametros']['fecha_hasta'];

        return view('contable.mayor_concepto.resultado', [
            'resultado' => $resultado,
            'empresa' => $empresa,
            'moneda' => $moneda,
            'periodo_texto' => $this->formatearPeriodo($fd, $fh),
            'solo_moneda_origen' => $soloMonedaOrigen,
        ]);
    }

    private function formatearPeriodo(int $desdeYmd, int $hastaYmd): string
    {
        $d = Carbon::createFromFormat('Ymd', str_pad((string) $desdeYmd, 8, '0', STR_PAD_LEFT));
        $h = Carbon::createFromFormat('Ymd', str_pad((string) $hastaYmd, 8, '0', STR_PAD_LEFT));

        return $d->format('d/m/Y').' — '.$h->format('d/m/Y');
    }
}
