<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Configuracion\Moneda;
use App\Services\Compras\RequisicionAhorroService;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints del KPI de ahorro en la pantalla de requisición.
 * `calcular` y `precioAnterior` no persisten: la requisición graba los campos al guardarse.
 * `guardar` es la carga posterior (requisición aprobada, con OC, cumplida): solo columnas de ahorro, sin circuito.
 */
class RequisicionAhorroController extends Controller
{
    /**
     * Cálculo en vivo del ahorro de cada línea y del total en pesos.
     */
    public function calcular(Request $request): JsonResponse
    {
        if (! $this->tienePermiso()) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $fecha = substr((string) $request->input('fecha', date('Y-m-d')), 0, 10);
        $precios = (array) $request->input('precios', []);
        $cantidades = (array) $request->input('cantidades', []);
        $monedas = (array) $request->input('moneda_linea_ids', []);
        $anteriores = (array) $request->input('preciooriginales', []);
        $monedasAnt = (array) $request->input('moneda_anterior_ids', []);

        $abreviaturas = Moneda::query()->pluck('abreviatura', 'id');

        $lineas = [];
        $paraResumen = [];
        $n = count($precios);
        for ($i = 0; $i < $n; $i++) {
            $precio = (float) str_replace(',', '.', (string) ($precios[$i] ?? 0));
            $cantidad = (float) str_replace(',', '.', (string) ($cantidades[$i] ?? 0));
            $monedaId = (int) ($monedas[$i] ?? 1) ?: 1;
            $anterior = (float) str_replace(',', '.', (string) ($anteriores[$i] ?? 0));

            $calc = AhorroLineaSupport::calcular(
                $precio,
                $monedaId,
                $cantidad,
                $anterior,
                (int) ($monedasAnt[$i] ?? 0) ?: null,
                $fecha,
            );

            if ($calc === null) {
                $lineas[] = null;

                continue;
            }

            $calc['moneda_abrev'] = (string) ($abreviaturas[$monedaId] ?? '');
            $lineas[] = $calc;
            $paraResumen[] = (object) [
                'ahorro_total_pesos' => $calc['ahorro_total_pesos'],
                'tc_ahorro' => $calc['tc_ahorro'],
                'precio' => $precio,
                'cantidad' => $cantidad,
            ];
        }

        return response()->json([
            'lineas' => $lineas,
            'resumen' => AhorroLineaSupport::resumen($paraResumen),
        ]);
    }

    /**
     * Propuesta de precio anterior: precio de la última OC del artículo.
     */
    public function precioAnterior(Request $request): JsonResponse
    {
        if (! $this->tienePermiso()) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $ultima = AhorroLineaSupport::ultimaOcDelArticulo(
            (int) $request->query('articulo_id', 0),
            (int) $request->query('empresa_id', 0) ?: null,
        );

        if ($ultima === null) {
            return response()->json(['encontrado' => false]);
        }

        $ultima['moneda_abrev'] = (string) (Moneda::query()->where('id', $ultima['moneda_id'])->value('abreviatura') ?? '');
        $ultima['origen'] = AhorroLineaSupport::ORIGEN_ULTIMA_COMPRA;

        return response()->json(['encontrado' => true] + $ultima);
    }

    /**
     * Guarda el ahorro de una línea de requisición ya grabada y lo copia a las líneas de OC vinculadas.
     */
    public function guardar(Request $request, int $id): JsonResponse
    {
        can('actualizar-requisicion');

        $datos = $request->validate([
            'requisicion_articulo_id' => ['required', 'integer'],
            'precio_anterior' => ['required', 'numeric', 'min:0.0001', 'max:9999999999'],
            'moneda_anterior_id' => ['required', 'integer', 'exists:moneda,id'],
            'origen_anterior' => ['nullable', 'string', 'in:'.implode(',', array_keys(AhorroLineaSupport::origenes()))],
            'referencia_anterior' => ['nullable', 'string', 'max:255'],
            'tipoahorro_id' => ['nullable', 'integer', 'exists:tipo_ahorro,id'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $req = Requisicion::query()->findOrFail($id);
        $linea = Requisicion_Articulo::query()
            ->where('requisicion_id', $req->id)
            ->find((int) $datos['requisicion_articulo_id']);

        if ($linea === null) {
            return response()->json(['mensaje' => 'ng', 'detalle' => 'La línea no pertenece a esta requisición.'], 422);
        }

        try {
            $resultado = app(RequisicionAhorroService::class)->guardar($req, $linea, [
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
            'ordenes_actualizadas' => $resultado['ordenes_actualizadas'],
            'linea' => [
                'id' => $linea->id,
                'preciooriginal' => (float) $linea->preciooriginal,
                'moneda_anterior_id' => $linea->moneda_anterior_id,
                'origen_anterior' => $linea->origen_anterior,
                'referencia_anterior' => $linea->referencia_anterior,
                'tipoahorro_id' => $linea->tipoahorro_id,
                'tipoahorro_nombre' => (string) optional($linea->tipoahorro)->nombre,
                'ahorro_total_pesos' => $linea->ahorro_total_pesos !== null ? (float) $linea->ahorro_total_pesos : null,
                'porc_ahorro' => $linea->porc_ahorro !== null ? (float) $linea->porc_ahorro : null,
            ],
        ]);
    }

    private function tienePermiso(): bool
    {
        foreach (['crear-requisicion', 'editar-requisicion', 'actualizar-requisicion', 'listar-requisicion'] as $permiso) {
            if (can($permiso, false)) {
                return true;
            }
        }

        return false;
    }
}
