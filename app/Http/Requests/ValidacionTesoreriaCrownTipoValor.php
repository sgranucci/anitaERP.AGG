<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownTipoValor extends FormRequest
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
                'max:10',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('tesoreria_crown_tipo_valor', 'codigo')
                    ->where(fn ($q) => $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:80'],
            'es_moneda_referencia' => ['required', 'in:0,1'],
            'controla_stock' => ['required', 'in:0,1'],
            'cotizacion_fija' => ['required', 'in:0,1'],
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
            'es_moneda_referencia' => 'moneda de referencia',
            'controla_stock' => 'controla stock',
            'cotizacion_fija' => 'cotización fija',
            'orden' => 'orden',
            'activo' => 'activo',
        ];
    }
}
