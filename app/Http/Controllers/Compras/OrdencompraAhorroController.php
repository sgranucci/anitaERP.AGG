<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Services\Compras\OrdencompraAhorroService;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdencompraAhorroController extends Controller
{
    public function __construct(private OrdencompraAhorroService $service)
    {
        $this->middleware('auth');
    }

    public function guardar(Request $request, int $id): JsonResponse
    {
        can('actualizar-ordencompra');

        $datos = $request->validate([
            'ordencompra_articulo_id' => ['required', 'integer'],
            'precio_anterior' => ['required', 'numeric', 'min:0.0001', 'max:9999999999'],
            'moneda_anterior_id' => ['required', 'integer', 'exists:moneda,id'],
            'origen_anterior' => ['nullable', 'string', 'in:'.implode(',', array_keys(AhorroLineaSupport::origenes()))],
            'referencia_anterior' => ['nullable', 'string', 'max:255'],
            'tipoahorro_id' => ['nullable', 'integer', 'exists:tipo_ahorro,id'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $oc = Ordencompra::query()->findOrFail($id);
        $linea = Ordencompra_Articulo::query()
            ->where('ordencompra_id', $oc->id)
            ->find((int) $datos['ordencompra_articulo_id']);

        if ($linea === null) {
            return response()->json(['mensaje' => 'ng', 'detalle' => 'La línea no pertenece a esta orden.'], 422);
        }

        try {
            $resultado = $this->service->guardar($oc, $linea, [
                'precio_anterior' => $datos['precio_anterior'],
                'moneda_anterior_id' => $datos['moneda_anterior_id'],
                'origen_anterior' => $datos['origen_anterior'] ?? null,
                'referencia_anterior' => $datos['referencia_anterior'] ?? null,
                'tipoahorro_id' => $datos['tipoahorro_id'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
            ]);
        } catch (DomainException $e) {
            return response()->json(['mensaje' => 'ng', 'detalle' => $e->getMessage()], 422);
        }

        $linea = $resultado['linea'];
        $linea->loadMissing('tipoahorro');

        return response()->json([
            'mensaje' => 'ok',
            'requisicion_actualizada' => $resultado['requisicion_actualizada'],
            'linea' => [
                'id' => $linea->id,
                'ahorro_total_pesos' => $linea->ahorro_total_pesos !== null ? (float) $linea->ahorro_total_pesos : null,
                'porc_ahorro' => $linea->porc_ahorro !== null ? (float) $linea->porc_ahorro : null,
                'tipoahorro_nombre' => (string) optional($linea->tipoahorro)->nombre,
                'origen' => AhorroLineaSupport::etiquetaOrigen($linea->origen_anterior),
                // Para sumar el total de la orden en pantalla sin recargar.
                'base_pesos' => AhorroLineaSupport::resumen([$linea])['base_pesos'],
            ],
        ]);
    }
}
