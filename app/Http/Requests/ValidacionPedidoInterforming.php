<?php

namespace App\Http\Requests;

use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionPedidoInterforming extends FormRequest
{
    public function authorize(): bool
    {
        return PedidoInterformingSupport::esInterforming();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => 'nullable|date',
            'fechaentrega' => 'required|date',
            'cliente_id' => 'required|integer|exists:cliente,id',
            'vendedor_id' => 'required|integer|exists:vendedor,id',
            'condicionventa_id' => 'nullable|integer|exists:condicionventa,id',
            'transporte_id' => 'nullable|integer|exists:transporte,id',
            'zonavta_id' => 'nullable|integer',
            'cliente_entrega_id' => 'nullable|integer',
            'lugarentrega' => 'nullable|string|max:60',
            'leyenda' => 'nullable|string|max:160',
            'descuento' => 'nullable|numeric',
            'descuentointegrado' => 'nullable|string|max:15',
            'orden_compra' => 'nullable|string|max:15',
            'deposito_id' => 'nullable|integer',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'cotizacion' => 'nullable|numeric',
            'en_stock' => 'nullable|string|max:1',
            'tipo_comprobante' => 'nullable|string|max:3',
            'letra_comprobante' => 'nullable|string|max:1',
            'sucursal_comprobante' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer|exists:articulo,id',
            'items.*.numeroitem' => 'nullable|integer',
            'items.*.cantidad' => 'required|numeric|min:0.000001',
            'items.*.precio' => 'nullable|numeric',
            'items.*.descuento' => 'nullable|numeric',
            'items.*.moneda_id' => 'nullable|integer',
            'items.*.listaprecio_id' => 'nullable|integer',
            'items.*.unidadmedida_id' => 'nullable|integer',
            'items.*.unidadmedida_alter_id' => 'nullable|integer',
            'items.*.cantidad_alter' => 'nullable|numeric',
            'items.*.fechaentrega' => 'nullable|date',
            'items.*.orden_compra' => 'nullable|string|max:15',
            'items.*.articulo_cliente' => 'nullable|string|max:16',
            'items.*.partida' => 'nullable|integer',
            'items.*.porc_fason' => 'nullable|numeric',
            'items.*.precio_fason' => 'nullable|numeric',
            'items.*.moneda_fason_id' => 'nullable|integer',
            'items.*.deposito_id' => 'nullable|integer',
            'items.*.ubicacion' => 'nullable|string|max:6',
            'items.*.detalle_ubicacion' => 'nullable|string|max:6',
            'items.*.incluyeimpuesto' => 'nullable|string|max:1',
            'items.*.observacion' => 'nullable|string|max:255',
            'items.*.descripcion_aux' => 'nullable|string|max:50',
            'items.*.estado' => 'nullable|string|max:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'vendedor_id' => 'vendedor',
            'fechaentrega' => 'fecha de entrega',
            'orden_compra' => 'orden de compra',
            'items.*.articulo_id' => 'artículo',
            'items.*.cantidad' => 'cantidad',
            'items.*.porc_fason' => '% fason',
        ];
    }
}
