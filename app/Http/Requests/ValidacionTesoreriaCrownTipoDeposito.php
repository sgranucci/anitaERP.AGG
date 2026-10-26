<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownTipoDeposito extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        $salaId = (int) $this->input('sala_id');

        return [
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'codigo' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('tesoreria_crown_tipo_deposito', 'codigo')
                    ->where(fn ($q) => $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:80'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'orden' => 'orden',
            'activo' => 'activo',
        ];
    }
}
