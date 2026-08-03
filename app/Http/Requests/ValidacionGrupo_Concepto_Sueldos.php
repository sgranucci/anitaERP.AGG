<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionGrupo_Concepto_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'codigo' => [
                'required', 'integer', 'min:1',
                Rule::unique('grupo_concepto_sueldos', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|string|max:80',
            'activo' => 'nullable|boolean',
            'conceptos' => 'nullable|array',
            'conceptos.*' => 'integer|exists:concepto_sueldos,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
            'empresa_id' => $this->filled('empresa_id') ? $this->input('empresa_id') : null,
        ]);
    }
}
