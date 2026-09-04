<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicion extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'empresa_id' => 'required|integer',
            'fecha' => 'required|date',
            'fechaentrega' => 'required|date',
            'centrocosto_id' => 'required|integer',
            'centrocostodestino_arbol_id' => 'nullable|integer|exists:centrocosto,id',
            'oficinacompra_id' => 'nullable|integer',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'nullable|string',
            'detalle_articulos' => 'nullable|array',
            'detalle_articulos.*' => 'nullable|string|max:2000',
            'tratamiento' => 'required|string|max:50',
            'motivotratamiento' => 'nullable|string|max:255',
            'contrataciondirecta' => 'nullable|string|max:50',
        ];
    }
}
