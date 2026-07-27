<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionSector_Solicitudpago extends FormRequest
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
                Rule::unique('sector_solicitudpago', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:30',
            'centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'centrocosto_id' => 'centro de costo',
        ];
    }
}
