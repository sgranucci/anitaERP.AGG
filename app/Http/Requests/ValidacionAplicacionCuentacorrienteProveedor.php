<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionAplicacionCuentacorrienteProveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'fecha' => 'required|date',
            'lineas' => 'required|array|min:1',
            'lineas.*.credito_id' => 'required|integer|min:1',
            'lineas.*.deuda_id' => 'required|integer|min:1',
            'lineas.*.monto' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Seleccione un proveedor.',
            'lineas.required' => 'Indique al menos una aplicación.',
            'lineas.*.monto.min' => 'El monto a aplicar debe ser mayor a cero.',
        ];
    }
}
