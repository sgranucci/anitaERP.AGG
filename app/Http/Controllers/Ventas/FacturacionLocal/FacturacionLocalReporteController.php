<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\ValeClienteLocal;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FacturacionLocalReporteController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');

        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $localId = (int) $request->input('local_id', 0);
        $desde = $request->input('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->format('Y-m-d'));
        $consultar = $request->boolean('consultar');

        $emisiones = null;
        $vales = null;
        if ($consultar) {
            $emisiones = $this->queryEmisiones($localId, $desde, $hasta)->paginate(25)->appends($request->query());
            $vales = ValeClienteLocal::query()
                ->when($localId > 0, fn ($q) => $q->where('local_venta_id', $localId))
                ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        return view('ventas.facturacion_local.reportes.index', compact(
            'locales', 'localId', 'desde', 'hasta', 'consultar', 'emisiones', 'vales'
        ));
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertFerli();
        can('reportes-facturacion-local');
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $localId = (int) $request->input('local_id', 0);
        $desde = $request->input('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->format('Y-m-d'));
        $filas = $this->queryEmisiones($localId, $desde, $hasta)->get();

        if (strtoupper($formato) === 'PDF') {
            $pdf = Pdf::loadView('ventas.facturacion_local.reportes.listado', [
                'filas' => $filas,
                'desde' => $desde,
                'hasta' => $hasta,
            ])->setPaper('legal', 'landscape');

            return $pdf->download('facturacion_local.pdf');
        }

        return redirect()->route('facturacion_local_reportes', $request->query());
    }

    private function queryEmisiones(int $localId, string $desde, string $hasta)
    {
        return FacturacionLocalEmision::query()
            ->with(['localVenta:id,codigo,nombre', 'venta:id,codigo,total,cae,letra', 'ventaNc:id,codigo,total'])
            ->when($localId > 0, fn ($q) => $q->where('local_venta_id', $localId))
            ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderByDesc('id');
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
