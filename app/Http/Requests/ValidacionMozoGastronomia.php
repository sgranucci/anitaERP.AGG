<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMozoGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'nombre' => 'required|max:255',
            'codigo' => [
                'nullable',
                'max:50',
                Rule::unique('mozo_gastronomia', 'codigo')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'clave' => 'nullable|string|min:4|max:60',
        ];
    }

    public function messages()
    {
        return [
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
        ];
    }
}
