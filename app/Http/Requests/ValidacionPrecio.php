<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPrecio extends FormRequest
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
            'articulo_id' => 'required|integer|exists:articulo,id',
            'listaprecio_id' => 'required|integer|exists:listaprecio,id',
            'fechavigencia' => 'required|date_format:Y-m-d',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'precio' => 'required|numeric',
        ];
    }
}
