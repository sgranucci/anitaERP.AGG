<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPropuestaPago extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|min:1',
            'fecha' => 'required|date',
            'fecha_vencimiento_desde' => 'nullable|date',
            'fecha_vencimiento_hasta' => 'nullable|date|after_or_equal:fecha_vencimiento_desde',
            'detalle' => 'nullable|string|max:500',
            'moneda_id' => 'nullable|integer',
            'caja_id' => 'nullable|integer|min:1',
            'cuentacaja_id' => 'nullable|integer|min:1',
            'chequera_id' => 'nullable|integer|min:1',
        ];
    }
}
