<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionCoeficiente extends FormRequest
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
            'nombre' => 'required|max:255|unique:coeficiente,nombre,' . $this->route('id'),
            'porcentajedivision' => 'required|numeric|min:0|max:100',
            'tasa' => 'required|numeric|min:0|max:100'
        ];
    }
}
