<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownDeposito extends FormRequest
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
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'tipo_deposito_id' => [
                'required',
                'integer',
                Rule::exists('tesoreria_crown_tipo_deposito', 'id')->where(fn ($q) => $q->where('sala_id', $salaId)),
            ],
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tesoreria_crown_deposito', 'codigo')
                    ->where(fn ($q) => $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'sala_id' => 'sala',
            'tipo_deposito_id' => 'tipo de depósito',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'orden' => 'orden',
            'activo' => 'activo',
        ];
    }
}
