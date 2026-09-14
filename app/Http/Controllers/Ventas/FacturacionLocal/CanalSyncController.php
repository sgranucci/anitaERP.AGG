<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\ArticuloCanalSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;

class CanalSyncController extends Controller
{
    public function index()
    {
        $this->assertFerli();
        can('sync-canal-facturacion-local');
        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        return view('ventas.facturacion_local.canal.sync', compact('locales'));
    }

    public function ejecutar(Request $request, ArticuloCanalSyncService $sync)
    {
        $this->assertFerli();
        can('sync-canal-facturacion-local');

        $localId = (int) $request->input('local_id', 0);
        $local = $localId > 0 ? LocalVenta::query()->find($localId) : null;
        $ejecutar = $request->boolean('ejecutar');

        // Pantalla: dry-run por defecto; --ejecutar solo con tilde explícito + confirmación
        $resultado = $sync->sincronizar($local, $ejecutar);

        return view('ventas.facturacion_local.canal.sync', [
            'locales' => LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'resultado' => $resultado,
            'local_id' => $localId,
            'ejecutar' => $ejecutar,
        ]);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
