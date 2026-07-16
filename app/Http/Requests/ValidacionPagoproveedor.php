<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPagoproveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'fecha' => 'required|date',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'cotizacion' => 'nullable|numeric|min:0',
            'monto' => 'nullable|numeric|min:0',
            'modo_cotizacion' => 'nullable|in:factura,dia',
            'estado' => 'nullable|in:PRE CARGA,CONFIRMADA',
            'caja_id' => 'nullable|integer|exists:caja,id',
            'detalle' => 'nullable|string|max:255',
        ];
    }
}
