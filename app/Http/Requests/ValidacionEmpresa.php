<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionEmpresa extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:100|unique:empresa,nombre,' . $this->route('id'),
            'domicilio' => 'nullable|max:100',
            'pais_id' => 'nullable|integer|exists:pais,id',
            'provincia_id' => 'nullable|integer|exists:provincia,id',
            'localidad_id' => 'nullable|integer|exists:localidad,id',
            'codigopostal' => 'nullable|max:50',
            'nroinscripcion' => 'nullable|max:50',
            'numeroiibb' => 'nullable|max:100',
            'fechainicioactividad' => 'nullable|date',
            'codigo' => 'nullable|integer',
        ];
    }
}
