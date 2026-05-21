<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTransferenciaMercaderia extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deposito_salida_id' => 'required|integer|exists:depmae,id',
            'deposito_entrada_id' => 'required|integer|exists:depmae,id|different:deposito_salida_id',
            'tipotransaccion_id' => 'required|integer|exists:tipotransaccion,id',
            'lineas' => 'required|array|min:1',
            'lineas.*.articulo_id' => 'required|integer|exists:articulo,id',
            'lineas.*.cantidad' => 'required|numeric|gt:0',
        ];
    }

    public function messages(): array
    {
        return [
            'deposito_entrada_id.different' => 'El depósito de entrada debe ser distinto al de salida.',
            'lineas.min' => 'Debe transferir al menos un artículo.',
        ];
    }
}
