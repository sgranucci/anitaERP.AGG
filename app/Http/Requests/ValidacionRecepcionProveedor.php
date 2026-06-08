<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRecepcionProveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordencompra_id' => 'required|integer|exists:ordencompra,id',
            'fecha' => 'required|date',
            'numerofactura' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:255',
            'deposito_id' => 'nullable|integer|exists:depmae,id',
            'tipo' => 'nullable|in:RECEPCION,DEVOLUCION',
            'recepcion_referencia_id' => 'nullable|integer|exists:recepcion_proveedor,id',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer|exists:articulo,id',
            'items.*.ordencompra_articulo_id' => 'nullable|integer|exists:ordencompra_articulo,id',
            'items.*.cantidad' => 'required|numeric|min:0.000001',
            'items.*.precio' => 'required|numeric|min:0',
            'items.*.precio_ordencompra' => 'nullable|numeric|min:0',
            'items.*.moneda_id' => 'required|integer|exists:moneda,id',
            'items.*.cotizacion' => 'nullable|numeric|min:0',
            'items.*.descuento' => 'nullable|numeric|min:0|max:100',
            'items.*.deposito_id' => 'nullable|integer|exists:depmae,id',
            'items.*.centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'items.*.coeficienteconversion' => 'nullable|numeric|min:0.000001',
            'items.*.tipo_linea' => 'nullable|in:OC,EXTRA,SUSTITUTO',
            'items.*.cantidad_oc' => 'nullable|numeric|min:0',
            'items.*.ordencompra_articulo_sustituido_id' => 'nullable|integer|exists:ordencompra_articulo,id',
            'items.*.comentario_diferencia' => 'nullable|string|max:500',
        ];
    }
}
