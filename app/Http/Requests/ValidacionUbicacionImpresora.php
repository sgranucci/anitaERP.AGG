<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionUbicacionImpresora extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:ubicacion_impresora,nombre,'.$this->route('id'),
            'descripcion' => 'nullable|max:2000',
        ];
    }
}
