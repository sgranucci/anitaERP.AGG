<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownValor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $tipoId = (int) $this->input('tipo_valor_id');

        $salaId = (int) $this->input('sala_id');

        return [
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'tipo_valor_id' => [
                'required',
                'integer',
                Rule::exists('tesoreria_crown_tipo_valor', 'id')->where(fn ($q) => $q->where('sala_id', $salaId)),
            ],
            'serie' => [
                'required',
                'string',
                'max:40',
                Rule::unique('tesoreria_crown_valor', 'serie')
                    ->where(fn ($q) => $q->where('tipo_valor_id', $tipoId))
                    ->ignore($id),
            ],
            'descripcion' => ['required', 'string', 'max:120'],
            'denominacion' => ['required', 'numeric', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'tipo_valor_id' => 'tipo de valor',
            'serie' => 'serie',
            'descripcion' => 'descripción',
            'denominacion' => 'denominación',
            'orden' => 'orden',
            'activo' => 'activo',
        ];
    }
}
