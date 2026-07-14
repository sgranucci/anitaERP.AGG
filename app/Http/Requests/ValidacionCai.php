<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCai extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'tipo' => 'nullable|string|max:3',
            'descripcion' => 'nullable|string|max:30',
            'sucursal' => 'required|integer|min:1|max:9999',
            'numero_cai' => [
                'required',
                'string',
                'max:18',
                Rule::unique('cai', 'numero_cai')->ignore($id),
            ],
            'fecha_vencimiento' => 'required|date',
        ];
    }

    public function attributes()
    {
        return [
            'tipo' => 'tipo',
            'descripcion' => 'descripción',
            'sucursal' => 'sucursal',
            'numero_cai' => 'número CAI',
            'fecha_vencimiento' => 'fecha de vencimiento',
        ];
    }
}
