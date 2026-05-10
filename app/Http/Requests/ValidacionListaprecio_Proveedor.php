<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionListaprecio_Proveedor extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'fecha' => 'required|date',
            'nombre' => 'required|string|max:255',
            'observaciones' => 'nullable|string',
            'condicionpago_id' => 'nullable|integer|exists:condicionpago,id',
            'condicionentrega_id' => 'nullable|integer|exists:condicionentrega,id',
            'condicioncompra_id' => 'nullable|integer|exists:condicioncompra,id',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'estado' => 'nullable|string|max:50',
            'articulo_ids' => 'nullable|array',
            'articulo_ids.*' => 'nullable|integer|exists:articulo,id',
            'precios' => 'nullable|array',
            'descuentos' => 'nullable|array',
            'articulo_proveedores' => 'nullable|array',
            'fechavigencias' => 'nullable|array',
            'fechavigencias.*' => 'nullable|date',
            'linea_ids' => 'nullable|array',
            'linea_ids.*' => 'nullable|integer',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:20480',
            'nombresanteriores' => 'nullable|array',
            'nombresanteriores.*' => 'nullable|string|max:255',
        ];
    }
}
