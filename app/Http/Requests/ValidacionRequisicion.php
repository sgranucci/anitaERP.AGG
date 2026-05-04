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
            'oficinacompra_id' => 'nullable|integer',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'nullable|string',
            'tratamiento' => 'required|string|max:50',
            'motivotratamiento' => 'nullable|string|max:255',
            'contrataciondirecta' => 'nullable|string|max:50',
        ];
    }
}
