<?php

namespace App\Http\Requests;

use App\Support\Sueldos\MotivoEgresoClase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMotivoegreso_Sueldos extends FormRequest
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
                Rule::unique('motivoegreso_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:30',
            'clase' => ['nullable', 'string', Rule::in(MotivoEgresoClase::permitidas())],
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
        ];
    }
}
