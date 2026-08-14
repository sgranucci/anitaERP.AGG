<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConceptoPerdida extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $exceptoId = (int) $this->route('id');

        return [
            'codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('concepto_perdida', 'codigo')->ignore($exceptoId),
            ],
            'nombre' => 'required|max:30',
        ];
    }

    public function messages()
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un concepto de pérdida con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar 30 caracteres (límite Anita).',
        ];
    }
}
