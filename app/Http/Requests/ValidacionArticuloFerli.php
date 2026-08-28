<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionArticuloFerli extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'sku' => 'required|max:20|unique:articulo,sku,'.$this->route('id'),
            'descripcion' => 'required|max:100|',
            'categoria_id' => 'required|numeric',
            'subcategoria_id' => 'required|numeric',
            'unidadmedida_id' => 'required|numeric',
            'usoarticulo_id' => 'required|numeric',
            'linea_id' => 'required|numeric',
            'mventa_id' => 'required|numeric',
        ];
    }
}
