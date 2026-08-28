<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPedidoFerli extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fecha' => 'required',
            'fechaentrega' => 'required',
            'cliente_id' => 'required|integer',
            'vendedor_id' => 'required|integer',
            'lugarentrega' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:255',
            'descuento' => 'sometimes|numeric|min:0|max:100',
            'descuentointegrado' => 'sometimes|string'
        ];
    }
}
