<?php

namespace App\Http\Requests;

use App\Models\Compras\Requisicion_Presupuesto;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicionPresupuesto extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $estados = implode(',', array_column(Requisicion_Presupuesto::$enumEstado, 'nombre'));

        return [
            'fecha' => 'required|date',
            'condicionentrega_id' => 'nullable|integer|exists:condicionentrega,id',
            'condicioncompra_id' => 'nullable|integer|exists:condicioncompra,id',
            'condicionpago_id' => 'nullable|integer|exists:condicionpago,id',
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'estado' => 'required|string|in:'.$estados,
            'requisicion_articulo_ids' => 'required|array|min:1',
            'requisicion_articulo_ids.*' => 'integer|exists:requisicion_articulo,id',
            'precios_unitarios' => 'required|array',
            'precios_unitarios.*' => 'numeric',
            'observaciones_linea' => 'nullable|array',
            'observaciones_linea.*' => 'nullable|string|max:2000',
            'archivos_presupuesto' => 'nullable|array',
            'archivos_presupuesto.*' => 'nullable|file|max:15360',
            'archivo_ids_conservar' => 'nullable|array',
            'archivo_ids_conservar.*' => 'integer',
        ];
    }
}
