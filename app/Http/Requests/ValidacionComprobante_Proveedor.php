<?php

namespace App\Http\Requests;

use App\Support\Compras\ComprobanteProveedorModoCarga;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionComprobante_Proveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|min:1',
            'proveedor_id' => 'required|integer|min:1',
            'tipotransaccion_compra_id' => 'required|integer|min:1',
            'letra' => 'required|string|size:1',
            'sucursal' => 'required|integer|min:0',
            'numerocomprobante' => 'required|integer|min:1',
            'fechacomprobante' => 'required|date',
            'fechaiva' => 'required|date',
            'moneda_id' => 'required|integer|min:1',
            'subtotal' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'cotizacion' => 'nullable|numeric',
            'modo_carga' => 'nullable|string|in:'.implode(',', ComprobanteProveedorModoCarga::todos()),
            'concepto_ivacompra_ids' => 'nullable|array',
            'concepto_ivacompra_ids.*' => 'nullable|integer',
            'montos' => 'nullable|array',
            'montos.*' => 'nullable|numeric',
        ];
    }
}
