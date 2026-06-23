<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\CotElectronico\ArbaCotPresentacionService;
use App\Services\Ventas\CotElectronico\CotElectronicoService;
use App\Support\Ventas\CuitFormatoValidacionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CotElectronicoController extends Controller
{
    public function __construct(
        private CotElectronicoService $service,
        private ArbaCotPresentacionService $presentacionService,
    ) {}

    public function index(Request $request)
    {
        can('procesar-cot-electronico');

        $fecha = $request->input('fecha', now()->format('Y-m-d'));
        $consultado = $request->boolean('consultar');
        $procesado = $request->boolean('procesar');

        $repartos = $this->normalizarRepartosRequest($request);
        $remitos = [];
        $resultadoProceso = null;
        $errorCuit = null;

        if ($consultado || $procesado) {
            $errorCuit = CuitFormatoValidacionSupport::primerErrorEnRepartos($repartos);

            if ($errorCuit === null) {
                $preview = $this->service->preview(Carbon::parse($fecha), $repartos);
                $repartos = $preview['repartos'];
                $remitos = $preview['remitos'];
            }
        }

        if ($procesado && $errorCuit === null) {
            $claves = array_values(array_filter((array) $request->input('remitos_seleccionados', [])));
            $resultadoProceso = $this->service->procesar(Carbon::parse($fecha), $repartos, $claves);

            if ($resultadoProceso['ok'] ?? false) {
                $preview = $this->service->preview(Carbon::parse($fecha), $repartos);
                $remitos = $preview['remitos'];
            }
        }

        return view('ventas.cot_electronico.index', [
            'fecha' => $fecha,
            'repartos' => $repartos,
            'remitos' => $remitos,
            'consultado' => $consultado || $procesado,
            'resultadoProceso' => $resultadoProceso,
            'resultadoPruebaConexion' => session('resultadoPruebaConexion'),
            'errorCuit' => $errorCuit,
            'ambiente' => (string) config('arba_cot.ambiente', 'test'),
        ]);
    }

    public function probarConexion()
    {
        can('procesar-cot-electronico');

        $resultado = $this->presentacionService->probarConexion();

        return redirect()
            ->route('cot_electronico')
            ->with('resultadoPruebaConexion', $resultado);
    }

    /** @return list<array<string, mixed>> */
    private function normalizarRepartosRequest(Request $request): array
    {
        $codigos = (array) $request->input('reparto_codigo', []);
        $nombres = (array) $request->input('reparto_nombre', []);
        $ids = (array) $request->input('reparto_transporte_id', []);
        $patentes = (array) $request->input('reparto_patente', []);
        $cuits = (array) $request->input('reparto_cuit_chofer', []);

        $repartos = [];
        $total = max(count($codigos), count($ids));

        for ($i = 0; $i < $total; $i++) {
            $codigo = trim((string) ($codigos[$i] ?? ''));
            $transporteId = (int) ($ids[$i] ?? 0);
            if ($codigo === '' && $transporteId < 1) {
                continue;
            }

            $repartos[] = [
                'transporte_id' => $transporteId,
                'codigo' => $codigo,
                'nombre' => trim((string) ($nombres[$i] ?? '')),
                'patente' => trim((string) ($patentes[$i] ?? '')),
                'cuit_chofer' => CuitFormatoValidacionSupport::formatear(trim((string) ($cuits[$i] ?? ''))),
            ];
        }

        return $repartos;
    }
}
