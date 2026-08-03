<?php

namespace App\Http\Requests;

use App\Support\Sueldos\NovedadSueldosCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionNovedad_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'liquidacion_id' => ['nullable', 'integer', 'exists:liquidacion_sueldos,id'],
            'empleado_id' => ['required', 'integer', 'exists:empleado_sueldos,id'],
            'concepto_id' => ['required', 'integer', 'exists:concepto_sueldos,id'],
            'valor1' => ['nullable', 'numeric'],
            'valor2' => ['nullable', 'numeric'],
            'estado' => ['required', 'string', Rule::in(NovedadSueldosCatalogo::estadosPermitidos())],
            'fecha_vto' => ['nullable', 'date'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'nro_interno' => ['nullable', 'integer', 'min:0'],
            'periodo' => ['nullable', 'integer'],
            'origen' => ['nullable', 'string', Rule::in(NovedadSueldosCatalogo::origenesPermitidos())],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'liquidacion_id' => 'liquidación',
            'empleado_id' => 'empleado',
            'concepto_id' => 'concepto',
            'valor1' => 'valor 1',
            'valor2' => 'valor 2',
            'estado' => 'estado',
            'fecha_vto' => 'fecha de vencimiento',
            'fecha_desde' => 'vigente desde',
            'fecha_hasta' => 'vigente hasta',
            'nro_interno' => 'nro. interno',
            'origen' => 'origen',
            'observacion' => 'observación',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'valor1' => $this->input('valor1', 0) !== null && $this->input('valor1') !== ''
                ? $this->input('valor1') : 0,
            'valor2' => $this->input('valor2', 0) !== null && $this->input('valor2') !== ''
                ? $this->input('valor2') : 0,
            'nro_interno' => (int) $this->input('nro_interno', 0),
            'estado' => NovedadSueldosCatalogo::normalizarEstado($this->input('estado')),
            'origen' => NovedadSueldosCatalogo::normalizarOrigen(
                $this->input('origen', NovedadSueldosCatalogo::ORIGEN_MANUAL)
            ),
            'liquidacion_id' => $this->filled('liquidacion_id') ? $this->input('liquidacion_id') : null,
        ]);
    }
}
