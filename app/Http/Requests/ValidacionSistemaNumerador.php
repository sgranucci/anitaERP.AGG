<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionSistemaNumerador extends FormRequest
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
                'required',
                'max:80',
                Rule::unique('sistema_numerador', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', (int) $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'nombre' => 'required|max:120',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'modulo' => 'required|max:40',
            'ultimo_numero' => 'required|integer|min:0',
            'anita_sistema' => 'nullable|max:30',
            'anita_fuente' => 'nullable|max:20',
            'anita_clave' => 'nullable|max:40',
            'activo' => 'nullable|boolean',
            'observacion' => 'nullable|max:255',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
        ]);
    }
}
