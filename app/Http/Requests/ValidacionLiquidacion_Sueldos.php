<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionAlcanceRecibo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionLiquidacion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $esAlta = $this->route('id') === null;

        return [
            'empresa_id' => [Rule::requiredIf($esAlta), 'integer', 'exists:empresa,id'],
            'alcance' => ['nullable', 'string', Rule::in(LiquidacionAlcanceRecibo::permitidos())],
            'descripcion' => 'required|string|max:60',
            'tipo' => ['required', 'string', Rule::in(array_keys(Liquidacion_Sueldos::TIPOS))],
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'periodo' => ['required', 'regex:/^\d{4}-?\d{2}$/'],
            'periodo_desde' => 'nullable|date',
            'periodo_hasta' => 'nullable|date|after_or_equal:periodo_desde',
            'fecha_liquidacion' => 'nullable|date',
            'fecha_pago' => 'nullable|date',
            'lugar_pago' => 'nullable|string|max:60',
            'simulacion' => 'nullable|boolean',
            'acumula_novedades' => 'nullable|boolean',
            'banco_deposito' => 'nullable|string|max:60',
            'periodo_deposito' => 'nullable|string|max:15',
            'fecha_ultimo_deposito' => 'nullable|date',
            'observacion' => 'nullable|string|max:2000',
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'descripcion' => 'descripción',
            'tipo' => 'tipo de liquidación',
            'periodo' => 'período',
            'fecha_pago' => 'fecha de pago',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'periodo' => preg_replace('/\D+/', '', (string) $this->input('periodo')),
            'simulacion' => $this->boolean('simulacion'),
            'acumula_novedades' => $this->has('acumula_novedades') ? $this->boolean('acumula_novedades') : true,
            'alcance' => LiquidacionAlcanceRecibo::normalizar($this->input('alcance')),
        ]);
    }
}
