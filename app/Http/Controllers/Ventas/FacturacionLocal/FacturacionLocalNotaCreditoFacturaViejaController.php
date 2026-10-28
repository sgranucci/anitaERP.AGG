<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoFacturaViejaService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\LocalVentaAsignacionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FacturacionLocalNotaCreditoFacturaViejaController extends Controller
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoFacturaViejaService $service,
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('emitir-nc-factura-vieja-local');

        $locales = LocalVentaAsignacionSupport::localesParaUsuario();
        $localId = (int) $request->input('local_id', 0);
        if ($localId > 0 && $locales->contains('id', $localId)) {
            session(['facturacion_local.local_id' => $localId]);
        } else {
            $localId = (int) session('facturacion_local.local_id', $locales->first()?->id ?? 0);
        }
        if ($localId > 0 && ! $locales->contains('id', $localId)) {
            $localId = (int) ($locales->first()?->id ?? 0);
        }

        $local = $localId > 0
            ? LocalVenta::query()->with([
                'puntoventa:id,codigo,nombre',
                'deposito:id,codigo,nombre',
            ])->find($localId)
            : null;
        $turno = $local ? $this->turnoService->turnoAbierto((int) $local->id) : null;
        $contexto = FacturacionLocalPosContextoSupport::paraLocal($local, false);
        $hoy = Carbon::today();

        return view('ventas.facturacion_local.nota_credito_factura_vieja.index', [
            'locales' => $locales,
            'local' => $local,
            'turno' => $turno,
            'contexto' => $contexto,
            'fecha' => $hoy->format('Y-m-d'),
            'fecha_min' => $hoy->copy()->startOfMonth()->format('Y-m-d'),
            'fecha_max' => $hoy->format('Y-m-d'),
        ]);
    }

    public function emitir(Request $request)
    {
        $this->assertFerli();
        can('emitir-nc-factura-vieja-local');

        $localId = (int) $request->input('local_id', 0);
        if ($localId <= 0 || ! LocalVentaAsignacionSupport::puedeOperarLocal($localId)) {
            return response()->json(['ok' => false, 'error' => 'Local no habilitado para este usuario.'], 403);
        }

        $local = LocalVenta::query()->find($localId);
        if (! $local) {
            return response()->json(['ok' => false, 'error' => 'No se encontró el local.'], 404);
        }

        $resultado = $this->service->emitir($local, [
            'fecha' => (string) $request->input('fecha', ''),
            'lineas' => $request->input('lineas', []),
        ]);
        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
