<?php

namespace App\Http\Requests;

use App\Support\Sueldos\FalloCajaTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionAgrupamiento_Sueldos extends FormRequest
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
                Rule::unique('agrupamiento_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:30',
            'fallo_tipo' => ['nullable', Rule::in(FalloCajaTipo::OPCIONES)],
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'fallo_tipo' => 'fallo',
        ];
    }
}
