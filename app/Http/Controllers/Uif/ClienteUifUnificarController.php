<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Services\Uif\ClienteUifUnificarService;
use Illuminate\Http\Request;
use RuntimeException;

class ClienteUifUnificarController extends Controller
{
    public function __construct(
        private readonly ClienteUifUnificarService $unificarService,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->assertPuedeUnificar();

        return view('uif.unificar_clientes.index');
    }

    public function preview(Request $request)
    {
        $this->assertPuedeUnificar();

        $conservarId = (int) $request->input('conservar_id', 0);
        $absorberId = (int) $request->input('absorber_id', 0);

        return response()->json($this->unificarService->preview($conservarId, $absorberId));
    }

    public function unificar(Request $request)
    {
        $this->assertPuedeUnificar();

        $confirmacion = strtoupper(trim((string) $request->input('confirmacion', '')));
        if ($confirmacion !== 'UNIFICAR') {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Para confirmar escriba UNIFICAR.',
                'errores' => ['Confirmación incorrecta.'],
            ], 422);
        }

        $conservarId = (int) $request->input('conservar_id', 0);
        $absorberId = (int) $request->input('absorber_id', 0);

        try {
            $resultado = $this->unificarService->ejecutar($conservarId, $absorberId);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => [$e->getMessage()],
            ], 500);
        }

        if (! $resultado['ok']) {
            return response()->json($resultado, 422);
        }

        $resultado['redirect'] = route('edita_cliente_uif', ['id' => $resultado['conservar_id']]);

        return response()->json($resultado);
    }

    private function assertPuedeUnificar(): void
    {
        if (! function_exists('esSupervisorUif') || ! esSupervisorUif()) {
            abort(403, 'Solo supervisor UIF puede unificar clientes.');
        }
        can('unificar-cliente-uif');
    }
}
