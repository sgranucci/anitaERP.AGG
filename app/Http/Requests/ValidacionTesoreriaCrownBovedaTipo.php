<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownBovedaTipo extends FormRequest
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
                'integer',
                'min:1',
                'max:9999',
                Rule::unique('tesoreria_crown_boveda_tipo', 'codigo')
                    ->where(fn ($q) => $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'abreviatura' => [
                'required',
                'string',
                'max:4',
                'regex:/^[A-Za-z0-9+\-\$]+$/',
                Rule::unique('tesoreria_crown_boveda_tipo', 'abreviatura')
                    ->where(fn ($q) => $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:40'],
            'signo' => ['required', 'in:0,1,2'],
            'operacion' => ['required', 'in:0,1,2,3,4,5,6,7'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'codigo' => 'código',
            'abreviatura' => 'abreviatura',
            'nombre' => 'nombre',
            'signo' => 'efecto en saldo',
            'operacion' => 'operación',
            'activo' => 'activo',
        ];
    }
}
