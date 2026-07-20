<?php

namespace App\Http\Requests;

use App\Support\Sueldos\ConceptoTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConcepto_Sueldos extends FormRequest
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
                'nullable',
                'integer',
                'min:1',
                Rule::unique('concepto_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:60',
            'tipo' => ['required', 'string', Rule::in(ConceptoTipo::tiposPermitidos())],
            'suma_a' => ['nullable', 'string', Rule::in(ConceptoTipo::basesPermitidas())],
            'momento' => ['required', 'string', Rule::in(ConceptoTipo::momentosPermitidos())],
            'factor' => 'nullable|numeric',
            'formula' => 'nullable|string|max:2000',
            'formula_cantidad' => 'nullable|string|max:2000',
            'formula_valor' => 'nullable|string|max:2000',
            'va_recibo' => 'nullable|boolean',
            'mes_retroactivo' => 'nullable|integer|min:-99|max:12',
            'leyenda_recibo' => 'nullable|string|max:2000',
            'concepto_afip' => 'nullable|string|max:6',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0',
            'acumuladores_override' => 'nullable|array',
            'acumuladores_override.*.accion' => 'nullable|string|in:auto,incluir,excluir',
            'acumuladores_override.*.signo' => 'nullable|integer|in:1,-1',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'tipo' => 'tipo de concepto',
            'suma_a' => 'base / acumulador',
            'momento' => 'momento de liquidación',
            'factor' => 'factor',
            'mes_retroactivo' => 'mes retroactivo',
            'concepto_afip' => 'concepto AFIP',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'va_recibo' => $this->boolean('va_recibo'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }
}
