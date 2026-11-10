<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownBovedaSubtipo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $tipoId = (int) $this->input('tipo_id');

        return [
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'tipo_id' => [
                'required',
                'integer',
                Rule::exists('tesoreria_crown_boveda_tipo', 'id')
                    ->where(fn ($q) => $q->where('sala_id', (int) $this->input('sala_id'))),
            ],
            'codigo' => [
                'required',
                'string',
                'max:8',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('tesoreria_crown_boveda_subtipo', 'codigo')
                    ->where(fn ($q) => $q->where('tipo_id', $tipoId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:60'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'tipo_id' => 'tipo de movimiento',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'activo' => 'activo',
        ];
    }
}
