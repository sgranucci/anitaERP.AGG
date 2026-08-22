<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMotivoSancion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('motivo_sancion_sueldos', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:60',
            'activo' => 'nullable|boolean',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
        ];
    }
}
