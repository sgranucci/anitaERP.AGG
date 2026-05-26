<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCategoriafidelidadGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:255',
            'codigo' => 'required|max:50|unique:categoriafidelidad_gastronomia,codigo,'.$id,
            'articulo_ids' => 'nullable|array',
            'articulo_ids.*' => 'nullable|integer|exists:articulo,id',
            'categoriafidelidad_articulo_ids' => 'nullable|array',
            'categoriafidelidad_articulo_ids.*' => 'nullable|integer',
            'codigoarticulos' => 'nullable|array',
            'descripcionarticulos' => 'nullable|array',
        ];
    }
}
