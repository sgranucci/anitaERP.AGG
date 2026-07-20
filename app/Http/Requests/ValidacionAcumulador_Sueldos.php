<?php

namespace App\Http\Requests;

use App\Support\Sueldos\ConceptoTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionAcumulador_Sueldos extends FormRequest
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
                'string',
                'max:30',
                Rule::unique('acumulador_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:80',
            'tipos_incluye' => 'nullable|array',
            'tipos_incluye.*' => ['string', Rule::in(ConceptoTipo::tiposPermitidos())],
            'signo' => 'required|integer|in:1,-1',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'tipos_incluye' => 'tipos incluidos',
            'signo' => 'signo',
            'activo' => 'activo',
            'orden' => 'orden',
            'empresa_id' => 'empresa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ];

        if ($this->has('codigo')) {
            $merge['codigo'] = strtoupper(trim((string) $this->input('codigo')));
        }

        $this->merge($merge);
    }
}
