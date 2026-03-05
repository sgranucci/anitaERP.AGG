<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPedido extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
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
