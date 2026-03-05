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
            'listaprecio_id' => 'required',
            'fechavigencia' => 'required|date_format:d-m-Y',
            'moneda_id' => 'required',
            'precio' => 'required',
        ];
    }
}
