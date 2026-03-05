<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionVendedor extends FormRequest
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
            'nombre' => 'required|max:50|unique:vendedor,nombre,' . $this->route('id'),
            'comisionventa' => 'sometimes|numeric|max:100',
            'comisioncobranza' => 'sometimes|numeric|max:100',
        ];
    }
}
