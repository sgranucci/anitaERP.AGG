<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaDiariaGeneradorSupport;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaSaldosInterbankingSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Posición bancaria diaria (tesorería): Saldos IB + cheques ERP + Disponible HOY.
 * Menú Caja / Reportes de integración.
 */
class PosicionBancariaDiariaController extends Controller
{
    public function __construct(
        private readonly PosicionBancariaDiariaGeneradorSupport $generador,
        private readonly PosicionBancariaSaldosInterbankingSupport $saldosIb,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('generar-posicion-bancaria-diaria');

        $fecha = $this->resolverFecha($request);
        $cotizUsd = $request->filled('cotizacion_usd')
            ? (float) $request->input('cotizacion_usd')
            : (CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, 2) ?? null);
        $cotizEur = $request->filled('cotizacion_eur')
            ? (float) $request->input('cotizacion_eur')
            : (CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, 3) ?? null);

        $preview = null;
        if ($request->boolean('consultar')) {
            $preview = $this->saldosIb->saldosPorCodigo($fecha);
        }
        $proyeccion = $this->proyeccionDesdeRequest($request);

        return view('caja.posicion_bancaria_diaria.index', [
            'fecha' => $fecha->toDateString(),
            'cotizacion_usd' => $cotizUsd,
            'cotizacion_eur' => $cotizEur,
            'dias_proyectados' => $proyeccion['dias'],
            'salto_dias' => $proyeccion['salto'],
            'consultado' => $request->boolean('consultar'),
            'preview' => $preview,
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        can('generar-posicion-bancaria-diaria');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $fecha = $this->resolverFecha($request);
        $cotizUsd = $request->filled('cotizacion_usd') ? (float) $request->input('cotizacion_usd') : null;
        $cotizEur = $request->filled('cotizacion_eur') ? (float) $request->input('cotizacion_eur') : null;
        $proyeccion = $this->proyeccionDesdeRequest($request);

        try {
            $resultado = $this->generador->generar(
                $fecha,
                null,
                $cotizUsd,
                $cotizEur,
                null,
                $proyeccion['dias'],
                $proyeccion['salto'],
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('posicion_bancaria_diaria', [
                    'fecha' => $fecha->toDateString(),
                    'cotizacion_usd' => $cotizUsd,
                    'cotizacion_eur' => $cotizEur,
                    'dias_proyectados' => $proyeccion['dias'],
                    'salto_dias' => $proyeccion['salto'],
                    'consultar' => 1,
                ])
                ->with('errores', 'No se pudo generar la posición: '.$e->getMessage());
        }

        $nombre = 'Posicion_Bancos_'.$fecha->format('Y-m-d').'.xlsx';
        $response = response()->download($resultado['path'], $nombre)->deleteFileAfterSend(true);

        if ($resultado['advertencias'] !== []) {
            session()->flash('mensaje', implode(' ', $resultado['advertencias']));
        }

        return $response;
    }

    /**
     * @return array{dias: int, salto: int}
     */
    private function proyeccionDesdeRequest(Request $request): array
    {
        $dias = $request->filled('dias_proyectados') ? (int) $request->input('dias_proyectados') : 5;
        $salto = $request->filled('salto_dias') ? (int) $request->input('salto_dias') : 1;

        return [
            'dias' => max(0, min(31, $dias)),
            'salto' => max(1, min(15, $salto)),
        ];
    }

    private function resolverFecha(Request $request): Carbon
    {
        $raw = trim((string) $request->input('fecha', ''));
        if ($raw === '') {
            return Carbon::today();
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            return Carbon::today();
        }
    }
}
